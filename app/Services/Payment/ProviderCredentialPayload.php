<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Models\Payment\PaymentProviderCredential;
use App\Services\Payment\Exceptions\PaymentValidationException;
use Illuminate\Support\Facades\Crypt;

/**
 * IMP-009 F-06 — the ONE canonical provider credential payload
 * contract shared by the admin write path and every provider adapter.
 *
 * Provider-required logical fields (verified against official provider
 * documentation 2026-09-20):
 * - tripay: merchant_code (create-transaction signature input per the
 *   official "Buat Signature" contract) + api_key (Bearer credential
 *   for every API call) + private_key (outbound HMAC key and inbound
 *   X-Callback-Signature key).
 * - xendit: api_key (secret key, HTTP basic auth) + callback_token
 *   (x-callback-token verification secret, distinct from the API key).
 * - stripe: secret_key (API basic auth) + webhook_secret (Stripe-
 *   Signature HMAC key, distinct from the API secret); publishable_key
 *   optional (Stripe.js public key — never secret).
 *
 * Raw unstructured secret text is never accepted: encode() validates
 * the per-provider shape on write; decode() re-validates on every
 * adapter load, so a legacy/malformed payload fails loudly at the
 * adapter boundary instead of producing empty-string credentials.
 * Secrets stay encrypted at rest, are never logged, never audited,
 * never serialized to any response.
 */
final class ProviderCredentialPayload
{
    /**
     * @return array<string>
     */
    public static function requiredFields(string $provider): array
    {
        return match ($provider) {
            'tripay' => ['merchant_code', 'api_key', 'private_key'],
            'xendit' => ['api_key', 'callback_token'],
            'stripe' => ['secret_key', 'webhook_secret'],
            default => throw new PaymentValidationException(
                'invalid_provider',
                "Provider '{$provider}' is not an approved payment provider."
            ),
        };
    }

    /**
     * @return array<string>
     */
    public static function optionalFields(string $provider): array
    {
        return match ($provider) {
            'stripe' => ['publishable_key'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function encode(string $provider, array $fields): string
    {
        $payload = [];

        foreach (self::requiredFields($provider) as $field) {
            $value = $fields[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new PaymentValidationException(
                    'provider_credential_invalid',
                    "Credential field '{$field}' is required for provider '{$provider}'."
                );
            }

            $payload[$field] = $value;
        }

        foreach (self::optionalFields($provider) as $field) {
            $value = $fields[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_string($value)) {
                throw new PaymentValidationException(
                    'provider_credential_invalid',
                    "Credential field '{$field}' must be a string for provider '{$provider}'."
                );
            }

            $payload[$field] = $value;
        }

        return json_encode($payload);
    }

    /**
     * @return array<string, string>
     */
    public static function decode(string $provider, ?PaymentProviderCredential $credential): array
    {
        if ($credential === null) {
            throw new PaymentAdapterError('provider_credential_missing', "No credential row is configured for provider '{$provider}'.");
        }

        try {
            $plaintext = Crypt::decryptString($credential->encrypted_secret);
        } catch (\Throwable) {
            throw new PaymentAdapterError('provider_credential_invalid', "The stored credential for provider '{$provider}' cannot be decrypted.");
        }

        $decoded = json_decode($plaintext, true);

        if (! is_array($decoded)) {
            throw new PaymentAdapterError('provider_credential_invalid', "The stored credential for provider '{$provider}' is not a structured payload.");
        }

        foreach (self::requiredFields($provider) as $field) {
            $value = $decoded[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new PaymentAdapterError('provider_credential_invalid', "The stored credential for provider '{$provider}' is missing field '{$field}'.");
            }
        }

        return $decoded;
    }
}
