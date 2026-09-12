<?php

namespace App\Enums;

/**
 * Q24 — persistent Identity Lifecycle. Independent of SecurityRestriction and of
 * email verification (which is a timestamp, not a lifecycle value).
 */
enum IdentityLifecycle: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
