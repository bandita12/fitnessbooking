<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);

        return response()->json($model->courses()->get());
    }

    public function store(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);
        $this->authorize('manageCourses', $model);

        $course = $model->courses()->create($request->validate([
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'thumbnail_url' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'web_link' => ['nullable', 'string', 'max:255'],
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s', 'after:start_time'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]));

        return response()->json($course, 201);
    }

    public function update(Request $request, int $studio, int $course): JsonResponse
    {
        $model = $this->resolve($request, $studio, $course);
        $this->authorize('update', $model);

        $model->update($request->validate([
            'trainer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'day_of_week' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'start_time' => ['sometimes', 'date_format:H:i:s'],
            'end_time' => ['sometimes', 'date_format:H:i:s'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]));

        return response()->json($model);
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
}
