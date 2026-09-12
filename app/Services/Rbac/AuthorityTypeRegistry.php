<?php

namespace App\Services\Rbac;

use App\Models\Rbac\AuthorityType;

/**
 * The 7 Authority Types docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md explicitly
 * approves. This registry seeds ONLY these — later domain stages register
 * their own additional types via their own migration + spec, per "Business
 * Authority > Authority Type Registry" (an extensible registry, not a closed
 * enum).
 */
class AuthorityTypeRegistry
{
    /**
     * @return array<string, array{name: string, description: string, is_financial: bool}>
     */
    public static function definitions(): array
    {
        return [
            AuthorityType::FINANCIAL_APPROVER => [
                'name' => 'Financial Approver',
                'description' => 'Authorized to approve a financial consequence.',
                'is_financial' => true,
            ],
            AuthorityType::REFUND_APPROVER => [
                'name' => 'Refund Approver',
                'description' => 'Authorized to approve a refund.',
                'is_financial' => true,
            ],
            AuthorityType::WITHDRAWAL_APPROVER => [
                'name' => 'Withdrawal Approver',
                'description' => 'Authorized to approve a withdrawal.',
                'is_financial' => true,
            ],
            AuthorityType::DISTRIBUTION_APPROVER => [
                'name' => 'Distribution Approver',
                'description' => 'Authorized to approve a distribution.',
                'is_financial' => true,
            ],
            AuthorityType::ZAKAT_AUTHORITY => [
                'name' => 'Zakat Authority',
                'description' => 'Designated Zakat administrator authority (Q9).',
                'is_financial' => false,
            ],
            AuthorityType::PARTNER_VERIFIER => [
                'name' => 'Partner Verifier',
                'description' => 'Authorized to verify a Partner.',
                'is_financial' => false,
            ],
            AuthorityType::BENEFICIARY_VERIFIER => [
                'name' => 'Beneficiary Verifier',
                'description' => 'Authorized to verify a Beneficiary application/case.',
                'is_financial' => false,
            ],
        ];
    }
}
