<?php

namespace App\Models\Audit;

use App\Models\Rbac\Principal;
use App\Services\Audit\Exceptions\AuditRecordImmutableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-004 canonical audit record — append-only operational evidence (Q28).
 *
 * "Immutable through authorized application operations": no update()/save()
 * beyond the initial create(), and no delete(), is exposed to ordinary
 * application code — enforced at the model level (save/delete guards) AND at
 * the Eloquent builder level (mass update/delete guards). This is not a
 * cryptographic tamper-evidence claim; DB-layer grant hardening is a
 * deployment-time SHOULD documented by the specification, not mandated here.
 *
 * The only future deletion path is a separately-authorized governed retention
 * purge (Q28/Q17 mechanism), which IMP-004 deliberately does not build.
 */
class AuditRecord extends Model
{
    protected $table = 'audit_records';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'occurred_at' => 'datetime',
            'subject_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actorPrincipal(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'actor_principal_id');
    }

    /**
     * Append-only guard: a persisted audit row is never updated through the
     * model. Initial insert (exists === false) is the only permitted save.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new AuditRecordImmutableException(
                'Canonical audit records are append-only — UPDATE of a persisted audit row is prohibited (Q28).'
            );
        }

        return parent::save($options);
    }

    /**
     * Append-only guard: no arbitrary delete surface in normal operation.
     */
    public function delete(): ?bool
    {
        throw new AuditRecordImmutableException(
            'Canonical audit records are append-only — DELETE of an audit row is prohibited outside an authorized governed retention purge (Q28).'
        );
    }

    /**
     * Append-only guard at the builder level: mass update()/delete() through
     * the Eloquent builder is blocked identically, so ordinary services cannot
     * bypass the model guards via AuditRecord::where(...)->update()/delete().
     */
    public function newEloquentBuilder($query): AuditRecordQueryBuilder
    {
        return new AuditRecordQueryBuilder($query);
    }
}
