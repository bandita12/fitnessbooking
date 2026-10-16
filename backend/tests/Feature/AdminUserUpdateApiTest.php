<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_update_user_fields(): void
    {
        $studio = Studio::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Trainer->value]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Neuer Name',
            'email' => 'neu@example.com',
            'role' => 'studio_admin',
            'studio_id' => $studio->id,
        ])->assertOk()->assertJson([
            'name' => 'Neuer Name',
            'role' => 'studio_admin',
            'studio_id' => $studio->id,
        ]);

        $this->assertSame(UserRole::StudioAdmin, $user->refresh()->role);
    }

    public function test_email_unique_ignores_current_user_but_rejects_taken(): void
    {
        $user = User::factory()->create(['email' => 'bleibt@example.com']);
        User::factory()->create(['email' => 'vergeben@example.com']);

        $this->putJson("/api/admin/users/{$user->id}", [
            'email' => 'bleibt@example.com',
        ])->assertOk();

        $this->putJson("/api/admin/users/{$user->id}", [
            'email' => 'vergeben@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_invalid_role_and_unknown_studio_rejected(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/admin/users/{$user->id}", [
            'role' => 'superuser',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->putJson("/api/admin/users/{$user->id}", [
            'studio_id' => 9999,
        ])->assertUnprocessable()->assertJsonValidationErrors('studio_id');
    }

    public function test_password_only_changes_when_filled(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Gleiches Passwort',
        ])->assertOk();
        $this->assertSame($oldHash, $user->refresh()->password);

        $this->putJson("/api/admin/users/{$user->id}", [
            'password' => 'neues-geheimnis',
        ])->assertOk();
        $this->assertTrue(Hash::check('neues-geheimnis', $user->refresh()->password));

        $this->putJson("/api/admin/users/{$user->id}", [
            'password' => 'kurz',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_admin_cannot_strip_own_admin_role(): void
    {
        $this->putJson("/api/admin/users/{$this->admin->id}", [
            'role' => 'customer',
        ])->assertUnprocessable();

        $this->assertSame(UserRole::Admin, $this->admin->refresh()->role);
    }

    public function test_non_admin_cannot_update_users(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'X',
        ])->assertForbidden();
    }

    public function test_admin_can_list_studio_admins(): void
    {
        User::factory()->create(['role' => UserRole::StudioAdmin->value]);
        User::factory()->create(['role' => UserRole::Customer->value]);

        $this->getJson('/api/admin/users?role=studio_admin')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.role', 'studio_admin');
    }
}
