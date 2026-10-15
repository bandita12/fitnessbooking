<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role, ?int $studioId = null): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'studio_id' => $studioId,
        ]);
    }

    private function makeStudio(string $name = 'Studio A'): Studio
    {
        return Studio::create([
            'name' => $name,
            'street' => 'Str. 1',
            'zip' => '12345',
            'city' => 'City',
        ]);
    }

    private function makeCourse(Studio $studio, ?int $capacity = null): Course
    {
        return Course::create([
            'studio_id' => $studio->id,
            'title' => 'Course',
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'capacity' => $capacity,
        ]);
    }

    private function makeSession(Course $course, array $overrides = []): CourseSession
    {
        return CourseSession::create(array_merge([
            'course_id' => $course->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'status' => 'scheduled',
        ], $overrides));
    }

    public function test_customer_can_book_a_free_session(): void
    {
        $studio = $this->makeStudio();
        $customer = $this->makeUser(UserRole::Customer);
        $session = $this->makeSession($this->makeCourse($studio));
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/bookings', ['course_session_id' => $session->id]);

        $response->assertCreated();
        $this->assertDatabaseHas('bookings', [
            'user_id' => $customer->id,
            'course_session_id' => $session->id,
        ]);
    }

    public function test_double_booking_same_session_fails_with_422(): void
    {
        $studio = $this->makeStudio();
        $customer = $this->makeUser(UserRole::Customer);
        $session = $this->makeSession($this->makeCourse($studio));
        Sanctum::actingAs($customer);

        $this->postJson('/api/bookings', ['course_session_id' => $session->id])->assertCreated();
        $this->postJson('/api/bookings', ['course_session_id' => $session->id])->assertUnprocessable();

        $this->assertSame(1, Booking::count());
    }

    public function test_fully_booked_session_fails_with_422(): void
    {
        $studio = $this->makeStudio();
        $session = $this->makeSession($this->makeCourse($studio, 1));
        Sanctum::actingAs($this->makeUser(UserRole::Customer));
        $this->postJson('/api/bookings', ['course_session_id' => $session->id])->assertCreated();

        Sanctum::actingAs($this->makeUser(UserRole::Customer));
        $this->postJson('/api/bookings', ['course_session_id' => $session->id])->assertUnprocessable();
    }

    public function test_cancelled_session_cannot_be_booked(): void
    {
        $studio = $this->makeStudio();
        $session = $this->makeSession($this->makeCourse($studio), ['status' => 'cancelled']);
        Sanctum::actingAs($this->makeUser(UserRole::Customer));

        $this->postJson('/api/bookings', ['course_session_id' => $session->id])->assertUnprocessable();
    }

    public function test_cancellation_more_than_two_hours_before_start_succeeds(): void
    {
        $studio = $this->makeStudio();
        $customer = $this->makeUser(UserRole::Customer);
        $session = $this->makeSession($this->makeCourse($studio));
        $booking = Booking::create(['user_id' => $customer->id, 'course_session_id' => $session->id]);
        Sanctum::actingAs($customer);

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_cancellation_within_two_hours_fails_with_422(): void
    {
        $studio = $this->makeStudio();
        $customer = $this->makeUser(UserRole::Customer);
        $course = $this->makeCourse($studio);
        $session = $this->makeSession($course, [
            'date' => now()->format('Y-m-d'),
        ]);
        $session->course->update(['start_time' => now()->addHour()->format('H:i:s')]);
        $booking = Booking::create(['user_id' => $customer->id, 'course_session_id' => $session->id]);
        Sanctum::actingAs($customer);

        $this->deleteJson("/api/bookings/{$booking->id}")->assertUnprocessable();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_foreign_bookings_resolve_to_404(): void
    {
        $studioA = $this->makeStudio('A');
        $studioB = $this->makeStudio('B');
        $customerA = $this->makeUser(UserRole::Customer);
        $customerB = $this->makeUser(UserRole::Customer);
        $foreignAdmin = $this->makeUser(UserRole::StudioAdmin, $studioB->id);
        $booking = Booking::create([
            'user_id' => $customerA->id,
            'course_session_id' => $this->makeSession($this->makeCourse($studioA))->id,
        ]);

        Sanctum::actingAs($customerB);
        $this->deleteJson("/api/bookings/{$booking->id}")->assertNotFound();

        Sanctum::actingAs($foreignAdmin);
        $this->deleteJson("/api/bookings/{$booking->id}")->assertNotFound();
    }

    public function test_booking_index_is_scoped_by_role(): void
    {
        $studio = $this->makeStudio();
        $customerA = $this->makeUser(UserRole::Customer);
        $customerB = $this->makeUser(UserRole::Customer);
        $admin = $this->makeUser(UserRole::Admin);
        $session = $this->makeSession($this->makeCourse($studio));
        Booking::create(['user_id' => $customerA->id, 'course_session_id' => $session->id]);
        Booking::create(['user_id' => $customerB->id, 'course_session_id' => $session->id]);

        Sanctum::actingAs($customerA);
        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($admin);
        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(2);
    }

    public function test_studio_admin_can_cancel_own_studio_booking_in_time(): void
    {
        $studio = $this->makeStudio();
        $studioAdmin = $this->makeUser(UserRole::StudioAdmin, $studio->id);
        $customer = $this->makeUser(UserRole::Customer);
        $booking = Booking::create([
            'user_id' => $customer->id,
            'course_session_id' => $this->makeSession($this->makeCourse($studio))->id,
        ]);
        Sanctum::actingAs($studioAdmin);

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();
    }

    public function test_guest_cannot_book(): void
    {
        $studio = $this->makeStudio();
        $session = $this->makeSession($this->makeCourse($studio));

        $this->postJson('/api/bookings', ['course_session_id' => $session->id])->assertUnauthorized();
    }
}
