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

class CourseOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    private Studio $studio;

    private function seedStudio(Studio $studio): array
    {
        $room = Room::factory()->create(['studio_id' => $studio->id]);

        $pivotTrainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $studio->trainers()->attach($pivotTrainer->id);

        $directTrainer = User::factory()->create([
            'role' => UserRole::Trainer->value,
            'studio_id' => $studio->id,
        ]);

        $course = Course::create([
            'studio_id' => $studio->id,
            'title' => 'Yoga',
            'day_of_week' => 3,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'trainer_id' => $pivotTrainer->id,
            'room_id' => $room->id,
        ]);

        return [$room, $pivotTrainer, $directTrainer, $course];
    }

    private function assertOverviewShape(Studio $studio, array $ids): void
    {
        [$room, $pivotTrainer, $directTrainer, $course] = $ids;

        $response = $this->getJson("/api/studios/{$studio->id}/courses/overview");

        $response->assertOk()
            ->assertJsonCount(1, 'courses')
            ->assertJsonCount(1, 'rooms')
            ->assertJsonCount(2, 'trainers')
            ->assertJsonPath('courses.0.id', $course->id)
            ->assertJsonPath('courses.0.title', 'Yoga')
            ->assertJsonPath('rooms.0.id', $room->id)
            ->assertJsonFragment(['id' => $pivotTrainer->id, 'name' => $pivotTrainer->name])
            ->assertJsonFragment(['id' => $directTrainer->id, 'name' => $directTrainer->name]);

        // Trainer shape matches the single trainers endpoint (id/name/email;
        // pivot-linked entries additionally carry the `pivot` key).
        foreach ($response->json('trainers') as $trainer) {
            $this->assertArrayHasKey('id', $trainer);
            $this->assertArrayHasKey('name', $trainer);
            $this->assertArrayHasKey('email', $trainer);
        }
    }

    public function test_admin_gets_courses_trainers_and_rooms_in_overview_shapes(): void
    {
        $this->studio = Studio::factory()->create();
        $ids = $this->seedStudio($this->studio);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin->value]));

        $this->assertOverviewShape($this->studio, $ids);
    }

    public function test_studio_admin_gets_overview_for_own_studio(): void
    {
        $this->studio = Studio::factory()->create();
        $ids = $this->seedStudio($this->studio);

        Sanctum::actingAs(User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $this->studio->id,
        ]));

        $this->assertOverviewShape($this->studio, $ids);
    }

    public function test_foreign_studio_overview_resolves_to_404(): void
    {
        $this->studio = Studio::factory()->create();
        Sanctum::actingAs(User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $this->studio->id,
        ]));

        $foreign = Studio::factory()->create();

        $this->getJson("/api/studios/{$foreign->id}/courses/overview")->assertNotFound();
    }
}
