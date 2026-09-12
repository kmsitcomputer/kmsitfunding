<?php

namespace App\Services\Identity;

use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * M01 / P2-m01 — request-versioned canonical email-change lifecycle. See
 * "Canonical Email Change Lifecycle" in
 * docs/implementation/IMP-002-identity-authentication.md for the full
 * contract this class implements.
 */
class EmailChangeService
{
    public function __construct(
        private readonly EmailNormalizer $normalizer,
        private readonly SessionInvalidator $sessions,
        private readonly AssuranceService $assurance,
        private readonly IdentityAuditLogger $audit,
    ) {}

    /**
     * @return array{request: EmailChangeRequest, plain_token: string}
     */
    public function requestChange(User $user, string $newEmail): array
    {
        $normalized = $this->normalizer->normalize($newEmail);
        $plainToken = Str::random(64);

        $request = DB::transaction(function () use ($user, $normalized, $plainToken) {
            // Unconditionally supersede any prior active request BEFORE/atomically
            // with creating the new one — mandatory, not optional (M01).
            $lastGeneration = EmailChangeRequest::where('user_id', $user->id)
                ->lockForUpdate()
                ->max('generation') ?? 0;

            EmailChangeRequest::where('user_id', $user->id)
                ->whereNull('verified_at')
                ->whereNull('superseded_at')
                ->whereNull('cancelled_at')
                ->whereNull('conflicted_at')
                ->update(['superseded_at' => now()]);

            return EmailChangeRequest::create([
                'user_id' => $user->id,
                'normalized_pending_email' => $normalized,
                'verification_token_hash' => Hash::make($plainToken),
                'generation' => $lastGeneration + 1,
                'requested_at' => now(),
                'expires_at' => now()->addMinutes((int) config('identity.email_change_request_ttl_minutes')),
            ]);
        });

        $this->audit->record('email_change_requested', $user, ['generation' => $request->generation]);

        return ['request' => $request, 'plain_token' => $plainToken];
    }

    /**
     * @return array{status: 'promoted'|'conflicted'|'invalid'}
     */
    public function verify(Request $httpRequest, User $user, int $requestId, string $plainToken): array
    {
        // Attempt promotion; on a uniqueness conflict the promotion transaction
        // rolls back completely (P2-m01) before conflict finalization runs as
        // its own, separate transaction.
        $promotionOutcome = $this->attemptPromotion($httpRequest, $user, $requestId, $plainToken);

        if ($promotionOutcome !== 'conflict') {
            return ['status' => $promotionOutcome === 'promoted' ? 'promoted' : 'invalid'];
        }

        $this->finalizeConflict($requestId);

        return ['status' => 'conflicted'];
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

                $user->forceFill([
                    'email' => $targetEmail,
                    'email_verified_at' => now(),
                ])->save();

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
