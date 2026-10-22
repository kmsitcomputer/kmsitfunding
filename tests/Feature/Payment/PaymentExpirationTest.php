<?php

namespace Tests\Feature\Payment;

use App\Models\Audit\AuditRecord;
use App\Models\Donation\Donation;
use App\Models\Payment\Payment;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Payment\PaymentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — expiration (docs/implementation/IMP-009-payment-hub.md
 * "Expiration", HD-IMP009-09 FINAL / LOCKED, AC-009-012/013): two
 * independent clocks (Payment expiry never touches the Donation);
 * provider-supplied expires_at windows always apply; the internal
 * fallback (config payment.pending_expiry_minutes, default null)
 * never acts while unset.
 */
class PaymentExpirationTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    private function seedSweepPrincipal(): void
    {
        SystemPrincipal::firstOrCreate(
            ['code' => 'scheduler.payment-expiry-sweep'],
            ['description' => 'Test expiry sweep identity.']
        );
    }

    private function makePayment(?\DateTimeInterface $expiresAt = null): Payment
    {
        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'exp-'.uniqid()
        )->payment;

        if ($expiresAt !== null) {
            $payment->forceFill(['expires_at' => $expiresAt])->save();
        }

        return $payment->fresh();
    }

    public function test_provider_supplied_window_expiry_moves_payment_to_expired_only(): void
    {
        $this->seedSweepPrincipal();
        $payment = $this->makePayment(now()->subMinute());
        $donationId = $payment->donation_id;

        $this->artisan('payment:expire-pending')->assertSuccessful();

        $this->assertSame('EXPIRED', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->expired_at);
        $this->assertSame('PENDING', Donation::query()->whereKey($donationId)->first()->status);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'payment.expired',
            'subject_id' => $payment->id,
            'actor_principal_kind' => 'system',
        ]);
    }

    public function test_future_window_is_not_swept(): void
    {
        $this->seedSweepPrincipal();
        $payment = $this->makePayment(now()->addHour());

        $this->artisan('payment:expire-pending')->assertSuccessful();

        $this->assertSame('PENDING', $payment->fresh()->status);
    }

    public function test_fallback_window_applies_only_when_configured(): void
    {
        $this->seedSweepPrincipal();

        config()->set('payment.pending_expiry_minutes', null);
        $unconfigured = $this->makePayment();
        $unconfigured->forceFill(['created_at' => now()->subDays(30)])->save();

        $this->artisan('payment:expire-pending')->assertSuccessful();
        $this->assertSame('PENDING', $unconfigured->fresh()->status);

        config()->set('payment.pending_expiry_minutes', 60);
        $configured = $this->makePayment();
        $configured->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->artisan('payment:expire-pending')->assertSuccessful();
        $this->assertSame('EXPIRED', $configured->fresh()->status);
    }

    public function test_sweep_is_idempotent_for_the_same_candidate(): void
    {
        $this->seedSweepPrincipal();
        $payment = $this->makePayment(now()->subMinute());

        $this->artisan('payment:expire-pending')->assertSuccessful();
        $this->artisan('payment:expire-pending')->assertSuccessful();

        $this->assertSame(1, AuditRecord::where('event_type', 'payment.expired')
            ->where('subject_id', $payment->id)
            ->count());
    }

    public function test_sweep_without_a_seeded_principal_fails_closed(): void
    {
        $this->makePayment(now()->subMinute());

        $this->artisan('payment:expire-pending')->assertFailed();
    }
}
