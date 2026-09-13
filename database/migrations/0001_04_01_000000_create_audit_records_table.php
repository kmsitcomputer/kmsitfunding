<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-004 — canonical audit record store (append-only). Schema follows
     * "Audit Record Schema" in docs/implementation/IMP-004-audit-governance-foundation.md
     * exactly: no updated_at (rows are never updated), subject_id deliberately
     * NOT a hard FK (a subject's own domain table lifecycle must never constrain
     * audit durability), actor_principal_id a hard FK to principals.id (RESTRICT —
     * principals are tombstoned, never hard-deleted, per IMP-003).
     */
    public function up(): void
    {
        Schema::create('audit_records', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 128);
            $table->unsignedTinyInteger('event_version');
            $table->string('criticality', 16);
            $table->dateTime('occurred_at', 6);
            $table->foreignId('actor_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->string('actor_principal_kind', 32);
            $table->string('execution_context', 128)->nullable();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('authentication_assurance', 16)->nullable();
            $table->string('policy_version_ref', 128)->nullable();
            $table->string('source_domain', 64)->nullable();
            $table->string('source_event_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('created_at', 6);

            $table->index(['event_type', 'occurred_at'], 'audit_records_event_type_occurred_at_index');
            $table->index('occurred_at', 'audit_records_occurred_at_index');
            $table->index(['actor_principal_id', 'occurred_at'], 'audit_records_actor_occurred_at_index');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'audit_records_subject_occurred_at_index');
            $table->index('request_id', 'audit_records_request_id_index');
            $table->index('correlation_id', 'audit_records_correlation_id_index');

            // Idempotency scope (IMP004-SPEC-M06): uniqueness is the COMPOSITE
            // (source_domain, source_event_id, event_type) — never a bare global
            // source_event_id rule. SQL NULL-distinct unique-index semantics mean
            // rows with NULL source_event_id never collide with each other (the
            // ordinary case for all 28 migrated events).
            $table->unique(['source_domain', 'source_event_id', 'event_type'], 'audit_records_source_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_records');
    }
};
