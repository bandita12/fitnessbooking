<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioController extends Controller
{
    /**
     * Studio list with additive sort helpers for calendar dropdowns:
     * `trainers_count` (studio_user pivot) and `customers_count`
     * (distinct users booked into sessions of this studio).
     */
    public function index(Request $request): JsonResponse
    {
        $studios = Studio::forTenant($request->user())
            ->withCount('trainers')
            ->addSelect(['customers_count' => Booking::selectRaw('COUNT(DISTINCT bookings.user_id)')
                ->join('course_sessions', 'course_sessions.id', '=', 'bookings.course_session_id')
                ->join('courses', 'courses.id', '=', 'course_sessions.course_id')
                ->whereColumn('courses.studio_id', 'studios.id'),
            ])
            ->get();

        return response()->json($studios);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'zip' => ['required', 'string', 'max:32'],
            'city' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'thumbnail_url' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'web_link' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Studio::create($data), 201);
    }

    public function update(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);
        $this->authorize('update', $model);

        $model->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'street' => ['sometimes', 'string', 'max:255'],
            'zip' => ['sometimes', 'string', 'max:32'],
            'city' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'web_link' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]));

        return response()->json($model);
    }

    public function destroy(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);
        $this->authorize('update', $model);
        $model->delete();

        return response()->json(null, 204);
    }
}
