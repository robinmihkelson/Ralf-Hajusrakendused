<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WeatherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', 'Tallinn'));
        if ($search === '') {
            $search = 'Tallinn';
        }

        [$city, $countryCode] = $this->parseSearch($search);
        $queryKey = Str::of($city)->lower()->trim()->slug('-');
        if ($countryCode !== '') {
            $queryKey = $queryKey->append('-', $countryCode);
        }

        $cacheKey = 'weather-api-'.$queryKey->toString();
        $cacheTTLMinutes = 10;
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['city'], $cached['current'])) {
            return response()->json([
                'source' => 'openweather',
                'search' => ['query' => $search],
                'cached' => true,
                'data' => $cached,
            ]);
        }

        if (env('OPENWEATHER_API_KEY', '') === '') {
            return response()->json([
                'error' => 'Puudub OPENWEATHER_API_KEY .env-is.',
            ], 500);
        }

        $location = $this->resolveLocation($city, $countryCode);
        if (isset($location['error'])) {
            return response()->json([
                'error' => $location['error'],
            ], 404);
        }

        $weather = $this->fetchWeather((float) $location['latitude'], (float) $location['longitude']);
        if (isset($weather['error'])) {
            return response()->json([
                'error' => $weather['error'],
            ], 502);
        }

        $payload = [
            'city' => $location['name'],
            'country' => $location['country'],
            'country_code' => $location['country_code'],
            'time' => $weather['time'],
            'coordinates' => [
                'lat' => round((float) $location['latitude'], 3),
                'lon' => round((float) $location['longitude'], 3),
            ],
            'current' => [
                'temperature' => $weather['temperature'],
                'wind_speed' => $weather['wind_speed'],
                'wind_direction' => $weather['wind_direction'],
                'weather_code' => $weather['weather_code'],
                'condition' => $this->conditionText((int) $weather['weather_code']),
                'icon' => $this->conditionIcon((int) $weather['weather_code']),
                'unit_temp' => '°C',
                'unit_wind' => 'km/h',
            ],
            'forecast' => [
                'min_temp' => $weather['min_temp'],
                'max_temp' => $weather['max_temp'],
                'today_humidity' => $weather['precipitation'],
            ],
        ];

        Cache::put($cacheKey, $payload, now()->addMinutes($cacheTTLMinutes));

        return response()->json([
            'source' => 'openweather',
            'search' => ['query' => $search],
            'cached' => false,
            'data' => $payload,
        ]);
    }

    private function parseSearch(string $search): array
    {
        $parts = array_map('trim', explode(',', $search, 2));
        $city = $parts[0];
        $country = $parts[1] ?? '';
        $country = strtoupper(preg_replace('/[^A-Za-z]/', '', $country));
        if (strlen($country) !== 2) {
            $country = '';
        }

        return [$city, $country];
    }

    private function resolveLocation(string $city, string $countryCode): array
    {
        $params = [
            'q' => $countryCode === '' ? $city : "$city,$countryCode",
            'limit' => 1,
            'appid' => env('OPENWEATHER_API_KEY'),
        ];

        $response = Http::timeout(8)->get('https://api.openweathermap.org/geo/1.0/direct', $params);
        if (! $response->successful()) {
            return ['error' => 'Geokoodide teenus ei tööta praegu.'];
        }

        $results = $response->json([], []);
        if (! is_array($results) || count($results) === 0) {
            return ['error' => 'Asukohta ei leitud. Proovi täpsustada linn + riik (nt Tallinn, EE).'];
        }

        $first = $results[0];
        if (! is_array($first)) {
            return ['error' => 'Ilmaandmete allikas andis ootamatu vastuse.'];
        }

        return [
            'name' => (string) ($first['name'] ?? $city),
            'country' => (string) ($first['country'] ?? $countryCode),
            'country_code' => (string) ($first['country'] ?? $countryCode),
            'latitude' => (float) ($first['lat'] ?? 0),
            'longitude' => (float) ($first['lon'] ?? 0),
        ];
    }

    private function fetchWeather(float $lat, float $lon): array
    {
        $response = Http::timeout(8)->get('https://api.openweathermap.org/data/2.5/weather', [
            'lat' => $lat,
            'lon' => $lon,
            'appid' => env('OPENWEATHER_API_KEY'),
            'units' => 'metric',
            'lang' => 'et',
        ]);

        if (! $response->successful()) {
            return ['error' => 'Ilma andmeid ei õnnestunud laadida.'];
        }

        $current = $response->json([], []);
        if (! is_array($current)
            || ! isset($current['main']['temp'], $current['wind']['speed'], $current['weather'][0]['id'])
        ) {
            return ['error' => 'Ilmaandmete vastus on puudulik.'];
        }

        $minTemp = $current['main']['temp_min'] ?? null;
        $maxTemp = $current['main']['temp_max'] ?? null;
        $humidity = $current['main']['humidity'] ?? null;

        return [
            'time' => date('Y-m-d H:i:s', (int) ($current['dt'] ?? time())),
            'temperature' => (float) $current['main']['temp'],
            'wind_speed' => round((float) $current['wind']['speed'] * 3.6, 1),
            'wind_direction' => (float) ($current['wind']['deg'] ?? 0),
            'weather_code' => (int) $current['weather'][0]['id'],
            'min_temp' => is_numeric($minTemp) ? (float) $minTemp : null,
            'max_temp' => is_numeric($maxTemp) ? (float) $maxTemp : null,
            'precipitation' => is_numeric($humidity) ? (int) $humidity : null,
        ];
    }

    private function conditionText(int $code): string
    {
        return match (true) {
            $code === 800 => 'Selge',
            in_array($code, [801], true) => 'Peaaegu selge',
            in_array($code, [802], true) => 'Mõõdukalt pilves',
            in_array($code, [803, 804], true) => 'Pimeda piline',
            ($code >= 200 && $code <= 232) => 'Äike',
            ($code >= 300 && $code <= 321) => 'Kerge vihm',
            ($code >= 500 && $code <= 531) => 'Vihm',
            ($code >= 600 && $code <= 622) => 'Lumine',
            ($code >= 701 && $code <= 781) => 'Udu',
            ($code >= 900) => 'Äike',
            default => 'Teadmata',
        };
    }

    private function conditionIcon(int $code): string
    {
        return match (true) {
            $code === 800 => '☀️',
            in_array($code, [801], true) => '🌤️',
            in_array($code, [802], true) => '⛅',
            in_array($code, [803, 804], true) => '☁️',
            ($code >= 200 && $code <= 232) => '⛈️',
            ($code >= 300 && $code <= 321) => '🌦️',
            ($code >= 500 && $code <= 531) => '🌧️',
            ($code >= 600 && $code <= 622) => '❄️',
            ($code >= 701 && $code <= 781) => '🌫️',
            ($code >= 900) => '🌩️',
            default => '🌡️',
        };
    }
}
