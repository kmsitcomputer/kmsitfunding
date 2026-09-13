<?php

namespace App\Enums;

/**
 * IMP-004 audit event criticality (Q26). Owned exclusively by the canonical
 * event registry — never caller-selectable. No third value exists.
 */
enum AuditCriticality: string
{
    case Critical = 'critical';
    case NonCritical = 'non_critical';
}
