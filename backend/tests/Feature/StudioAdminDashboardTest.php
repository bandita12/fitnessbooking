<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudioAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Studio $studio;

    private User $studioAdmin;

    /** @var Collection<int, User> */
    private $trainers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studio = Studio::factory()->create();
        $this->studioAdmin = User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $this->studio->id,
        ]);
        $this->trainers = User::factory()->count(2)->create([
            'role' => UserRole::Trainer->value,
        ]);
        $this->studio->trainers()->attach($this->trainers->pluck('id')->all());

        Sanctum::actingAs($this->studioAdmin);
    }

    public function test_dashboard_baseline_lists_rooms_and_linked_trainers(): void
    {
        Room::factory()->for($this->studio)->create(['name' => 'Saal 1']);

        $this->getJson("/api/studios/{$this->studio->id}/rooms")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Saal 1');

        $this->getJson("/api/studios/{$this->studio->id}/trainers")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_studio_admin_can_create_room_with_all_metrics(): void
    {
        $response = $this->postJson("/api/studios/{$this->studio->id}/rooms", [
            'name' => 'Saal Neu',
            'capacity' => 30,
            'description' => 'Beschreibung',
            'thumbnail_url' => 'https://example.com/t.png',
            'image_url' => 'https://example.com/i.png',
            'color_code' => '#00ff00',
            'location_type' => 'outdoor',
            'lighting' => 'daylight',
            'size_sqm' => 200,
            'width_meters' => '20.00',
            'length_meters' => '10.00',
            'height_meters' => '4.50',
        ]);

        $response->assertCreated()->assertJsonPath('location_type', 'outdoor');
        $this->assertDatabaseHas('rooms', [
            'studio_id' => $this->studio->id,
            'name' => 'Saal Neu',
            'color_code' => '#00ff00',
        ]);
    }

    public function test_studio_admin_can_link_another_trainer_via_pivot(): void
    {
        $outsider = User::factory()->create(['role' => UserRole::Trainer->value]);

        $this->postJson("/api/studios/{$this->studio->id}/trainers/attach", [
            'email' => $outsider->email,
        ])->assertOk();

        $this->assertSame(3, $this->studio->trainers()->count());
    }

    public function test_linked_trainer_sees_own_studio_rooms_but_no_foreign_studio(): void
    {
        $foreign = Studio::factory()->create();
        Room::factory()->for($this->studio)->create();
        Sanctum::actingAs($this->trainers->first());

        $this->getJson("/api/studios/{$this->studio->id}/rooms")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/studios/{$foreign->id}/rooms")->assertNotFound();
    }
}
