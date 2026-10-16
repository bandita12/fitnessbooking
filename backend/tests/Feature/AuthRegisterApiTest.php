<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegisterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_customer_and_logs_in(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Neue Kundin',
            'email' => 'neu@example.com',
            'password' => 'geheimnis1',
            'password_confirmation' => 'geheimnis1',
        ]);

        $response->assertCreated()->assertJson([
            'name' => 'Neue Kundin',
            'email' => 'neu@example.com',
            'role' => 'customer',
            'studio_id' => null,
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'neu@example.com',
            'role' => UserRole::Customer->value,
            'studio_id' => null,
        ]);

        $this->getJson('/api/me')->assertOk()->assertJson([
            'email' => 'neu@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'belegt@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Zweite',
            'email' => 'belegt@example.com',
            'password' => 'geheimnis1',
            'password_confirmation' => 'geheimnis1',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_register_rejects_password_mismatch(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Neue Kundin',
            'email' => 'neu@example.com',
            'password' => 'geheimnis1',
            'password_confirmation' => 'anders123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertGuest();
    }

    public function test_register_ignores_role_escalation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Angreifer',
            'email' => 'angreifer@example.com',
            'password' => 'geheimnis1',
            'password_confirmation' => 'geheimnis1',
            'role' => 'admin',
            'studio_id' => 999,
        ]);

        $response->assertCreated()->assertJson([
            'role' => 'customer',
            'studio_id' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'angreifer@example.com',
            'role' => UserRole::Customer->value,
            'studio_id' => null,
        ]);
    }
}
