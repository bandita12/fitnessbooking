<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Studio;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
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

    private function makeCourse(Studio $studio, ?User $trainer = null): Course
    {
        return Course::create([
            'studio_id' => $studio->id,
            'trainer_id' => $trainer?->id,
            'title' => 'Course',
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
    }

    public function test_role_casts_to_enum_and_defaults_to_customer(): void
    {
        $user = User::factory()->create();
        $user->refresh();

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertSame('customer', $user->getAttributes()['role']);
    }

    public function test_capability_matrix(): void
    {
        $studio = $this->makeStudio();
        $matrix = [
            UserRole::Admin->value => [
                'studios.manage' => true, 'users.assign-roles' => true,
                'studios.update' => true, 'rooms.manage' => true, 'courses.manage' => true,
                'course-updates.create' => true, 'courses.view' => true, 'bookings.manage' => true,
            ],
            UserRole::StudioAdmin->value => [
                'studios.manage' => false, 'users.assign-roles' => false,
                'studios.update' => true, 'rooms.manage' => true, 'courses.manage' => true,
                'course-updates.create' => true, 'courses.view' => true, 'bookings.manage' => true,
            ],
            UserRole::Trainer->value => [
                'studios.manage' => false, 'users.assign-roles' => false,
                'studios.update' => false, 'rooms.manage' => false, 'courses.manage' => false,
                'course-updates.create' => true, 'courses.view' => true, 'bookings.manage' => true,
            ],
            UserRole::Customer->value => [
                'studios.manage' => false, 'users.assign-roles' => false,
                'studios.update' => false, 'rooms.manage' => false, 'courses.manage' => false,
                'course-updates.create' => false, 'courses.view' => true, 'bookings.manage' => true,
            ],
        ];

        foreach ($matrix as $role => $expectations) {
            $user = $this->makeUser(UserRole::from($role), $role === UserRole::Customer->value ? null : $studio->id);

            foreach ($expectations as $ability => $expected) {
                $this->assertSame($expected, Gate::forUser($user)->allows($ability), "{$role} / {$ability}");
            }
        }
    }

    public function test_studio_policy_scopes_to_own_studio(): void
    {
        $own = $this->makeStudio('Own');
        $foreign = $this->makeStudio('Foreign');
        $studioAdmin = $this->makeUser(UserRole::StudioAdmin, $own->id);
        $trainer = $this->makeUser(UserRole::Trainer, $own->id);
        $customer = $this->makeUser(UserRole::Customer);

        $this->assertTrue($studioAdmin->can('update', $own));
        $this->assertTrue($studioAdmin->can('manageRooms', $own));
        $this->assertTrue($studioAdmin->can('manageCourses', $own));
        $this->assertFalse($studioAdmin->can('update', $foreign));
        $this->assertFalse($studioAdmin->can('manageRooms', $foreign));

        $this->assertFalse($trainer->can('update', $own));
        $this->assertFalse($customer->can('update', $own));
    }

    public function test_course_policy_allows_lead_and_substitute_trainer_only(): void
    {
        $studio = $this->makeStudio();
        $other = $this->makeStudio('Other');
        $lead = $this->makeUser(UserRole::Trainer, $studio->id);
        $substitute = $this->makeUser(UserRole::Trainer, $studio->id);
        $unassigned = $this->makeUser(UserRole::Trainer, $studio->id);
        $studioAdmin = $this->makeUser(UserRole::StudioAdmin, $studio->id);
        $foreignAdmin = $this->makeUser(UserRole::StudioAdmin, $other->id);

        $course = $this->makeCourse($studio, $lead);
        CourseSession::create([
            'course_id' => $course->id,
            'trainer_id' => $substitute->id,
            'date' => '2026-10-20',
        ]);

        $this->assertTrue($lead->can('createUpdate', $course));
        $this->assertTrue($substitute->can('createUpdate', $course));
        $this->assertFalse($unassigned->can('createUpdate', $course));
        $this->assertTrue($studioAdmin->can('update', $course));
        $this->assertFalse($foreignAdmin->can('update', $course));
    }

    public function test_cross_tenant_studio_resolution_throws_not_found_instead_of_forbidden(): void
    {
        $own = $this->makeStudio('Own');
        $foreign = $this->makeStudio('Foreign');
        $studioAdmin = $this->makeUser(UserRole::StudioAdmin, $own->id);

        $this->expectException(ModelNotFoundException::class);
        Studio::forTenant($studioAdmin)->findOrFail($foreign->id);
    }

    public function test_unassigned_trainer_course_resolution_throws_not_found_instead_of_forbidden(): void
    {
        $own = $this->makeStudio('Own');
        $foreign = $this->makeStudio('Foreign');
        $trainer = $this->makeUser(UserRole::Trainer, $own->id);
        $customer = $this->makeUser(UserRole::Customer);
        $course = $this->makeCourse($foreign);

        $this->assertFalse($customer->can('createUpdate', $course));

        $this->expectException(ModelNotFoundException::class);
        Course::assignedTo($trainer)->findOrFail($course->id);
    }

    public function test_database_seeder_creates_one_user_per_role_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, User::count());
        $this->assertSame(
            ['admin', 'customer', 'studio_admin', 'trainer'],
            User::orderBy('role')->pluck('role')->map(fn (UserRole $r) => $r->value)->all()
        );

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, User::count());
    }
}
