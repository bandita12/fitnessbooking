<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainerUpdateApiTest extends TestCase
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

    public function test_studio_admin_can_update_trainer_of_own_studio(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs(User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $studio->id,
        ]));

        $trainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $studio->trainers()->attach($trainer->id);

        $this->putJson("/api/studios/{$studio->id}/trainers/{$trainer->id}", [
            'name' => 'Neuer Name',
            'email' => 'neu@example.com',
        ])->assertOk()->assertJson([
            'name' => 'Neuer Name',
            'email' => 'neu@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $trainer->id,
            'name' => 'Neuer Name',
            'role' => UserRole::Trainer->value,
        ]);

        $this->putJson("/api/studios/{$studio->id}/trainers/{$trainer->id}", [
            'password' => 'neues-geheimnis',
        ])->assertOk();
        $this->assertTrue(Hash::check('neues-geheimnis', $trainer->refresh()->password));
    }

    public function test_foreign_studio_trainer_resolves_to_404(): void
    {
        $own = $this->makeStudio('Own');
        $foreign = $this->makeStudio('Foreign');
        Sanctum::actingAs(User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $own->id,
        ]));

        $trainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $foreign->trainers()->attach($trainer->id);

        // Fremdes Studio selbst → 404 (Tenant-Scope).
        $this->putJson("/api/studios/{$foreign->id}/trainers/{$trainer->id}", [
            'name' => 'X',
        ])->assertNotFound();

        // Trainer nicht mit eigenem Studio verknüpft → 404.
        $unlinked = User::factory()->create(['role' => UserRole::Trainer->value]);
        $this->putJson("/api/studios/{$own->id}/trainers/{$unlinked->id}", [
            'name' => 'X',
        ])->assertNotFound();
    }

    public function test_duplicate_email_rejected_with_422(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs(User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $studio->id,
        ]));

        $trainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $studio->trainers()->attach($trainer->id);
        User::factory()->create(['email' => 'vergeben@example.com']);

        $this->putJson("/api/studios/{$studio->id}/trainers/{$trainer->id}", [
            'email' => 'vergeben@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
