<?php

namespace Tests\Feature\Identity;

use App\Services\Identity\CanonicalEmailUniqueViolationDetector;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IMP002-IMPL-M01 / section 41 — the classifier must convert ONLY the exact
 * canonical users.email unique-constraint violation into a conflict; every
 * other QueryException (a different unique index, a foreign-key failure, a
 * plain syntax error) must be left alone so EmailChangeService can let it
 * propagate rather than silently mask a real database failure.
 */
class CanonicalEmailUniqueViolationDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_real_users_email_unique_violation(): void
    {
        DB::table('users')->insert([
            'public_id' => 'first',
            'email' => 'race@example.com',
            'password' => 'irrelevant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('users')->insert([
                'public_id' => 'second',
                'email' => 'race@example.com',
                'password' => 'irrelevant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected a QueryException from the duplicate email insert.');
        } catch (QueryException $exception) {
            $detector = app(CanonicalEmailUniqueViolationDetector::class);
            $this->assertTrue($detector->isCanonicalEmailUniqueViolation($exception));
        }
    }

    public function test_does_not_classify_an_unrelated_unique_violation_as_email_conflict(): void
    {
        DB::table('users')->insert([
            'public_id' => 'duplicate-public-id',
            'email' => 'first@example.com',
            'password' => 'irrelevant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // public_id is ALSO unique, but this is not the canonical email
            // constraint — must never be classified as an email conflict.
            DB::table('users')->insert([
                'public_id' => 'duplicate-public-id',
                'email' => 'second@example.com',
                'password' => 'irrelevant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected a QueryException from the duplicate public_id insert.');
        } catch (QueryException $exception) {
            $detector = app(CanonicalEmailUniqueViolationDetector::class);
            $this->assertFalse($detector->isCanonicalEmailUniqueViolation($exception));
        }
    }

    public function test_does_not_classify_a_foreign_key_failure_as_email_conflict(): void
    {
        try {
            DB::table('email_change_requests')->insert([
                'user_id' => 999999,
                'normalized_pending_email' => 'nobody@example.com',
                'verification_token_hash' => 'irrelevant',
                'generation' => 1,
                'requested_at' => now(),
                'expires_at' => now()->addHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected a QueryException from the foreign-key violation.');
        } catch (QueryException $exception) {
            $detector = app(CanonicalEmailUniqueViolationDetector::class);
            $this->assertFalse($detector->isCanonicalEmailUniqueViolation($exception));
        }
    }
}
