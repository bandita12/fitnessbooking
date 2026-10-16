<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudioManagementApiTest extends TestCase
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

    private function studioAdmin(Studio $studio): User
    {
        return User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $studio->id,
        ]);
    }

    public function test_studio_admin_can_crud_rooms_of_own_studio(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs($this->studioAdmin($studio));

        $this->getJson("/api/studios/{$studio->id}/rooms")->assertOk()->assertJsonCount(0);

        $create = $this->postJson("/api/studios/{$studio->id}/rooms", [
            'name' => 'Saal 1',
            'capacity' => 20,
            'description' => 'Großer Saal',
            'thumbnail_url' => 'https://example.com/t.png',
            'image_url' => 'https://example.com/i.png',
            'color_code' => '#ff0000',
            'location_type' => 'indoor',
            'lighting' => 'led',
            'size_sqm' => 120,
            'width_meters' => '12.50',
            'length_meters' => '9.60',
            'height_meters' => '3.20',
        ])->assertCreated()->assertJsonPath('color_code', '#ff0000');
        $roomId = $create->json('id');

        $this->putJson("/api/studios/{$studio->id}/rooms/{$roomId}", [
            'name' => 'Saal 1a',
            'capacity' => 25,
        ])->assertOk()->assertJsonPath('name', 'Saal 1a');

        $this->deleteJson("/api/studios/{$studio->id}/rooms/{$roomId}")->assertNoContent();
        $this->assertSame(0, Room::count());
    }

    public function test_room_validation_rejects_bad_input(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs($this->studioAdmin($studio));

        $this->postJson("/api/studios/{$studio->id}/rooms", [
            'name' => 'X',
            'capacity' => 0,
            'color_code' => 'red',
            'location_type' => 'moon',
        ])->assertUnprocessable()->assertJsonValidationErrors(['capacity', 'color_code', 'location_type']);
    }

    public function test_foreign_studio_rooms_resolve_to_404(): void
    {
        $own = $this->makeStudio('Own');
        $foreign = $this->makeStudio('Foreign');
        Sanctum::actingAs($this->studioAdmin($own));

        $this->getJson("/api/studios/{$foreign->id}/rooms")->assertNotFound();
        $this->postJson("/api/studios/{$foreign->id}/rooms", [
            'name' => 'X',
            'capacity' => 5,
        ])->assertNotFound();
    }

    public function test_studio_admin_can_register_and_link_trainers(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs($this->studioAdmin($studio));

        $this->getJson("/api/studios/{$studio->id}/trainers")->assertOk()->assertJsonCount(0);

        $registered = $this->postJson("/api/studios/{$studio->id}/trainers", [
            'name' => 'Trainer Neu',
            'email' => 'neu@example.com',
            'password' => 'geheim123',
        ])->assertCreated()->assertJsonPath('role', 'trainer');
        $this->assertTrue($studio->trainers()->whereKey($registered->json('id'))->exists());

        $existing = User::factory()->create(['role' => UserRole::Trainer->value]);
        $this->postJson("/api/studios/{$studio->id}/trainers/attach", [
            'email' => $existing->email,
        ])->assertOk();
        $this->assertSame(2, $studio->trainers()->count());

        $this->deleteJson("/api/studios/{$studio->id}/trainers/{$existing->id}")->assertNoContent();
        $this->assertSame(1, $studio->trainers()->count());
    }

    public function test_attach_rejects_non_trainers_and_duplicates_are_idempotent(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs($this->studioAdmin($studio));
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);

        $this->postJson("/api/studios/{$studio->id}/trainers/attach", [
            'email' => $customer->email,
        ])->assertUnprocessable();

        $trainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $this->postJson("/api/studios/{$studio->id}/trainers/attach", [
            'email' => $trainer->email,
        ])->assertOk();
        $this->postJson("/api/studios/{$studio->id}/trainers/attach", [
            'email' => $trainer->email,
        ])->assertOk();
        $this->assertSame(1, $studio->trainers()->count());
    }

    public function test_trainer_cannot_manage_rooms(): void
    {
        $studio = $this->makeStudio();
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Trainer->value]));

        $this->postJson("/api/studios/{$studio->id}/rooms", [
            'name' => 'X',
            'capacity' => 5,
        ])->assertForbidden();
    }
}
