<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseSessionController extends Controller
{
    public function index(Request $request, int $studio, int $course): JsonResponse
    {
        return response()->json($this->resolveCourse($request, $studio, $course)->sessions()->get());
    }

    public function store(Request $request, int $studio, int $course): JsonResponse
    {
        $courseModel = $this->resolveCourse($request, $studio, $course);
        $this->authorize('update', $courseModel);

        $session = $courseModel->sessions()->create($request->validate([
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'status' => ['sometimes', 'string', 'in:scheduled,cancelled,substituted'],
            'session_info' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]));

        return response()->json($session, 201);
    }

    public function update(Request $request, int $studio, int $course, int $session): JsonResponse
    {
        $courseModel = $this->resolveCourse($request, $studio, $course);
        $this->authorize('update', $courseModel);

        $model = $courseModel->sessions()->findOrFail($session);
        $model->update($request->validate([
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'in:scheduled,cancelled,substituted'],
            'session_info' => ['sometimes', 'nullable', 'string'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]));

        return response()->json($model);
    }

    public function destroy(Request $request, int $studio, int $course, int $session): JsonResponse
    {
        $courseModel = $this->resolveCourse($request, $studio, $course);
        $this->authorize('update', $courseModel);
        $courseModel->sessions()->findOrFail($session)->delete();

        return response()->json(null, 204);
    }

    /**
     * Generate the concrete sessions for the coming weeks (default 4).
     * Skips dates that already have a session (idempotent).
     */
    public function generate(Request $request, int $studio, int $course): JsonResponse
    {
        $courseModel = $this->resolveCourse($request, $studio, $course);
        $this->authorize('update', $courseModel);

        $data = $request->validate([
            'weeks' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);
        $weeks = $data['weeks'] ?? 4;

        $created = 0;
        $today = now()->startOfDay();
        for ($offset = 0; $offset < $weeks * 7; $offset++) {
            $date = $today->copy()->addDays($offset);
            if ($date->dayOfWeekIso !== (int) $courseModel->day_of_week) {
                continue;
            }
            if ($courseModel->sessions()->whereDate('date', $date)->exists()) {
                continue;
            }
            $courseModel->sessions()->create(['date' => $date->format('Y-m-d')]);
            $created++;
        }

        return response()->json([
            'created' => $created,
            'sessions' => $courseModel->sessions()->orderBy('date')->get(),
        ], 201);
    }

    private function resolveCourse(Request $request, int $studio, int $course): Course
    {
        $studioModel = Studio::forTenant($request->user())->findOrFail($studio);

        return $studioModel->courses()->findOrFail($course);
    }
}
