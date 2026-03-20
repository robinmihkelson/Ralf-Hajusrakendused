<?php

namespace App\Http\Controllers;

use App\Models\Marker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Marker::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $marker = Marker::create($validated);

        return response()->json($marker, 201);
    }

    public function show(Marker $marker): JsonResponse
    {
        return response()->json($marker);
    }

    public function update(Request $request, Marker $marker): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $marker->update($validated);

        return response()->json($marker->fresh());
    }

    public function destroy(Marker $marker): JsonResponse
    {
        $marker->delete();

        return response()->json(['success' => true]);
    }
}
