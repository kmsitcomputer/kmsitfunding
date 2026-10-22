<?php

namespace Tests\Feature\Donation;

use App\Models\Campaign\Campaign;
use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Services\Donation\DonationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — database invariants (docs/implementation/IMP-008-donation.md
 * "Database Impact" / "Tests Required", AC-008-011): RESTRICT FKs proven
 * by raw queries bypassing the service layer; uniqueness proven at the
 * DB level; guest-path CHECK at the DB level on MySQL with the
 * app-level guard as the SQLite enforcement layer.
 */
class DonationSchemaConstraintsTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    private function makeDonation(): Donation
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, 'schema-'.uniqid());
    }

    public function test_campaign_referenced_by_a_donation_cannot_be_deleted_at_the_database_level(): void
    {
        $donation = $this->makeDonation();

        $this->expectException(QueryException::class);
        Campaign::query()->whereKey($donation->campaign_id)->delete();
    }

    public function test_principal_referenced_as_donor_cannot_be_deleted_at_the_database_level(): void
    {
        $donor = $this->makeUnauthorizedActor();
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
        ], $donor, 'schemap-'.uniqid());

        $this->expectException(QueryException::class);
        Principal::query()->whereKey($donor->id)->delete();
    }

    public function test_donation_ulid_uniqueness_is_enforced_by_the_database(): void
    {
        $donation = $this->makeDonation();

        $this->expectException(QueryException::class);

        Donation::query()->insert([
            'ulid' => $donation->ulid,
            'campaign_id' => $donation->campaign_id,
            'amount_minor' => 100,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'guest_name' => 'G2',
            'guest_email' => 'g2@example.com',
            'idempotency_key' => 'ulid-dup-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_idempotency_key_uniqueness_is_enforced_by_the_database(): void
    {
        $donation = $this->makeDonation();

        $this->expectException(QueryException::class);

        Donation::query()->insert([
            'ulid' => (string) Str::ulid(),
            'campaign_id' => $donation->campaign_id,
            'amount_minor' => 100,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'guest_name' => 'G2',
            'guest_email' => 'g2@example.com',
            'idempotency_key' => $donation->idempotency_key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_neither_donor_path_populated_is_rejected_at_the_app_guard(): void
    {
        $donation = new Donation;
        $donation->forceFill([
            'ulid' => (string) Str::ulid(),
            'campaign_id' => $this->makeDonation()->campaign_id,
            'amount_minor' => 100,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'idempotency_key' => 'neither-'.uniqid(),
        ]);

        $this->assertFalse(Donation::isDonorPathConsistent($donation));

        $this->expectException(\RuntimeException::class);
        $donation->save();
    }

    public function test_both_donor_paths_populated_is_rejected_at_the_app_guard(): void
    {
        $donor = $this->makeUnauthorizedActor();
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $donation = new Donation;
        $donation->forceFill([
            'ulid' => (string) Str::ulid(),
            'campaign_id' => $campaign->id,
            'donor_principal_id' => $donor->id,
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
            'amount_minor' => 100,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'idempotency_key' => 'both-'.uniqid(),
        ]);

        $this->assertFalse(Donation::isDonorPathConsistent($donation));

        $this->expectException(\RuntimeException::class);
        $donation->save();
    }
}
