<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudio(): Studio
    {
        return Studio::create([
            'name' => 'Studio A',
            'street' => 'Str. 1',
            'zip' => '12345',
            'city' => 'City',
        ]);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $studio = $this->makeStudio();
        $user = User::factory()->create([
            'role' => UserRole::Trainer->value,
            'studio_id' => $studio->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson([
                'id' => $user->id,
                'email' => $user->email,
                'role' => 'trainer',
                'studio_id' => $studio->id,
            ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_me_returns_401_json_for_guests(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_me_returns_user_with_role_and_studio_id(): void
    {
        $studio = $this->makeStudio();
        $user = User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $studio->id,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/me')->assertOk()->assertJson([
            'id' => $user->id,
            'email' => $user->email,
            'role' => 'studio_admin',
            'studio_id' => $studio->id,
        ]);
    }

    public function test_logout_destroys_the_session(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
        $this->assertAuthenticated();

        $this->postJson('/api/auth/logout')->assertNoContent();

        // No assertGuest() / fresh-request check here: guard instances persist
        // across HTTP calls within one test, so only the session store itself
        // can prove the logout — it must hold no login data anymore.
        $keys = array_keys($this->app['session']->all());
        $this->assertEmpty(
            array_filter($keys, fn (string $key): bool => str_starts_with($key, 'login_')),
            'Session still contains login data after logout.'
        );
    }
}
