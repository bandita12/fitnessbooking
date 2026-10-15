<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates exactly one test user per role via firstOrCreate (idempotent).
     * Relies on .env values ADMIN_PASSWORD, STUDIO_ADMIN_PASSWORD,
     * TRAINER_PASSWORD, CUSTOMER_PASSWORD with sensible defaults.
     */
    public function run(): void
    {
        // Admin — Super-Admin mit vollem Zugriff
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'role' => UserRole::Admin->value,
                'password' => env('ADMIN_PASSWORD', 'secret'),
                'studio_id' => null,
            ],
        );

        // Studio-Admin — verantwortlich für ein einziges Studio
        User::firstOrCreate(
            ['email' => 'studio1@example.com'],
            [
                'name' => 'Studio Admin User',
                'role' => UserRole::StudioAdmin->value,
                'password' => env('STUDIO_ADMIN_PASSWORD', 'secret'),
                'studio_id' => 1, // FK auf das erste Studio (wird von create_studios_table bereitgestellt)
            ],
        );

        // Trainer — Kursleiter, zugewiesen über course_user-Roster oder trainer_id auf courses/sessions
        User::firstOrCreate(
            ['email' => 'trainer1@example.com'],
            [
                'name' => 'Trainer User',
                'role' => UserRole::Trainer->value,
                'password' => env('TRAINER_PASSWORD', 'secret'),
                'studio_id' => 1,
            ],
        );

        // Kunde — kann Kurse buchen und bis 2h vor Beginn stornieren
        User::firstOrCreate(
            ['email' => 'kunde@example.com'],
            [
                'name' => 'Customer User',
                'role' => UserRole::Customer->value,
                'password' => env('CUSTOMER_PASSWORD', 'secret'),
                'studio_id' => null,
            ],
        );
    }
}
