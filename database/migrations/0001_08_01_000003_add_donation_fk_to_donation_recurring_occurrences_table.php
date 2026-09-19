<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-008 — closes the donations <-> donation_recurring_occurrences FK
     * cycle (docs/implementation/IMP-008-donation.md "Database Impact"):
     * adds the RESTRICT FK and the "one Occurrence generates at most one
     * Donation" unique constraint on donation_recurring_occurrences.
     * donation_id, after donations exists.
     */
    public function up(): void
    {
        Schema::table('donation_recurring_occurrences', function (Blueprint $table) {
            $table->foreign('donation_id')->references('id')->on('donations')->restrictOnDelete();
            $table->unique('donation_id', 'donation_recurring_occurrences_donation_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('donation_recurring_occurrences', function (Blueprint $table) {
            $table->dropForeign(['donation_id']);
            $table->dropUnique('donation_recurring_occurrences_donation_id_unique');
        });
    }
};
