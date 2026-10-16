<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Room;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(Studio $studio, ?User $lead = null): CourseSession
    {
        $course = Course::create([
            'studio_id' => $studio->id,
            'trainer_id' => $lead?->id,
            'title' => 'Kurs',
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        return CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
    }

    public function test_lead_and_substitute_trainer_can_update_status_and_info(): void
    {
        $studio = Studio::factory()->create();
        $lead = User::factory()->create(['role' => UserRole::Trainer->value]);
        $substitute = User::factory()->create(['role' => UserRole::Trainer->value]);
        $session = $this->makeSession($studio, $lead);
        $session->update(['trainer_id' => $substitute->id]);

        Sanctum::actingAs($lead);
        $this->putJson("/api/course_sessions/{$session->id}", [
            'status' => 'cancelled',
            'session_info' => 'Entfällt leider',
        ])->assertOk()->assertJsonPath('status', 'cancelled');

        Sanctum::actingAs($substitute);
        $this->putJson("/api/course_sessions/{$session->id}", [
            'status' => 'scheduled',
            'session_info' => 'Findet doch statt',
        ])->assertOk()->assertJsonPath('session_info', 'Findet doch statt');
    }

    public function test_trainer_cannot_change_room_or_time_fields(): void
    {
        $studio = Studio::factory()->create();
        $lead = User::factory()->create(['role' => UserRole::Trainer->value]);
        $session = $this->makeSession($studio, $lead);
        $room = Room::create(['studio_id' => $studio->id, 'name' => 'Neu', 'capacity' => 5]);
        Sanctum::actingAs($lead);

        $this->putJson("/api/course_sessions/{$session->id}", [
            'status' => 'scheduled',
            'room_id' => $room->id,
            'capacity' => 99,
        ])->assertOk();

        $session->refresh();
        $this->assertNull($session->room_id);
        $this->assertNull($session->capacity);
        $this->assertSame('scheduled', $session->status);
    }

    public function test_unassigned_trainer_gets_404_and_customer_403(): void
    {
        $studio = Studio::factory()->create();
        $session = $this->makeSession($studio);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Trainer->value]));

        $this->putJson("/api/course_sessions/{$session->id}", [
            'status' => 'cancelled',
        ])->assertNotFound();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));
        $this->putJson("/api/course_sessions/{$session->id}", [
            'status' => 'cancelled',
        ])->assertForbidden();
    }

    public function test_studio_admin_can_update_own_studio_session(): void
    {
        $studio = Studio::factory()->create();
        $session = $this->makeSession($studio);
        Sanctum::actingAs(User::factory()->create([
            'role' => UserRole::StudioAdmin->value,
            'studio_id' => $studio->id,
        ]));

        $this->putJson("/api/course_sessions/{$session->id}", [
            'status' => 'cancelled',
        ])->assertOk()->assertJsonPath('status', 'cancelled');
    }
}
