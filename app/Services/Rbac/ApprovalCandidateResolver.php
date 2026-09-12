<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Principal;

/**
 * "Approval Candidate Resolution" primitive contract only (see "Approval
 * Authority (primitive only)"). No Approval Policy/Instance/Step/Decision
 * model exists yet — a future Approval module implements this interface;
 * IMP-003 does not implement a concrete resolver of its own.
 */
interface ApprovalCandidateResolver
{
    /**
     * Whether $principal is a valid approval candidate for $step given
     * $resource — the owning Approval module supplies the concrete meaning
     * of $step and $resource; IMP-003 only defines the call shape.
     */
    public function isApprovalCandidate(Principal $principal, mixed $step, mixed $resource): bool;
}
