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

class CourseRoomApiTest extends TestCase
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

    private function makeCourse(?int $roomId = null): Course
    {
        return Course::create([
            'studio_id' => $this->studio->id,
            'room_id' => $roomId,
            'title' => 'Kurs',
            'day_of_week' => now()->dayOfWeekIso,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
    }

    public function test_generated_sessions_inherit_course_room(): void
    {
        $room = Room::factory()->create(['studio_id' => $this->studio->id]);
        $course = $this->makeCourse($room->id);

        $this->postJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/generate",
            ['weeks' => 1]
        )->assertCreated();

        $this->assertSame(1, $course->sessions()->count());
        foreach ($course->sessions()->get() as $session) {
            $this->assertSame($room->id, $session->room_id);
        }
    }

    public function test_manually_overridden_session_room_is_kept_on_regenerate(): void
    {
        $defaultRoom = Room::factory()->create(['studio_id' => $this->studio->id]);
        $otherRoom = Room::factory()->create(['studio_id' => $this->studio->id]);
        $course = $this->makeCourse($defaultRoom->id);

        $this->postJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/generate",
            ['weeks' => 1]
        )->assertCreated();

        $session = $course->sessions()->firstOrFail();
        $this->putJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/{$session->id}",
            ['room_id' => $otherRoom->id]
        )->assertOk();

        $this->postJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/generate",
            ['weeks' => 1]
        )->assertCreated()->assertJsonPath('created', 0);

        $this->assertSame($otherRoom->id, $session->refresh()->room_id);
    }

    public function test_course_update_with_room_change(): void
    {
        $oldRoom = Room::factory()->create(['studio_id' => $this->studio->id]);
        $newRoom = Room::factory()->create(['studio_id' => $this->studio->id]);
        $trainer = User::factory()->create(['role' => UserRole::Trainer->value]);
        $course = $this->makeCourse($oldRoom->id);

        $this->putJson("/api/studios/{$this->studio->id}/courses/{$course->id}", [
            'title' => 'Yoga am Morgen',
            'description' => 'Sanfter Start',
            'day_of_week' => 2,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'trainer_id' => $trainer->id,
            'room_id' => $newRoom->id,
        ])->assertOk()->assertJsonPath('title', 'Yoga am Morgen');

        $refreshed = $course->refresh();
        $this->assertSame('Yoga am Morgen', $refreshed->title);
        $this->assertSame($newRoom->id, $refreshed->room_id);
        $this->assertSame($trainer->id, $refreshed->trainer_id);
    }

    public function test_course_update_in_foreign_studio_returns_404(): void
    {
        $foreign = Studio::factory()->create();
        $course = Course::create([
            'studio_id' => $foreign->id,
            'title' => 'Fremd',
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $this->putJson("/api/studios/{$foreign->id}/courses/{$course->id}", [
            'title' => 'Gehackt',
        ])->assertNotFound();
    }

    public function test_course_update_with_foreign_room_returns_422(): void
    {
        $foreignStudio = Studio::factory()->create();
        $foreignRoom = Room::factory()->create(['studio_id' => $foreignStudio->id]);
        $course = $this->makeCourse();

        $this->putJson("/api/studios/{$this->studio->id}/courses/{$course->id}", [
            'room_id' => $foreignRoom->id,
        ])->assertUnprocessable();

        $this->assertNull($course->refresh()->room_id);
    }
}
