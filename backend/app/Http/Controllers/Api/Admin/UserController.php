<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UserController extends Controller
{
    /**
     * Create a studio admin bound to one studio. The role is always forced
     * to `studio_admin` — any `role` input is ignored.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'studio_id' => $data['studio_id'],
            'role' => UserRole::StudioAdmin,
        ]);

        return response()->json($user->only(['id', 'name', 'email', 'role', 'studio_id']), 201);
    }

    /**
     * List users, optionally filtered by role.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', new Enum(UserRole::class)],
        ]);

        $users = User::when(
            array_key_exists('role', $data),
            fn ($query) => $query->where('role', $data['role'] instanceof UserRole ? $data['role']->value : $data['role'])
        )->orderBy('id')->get(['id', 'name', 'email', 'role', 'studio_id']);

        return response()->json($users);
    }

    /**
     * Update a user. Password is only changed when a non-empty value is sent.
     * A super-admin cannot strip their own admin role.
     */
    public function update(Request $request, int $user): JsonResponse
    {
        $model = User::findOrFail($user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($model->id)],
            'role' => ['sometimes', new Enum(UserRole::class)],
            'studio_id' => ['sometimes', 'nullable', 'integer', 'exists:studios,id'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ]);

        if ($model->id === $request->user()->id && array_key_exists('role', $data)) {
            $role = $data['role'] instanceof UserRole ? $data['role']->value : $data['role'];
            if ($role !== UserRole::Admin->value) {
                return response()->json(['message' => 'Die eigene Admin-Rolle kann nicht entzogen werden.'], 422);
            }
        }

        if (empty($data['password'] ?? null)) {
            unset($data['password']);
        }

        $model->update($data);

        return response()->json($model->only(['id', 'name', 'email', 'role', 'studio_id']));
    }

    /**
     * Delete a user. A super-admin cannot delete their own account.
     */
    public function destroy(Request $request, int $user): JsonResponse
    {
        $model = User::findOrFail($user);

        if ($model->id === $request->user()->id) {
            return response()->json(['message' => 'Das eigene Konto kann nicht gelöscht werden.'], 422);
        }

        $model->delete();

        return response()->json(null, 204);
    }
}
