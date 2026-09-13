<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;

/**
 * IMP-003 extensible Business/Financial Authority Type registry. Seeded with
 * the 7 types docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md names; later domain
 * stages register their own. See "Business Authority > Authority Type
 * Registry".
 */
class AuthorityType extends Model
{
    public const FINANCIAL_APPROVER = 'financial_approver';

    public const REFUND_APPROVER = 'refund_approver';

    public const WITHDRAWAL_APPROVER = 'withdrawal_approver';

    public const DISTRIBUTION_APPROVER = 'distribution_approver';

    public const ZAKAT_AUTHORITY = 'zakat_authority';

    public const PARTNER_VERIFIER = 'partner_verifier';

    public const BENEFICIARY_VERIFIER = 'beneficiary_verifier';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_financial',
    ];

    protected function casts(): array
    {
        return [
            'is_financial' => 'boolean',
        ];
    }
}
