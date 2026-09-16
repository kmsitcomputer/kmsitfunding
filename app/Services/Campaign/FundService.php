<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Fund;
use App\Models\Rbac\Principal;
use App\Services\Campaign\Exceptions\FundValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-007 — Fund identity CRUD (docs/implementation/
 * IMP-007-campaign-program-fund.md section 8/12 BR-5). Fund is designation/
 * restriction context only — no amount/balance is ever stored or mutated
 * here.
 */
class FundService
{
    public function __construct(private readonly CampaignAuditLogger $auditLogger) {}

    /**
     * @param  array{name:string,code:string,restriction_note?:string}  $payload
     */
    public function create(array $payload, Principal $actor): Fund
    {
        return DB::transaction(function () use ($payload, $actor) {
            $code = strtoupper($payload['code']);

            if (Fund::where('code', $code)->exists()) {
                throw new FundValidationException('code_taken', "Fund code '{$code}' is already in use.");
            }

            $fund = new Fund;
            $fund->forceFill([
                'name' => $payload['name'],
                'code' => $code,
                'restriction_note' => $payload['restriction_note'] ?? null,
                'status' => 'ACTIVE',
                'created_by_principal_id' => $actor->id,
                'updated_by_principal_id' => $actor->id,
            ]);
            $fund->save();

            $this->auditLogger->recordFundCreated($fund->id, [
                'name' => $fund->name,
                'code' => $fund->code,
            ], $actor);

            return $fund->fresh();
        });
    }

    /**
     * @param  array{name?:string,restriction_note?:string}  $payload
     */
    public function update(Fund $fund, array $payload, Principal $actor): Fund
    {
        return DB::transaction(function () use ($fund, $payload, $actor) {
            $locked = Fund::query()->whereKey($fund->id)->lockForUpdate()->firstOrFail();

            $fieldsChanged = [];

            foreach (['name', 'restriction_note'] as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== $locked->{$field}) {
                    $locked->{$field} = $payload[$field];
                    $fieldsChanged[] = $field;
                }
            }

            $locked->updated_by_principal_id = $actor->id;
            $locked->save();

            $this->auditLogger->recordFundUpdated($locked->id, ['fields_changed' => $fieldsChanged], $actor);

            return $locked;
        });
    }

    /**
     * Fund archival always succeeds and is idempotent — protection against
     * an ARCHIVED Fund being used lives entirely at ASSIGNMENT time (a
     * Campaign cannot select an ARCHIVED Fund) and at PUBLISH time (BR-1:
     * CampaignLifecycleService::publish() requires the Fund be ACTIVE),
     * never as an archival-blocking check here. Archiving a Fund does NOT
     * retroactively affect any Campaign's historical fund_id, published or
     * otherwise (section 11 Fund lifecycle note; AC-007-016).
     *
     * IMPLEMENTATION-PHASE SPEC CORRECTION: the originally-drafted BR-5
     * ("cannot be archived while any non-CLOSED Campaign references it") was
     * internally inconsistent with AC-007-016 and with section 11's own Fund
     * lifecycle note, both of which require archival to succeed regardless
     * of existing references. This is an ordinary specification drafting
     * defect (not a Human Decision), corrected here per the standing
     * implementation authorization's "fix ordinary implementation defects"
     * instruction; the specification's BR-5 prose is patched to match in the
     * same pass (see the self-audit findings in the Final Technical Report).
     */
    public function archive(Fund $fund, Principal $actor): Fund
    {
        return DB::transaction(function () use ($fund, $actor) {
            $locked = Fund::query()->whereKey($fund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ARCHIVED') {
                return $locked;
            }

            $locked->forceFill(['status' => 'ARCHIVED', 'updated_by_principal_id' => $actor->id])->save();
            $this->auditLogger->recordFundArchived($locked->id, $actor);

            return $locked;
        });
    }
}
