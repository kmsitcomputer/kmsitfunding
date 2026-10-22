<?php

namespace App\Services\Donation;

use App\Models\Campaign\Campaign;
use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Services\Campaign\CampaignEligibilityResolver;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\Exceptions\DonationValidationException;
use App\Support\Money\CurrencyMinorUnits;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use App\Support\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-008 — Donation creation (docs/implementation/IMP-008-donation.md
 * "Domain Model" / "Idempotency", BR-1..BR-3/BR-12/HD-IMP008-05A).
 * Campaign-only, eligibility via CampaignEligibilityResolver (never
 * re-derived), money via Money/CurrencyMinorUnits (never float-never-assume).
 * donor_principal_id is derived from the acting Principal — a caller can
 * never set an arbitrary donor_principal_id. NULL actor means guest
 * (guest_name+guest_email required; HD-IMP008-01B allows guest one-time).
 *
 * Idempotency: the client-provided key is validated FIRST (before any
 * Campaign lookup or eligibility check — BR-12), enforced by the UNIQUE
 * constraint as the deterministic backstop: an INSERT violation is caught
 * and handled as a replay (payload match -> return existing; payload
 * differ -> typed conflict), never surfaced as a raw database error —
 * never a check-then-insert race.
 *
 * The guest-actor donation.created audit row uses ADR-002's narrow
 * Unauthenticated attribution (actor null, registry-fixed execution
 * context); the authenticated case uses the acting Principal.
 */
class DonationService
{
    public function __construct(
        private readonly CampaignEligibilityResolver $eligibility,
        private readonly DonationAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{amount_minor:int,currency:string,is_anonymous?:bool,donor_display_name?:?string,guest_name?:?string,guest_email?:?string,recurring_occurrence_id?:?int}  $payload
     */
    public function create(Campaign $campaign, array $payload, ?Principal $actor, mixed $idempotencyKey): Donation
    {
        $key = self::normalizeIdempotencyKey($idempotencyKey);

        $this->assertValidMoney($payload['amount_minor'], $payload['currency']);

        $campaign = Campaign::query()->whereKey($campaign->id)->firstOrFail();

        if (! $this->eligibility->isDonationEligible($campaign)) {
            throw new DonationValidationException(
                'campaign_not_eligible',
                "Campaign {$campaign->id} is not currently eligible to receive donations."
            );
        }

        $donorPrincipalId = $actor?->id;
        $guestName = $actor === null ? ($payload['guest_name'] ?? null) : null;
        $guestEmail = $actor === null ? ($payload['guest_email'] ?? null) : null;

        if ($actor === null && ($guestName === null || $guestEmail === null)) {
            throw new DonationValidationException(
                'guest_identity_incomplete',
                'A guest donation requires both guest_name and guest_email.'
            );
        }

        if ($actor !== null && (($payload['guest_name'] ?? null) !== null || ($payload['guest_email'] ?? null) !== null)) {
            throw new DonationValidationException(
                'donor_path_conflict',
                'An authenticated donation must not carry guest_name/guest_email.'
            );
        }

        if (! is_int($payload['amount_minor']) || $payload['amount_minor'] <= 0) {
            throw new DonationValidationException(
                'invalid_amount',
                'amount_minor must be a positive integer.'
            );
        }

        try {
            return DB::transaction(function () use ($campaign, $payload, $actor, $donorPrincipalId, $guestName, $guestEmail, $key) {
                $donation = new Donation;
                $donation->forceFill([
                    'campaign_id' => $campaign->id,
                    'donor_principal_id' => $donorPrincipalId,
                    'recurring_occurrence_id' => $payload['recurring_occurrence_id'] ?? null,
                    'guest_name' => $guestName,
                    'guest_email' => $guestEmail,
                    'donor_display_name' => $payload['donor_display_name'] ?? null,
                    'amount_minor' => $payload['amount_minor'],
                    'currency' => $payload['currency'],
                    'status' => 'PENDING',
                    'is_anonymous' => (bool) ($payload['is_anonymous'] ?? false),
                    'idempotency_key' => $key,
                ]);
                $donation->save();

                $isGuest = $actor === null;

                $this->auditLogger->recordDonationCreated($donation->id, [
                    'campaign_id' => $campaign->id,
                    'amount_minor' => $donation->amount_minor,
                    'currency' => $donation->currency,
                    'is_anonymous' => $donation->is_anonymous ? 1 : 0,
                    'is_guest' => $isGuest ? 1 : 0,
                ], $actor);

                return $donation->fresh();
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return $this->resolveIdempotentReplay($key, $campaign->id, $payload, $donorPrincipalId, $guestName, $guestEmail);
        }
    }

    public static function normalizeIdempotencyKey(mixed $key): string
    {
        if (! is_string($key)) {
            throw new DonationValidationException('missing_idempotency_key', 'An Idempotency-Key header is required to create a donation.');
        }

        $normalized = trim($key);

        if ($normalized === '' || strlen($normalized) > 128 || preg_match('/[^\x20-\x7E]/', $normalized) !== 0) {
            throw new DonationValidationException(
                'invalid_idempotency_key',
                'The Idempotency-Key must be printable ASCII, 1-128 characters.'
            );
        }

        return $normalized;
    }

    private function assertValidMoney(mixed $amount, mixed $currency): void
    {
        if (! is_string($currency) || ! CurrencyMinorUnits::isRegistered($currency)) {
            throw new UnknownCurrencyException(is_string($currency) ? $currency : '');
        }

        Money::ofMinorUnits(0, $currency);
    }

    private function resolveIdempotentReplay(
        string $key,
        int $campaignId,
        array $payload,
        ?int $donorPrincipalId,
        ?string $guestName,
        ?string $guestEmail,
    ): Donation {
        $existing = Donation::query()->where('idempotency_key', $key)->first();

        if ($existing === null) {
            throw new DonationTransitionConflictException(
                'idempotency_unresolved',
                'The idempotency key collided but no existing donation could be resolved.'
            );
        }

        $payloadMatches = $existing->campaign_id === $campaignId
            && $existing->amount_minor === $payload['amount_minor']
            && $existing->currency === $payload['currency']
            && $existing->donor_principal_id === $donorPrincipalId
            && $existing->guest_name === $guestName
            && $existing->guest_email === $guestEmail;

        if (! $payloadMatches) {
            throw new DonationTransitionConflictException(
                'idempotency_conflict',
                'This idempotency key was already used for a materially different donation.'
            );
        }

        return $existing;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return in_array($driverCode, [1062, 19, 2067], true);
    }
}
