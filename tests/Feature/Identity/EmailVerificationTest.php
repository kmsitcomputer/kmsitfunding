<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signed_link_verifies_email(): void
    {
        // Fake only the event under test — faking everything would also
        // suppress the Eloquent `creating` listener that assigns public_id.
        Event::fake(Verified::class);

        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/dashboard');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_invalid_hash_fails(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_link_fails(): void
    {
        $user = User::create(['email' => 'user@example.com', 'password' => Hash::make('password12345')]);

        $url = URL::temporarySignedRoute('verification.verify', now()->subMinutes(1), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
    }
}
