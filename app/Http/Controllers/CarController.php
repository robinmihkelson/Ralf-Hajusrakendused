<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CarController extends Controller
{
    private const CACHE_VERSION_KEY = 'cars:cache_version';

    public function page(): Response
    {
        return Inertia::render('API', [
            'defaultLimit' => 12,
            'docsEndpoint' => '/api/cars/docs',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', Rule::in(['all', 'mine', 'others'])],
            'sort_by' => ['nullable', Rule::in(['title', 'brand', 'production_year', 'horsepower', 'created_at'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'year_from' => ['nullable', 'integer', 'min:1886', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1886', 'max:2100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = [
            'search' => trim((string) ($payload['search'] ?? '')),
            'brand' => trim((string) ($payload['brand'] ?? '')),
            'scope' => (string) ($payload['scope'] ?? 'all'),
            'sort_by' => (string) ($payload['sort_by'] ?? 'created_at'),
            'sort_dir' => (string) ($payload['sort_dir'] ?? 'desc'),
            'year_from' => isset($payload['year_from']) ? (int) $payload['year_from'] : null,
            'year_to' => isset($payload['year_to']) ? (int) $payload['year_to'] : null,
            'limit' => isset($payload['limit']) ? (int) $payload['limit'] : 12,
            'viewer_user_id' => $request->user()->id,
        ];

        $cacheVersion = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
        $cacheKey = 'cars:list:v'.$cacheVersion.':'.md5(json_encode($filters));

        $result = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($filters) {
            $query = Car::query()
                ->with('user:id,name');

            if ($filters['scope'] === 'mine') {
                $query->where('user_id', $filters['viewer_user_id']);
            }

            if ($filters['scope'] === 'others') {
                $query->where('user_id', '!=', $filters['viewer_user_id']);
            }

            if ($filters['brand'] !== '') {
                $query->where('brand', 'like', '%'.$filters['brand'].'%');
            }

            if ($filters['search'] !== '') {
                $query->where(function ($searchQuery) use ($filters) {
                    $searchQuery
                        ->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhere('brand', 'like', '%'.$filters['search'].'%')
                        ->orWhere('description', 'like', '%'.$filters['search'].'%');
                });
            }

            if ($filters['year_from'] !== null) {
                $query->where('production_year', '>=', $filters['year_from']);
            }

            if ($filters['year_to'] !== null) {
                $query->where('production_year', '<=', $filters['year_to']);
            }

            $total = (clone $query)->count();
            $items = $query
                ->orderBy($filters['sort_by'], $filters['sort_dir'])
                ->limit($filters['limit'])
                ->get();

            return [
                'data' => $items,
                'meta' => [
                    'total' => $total,
                    'returned' => $items->count(),
                    'limit' => $filters['limit'],
                    'sort_by' => $filters['sort_by'],
                    'sort_dir' => $filters['sort_dir'],
                    'scope' => $filters['scope'],
                    'cache_ttl_seconds' => 600,
                ],
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'image' => ['required', 'url', 'max:2048'],
            'description' => ['required', 'string', 'max:5000'],
            'brand' => ['required', 'string', 'max:120'],
            'production_year' => ['required', 'integer', 'min:1886', 'max:2100'],
            'horsepower' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $car = Car::query()->create([
            ...$payload,
            'user_id' => $request->user()->id,
        ]);

        if (! Cache::has(self::CACHE_VERSION_KEY)) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
        }

        Cache::increment(self::CACHE_VERSION_KEY);

        return response()->json([
            'item' => $car->load('user:id,name'),
        ], 201);
    }

    public function docs(): JsonResponse
    {
        return response()->json([
            'name' => 'Cars API',
            'theme' => 'Cars',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/cars',
                    'description' => 'Returns created records with filtering, sorting, search and limit.',
                    'query_params' => [
                        'search' => 'Full-text search in title, brand and description',
                        'brand' => 'Filter by brand (partial match)',
                        'scope' => 'all | mine | others',
                        'sort_by' => 'title | brand | production_year | horsepower | created_at',
                        'sort_dir' => 'asc | desc',
                        'year_from' => 'Minimum production year',
                        'year_to' => 'Maximum production year',
                        'limit' => 'Max returned rows (1..100)',
                    ],
                    'example' => '/api/cars?search=bmw&scope=others&sort_by=production_year&sort_dir=desc&limit=10',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/cars',
                    'description' => 'Create a new car record.',
                    'body_fields' => [
                        'title' => 'required string',
                        'image' => 'required URL',
                        'description' => 'required string',
                        'brand' => 'required string',
                        'production_year' => 'required integer',
                        'horsepower' => 'required integer',
                    ],
                ],
            ],
            'cache' => [
                'enabled' => true,
                'strategy' => 'Cache::remember with versioned cache key',
                'ttl_seconds' => 600,
                'invalidated_on_create' => true,
            ],
        ]);
    }
}
