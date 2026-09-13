<?php

namespace App\Enums;

/**
 * IMP-003 canonical Principal kinds (docs/implementation/IMP-003-rbac-scope-business-authority.md
 * "Principal Model"). A Principal is exactly one of these three — never a fourth, never none.
 */
enum PrincipalKind: string
{
    case Human = 'human';
    case System = 'system';
    case Integration = 'integration';
}
