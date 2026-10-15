<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Runs RolesAndUsersSeeder einmal idempotent — nur wenn die users-Tabelle
     * komplett leer ist, damit bestehende Dev-Daten niemals überschrieben werden.
     */
    public function run(): void
    {
        if (User::count() === 0) {
            $this->call(RolesAndUsersSeeder::class);
        }
        // Falls später weitere Seed-Klassen hinzukommen, können sie hier ergänzt werden.
    }
}
