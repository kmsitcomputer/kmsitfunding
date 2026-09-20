<?php

namespace Tests\Feature\Payment;

use App\Enums\ScopeType;
use App\Models\Payment\ManualTransferEvidence;
use App\Models\Payment\Payment;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Payment\PaymentCreationService;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — HTTP surface + file security (docs/implementation/
 * IMP-009-payment-hub.md "Routes / API Boundary" / "File Security",
 * HD-IMP009-04, AC-009-017): public guest creation with
 * Idempotency-Key; authenticated creation; donor cancel; no
 * guest-facing resume via identifiers; evidence upload/retrieval
 * authorization (private disk, randomized name, no permanent public
 * URL, unauthorized retrieval denied).
 */
class PaymentHttpTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function actingAsDonor(): Principal
    {
        $user = User::create([
            'email' => 'payment-donor-http-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $principal = app(PrincipalService::class)->forUser($user);
        $this->actingAs($user);

        return $principal;
    }

    public function test_guest_creates_a_payment_through_the_public_endpoint(): void
    {
        $donation = $this->makePendingGuestDonation();

        $response = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-guest-'.uniqid()]
        );

        $response->assertRedirect();
        $this->assertSame(1, Payment::query()->count());

        $payment = Payment::query()->first();

        $this->assertSame('PENDING', $payment->status);
        $this->assertSame($donation->id, $payment->donation_id);
    }

    public function test_public_creation_without_an_idempotency_key_is_rejected(): void
    {
        $donation = $this->makePendingGuestDonation();

        $response = $this->post("/donations/{$donation->ulid}/payments", [
            'provider' => 'manual_transfer',
        ]);

        $response->assertSessionHasErrors('idempotency_key');
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_authenticated_donor_creates_and_cancels_their_own_payment(): void
    {
        $principal = $this->actingAsDonor();

        $role = Role::create(['code' => 'pay_http_'.uniqid(), 'name' => 'Payment HTTP Test Role']);
        $permission = Permission::firstOrCreate(['code' => PermissionRegistry::PAYMENT_CANCEL], ['description' => 'test']);
        $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Own->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        $donation = $this->makePendingOwnedDonation($principal);

        $create = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-auth-'.uniqid()]
        );

        $create->assertRedirect();

        $payment = Payment::query()->first();
        $this->assertNotNull($payment);

        $cancel = $this->post("/me/donations/{$donation->ulid}/payments/{$payment->ulid}/cancel");

        $cancel->assertRedirect();
        $this->assertSame('CANCELLED', $payment->fresh()->status);
    }

    public function test_no_identifier_based_guest_resume_endpoint_exists(): void
    {
        $donation = $this->makePendingGuestDonation();

        $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-noresume-'.uniqid()]
        );

        $payment = Payment::query()->first();

        // No bearer-token/magic-link/identifier-as-credential resume
        // path exists anywhere: unknown routes fall to the catch-all,
        // never a payment read.
        $this->get("/donations/{$donation->ulid}/payments/{$payment->ulid}/resume")->assertNotFound();
        $this->get("/payments/{$payment->ulid}")->assertNotFound();
        $this->get("/payments/{$payment->ulid}/retry?email=guest@example.com")->assertNotFound();
    }

    public function test_unauthenticated_donor_routes_require_authentication(): void
    {
        $donation = $this->makePendingGuestDonation();

        $this->get("/me/donations/{$donation->ulid}/payments")->assertRedirect('/login');
    }

    public function test_evidence_file_is_private_randomized_and_authorization_gated(): void
    {
        Storage::fake('local');

        $principal = $this->actingAsDonor();
        $donation = $this->makePendingOwnedDonation($principal);
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], $principal, 'http-ev-'.uniqid()
        );

        $role = Role::create(['code' => 'pay_ev_'.uniqid(), 'name' => 'Payment Evidence Test Role']);
        $permission = Permission::firstOrCreate(
            ['code' => PermissionRegistry::PAYMENT_MANUAL_TRANSFER_SUBMIT_EVIDENCE],
            ['description' => 'test']
        );
        $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        $viewPermission = Permission::firstOrCreate(['code' => PermissionRegistry::PAYMENT_VIEW], ['description' => 'test']);
        $role->permissions()->attach($viewPermission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Own->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        $upload = $this->post(
            "/me/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence",
            ['evidence' => UploadedFile::fake()->image('receipt.jpg')->size(100)]
        );

        $upload->assertRedirect();

        $evidence = ManualTransferEvidence::query()->first();
        $this->assertNotNull($evidence);
        $this->assertStringStartsWith('evidence/', $evidence->file_path);
        $this->assertStringNotContainsString('receipt.jpg', $evidence->file_path);

        // The stored path is on the private disk — never the public
        // disk, never a permanent public URL.
        $this->assertFalse(Storage::disk('public')->exists($evidence->file_path));

        // Owner retrieval through the gated route succeeds.
        $show = $this->get("/me/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence/{$evidence->ulid}");
        $show->assertOk();

        // Another donor is denied.
        $intruder = User::create([
            'email' => 'payment-intruder-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $this->actingAs($intruder);

        $this->get("/me/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence/{$evidence->ulid}")
            ->assertForbidden();
    }

    public function test_unauthenticated_evidence_retrieval_is_denied(): void
    {
        Storage::fake('local');

        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-ev-guest-'.uniqid()
        );

        $evidence = ManualTransferEvidence::create([
            'ulid' => (string) Str::ulid(),
            'payment_id' => $payment->id,
            'file_path' => 'evidence/guest-proof.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 512,
        ]);
        Storage::disk('local')->put('evidence/guest-proof.jpg', 'fake-bytes');

        // Guest (logged-out) retrieval redirects to login — never the
        // file. No permanent public URL ever resolves to it.
        $this->get("/me/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence/{$evidence->ulid}")
            ->assertRedirect('/login');
    }
}
