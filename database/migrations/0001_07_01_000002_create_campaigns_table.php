<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-007 — Campaign: the specific fundraising initiative
     * (docs/implementation/IMP-007-campaign-program-fund.md sections 8/8a/8b).
     * Lifecycle DRAFT|REVIEW|APPROVED|PUBLISHED|CLOSED (HD-IMP007-01).
     * target_amount_minor is a bigint (never decimal/float — HD-IMP007-02).
     * program_id/fund_id are both RESTRICT-on-delete (BR-3/AC-007-009/010) —
     * a Program/Fund still referenced by a Campaign cannot be deleted at the
     * database level, not merely by application pre-check.
     */
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('program_id')->nullable()->constrained('programs')->restrictOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->restrictOnDelete();

            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description_html')->nullable();
            $table->string('purpose', 500)->nullable();

            $table->unsignedBigInteger('target_amount_minor')->nullable();
            $table->char('currency', 3)->nullable();

            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();

            $table->string('status', 16)->default('DRAFT');
            $table->unsignedInteger('edit_version')->default(0);

            $table->dateTime('submitted_at', 6)->nullable();
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();

            $table->foreignId('created_by_principal_id')->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')->constrained('principals')->restrictOnDelete();
            $table->foreignId('submitted_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('approved_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('published_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('closed_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status');
            $table->index('program_id');
            $table->index('fund_id');
            $table->index(['status', 'starts_at', 'ends_at'], 'campaigns_eligibility_window_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
