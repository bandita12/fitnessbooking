<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Studio::forTenant($request->user())->get());
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
