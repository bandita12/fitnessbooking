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

class CoursesCalendarApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(Studio $studio, ?Room $room = null): Course
    {
        return Course::create([
            'studio_id' => $studio->id,
            'room_id' => $room?->id,
            'title' => 'Kurs',
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'capacity' => 10,
        ]);
    }

    private function makeRoom(Studio $studio, string $name): Room
    {
        return Room::create([
            'studio_id' => $studio->id,
            'name' => $name,
            'capacity' => 20,
            'size_sqm' => 100,
        ]);
    }

    public function test_catalog_filters_by_effective_room(): void
    {
        $studio = Studio::factory()->create();
        $roomA = $this->makeRoom($studio, 'Saal A');
        $roomB = $this->makeRoom($studio, 'Saal B');
        $course = $this->makeCourse($studio, $roomA);
        $sessionInA = CourseSession::create([
            'course_id' => $course->id,
            'room_id' => $roomA->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        // No session room → falls back to the course room (Saal A).
        $sessionFallback = CourseSession::create([
            'course_id' => $course->id,
            'room_id' => null,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        $other = CourseSession::create([
            'course_id' => $course->id,
            'room_id' => $roomB->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));

        $ids = $this->getJson("/api/course_sessions?room_id={$roomA->id}")
            ->assertOk()
            ->json();

        $this->assertEqualsCanonicalizing(
            [$sessionInA->id, $sessionFallback->id],
            array_column($ids, 'id')
        );
        $this->assertNotContains($other->id, array_column($ids, 'id'));
    }

    public function test_catalog_excludes_cancelled_by_default_but_includes_on_request(): void
    {
        $studio = Studio::factory()->create();
        $course = $this->makeCourse($studio);
        CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'cancelled',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));

        $this->getJson('/api/course_sessions')->assertOk()->assertJsonCount(0);

        $this->getJson('/api/course_sessions?include_cancelled=1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'cancelled');
    }

    public function test_my_sessions_returns_only_own_bookings(): void
    {
        $studio = Studio::factory()->create();
        $course = $this->makeCourse($studio);
        $mine = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        $cancelledMine = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'status' => 'cancelled',
        ]);
        $foreign = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        $me = User::factory()->create(['role' => UserRole::Customer->value]);
        $other = User::factory()->create(['role' => UserRole::Customer->value]);
        $myBooking = Booking::create(['user_id' => $me->id, 'course_session_id' => $mine->id]);
        Booking::create(['user_id' => $me->id, 'course_session_id' => $cancelledMine->id]);
        Booking::create(['user_id' => $other->id, 'course_session_id' => $foreign->id]);
        Sanctum::actingAs($me);

        $response = $this->getJson('/api/my-sessions')->assertOk();

        $response->assertJsonCount(2);
        $ids = array_column($response->json(), 'id');
        $this->assertContains($mine->id, $ids);
        $this->assertContains($cancelledMine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        foreach ($response->json() as $entry) {
            $this->assertNotNull($entry['my_booking_id']);
        }
        $this->assertSame($myBooking->id, $response->json('0.my_booking_id'));
    }

    public function test_my_sessions_guest_is_rejected(): void
    {
        $this->getJson('/api/my-sessions')->assertUnauthorized();
    }

    public function test_studios_index_reports_trainer_and_customer_counts(): void
    {
        $studio = Studio::factory()->create();
        $trainers = User::factory()->count(2)->create(['role' => UserRole::Trainer->value]);
        $studio->trainers()->attach($trainers->pluck('id')->all());
        $course = $this->makeCourse($studio);
        $session = CourseSession::create([
            'course_id' => $course->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        $customers = User::factory()->count(2)->create(['role' => UserRole::Customer->value]);
        foreach ($customers as $customer) {
            Booking::create(['user_id' => $customer->id, 'course_session_id' => $session->id]);
        }
        // Second booking by the same customer counts once (distinct users).
        $extraCourse = $this->makeCourse($studio);
        $extraSession = CourseSession::create([
            'course_id' => $extraCourse->id,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'scheduled',
        ]);
        Booking::create(['user_id' => $customers[0]->id, 'course_session_id' => $extraSession->id]);
        Sanctum::actingAs($customers[0]);

        $this->getJson('/api/studios')
            ->assertOk()
            ->assertJsonPath('0.trainers_count', 2)
            ->assertJsonPath('0.customers_count', 2);
    }
}
