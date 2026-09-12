<?php

namespace Database\Seeders;

use App\Models\Rbac\AuthorityType;
use App\Services\Rbac\AuthorityTypeRegistry;
use Illuminate\Database\Seeder;

/**
 * Idempotent (upsert-by-code) sync of the 7 approved Authority Types into
 * the `authority_types` table.
 */
class RbacAuthorityTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AuthorityTypeRegistry::definitions() as $code => $definition) {
            AuthorityType::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_financial' => $definition['is_financial'],
                ],
            );
        }
    }
}
