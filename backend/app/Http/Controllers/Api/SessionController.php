<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CourseSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Update a single session by id. Resolution is role-aware so foreign
     * sessions resolve to 404 (never 403, never leaking existence).
     * Trainers may only change status and session_info — room, time and
     * capacity stay reserved for admins (filtered below).
     */
    public function update(Request $request, int $session): JsonResponse
    {
        $user = $request->user();

        $query = CourseSession::whereKey($session);
        if ($user->role === UserRole::Admin) {
            $model = $query->firstOrFail();
        } elseif ($user->role === UserRole::StudioAdmin) {
            $model = (clone $query)->whereHas(
                'course',
                fn ($q) => $q->where('studio_id', $user->studio_id)
            )->firstOrFail();
        } else {
            $studioIds = $user->studios()->pluck('studios.id')->all();
            $model = (clone $query)->where(function ($q) use ($user, $studioIds): void {
                $q->whereHas('course', fn ($qq) => $qq->where('trainer_id', $user->id))
                    ->orWhere('trainer_id', $user->id)
                    ->orWhereHas('course', fn ($qq) => $qq->whereIn('studio_id', $studioIds));
            })->firstOrFail();
        }

        $this->authorize('update', $model->course);

        $data = $request->validate([
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'in:scheduled,cancelled,substituted'],
            'session_info' => ['sometimes', 'nullable', 'string'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        if ($user->role === UserRole::Trainer) {
            $data = array_intersect_key($data, array_flip(['status', 'session_info']));
        }

        $model->update($data);

        return response()->json($model);
    }
}
