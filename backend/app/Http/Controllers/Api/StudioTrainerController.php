<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudioTrainerController extends Controller
{
    public function index(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);

        return response()->json($model->trainers()->get(['users.id', 'users.name', 'users.email']));
    }

    /**
     * Register a new trainer account and link it to the studio.
     */
    public function store(Request $request, int $studio): JsonResponse
    {
        $model = $this->resolveStudio($request, $studio);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $trainer = DB::transaction(function () use ($model, $data): User {
            $created = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'studio_id' => null,
                'role' => UserRole::Trainer,
            ]);
            $model->trainers()->syncWithoutDetaching([$created->id]);

            return $created;
        });

        return response()->json(
            $trainer->only(['id', 'name', 'email', 'role']),
            201
        );
    }

    /**
     * Link an existing trainer account to the studio (Freischaltung).
     */
    public function attach(Request $request, int $studio): JsonResponse
    {
        $model = $this->resolveStudio($request, $studio);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $trainer = User::where('email', $data['email'])->firstOrFail();

        if ($trainer->role !== UserRole::Trainer) {
            return response()->json(['message' => 'Nur Trainer können freigeschaltet werden.'], 422);
        }

        $model->trainers()->syncWithoutDetaching([$trainer->id]);

        return response()->json(
            $trainer->only(['id', 'name', 'email', 'role']),
            200
        );
    }

    public function destroy(Request $request, int $studio, int $trainer): JsonResponse
    {
        $model = $this->resolveStudio($request, $studio);
        $model->trainers()->detach($trainer);

        return response()->json(null, 204);
    }

    private function resolveStudio(Request $request, int $studio): Studio
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);
        $this->authorize('manageCourses', $model);

        return $model;
    }
}
