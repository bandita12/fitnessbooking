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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_update_user_fields(): void
    {
        $studio = Studio::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Trainer->value]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Neuer Name',
            'email' => 'neu@example.com',
            'role' => 'studio_admin',
            'studio_id' => $studio->id,
        ])->assertOk()->assertJson([
            'name' => 'Neuer Name',
            'role' => 'studio_admin',
            'studio_id' => $studio->id,
        ]);

        $this->assertSame(UserRole::StudioAdmin, $user->refresh()->role);
    }

    public function test_email_unique_ignores_current_user_but_rejects_taken(): void
    {
        $user = User::factory()->create(['email' => 'bleibt@example.com']);
        User::factory()->create(['email' => 'vergeben@example.com']);

        $this->putJson("/api/admin/users/{$user->id}", [
            'email' => 'bleibt@example.com',
        ])->assertOk();

        $this->putJson("/api/admin/users/{$user->id}", [
            'email' => 'vergeben@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_invalid_role_and_unknown_studio_rejected(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/admin/users/{$user->id}", [
            'role' => 'superuser',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->putJson("/api/admin/users/{$user->id}", [
            'studio_id' => 9999,
        ])->assertUnprocessable()->assertJsonValidationErrors('studio_id');
    }

    public function test_password_only_changes_when_filled(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Gleiches Passwort',
        ])->assertOk();
        $this->assertSame($oldHash, $user->refresh()->password);

        $this->putJson("/api/admin/users/{$user->id}", [
            'password' => 'neues-geheimnis',
        ])->assertOk();
        $this->assertTrue(Hash::check('neues-geheimnis', $user->refresh()->password));

        $this->putJson("/api/admin/users/{$user->id}", [
            'password' => 'kurz',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_admin_cannot_strip_own_admin_role(): void
    {
        $this->putJson("/api/admin/users/{$this->admin->id}", [
            'role' => 'customer',
        ])->assertUnprocessable();

        $this->assertSame(UserRole::Admin, $this->admin->refresh()->role);
    }

    public function test_non_admin_cannot_update_users(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer->value]));

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'X',
        ])->assertForbidden();
    }

    public function test_admin_can_list_studio_admins(): void
    {
        User::factory()->create(['role' => UserRole::StudioAdmin->value]);
        User::factory()->create(['role' => UserRole::Customer->value]);

        $this->getJson('/api/admin/users?role=studio_admin')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.role', 'studio_admin');
    }

    public function test_admin_can_delete_user_but_not_themselves(): void
    {
        $user = User::factory()->create(['role' => UserRole::StudioAdmin->value]);

        $this->deleteJson("/api/admin/users/{$user->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $this->deleteJson("/api/admin/users/{$this->admin->id}")->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_deleting_studio_cascades_to_rooms_sessions_and_bookings(): void
    {
        $studio = Studio::factory()->create();
        $room = Room::create([
            'studio_id' => $studio->id,
            'name' => 'Saal',
            'capacity' => 10,
        ]);
        $course = Course::create([
            'studio_id' => $studio->id,
            'title' => 'Kurs',
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
        $session = CourseSession::create([
            'course_id' => $course->id,
            'room_id' => $room->id,
            'date' => now()->addDay()->format('Y-m-d'),
        ]);
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        Booking::create([
            'user_id' => $customer->id,
            'course_session_id' => $session->id,
        ]);

        $this->deleteJson("/api/admin/studios/{$studio->id}")->assertNoContent();

        $this->assertDatabaseMissing('studios', ['id' => $studio->id]);
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('course_sessions', ['id' => $session->id]);
        $this->assertSame(0, Booking::count());
    }

    public function test_admin_can_update_studio_marketing_fields(): void
    {
        $studio = Studio::factory()->create();

        $this->putJson("/api/admin/studios/{$studio->id}", [
            'name' => 'Neu',
            'description' => 'Beschreibung',
            'web_link' => 'https://example.com',
        ])->assertOk()->assertJsonPath('name', 'Neu');

        $this->assertDatabaseHas('studios', [
            'id' => $studio->id,
            'description' => 'Beschreibung',
        ]);
    }
}
