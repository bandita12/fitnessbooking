<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request, int $studio): JsonResponse
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);

        return response()->json($model->rooms()->get());
    }

    public function store(Request $request, int $studio): JsonResponse
    {
        $model = $this->resolveStudio($request, $studio);

        $room = $model->rooms()->create($this->validated($request));

        return response()->json($room, 201);
    }

    public function update(Request $request, int $studio, int $room): JsonResponse
    {
        $model = $this->resolve($request, $studio, $room);
        $model->update($this->validated($request, true));

        return response()->json($model);
    }

    public function destroy(Request $request, int $studio, int $room): JsonResponse
    {
        $this->resolve($request, $studio, $room)->delete();

        return response()->json(null, 204);
    }

    private function resolveStudio(Request $request, int $studio): Studio
    {
        $model = Studio::forTenant($request->user())->findOrFail($studio);
        $this->authorize('manageRooms', $model);

        return $model;
    }

    private function resolve(Request $request, int $studio, int $room): Room
    {
        return $this->resolveStudio($request, $studio)->rooms()->findOrFail($room);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $sometimes = false): array
    {
        $maybe = $sometimes ? 'sometimes' : 'required';

        $validated = $request->validate([
            'name' => [$maybe, 'string', 'max:255'],
            'capacity' => [$maybe, 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color_code' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'location_type' => ['sometimes', 'string', 'in:indoor,outdoor'],
            'lighting' => ['sometimes', 'nullable', 'string', 'max:16'],
            'size_sqm' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'width_meters' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'length_meters' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height_meters' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        if (array_key_exists('lighting', $validated) && $validated['lighting'] === 'none') {
            $validated['lighting'] = null;
        }

        return $validated;
    }
}
