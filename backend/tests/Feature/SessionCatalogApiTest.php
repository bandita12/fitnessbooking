<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Room;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(Studio $studio, array $overrides = []): CourseSession
    {
        $course = Course::create([
            'studio_id' => $studio->id,
            'title' => 'Kurs',
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'capacity' => 10,
        ]);
        $room = Room::create([
            'studio_id' => $studio->id,
            'name' => 'Saal',
            'capacity' => 20,
            'size_sqm' => 100,
            'lighting' => 'led',
        ]);

        return CourseSession::create(array_merge([
            'course_id' => $course->id,
            'room_id' => $room->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ], $overrides));
    }

    public function test_catalog_lists_upcoming_sessions_with_availability(): void
    {
        $studio = Studio::factory()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $session = $this->makeSession($studio);
        CourseSession::create([
            'course_id' => $session->course_id,
            'date' => now()->subDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        CourseSession::create([
            'course_id' => $session->course_id,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'status' => 'cancelled',
        ]);
        Booking::create(['user_id' => $customer->id, 'course_session_id' => $session->id]);
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/course_sessions')->assertOk();

        $response->assertJsonCount(1);
        $response->assertJsonPath('0.course.title', 'Kurs');
        $response->assertJsonPath('0.room.name', 'Saal');
        $response->assertJsonPath('0.room.lighting', 'led');
        $response->assertJsonPath('0.free_spots', 9);
        $this->assertNotNull($response->json('0.my_booking_id'));
    }

    public function test_catalog_marks_substitute_and_null_booking_state(): void
    {
        $studio = Studio::factory()->create();
        $lead = User::factory()->create(['role' => UserRole::Trainer->value]);
        $substitute = User::factory()->create(['role' => UserRole::Trainer->value, 'name' => 'Vertretung']);
        $session = $this->makeSession($studio);
        $session->course->update(['trainer_id' => $lead->id]);
        $session->update([
            'trainer_id' => $substitute->id,
            'status' => 'substituted',
            'session_info' => 'Bitte früher kommen',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));

        $this->getJson('/api/course_sessions')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.is_substitute', true)
            ->assertJsonPath('0.trainer.name', 'Vertretung')
            ->assertJsonPath('0.session_info', 'Bitte früher kommen');
    }

    public function test_catalog_supports_studio_filter_and_guest_is_rejected(): void
    {
        $studioA = Studio::factory()->create();
        $studioB = Studio::factory()->create();
        $this->makeSession($studioA);
        $this->makeSession($studioB);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));

        $this->getJson("/api/course_sessions?studio_id={$studioA->id}")
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson('/api/course_sessions')->assertOk()->assertJsonCount(2);
    }

    public function test_guest_cannot_access_catalog(): void
    {
        $this->getJson('/api/course_sessions')->assertUnauthorized();
    }
}
