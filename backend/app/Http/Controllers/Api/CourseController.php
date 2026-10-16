<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Studio;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);

        return response()->json($model->courses()->get());
    }

    /**
     * Combined payload for the course planning form: courses, trainers
     * and rooms in a single roundtrip. Shapes match the three
     * single-resource endpoints exactly.
     */
    public function overview(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);

        $pivot = $model->trainers()->get(['users.id', 'users.name', 'users.email']);
        $direct = User::where('role', UserRole::Trainer->value)
            ->where('studio_id', $model->id)
            ->get(['id', 'name', 'email']);
        $trainers = $pivot->concat($direct)->unique('id')->values();

        return response()->json([
            'courses' => $model->courses()->get(),
            'trainers' => $trainers,
            'rooms' => $model->rooms()->get(),
        ]);
    }

    public function store(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);
        $this->authorize('manageCourses', $model);

        $course = $model->courses()->create($this->validated($request, $model));

        return response()->json($course, 201);
    }

    public function update(Request $request, int $studio, int $course): JsonResponse
    {
        $model = $this->resolve($request, $studio, $course);
        $this->authorize('update', $model);

        $model->update($this->validated($request, $model->studio, true));

        return response()->json($model->refresh());
    }

    public function destroy(Request $request, int $studio, int $course): JsonResponse
    {
        $studioModel = Studio::forTenant($request->user())->findOrFail($studio);
        $this->authorize('manageCourses', $studioModel);
        $studioModel->courses()->findOrFail($course)->delete();

        return response()->json(null, 204);
    }

    private function resolve(Request $request, int $studio, int $course): Course
    {
        $studioModel = Studio::forTenant($request->user())->findOrFail($studio);

        return $studioModel->courses()->findOrFail($course);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Studio $studio, bool $sometimes = false): array
    {
        $maybe = $sometimes ? 'sometimes' : 'required';
        // On partial updates `start_time` may be absent, so `after:start_time`
        // only applies when both times are validated together.
        $endTime = [$maybe, 'date_format:H:i:s'];
        if (! $sometimes || $request->has('start_time')) {
            $endTime[] = 'after:start_time';
        }

        return $request->validate([
            'trainer_id' => [$maybe, 'nullable', 'integer', 'exists:users,id'],
            // `room_id` stays optional on create AND update (nullable FK).
            'room_id' => ['sometimes', 'nullable', 'integer', Rule::exists('rooms', 'id')->where('studio_id', $studio->id)],
            'title' => [$maybe, 'string', 'max:255'],
            'description' => [$maybe, 'nullable', 'string'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'web_link' => ['sometimes', 'nullable', 'string', 'max:255'],
            'day_of_week' => [$maybe, 'integer', 'min:1', 'max:7'],
            'start_time' => [$maybe, 'date_format:H:i:s'],
            'end_time' => $endTime,
            'capacity' => [$maybe, 'nullable', 'integer', 'min:1'],
        ]);
    }
}
