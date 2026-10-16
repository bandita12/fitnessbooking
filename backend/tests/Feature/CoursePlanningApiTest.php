<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoursePlanningApiTest extends TestCase
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

    private function makeCourse(int $dayOfWeek): Course
    {
        return Course::create([
            'studio_id' => $this->studio->id,
            'title' => 'Kurs',
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
    }

    public function test_studio_admin_can_create_course_template(): void
    {
        $trainer = User::factory()->create(['role' => UserRole::Trainer->value]);

        $response = $this->postJson("/api/studios/{$this->studio->id}/courses", [
            'title' => 'Yoga',
            'description' => 'Entspannt',
            'day_of_week' => 3,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'trainer_id' => $trainer->id,
            'capacity' => 15,
        ]);

        $response->assertCreated()->assertJsonPath('day_of_week', 3);
        $this->assertDatabaseHas('courses', [
            'studio_id' => $this->studio->id,
            'title' => 'Yoga',
        ]);
    }

    public function test_generate_creates_four_weekly_sessions_and_is_idempotent(): void
    {
        $course = $this->makeCourse(now()->dayOfWeekIso);

        $first = $this->postJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/generate",
            ['weeks' => 4]
        )->assertCreated();

        $this->assertSame(4, $first->json('created'));
        $this->assertSame(4, $course->sessions()->count());

        $second = $this->postJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/generate",
            ['weeks' => 4]
        )->assertCreated();

        $this->assertSame(0, $second->json('created'));
        $this->assertSame(4, $course->sessions()->count());

        $dates = $course->sessions()->orderBy('date')->pluck('date')
            ->map(fn ($d): string => (string) $d)->all();
        foreach ($dates as $index => $date) {
            if ($index > 0) {
                $this->assertSame(7, (int) now()->parse($dates[$index - 1])->diffInDays(now()->parse($date)));
            }
        }
    }

    public function test_session_can_be_substituted_or_cancelled_with_info(): void
    {
        $course = $this->makeCourse(now()->dayOfWeekIso);
        $this->postJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/generate",
            ['weeks' => 1]
        )->assertCreated();

        $session = $course->sessions()->firstOrFail();
        $substitute = User::factory()->create(['role' => UserRole::Trainer->value]);

        $this->putJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/{$session->id}",
            [
                'trainer_id' => $substitute->id,
                'status' => 'substituted',
                'session_info' => 'Vertretung durch Max',
            ]
        )->assertOk()->assertJsonPath('status', 'substituted');

        $this->putJson(
            "/api/studios/{$this->studio->id}/courses/{$course->id}/sessions/{$session->id}",
            ['status' => 'cancelled']
        )->assertOk()->assertJsonPath('status', 'cancelled');

        $this->assertSame(
            'Vertretung durch Max',
            $session->refresh()->session_info
        );
    }

    public function test_foreign_studio_course_planning_resolves_to_404(): void
    {
        $foreign = Studio::factory()->create();
        $course = Course::create([
            'studio_id' => $foreign->id,
            'title' => 'Fremd',
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $this->postJson(
            "/api/studios/{$foreign->id}/courses/{$course->id}/sessions/generate"
        )->assertNotFound();

        $session = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->addDay()->format('Y-m-d'),
        ]);
        $this->putJson(
            "/api/studios/{$foreign->id}/courses/{$course->id}/sessions/{$session->id}",
            ['status' => 'cancelled']
        )->assertNotFound();
    }
}
