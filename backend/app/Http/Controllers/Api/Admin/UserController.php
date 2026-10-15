<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
