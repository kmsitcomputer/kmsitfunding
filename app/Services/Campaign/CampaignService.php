<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\Fund;
use App\Models\Campaign\Program;
use App\Models\Rbac\Principal;
use App\Services\Campaign\Exceptions\CampaignValidationException;
use App\Support\Money\CurrencyMinorUnits;
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
    public function __construct(private readonly CampaignAuditLogger $auditLogger) {}

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

            $campaign = new Campaign;
            $campaign->forceFill([
                'program_id' => $programId,
                'fund_id' => null,
                'name' => $payload['name'],
                'slug' => $slug,
                'summary' => $payload['summary'] ?? null,
                'description_html' => $payload['description_html'] ?? null,
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
                $newFundId = $payload['fund_ulid'] !== null
                    ? Fund::where('ulid', $payload['fund_ulid'])->value('id')
                    : null;

                if ($payload['fund_ulid'] !== null && $newFundId === null) {
                    throw new CampaignValidationException('fund_not_found', 'The referenced Fund does not exist.');
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

        if ($amount === null) {
            return;
        }

        if (! is_int($amount) || $amount < 0) {
            throw new CampaignValidationException('invalid_amount', 'target_amount_minor must be a non-negative integer.');
        }

        $resolvedCurrency = $currency ?? config('campaign.default_currency');

        if (! CurrencyMinorUnits::isRegistered($resolvedCurrency)) {
            throw new CampaignValidationException(
                'unknown_currency',
                "Currency '{$resolvedCurrency}' is not registered in config/money.php."
            );
        }
    }
}
