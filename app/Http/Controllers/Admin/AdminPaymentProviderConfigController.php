<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment\ManualTransferBankAccount;
use App\Models\Payment\PaymentProviderCredential;
use App\Models\Rbac\Principal;
use App\Policies\PaymentPolicy;
use App\Services\Payment\PaymentAuditLogger;
use App\Services\Payment\ProviderCredentialPayload;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-009 provider configuration management (docs/implementation/
 * IMP-009-payment-hub.md "Routes / API Boundary" / "Configuration":
 * GET /admin/payment/provider-config, POST
 * /admin/payment/provider-config/{provider}). GLOBAL_PLATFORM scope.
 * The secret value is write-only — never echoed back, never logged,
 * never included in any audit payload (only provider/mode/is_enabled
 * are audited, via provider_config.credential_changed, CRITICAL).
 * Bank accounts are non-secret routing display data with full
 * read-back.
 */
class AdminPaymentProviderConfigController extends Controller
{
    public function index(Request $request, PaymentPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->manageProviderConfig($actor), 403);

        $credentials = PaymentProviderCredential::query()->orderBy('provider')->get()->map(fn (PaymentProviderCredential $credential) => [
            'provider' => $credential->provider,
            'mode' => $credential->mode,
            'is_enabled' => $credential->is_enabled,
            'has_secret' => $credential->encrypted_secret !== '',
        ]);

        $accounts = ManualTransferBankAccount::query()->orderBy('id')->get()->map(fn (ManualTransferBankAccount $account) => [
            'ulid' => $account->ulid,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_holder_name' => $account->account_holder_name,
            'currency' => $account->currency,
            'is_active' => $account->is_active,
        ]);

        return Inertia::render('Admin/Payment/ProviderConfig', [
            'credentials' => $credentials,
            'bank_accounts' => $accounts,
        ]);
    }

    public function updateCredential(Request $request, PaymentPolicy $policy, string $provider, PaymentAuditLogger $auditLogger): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->manageProviderConfig($actor), 403);

        abort_unless(in_array($provider, ['tripay', 'xendit', 'stripe'], true), 404);

        // F-06: the admin write path shares the ONE canonical
        // credential payload contract with the adapters — typed,
        // validated per provider. Raw unstructured secret text is
        // never accepted.
        $rules = [
            'mode' => ['required', 'string', 'in:SANDBOX,PRODUCTION'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];

        foreach (ProviderCredentialPayload::requiredFields($provider) as $field) {
            $rules[$field] = ['required', 'string', 'min:1', 'max:8192'];
        }

        foreach (ProviderCredentialPayload::optionalFields($provider) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:8192'];
        }

        $validated = $request->validate($rules);

        $credential = PaymentProviderCredential::query()->where('provider', $provider)->firstOrFail();
        $credential->forceFill([
            'mode' => $validated['mode'],
            'encrypted_secret' => Crypt::encryptString(ProviderCredentialPayload::encode($provider, $validated)),
            'is_enabled' => (bool) ($validated['is_enabled'] ?? $credential->is_enabled),
        ])->save();

        $auditLogger->recordCredentialChanged($credential->id, [
            'provider' => $provider,
            'mode' => $validated['mode'],
            'is_enabled' => $credential->is_enabled ? 1 : 0,
        ], $actor);

        return redirect()->route('payment.admin.config.provider-config')->with('status', 'provider-credential-updated');
    }

    public function storeBankAccount(Request $request, PaymentPolicy $policy): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->manageProviderConfig($actor), 403);

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:64'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        ManualTransferBankAccount::create(array_merge($validated, ['is_active' => true]));

        return redirect()->route('payment.admin.config.provider-config')->with('status', 'bank-account-created');
    }

    public function toggleBankAccount(Request $request, PaymentPolicy $policy, ManualTransferBankAccount $account): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->manageProviderConfig($actor), 403);

        $account->forceFill(['is_active' => ! $account->is_active])->save();

        return redirect()->route('payment.admin.config.provider-config')->with('status', 'bank-account-updated');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
