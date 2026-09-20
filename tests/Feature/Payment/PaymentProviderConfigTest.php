<?php

namespace Tests\Feature\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Enums\ScopeType;
use App\Models\Audit\AuditRecord;
use App\Models\Payment\ManualTransferBankAccount;
use App\Models\Payment\PaymentProviderCredential;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Payment\ProviderCredentialPayload;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 F-06 — provider credential write/read contract: the admin
 * write path and the adapters share ONE canonical typed payload
 * contract (no raw secret text). Round-trip: admin write → encrypted
 * persistence → adapter-shaped load with the expected fields.
 * Secrets are never exposed to the frontend, never logged, never
 * audited.
 *
 * IMP-009 F-11 — the literal `bank-accounts` route is never swallowed
 * by `{provider}`: bank-account creation reaches the intended
 * controller.
 */
class PaymentProviderConfigTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function actingPlatformAdmin(): Principal
    {
        $user = User::create([
            'email' => 'payment-config-admin-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);
        $this->actingAs($user);

        $role = Role::create(['code' => 'pay_cfg_'.uniqid(), 'name' => 'Payment Config Test Role']);
        $permission = Permission::firstOrCreate(['code' => PermissionRegistry::PAYMENT_PROVIDER_CONFIG_MANAGE], ['description' => 'test']);
        $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $principal;
    }

    private function seedCredentialRow(string $provider): void
    {
        PaymentProviderCredential::create([
            'provider' => $provider,
            'mode' => 'SANDBOX',
            'encrypted_secret' => Crypt::encryptString('placeholder'),
            'is_enabled' => false,
        ]);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function assertCredentialRoundTrip(string $provider, array $fields): void
    {
        $this->actingPlatformAdmin();
        $this->seedCredentialRow($provider);

        $response = $this->post("/admin/payment/provider-config/{$provider}", array_merge([
            'mode' => 'PRODUCTION',
            'is_enabled' => true,
        ], $fields));

        $response->assertRedirect();

        $credential = PaymentProviderCredential::query()->where('provider', $provider)->firstOrFail();
        $this->assertSame('PRODUCTION', $credential->mode);
        $this->assertTrue($credential->is_enabled);

        $this->assertNotSame(json_encode($fields), $credential->encrypted_secret);

        $decoded = ProviderCredentialPayload::decode($provider, $credential->fresh());

        foreach ($fields as $field => $value) {
            $this->assertSame($value, $decoded[$field]);
        }

        $response->assertDontSee($fields[array_key_first($fields)]);

        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'provider_config.credential_changed',
            'subject_id' => $credential->id,
        ]);

        $audit = AuditRecord::query()
            ->where('event_type', 'provider_config.credential_changed')
            ->where('subject_id', $credential->id)
            ->firstOrFail();

        foreach ($fields as $value) {
            $this->assertStringNotContainsString($value, (string) $audit->getRawOriginal('metadata'));
        }
    }

    public function test_tripay_credential_round_trip(): void
    {
        $this->assertCredentialRoundTrip('tripay', [
            'merchant_code' => 'T0001',
            'api_key' => 'tripay-admin-api-key',
            'private_key' => 'tripay-admin-private-key',
        ]);
    }

    public function test_xendit_credential_round_trip(): void
    {
        $this->assertCredentialRoundTrip('xendit', [
            'api_key' => 'xnd_admin_key',
            'callback_token' => 'xnd-admin-callback-token',
        ]);
    }

    public function test_stripe_credential_round_trip(): void
    {
        $this->assertCredentialRoundTrip('stripe', [
            'secret_key' => 'sk_admin_key',
            'webhook_secret' => 'whsec_admin_secret',
        ]);
    }

    public function test_raw_secret_text_is_rejected_for_a_structured_provider(): void
    {
        $this->actingPlatformAdmin();
        $this->seedCredentialRow('tripay');

        $response = $this->post('/admin/payment/provider-config/tripay', [
            'mode' => 'SANDBOX',
            'secret' => 'raw unstructured secret text',
        ]);

        $response->assertSessionHasErrors(['merchant_code', 'api_key', 'private_key']);

        $credential = PaymentProviderCredential::query()->where('provider', 'tripay')->firstOrFail();
        $this->assertSame('placeholder', Crypt::decryptString($credential->encrypted_secret));
    }

    public function test_malformed_stored_payload_fails_loudly_at_the_adapter_boundary(): void
    {
        $credential = PaymentProviderCredential::create([
            'provider' => 'stripe',
            'mode' => 'SANDBOX',
            'encrypted_secret' => Crypt::encryptString('not-json-at-all'),
            'is_enabled' => true,
        ]);

        try {
            ProviderCredentialPayload::decode('stripe', $credential);
            $this->fail('A malformed stored payload must fail loudly.');
        } catch (PaymentAdapterError $e) {
            $this->assertSame('provider_credential_invalid', $e->reason);
        }
    }

    public function test_bank_account_creation_reaches_the_intended_controller(): void
    {
        $this->actingPlatformAdmin();

        $response = $this->post('/admin/payment/provider-config/bank-accounts', [
            'bank_name' => 'Bank Test',
            'account_number' => '1234567890',
            'account_holder_name' => 'Test Holder',
            'currency' => 'IDR',
        ]);

        $response->assertRedirect();

        $account = ManualTransferBankAccount::query()->firstOrFail();
        $this->assertSame('Bank Test', $account->bank_name);
        $this->assertSame('1234567890', $account->account_number);
        $this->assertTrue($account->is_active);
    }
}
