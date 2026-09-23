<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-009 — Provider Event forensic record
     * (docs/implementation/IMP-009-payment-hub.md "Domain Model" /
     * "Database Impact"). Security/audit evidence, not business state:
     * one Payment may receive many events (retries, duplicates,
     * out-of-order deliveries). payment_id is NULLABLE because an
     * inbound event referencing an unknown provider_reference must
     * still be recorded for forensics before rejection. raw_payload is
     * application-layer encrypted at rest; business logic never reads
     * it as an authoritative source.
     */
    public function up(): void
    {
        Schema::create('payment_provider_events', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_event_id', 191)->nullable();
            $table->string('event_type', 64);
            $table->boolean('signature_valid');
            $table->string('processing_result', 32);
            $table->longText('raw_payload_ciphertext')->nullable();
            $table->dateTime('received_at', 6);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('payment_id');
            $table->index('provider');
            $table->index('processing_result');
            $table->index('received_at');
            $table->unique(['provider', 'provider_event_id'], 'ux_provider_events_provider_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_events');
    }
};
