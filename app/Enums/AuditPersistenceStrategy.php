<?php

namespace App\Enums;

/**
 * IMP-004 persistence strategy — a second, orthogonal registry axis to
 * criticality (IMP004-REAUDIT-M02). MUTATION_ATOMIC: the audit append shares
 * the business mutation's transaction and rolls it back on failure.
 * DENIAL_DURABLE: no authorized mutation exists; the write is sequenced
 * strictly after the denying transaction's rollback (security.authorization.denied
 * only). NON_CRITICAL events use neither strategy (null on their registry entry).
 */
enum AuditPersistenceStrategy: string
{
    case MutationAtomic = 'mutation_atomic';
    case DenialDurable = 'denial_durable';
}
