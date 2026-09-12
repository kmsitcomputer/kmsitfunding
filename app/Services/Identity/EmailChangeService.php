<?php

namespace App\Services\Identity;

use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * M01 / P2-m01 — request-versioned canonical email-change lifecycle. See
 * "Canonical Email Change Lifecycle" in
 * docs/implementation/IMP-002-identity-authentication.md for the full
 * contract this class implements.
 *
 * IMP002-IMPL-M01 / IMP002-IMPL-m01 remediation: a request-time application
 * availability check rejects an already-occupied target email up front
 * (without creating a request), and the User row is locked BEFORE the
 * generation lookup/supersede/create sequence so two concurrent requests for
 * the same User cannot both observe "no active request" and both create one.
 * The real database unique-constraint race (target claimed by another
 * transaction between the pre-check and the promotion UPDATE) is handled
 * separately in attemptPromotion() via CanonicalEmailUniqueViolationDetector.
 */
class EmailChangeService
{
    public function __construct(
        private readonly EmailNormalizer $normalizer,
        private readonly SessionInvalidator $sessions,
        private readonly AssuranceService $assurance,
        private readonly IdentityAuditLogger $audit,
        private readonly CanonicalEmailUniqueViolationDetector $uniqueViolationDetector,
    ) {}

    /**
     * @return array{status: 'requested'|'unavailable', request?: EmailChangeRequest, plain_token?: string}
     */
    public function requestChange(User $user, string $newEmail): array
    {
        $normalized = $this->normalizer->normalize($newEmail);

        $outcome = DB::transaction(function () use ($user, $normalized) {
            // Lock the User row FIRST (m01) — this is the per-User serialization
            // point. Without it, two concurrent first-ever requests for the same
            // User could both observe "no existing rows to lock" (lockForUpdate
            // on an empty result set locks nothing) and both proceed to create
            // an ACTIVE request.
            User::where('id', $user->id)->lockForUpdate()->first();

            // Request-time availability pre-check (M01): reject up front rather
            // than create a request doomed to conflict at promotion time. This
            // does not by itself guarantee no race remains — a target email can
            // still be claimed by another transaction between this check and
            // promotion, which attemptPromotion() below handles via the real
            // database unique-constraint classifier.
            if (User::where('email', $normalized)->where('id', '!=', $user->id)->exists()) {
                return ['status' => 'unavailable'];
            }

            $plainToken = Str::random(64);

            $lastGeneration = EmailChangeRequest::where('user_id', $user->id)
                ->lockForUpdate()
                ->max('generation') ?? 0;

            // Unconditionally supersede any prior active request BEFORE/atomically
            // with creating the new one — mandatory, not optional (M01).
            EmailChangeRequest::where('user_id', $user->id)
                ->whereNull('verified_at')
                ->whereNull('superseded_at')
                ->whereNull('cancelled_at')
                ->whereNull('conflicted_at')
                ->update(['superseded_at' => now()]);

            $request = EmailChangeRequest::create([
                'user_id' => $user->id,
                'normalized_pending_email' => $normalized,
                'verification_token_hash' => Hash::make($plainToken),
                'generation' => $lastGeneration + 1,
                'requested_at' => now(),
                'expires_at' => now()->addMinutes((int) config('identity.email_change_request_ttl_minutes')),
            ]);

            return ['status' => 'requested', 'request' => $request, 'plain_token' => $plainToken];
        });

        if ($outcome['status'] === 'requested') {
            $this->audit->record('email_change_requested', $user, ['generation' => $outcome['request']->generation]);
        }

        return $outcome;
    }

    /**
     * @return array{status: 'promoted'|'failed'}
     */
    public function verify(Request $httpRequest, User $user, int $requestId, string $plainToken): array
    {
        // Attempt promotion; on a uniqueness conflict the promotion transaction
        // rolls back completely (P2-m01) before conflict finalization runs as
        // its own, separate transaction. IMP002-IMPL-M02: every non-promoted
        // outcome (invalid token, expired, cancelled, superseded, conflicted)
        // collapses to the SAME generic "failed" status outward — only the
        // internal request row and audit log record which one actually
        // occurred; the caller must never be able to distinguish a uniqueness
        // conflict from any other failure.
        $promotionOutcome = $this->attemptPromotion($httpRequest, $user, $requestId, $plainToken);

        if ($promotionOutcome === 'conflict') {
            $this->finalizeConflict($requestId);
        }

        return ['status' => $promotionOutcome === 'promoted' ? 'promoted' : 'failed'];
    }

    /**
     * @return 'promoted'|'invalid'|'conflict'
     */
    private function attemptPromotion(Request $httpRequest, User $user, int $requestId, string $plainToken): string
    {
        try {
            return DB::transaction(function () use ($httpRequest, $user, $requestId, $plainToken) {
                /** @var EmailChangeRequest|null $emailChangeRequest */
                $emailChangeRequest = EmailChangeRequest::where('id', $requestId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($emailChangeRequest === null
                    || ! $emailChangeRequest->isActive()
                    || ! Hash::check($plainToken, $emailChangeRequest->verification_token_hash)
                ) {
                    return 'invalid';
                }

                $targetEmail = $emailChangeRequest->normalized_pending_email;

                if (User::where('email', $targetEmail)->where('id', '!=', $user->id)->exists()) {
                    // Force a rollback — nothing in this transaction, including a
                    // conflict marker, may be persisted from here (P2-m01).
                    throw new EmailChangeConflictException;
                }

                $oldEmail = $user->email;

                try {
                    $user->forceFill([
                        'email' => $targetEmail,
                        'email_verified_at' => now(),
                    ])->save();
                } catch (QueryException $exception) {
                    // The application-level pre-check above can still be stale —
                    // another transaction may have claimed the target email
                    // between the check and this UPDATE. The database's own
                    // unique constraint is the final authority (M01); only the
                    // EXACT canonical users.email violation is reclassified as
                    // a conflict. Anything else (deadlock, FK failure, an
                    // unrelated unique violation, a connection error) is a real
                    // database failure and must propagate, not be swallowed.
                    if ($this->uniqueViolationDetector->isCanonicalEmailUniqueViolation($exception)) {
                        throw new EmailChangeConflictException(previous: $exception);
                    }

                    throw $exception;
                }

                $emailChangeRequest->forceFill(['verified_at' => now()])->save();

                EmailChangeRequest::where('user_id', $user->id)
                    ->where('id', '!=', $emailChangeRequest->id)
                    ->whereNull('verified_at')
                    ->whereNull('superseded_at')
                    ->whereNull('cancelled_at')
                    ->whereNull('conflicted_at')
                    ->update(['superseded_at' => now()]);

                $this->sessions->invalidateAllExcept($user, $httpRequest->session()->getId());
                $httpRequest->session()->regenerate();
                $this->assurance->invalidate();

                // The old email is no longer a valid password-reset lookup target for
                // this identity after promotion — invalidate any outstanding token.
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();

                $this->audit->record('email_change_completed', $user, ['generation' => $emailChangeRequest->generation]);

                return 'promoted';
            });
        } catch (EmailChangeConflictException) {
            return 'conflict';
        }
    }

    /**
     * Separate, durable, race-safe conflict finalization (P2-m01). Only an
     * EmailChangeRequest still ACTIVE at this point transitions to CONFLICTED —
     * if it has since reached ANY other terminal state (verified, superseded,
     * cancelled, or already conflicted) OR has since expired, that pre-existing
     * outcome is authoritative and is never overwritten. EXPIRED wins over
     * CONFLICTED.
     */
    private function finalizeConflict(int $requestId): void
    {
        DB::transaction(function () use ($requestId) {
            /** @var EmailChangeRequest|null $emailChangeRequest */
            $emailChangeRequest = EmailChangeRequest::where('id', $requestId)->lockForUpdate()->first();

            if ($emailChangeRequest === null || ! $emailChangeRequest->isActive()) {
                return;
            }

            $emailChangeRequest->forceFill([
                'conflicted_at' => now(),
                'conflict_reason_code' => EmailChangeRequest::CONFLICT_TARGET_EMAIL_ALREADY_IN_USE,
            ])->save();

            $this->audit->record('email_change_conflicted', $emailChangeRequest->user, [
                'generation' => $emailChangeRequest->generation,
                'conflict_reason_code' => $emailChangeRequest->conflict_reason_code,
            ]);
        });
    }
}
