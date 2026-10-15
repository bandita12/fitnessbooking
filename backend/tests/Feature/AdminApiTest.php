<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudio(string $name = 'Studio A'): Studio
    {
        return Studio::create([
            'name' => $name,
            'street' => 'Str. 1',
            'zip' => '12345',
            'city' => 'City',
        ]);
    }

    public function test_admin_can_list_all_studios(): void
    {
        $this->makeStudio('A');
        $this->makeStudio('B');
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin->value]));

        $this->getJson('/api/admin/studios')->assertOk()->assertJsonCount(2);
    }

    public function test_studio_admin_cannot_list_admin_studios(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs(User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $studio->id,
        ]));

        $this->getJson('/api/admin/studios')->assertForbidden();
    }

    public function test_admin_can_create_studio_with_marketing_fields(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin->value]));

        $response = $this->postJson('/api/admin/studios', [
            'name' => 'Neu',
            'street' => 'Weg 2',
            'zip' => '54321',
            'city' => 'Stadt',
            'description' => 'Beschreibung',
            'web_link' => 'https://example.com',
            'thumbnail_url' => 'https://example.com/t.png',
            'image_url' => 'https://example.com/i.png',
        ]);

        $response->assertCreated()->assertJsonPath('web_link', 'https://example.com');
        $this->assertDatabaseHas('studios', ['name' => 'Neu', 'city' => 'Stadt']);
    }

    public function test_admin_can_create_studio_admin_bound_to_studio(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin->value]));

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Studio Admin',
            'email' => 'studio-admin@example.com',
            'password' => 'geheim123',
            'studio_id' => $studio->id,
        ]);

        $response->assertCreated()->assertJson([
            'email' => 'studio-admin@example.com',
            'role' => 'studio_admin',
            'studio_id' => $studio->id,
        ]);

        $user = User::where('email', 'studio-admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('geheim123', $user->password));
    }

    public function test_role_input_is_ignored_and_duplicate_email_rejected(): void
    {
        $studio = $this->makeStudio();
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin->value]));

        $this->postJson('/api/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'geheim123',
            'studio_id' => $studio->id,
            'role' => 'admin',
        ])->assertCreated()->assertJsonPath('role', 'studio_admin');

        $this->postJson('/api/admin/users', [
            'name' => 'Copy',
            'email' => 'taken@example.com',
            'password' => 'geheim123',
            'studio_id' => $studio->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_customer_cannot_create_studio_admin(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));

        $this->postJson('/api/admin/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'geheim123',
            'studio_id' => $studio->id,
        ])->assertForbidden();
    }
}
