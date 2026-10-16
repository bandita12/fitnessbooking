<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Room;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudioTrainerListingTest extends TestCase
{
    use RefreshDatabase;

    private Studio $studio;

    private User $studioAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studio = Studio::factory()->create();
        $this->studioAdmin = User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $this->studio->id,
        ]);
        Sanctum::actingAs($this->studioAdmin);
    }

    public function test_lists_pivot_linked_and_directly_assigned_trainers_without_duplicates(): void
    {
        $pivotTrainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $this->studio->trainers()->attach($pivotTrainer->id);

        // Directly assigned via users.studio_id (e.g. set by a super-admin or
        // predating the n:m pivot) — must show up as well.
        $directTrainer = User::factory()->create([
            'role' => UserRole::Trainer->value,
            'studio_id' => $this->studio->id,
        ]);

        // Linked both ways — must appear exactly once.
        $bothTrainer = User::factory()->create([
            'role' => UserRole::Trainer->value,
            'studio_id' => $this->studio->id,
        ]);
        $this->studio->trainers()->attach($bothTrainer->id);

        $this->getJson("/api/studios/{$this->studio->id}/trainers")
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.id', $pivotTrainer->id)
            ->assertJsonFragment(['id' => $directTrainer->id, 'name' => $directTrainer->name])
            ->assertJsonFragment(['id' => $bothTrainer->id, 'name' => $bothTrainer->name]);
    }

    public function test_excludes_trainers_of_other_studios_and_customers(): void
    {
        $foreign = Studio::factory()->create();
        $foreignTrainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $foreign->trainers()->attach($foreignTrainer->id);
        User::factory()->create([
            'role' => UserRole::Trainer->value,
            'studio_id' => $foreign->id,
        ]);
        User::factory()->create([
            'role' => UserRole::Customer->value,
            'studio_id' => $this->studio->id,
        ]);

        $this->getJson("/api/studios/{$this->studio->id}/trainers")
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_studio_admin_dashboard_lists_rooms_trainers_and_courses(): void
    {
        $room = Room::factory()->create(['studio_id' => $this->studio->id]);
        $trainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $this->studio->trainers()->attach($trainer->id);
        Course::create([
            'studio_id' => $this->studio->id,
            'title' => 'Yoga',
            'day_of_week' => 3,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'trainer_id' => $trainer->id,
            'room_id' => $room->id,
        ]);

        $this->getJson("/api/studios/{$this->studio->id}/rooms")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/studios/{$this->studio->id}/trainers")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/studios/{$this->studio->id}/courses")->assertOk()->assertJsonCount(1);
    }

    public function test_foreign_studio_trainers_resolve_to_404(): void
    {
        $foreign = Studio::factory()->create();

        $this->getJson("/api/studios/{$foreign->id}/trainers")->assertNotFound();
    }
}
