<?php

namespace Tests\Feature\Payment;

use App\Enums\ScopeType;
use App\Enums\SecurityRestriction;
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

    private function grantCreate(Principal $principal, ScopeType $scope): void
    {
        $role = Role::create(['code' => 'pay_create_'.uniqid(), 'name' => 'Payment Create Test Role']);
        $permission = Permission::firstOrCreate(['code' => PermissionRegistry::PAYMENT_CREATE], ['description' => 'test']);
        $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => $scope->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);
    }

    public function test_authenticated_owner_without_payment_create_is_denied(): void
    {
        $principal = $this->actingAsDonor();
        $donation = $this->makePendingOwnedDonation($principal);

        $response = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-noperm-'.uniqid()]
        );

        $response->assertForbidden();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_payment_create_with_wrong_scope_is_denied(): void
    {
        $principal = $this->actingAsDonor();
        $this->grantCreate($principal, ScopeType::Organization);
        $donation = $this->makePendingOwnedDonation($principal);

        $response = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-wrongscope-'.uniqid()]
        );

        $response->assertForbidden();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_security_restricted_principal_is_denied_despite_grant(): void
    {
        $user = User::create([
            'email' => 'payment-restricted-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);
        $this->actingAs($user);
        $this->grantCreate($principal, ScopeType::Own);
        $donation = $this->makePendingOwnedDonation($principal);

        $user->forceFill(['security_restriction' => SecurityRestriction::Suspended])->save();

        $response = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-restricted-'.uniqid()]
        );

        $response->assertForbidden();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_payment_create_for_another_donors_donation_is_denied(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $intruderUser = User::create([
            'email' => 'payment-intruder-create-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $intruder = app(PrincipalService::class)->forUser($intruderUser);
        $this->actingAs($intruderUser);
        $this->grantCreate($intruder, ScopeType::Own);
        $donation = $this->makePendingOwnedDonation($owner);

        $response = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-intruder-'.uniqid()]
        );

        $response->assertForbidden();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_authorized_own_creation_is_accepted(): void
    {
        $principal = $this->actingAsDonor();
        $this->grantCreate($principal, ScopeType::Own);
        $donation = $this->makePendingOwnedDonation($principal);

        $response = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-ok-'.uniqid()]
        );

        $response->assertRedirect();
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_guest_cannot_create_against_a_donor_owned_donation(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $donation = $this->makePendingOwnedDonation($owner);

        $response = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-guestowned-'.uniqid()]
        );

        $response->assertForbidden();
        $this->assertSame(0, Payment::query()->count());
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

    public function test_authenticated_donor_creates_and_cancels_their_own_payment(): void
    {
        $principal = $this->actingAsDonor();

        $role = Role::create(['code' => 'pay_http_'.uniqid(), 'name' => 'Payment HTTP Test Role']);
        $createPermission = Permission::firstOrCreate(['code' => PermissionRegistry::PAYMENT_CREATE], ['description' => 'test']);
        $role->permissions()->attach($createPermission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
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

    public function test_public_creation_without_an_idempotency_key_is_rejected(): void
    {
        $donation = $this->makePendingGuestDonation();

        $response = $this->post("/donations/{$donation->ulid}/payments", [
            'provider' => 'manual_transfer',
        ]);

        $response->assertSessionHasErrors('idempotency_key');
        $this->assertSame(0, Payment::query()->count());
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

    public function test_public_status_read_without_the_creation_session_is_not_found(): void
    {
        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-pub-nosess-'.uniqid()
        );

        $this->get("/donations/{$donation->ulid}/payments/{$payment->ulid}")->assertNotFound();
    }

    public function test_public_status_read_survives_ordinary_subsequent_requests(): void
    {
        $donation = $this->makePendingGuestDonation();

        $create = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-inflow-'.uniqid()]
        );
        $create->assertRedirect();

        $payment = Payment::query()->first();
        $url = "/donations/{$donation->ulid}/payments/{$payment->ulid}";

        $inFlow = $this->get($url);
        $inFlow->assertOk();
        $inFlow->assertJsonPath('ulid', $payment->ulid);
        $inFlow->assertJsonPath('status', 'PENDING');

        $this->get('/payments/'.$payment->ulid)->assertNotFound();

        $later = $this->get($url);
        $later->assertOk();
        $later->assertJsonPath('ulid', $payment->ulid);
    }

    public function test_public_status_read_from_a_different_anonymous_session_is_not_found(): void
    {
        $donation = $this->makePendingGuestDonation();

        $create = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-crosssess-'.uniqid()]
        );
        $create->assertRedirect();

        $payment = Payment::query()->first();
        $url = "/donations/{$donation->ulid}/payments/{$payment->ulid}";

        $this->get($url)->assertOk();

        $this->flushSession();

        $this->get($url)->assertNotFound();
    }

    public function test_two_guest_payments_in_one_session_do_not_invalidate_each_other(): void
    {
        $firstDonation = $this->makePendingGuestDonation();
        $secondDonation = $this->makePendingGuestDonation();

        $this->post(
            "/donations/{$firstDonation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-multi-a-'.uniqid()]
        )->assertRedirect();

        $this->post(
            "/donations/{$secondDonation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-multi-b-'.uniqid()]
        )->assertRedirect();

        $first = Payment::query()->where('donation_id', $firstDonation->id)->firstOrFail();
        $second = Payment::query()->where('donation_id', $secondDonation->id)->firstOrFail();

        $this->get("/donations/{$firstDonation->ulid}/payments/{$first->ulid}")->assertOk();
        $this->get("/donations/{$secondDonation->ulid}/payments/{$second->ulid}")->assertOk();
        $this->get("/donations/{$firstDonation->ulid}/payments/{$first->ulid}")->assertOk();
    }

    public function test_client_secret_never_leaks_through_an_unrestricted_get(): void
    {
        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-pub-secret-'.uniqid()
        );
        $payment->forceFill(['instructions_payload' => [
            'type' => 'stripe_payment_intent',
            'id' => 'pi_test_secret',
            'client_secret' => 'pi_test_secret_s3cr3t',
        ]])->save();

        $this->get("/donations/{$donation->ulid}/payments/{$payment->ulid}")->assertNotFound();
    }

    public function test_guest_evidence_upload_after_ordinary_intermediate_requests_is_accepted(): void
    {
        Storage::fake('local');

        $donation = $this->makePendingGuestDonation();

        $create = $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-guestev-'.uniqid()]
        );
        $create->assertRedirect();

        $payment = Payment::query()->first();

        $this->get("/donations/{$donation->ulid}/payments/{$payment->ulid}")->assertOk();
        $this->get('/payments/'.$payment->ulid)->assertNotFound();
        $this->get("/donations/{$donation->ulid}/payments/{$payment->ulid}")->assertOk();

        $upload = $this->post(
            "/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence",
            ['evidence' => UploadedFile::fake()->image('receipt.jpg')->size(100)]
        );

        $upload->assertRedirect();

        $evidence = ManualTransferEvidence::query()->first();
        $this->assertNotNull($evidence);
        $this->assertSame($payment->id, $evidence->payment_id);
        $this->assertNull($evidence->submitted_by_principal_id);
    }

    public function test_guest_evidence_upload_from_a_different_anonymous_session_is_denied(): void
    {
        Storage::fake('local');

        $donation = $this->makePendingGuestDonation();

        $this->post(
            "/donations/{$donation->ulid}/payments",
            ['provider' => 'manual_transfer'],
            ['Idempotency-Key' => 'http-pay-guestev-x-'.uniqid()]
        )->assertRedirect();

        $payment = Payment::query()->first();

        $this->flushSession();

        $upload = $this->post(
            "/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence",
            ['evidence' => UploadedFile::fake()->image('receipt.jpg')->size(100)]
        );

        $upload->assertForbidden();
        $this->assertSame(0, ManualTransferEvidence::query()->count());
    }

    public function test_guest_evidence_upload_shows_identifier_knowledge_alone_grants_nothing(): void
    {
        Storage::fake('local');

        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-pub-evidonly-'.uniqid()
        );

        $this->get("/donations/{$donation->ulid}/payments/{$payment->ulid}")->assertNotFound();

        $upload = $this->post(
            "/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence",
            ['evidence' => UploadedFile::fake()->image('receipt.jpg')->size(100)]
        );

        $upload->assertForbidden();
        $this->assertSame(0, ManualTransferEvidence::query()->count());
    }

    public function test_guest_evidence_upload_without_the_creation_session_is_denied(): void
    {
        Storage::fake('local');

        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-pub-evnosess-'.uniqid()
        );

        $upload = $this->post(
            "/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence",
            ['evidence' => UploadedFile::fake()->image('receipt.jpg')->size(100)]
        );

        $upload->assertForbidden();
        $this->assertSame(0, ManualTransferEvidence::query()->count());
    }

    public function test_guest_evidence_upload_against_a_donor_owned_donation_is_denied(): void
    {
        Storage::fake('local');

        $owner = $this->makeUnauthorizedActor();
        $donation = $this->makePendingOwnedDonation($owner);
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], $owner, 'http-pub-evowned-'.uniqid()
        );

        $upload = $this->withSession(['guest_payment_ulids' => [$payment->ulid]])->post(
            "/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence",
            ['evidence' => UploadedFile::fake()->image('receipt.jpg')->size(100)]
        );

        $upload->assertForbidden();
        $this->assertSame(0, ManualTransferEvidence::query()->count());
    }

    public function test_guest_evidence_upload_for_a_non_manual_provider_is_rejected(): void
    {
        Storage::fake('local');

        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-pub-evnonman-'.uniqid()
        );
        $payment->forceFill(['provider' => 'tripay'])->save();

        $upload = $this->withSession(['guest_payment_ulids' => [$payment->ulid]])->post(
            "/donations/{$donation->ulid}/payments/{$payment->ulid}/manual-transfer/evidence",
            ['evidence' => UploadedFile::fake()->image('receipt.jpg')->size(100)]
        );

        $upload->assertSessionHasErrors('not_manual_transfer');
        $this->assertSame(0, ManualTransferEvidence::query()->count());
    }

    public function test_unauthenticated_donor_routes_require_authentication(): void
    {
        $donation = $this->makePendingGuestDonation();

        $this->get("/me/donations/{$donation->ulid}/payments")->assertRedirect('/login');
    }

    private function actingAsOrgAdmin(array $permissionCodes): Principal
    {
        $user = User::create([
            'email' => 'payment-orgadmin-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);
        $this->actingAs($user);

        $role = Role::create(['code' => 'pay_orgadmin_'.uniqid(), 'name' => 'Payment Org Admin Test Role']);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Organization->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $principal;
    }

    public function test_org_admin_cancels_a_pending_payment_through_the_support_surface(): void
    {
        $this->actingAsOrgAdmin([PermissionRegistry::PAYMENT_CANCEL]);

        $owner = $this->makeUnauthorizedActor();
        $donation = $this->makePendingOwnedDonation($owner);
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], $owner, 'http-admincancel-'.uniqid()
        );

        $response = $this->post("/admin/payment/payments/{$payment->ulid}/cancel");

        $response->assertRedirect();
        $this->assertSame('CANCELLED', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->cancelled_by_principal_id);
    }

    public function test_admin_cancel_without_the_permission_is_denied(): void
    {
        $this->actingAsOrgAdmin([PermissionRegistry::PAYMENT_VIEW]);

        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-admincancel-noperm-'.uniqid()
        );

        $this->post("/admin/payment/payments/{$payment->ulid}/cancel")->assertForbidden();
        $this->assertSame('PENDING', $payment->fresh()->status);
    }

    public function test_admin_cancel_from_a_terminal_state_is_rejected(): void
    {
        $this->actingAsOrgAdmin([PermissionRegistry::PAYMENT_CANCEL]);

        $donation = $this->makePendingGuestDonation();
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'http-admincancel-term-'.uniqid()
        );
        $payment->forceFill(['status' => 'SUCCEEDED', 'succeeded_at' => now()])->save();

        $this->post("/admin/payment/payments/{$payment->ulid}/cancel")->assertForbidden();
        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
    }

    public function test_donor_own_grant_never_authorizes_the_admin_cancel_surface(): void
    {
        $principal = $this->actingAsDonor();
        $this->grantCreate($principal, ScopeType::Own);

        $role = Role::create(['code' => 'pay_owncancel_'.uniqid(), 'name' => 'Payment Own Cancel Test Role']);
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
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], $principal, 'http-admincancel-own-'.uniqid()
        );

        $this->post("/admin/payment/payments/{$payment->ulid}/cancel")->assertForbidden();
        $this->assertSame('PENDING', $payment->fresh()->status);
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
