<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SharkController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('SharksAPI');
    }

    public function index(Request $request): JsonResponse
    {
        $endpoint = trim((string) config('services.sharks.url', ''));
        $configuredApiKey = trim((string) config('services.sharks.api_key', ''));

        if ($endpoint === '') {
            return response()->json([
                'error' => 'Missing SHARKS_API_URL in configuration.',
            ], 500);
        }

        $endpointQuery = [];
        parse_str((string) parse_url($endpoint, PHP_URL_QUERY), $endpointQuery);

        $apiKeyFromEndpoint = trim((string) ($endpointQuery['api_key'] ?? ''));
        $apiKey = $configuredApiKey !== '' ? $configuredApiKey : $apiKeyFromEndpoint;
        if ($apiKey === '') {
            return response()->json([
                'error' => 'Missing SHARKS_API_KEY in configuration.',
            ], 500);
        }

        unset($endpointQuery['api_key']);

        $queryFromRequest = collect($request->query())
            ->except(['api_key'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        $query = [
            ...$endpointQuery,
            ...$queryFromRequest,
            'api_key' => $apiKey,
        ];

        $baseEndpoint = strtok($endpoint, '?') ?: $endpoint;

        $cacheTtlSeconds = max((int) config('services.sharks.cache_ttl_seconds', 600), 30);
        $cacheKey = 'sharks_api:'.md5($baseEndpoint.'?'.http_build_query($query));
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['items'])) {
            return response()->json([
                'source' => $baseEndpoint,
                'cached' => true,
                'data' => $cached['items'],
                'meta' => [
                    'total' => count($cached['items']),
                    'returned' => count($cached['items']),
                ],
            ]);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                ])
                ->timeout(12)
                ->get($baseEndpoint, $query);
        } catch (Throwable $exception) {
            return response()->json([
                'error' => 'Could not connect to external sharks API.',
            ], 502);
        }

        if (! $response->ok()) {
            $message = $response->json('error') ?? $response->json('message') ?? 'External sharks API is unavailable.';

            return response()->json([
                'error' => "Sharks API request failed (HTTP {$response->status()}): {$message}",
            ], 502);
        }

        $items = $this->normalizeItems($response->json());
        Cache::put($cacheKey, ['items' => $items], now()->addSeconds($cacheTtlSeconds));

        return response()->json([
            'source' => $baseEndpoint,
            'cached' => false,
            'data' => $items,
            'meta' => [
                'total' => count($items),
                'returned' => count($items),
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        $nestedData = $payload['data'] ?? null;
        if (is_array($nestedData) && array_is_list($nestedData)) {
            return array_values(array_filter($nestedData, 'is_array'));
        }

        if ($this->isAssocArrayOfItems($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [$payload];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isAssocArrayOfItems(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }

        foreach ($payload as $value) {
            if (! is_array($value)) {
                return false;
            }
        }

        return true;
    }
}
