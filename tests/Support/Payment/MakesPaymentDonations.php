<?php

namespace Tests\Support\Payment;

use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Services\Donation\DonationService;
use Tests\Support\Donation\MakesDonationCampaigns;

/**
 * IMP-009 test-only fixture helper: builds a PENDING Donation through
 * the real DonationService path (guest or authenticated), so Payment
 * tests exercise the genuine Donation contract — never raw status
 * writes.
 */
trait MakesPaymentDonations
{
    use MakesDonationCampaigns;

    private int $paymentFixtureSequence = 0;

    private function makePendingGuestDonation(?Principal $seeder = null, ?string $key = null): Donation
    {
        $this->paymentFixtureSequence++;

        $seeder ??= $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest-'.$this->paymentFixtureSequence.'-'.uniqid().'@example.com',
        ], null, $key ?? 'pay-guest-'.$this->paymentFixtureSequence.'-'.uniqid());
    }

    private function makePendingOwnedDonation(Principal $owner, ?string $key = null): Donation
    {
        $this->paymentFixtureSequence++;

        $campaign = $this->makeEligibleCampaign($owner);

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
        ], $owner, $key ?? 'pay-owned-'.$this->paymentFixtureSequence.'-'.uniqid());
    }
}
