<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\Fund;
use App\Models\Campaign\Program;
use App\Models\Rbac\Principal;
use App\Services\Campaign\Exceptions\CampaignValidationException;
use App\Support\Money\CurrencyMinorUnits;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IMP-007 — Campaign identity CRUD (docs/implementation/
 * IMP-007-campaign-program-fund.md section 8/12/13). Lifecycle TRANSITIONS
 * (submit/approve/reject/publish/close) live exclusively in
 * CampaignLifecycleService — never here, mirroring PageService/
 * PublicationService's write-boundary split.
 */
class CampaignService
{
    public function __construct(
        private readonly CampaignAuditLogger $auditLogger,
        private readonly CampaignContentSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{name:string,slug?:string,summary?:string,description_html?:string,purpose?:string,program_ulid?:string,target_amount_minor?:int,currency?:string,starts_at?:string,ends_at?:string}  $payload
     */
    public function create(array $payload, Principal $actor): Campaign
    {
        return DB::transaction(function () use ($payload, $actor) {
            $slug = $payload['slug'] ?? Str::slug($payload['name']);

            if ($slug === '') {
                throw new CampaignValidationException('invalid_slug', 'A campaign slug must not be empty.');
            }

            if (Campaign::where('slug', $slug)->exists()) {
                throw new CampaignValidationException('slug_taken', "Campaign slug '{$slug}' is already in use.");
            }

            $programId = null;

            if (! empty($payload['program_ulid'])) {
                $programId = Program::where('ulid', $payload['program_ulid'])->value('id');

                if ($programId === null) {
                    throw new CampaignValidationException('program_not_found', 'The referenced Program does not exist.');
                }
            }

            $this->assertValidMoney($payload);
            $this->assertValidPeriod($payload['starts_at'] ?? null, $payload['ends_at'] ?? null);

            $campaign = new Campaign;
            $campaign->forceFill([
                'program_id' => $programId,
                'fund_id' => null,
                'name' => $payload['name'],
                'slug' => $slug,
                'summary' => $payload['summary'] ?? null,
                'description_html' => isset($payload['description_html']) ? $this->sanitizer->sanitize($payload['description_html']) : null,
                'purpose' => $payload['purpose'] ?? null,
                'target_amount_minor' => $payload['target_amount_minor'] ?? null,
                'currency' => $payload['currency'] ?? config('campaign.default_currency'),
                'starts_at' => $payload['starts_at'] ?? null,
                'ends_at' => $payload['ends_at'] ?? null,
                'status' => 'DRAFT',
                'edit_version' => 0,
                'created_by_principal_id' => $actor->id,
                'updated_by_principal_id' => $actor->id,
            ]);
            $campaign->save();

            $this->auditLogger->recordCampaignCreated($campaign->id, [
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'program_id' => $programId,
            ], $actor);

            return $campaign->fresh();
        });
    }

    /**
     * BR-4: DRAFT/REVIEW/APPROVED may all be edited via CAMPAIGN_UPDATE
     * alone; CLOSED rejects all field mutation (enforced here AND as a
     * CampaignPolicy resourceStatePredicate — defense-in-depth).
     *
     * @param  array{name?:string,summary?:string,description_html?:string,purpose?:string,program_ulid?:?string,fund_ulid?:?string,target_amount_minor?:?int,currency?:?string,starts_at?:?string,ends_at?:?string}  $payload
     */
    public function update(Campaign $campaign, array $payload, int $expectedEditVersion, Principal $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $payload, $expectedEditVersion, $actor) {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'CLOSED') {
                throw new CampaignValidationException(
                    'campaign_closed',
                    'A CLOSED campaign is content-frozen and cannot be edited.'
                );
            }

            if ($locked->edit_version !== $expectedEditVersion) {
                throw new CampaignValidationException(
                    'stale_edit_version',
                    'This campaign was changed by someone else — reload and try again.'
                );
            }

            $fieldsChanged = [];
            $previousFundId = $locked->fund_id;

            if (array_key_exists('description_html', $payload)) {
                $payload['description_html'] = $payload['description_html'] !== null
                    ? $this->sanitizer->sanitize($payload['description_html'])
                    : null;
            }

            foreach (['name', 'summary', 'description_html', 'purpose'] as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== $locked->{$field}) {
                    $locked->{$field} = $payload[$field];
                    $fieldsChanged[] = $field;
                }
            }

            if (array_key_exists('program_ulid', $payload)) {
                $programId = $payload['program_ulid'] !== null
                    ? Program::where('ulid', $payload['program_ulid'])->value('id')
                    : null;

                if ($payload['program_ulid'] !== null && $programId === null) {
                    throw new CampaignValidationException('program_not_found', 'The referenced Program does not exist.');
                }

                if ($programId !== $locked->program_id) {
                    $locked->program_id = $programId;
                    $fieldsChanged[] = 'program_id';
                }
            }

            if (array_key_exists('fund_ulid', $payload)) {
                if ($payload['fund_ulid'] !== null) {
                    $fund = Fund::query()->where('ulid', $payload['fund_ulid'])->first();

                    if ($fund === null) {
                        throw new CampaignValidationException('fund_not_found', 'The referenced Fund does not exist.');
                    }

                    // BR-5: protection against an ARCHIVED Fund being used
                    // lives at ASSIGNMENT time — the admin UI's own
                    // ACTIVE-only selector is presentation, not enforcement.
                    // (The publish-time re-check in CampaignLifecycleService
                    // remains the second, independent guard.)
                    if ($fund->status !== 'ACTIVE') {
                        throw new CampaignValidationException(
                            'fund_not_active',
                            'An ARCHIVED Fund cannot be assigned to a campaign (BR-5).'
                        );
                    }

                    $newFundId = $fund->id;
                } else {
                    $newFundId = null;
                }

                if ($newFundId !== $locked->fund_id) {
                    $locked->fund_id = $newFundId;
                    $fieldsChanged[] = 'fund_id';
                }
            }

            if (array_key_exists('target_amount_minor', $payload) || array_key_exists('currency', $payload)) {
                $this->assertValidMoney([
                    'target_amount_minor' => $payload['target_amount_minor'] ?? $locked->target_amount_minor,
                    'currency' => $payload['currency'] ?? $locked->currency,
                ]);
            }

            // Validated against the RESOLVED pair (payload value where
            // supplied, persisted value otherwise) — a partial update that
            // supplies only one of the two dates is a case the request-level
            // rule alone cannot cover.
            $this->assertValidPeriod(
                array_key_exists('starts_at', $payload) ? $payload['starts_at'] : $locked->starts_at,
                array_key_exists('ends_at', $payload) ? $payload['ends_at'] : $locked->ends_at,
            );

            foreach (['target_amount_minor', 'currency', 'starts_at', 'ends_at'] as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== $locked->{$field}) {
                    $locked->{$field} = $payload[$field];
                    $fieldsChanged[] = $field;
                }
            }

            $locked->edit_version++;
            $locked->updated_by_principal_id = $actor->id;
            $locked->save();

            $this->auditLogger->recordCampaignUpdated($locked->id, ['fields_changed' => $fieldsChanged], $actor);

            if (in_array('fund_id', $fieldsChanged, true)) {
                $this->auditLogger->recordCampaignFundAssigned($locked->id, [
                    'previous_fund_id' => $previousFundId,
                    'new_fund_id' => $locked->fund_id,
                ], $actor);
            }

            return $locked;
        });
    }

    /**
     * @param  array{target_amount_minor?:?int,currency?:?string}  $payload
     */
    private function assertValidMoney(array $payload): void
    {
        $amount = $payload['target_amount_minor'] ?? null;
        $currency = $payload['currency'] ?? null;

        // Currency validity is checked ALWAYS — an unregistered currency must
        // never be persisted, even when no target amount accompanies it
        // (section 13: currency "must exist in the CurrencyMinorUnits
        // registry ... an unregistered currency code is rejected at
        // validation time, never silently assumed to have 2 minor-unit
        // digits"; AC-007-021). Campaign.currency always resolves to a
        // concrete value, since it falls back to the configured default.
        $resolvedCurrency = $currency ?? config('campaign.default_currency');

        if (! CurrencyMinorUnits::isRegistered($resolvedCurrency)) {
            throw new CampaignValidationException(
                'unknown_currency',
                "Currency '{$resolvedCurrency}' is not registered in config/money.php."
            );
        }

        if ($amount === null) {
            return;
        }

        if (! is_int($amount) || $amount < 0) {
            throw new CampaignValidationException('invalid_amount', 'target_amount_minor must be a non-negative integer.');
        }
    }

    /**
     * section 13: "starts_at/ends_at: nullable date; if both present, ends_at
     * must be >= starts_at". Enforced here (the transaction owner) so the rule
     * holds for EVERY entry point — including a partial update supplying only
     * one of the two dates, which a request-level rule alone cannot cover.
     *
     * A null on either side means "no constraint on that side" — consistent
     * with the eligibility contract's own null semantics (section 8b).
     */
    private function assertValidPeriod(mixed $startsAt, mixed $endsAt): void
    {
        if ($startsAt === null || $endsAt === null) {
            return;
        }

        if (Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
            throw new CampaignValidationException(
                'invalid_period',
                'ends_at must be greater than or equal to starts_at.'
            );
        }
    }
}
