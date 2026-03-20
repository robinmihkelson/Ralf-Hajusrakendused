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

        $units = env('OPENWEATHER_UNITS', 'metric');

        [$city, $countryCode] = $this->parseSearch($search);
        $queryKey = Str::of($city)->lower()->trim()->slug('-');
        if ($countryCode !== '') {
            $queryKey = $queryKey->append('-', $countryCode);
        }

        $cacheKey = 'weather-api-'.$units.'-'.$queryKey->toString();
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
                'error' => 'Missing OPENWEATHER_API_KEY in environment.',
            ], 500);
        }

        $weather = $this->fetchWeatherByQuery($city, $countryCode, $units);
        if (isset($weather['error'])) {
            return response()->json([
                'error' => $weather['error'],
            ], 502);
        }

        if (! isset($weather['location'])) {
            return response()->json([
                'error' => 'Location data is missing from weather response.',
            ], 502);
        }

        $payload = [
            'city' => $weather['location']['name'],
            'country' => $weather['location']['country'],
            'country_code' => $weather['location']['country_code'],
            'time' => $weather['time'],
            'coordinates' => [
                'lat' => round((float) $weather['location']['lat'], 3),
                'lon' => round((float) $weather['location']['lon'], 3),
            ],
            'current' => [
                'temperature' => $weather['temperature'],
                'wind_speed' => $weather['wind_speed'],
                'wind_direction' => $weather['wind_direction'],
                'weather_code' => $weather['weather_code'],
                'condition' => $this->conditionText((int) $weather['weather_code']),
                'icon' => $this->conditionIcon((int) $weather['weather_code']),
                'unit_temp' => $units === 'imperial' ? '°F' : '°C',
                'unit_wind' => $units === 'imperial' ? 'mph' : 'm/s',
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

    private function fetchWeatherByQuery(string $city, string $countryCode, string $units): array
    {
        $query = $countryCode === '' ? $city : $city.",".$countryCode;
        $response = Http::timeout(8)->get('https://api.openweathermap.org/data/2.5/weather', [
            'q' => $query,
            'appid' => env('OPENWEATHER_API_KEY'),
            'units' => $units,
            'lang' => 'en',
        ]);
        if (! $response->successful()) {
            $errorMessage = $response->json('message', 'Weather service is currently unavailable.');
            $status = $response->status();
            return ['error' => "Weather data could not be loaded (HTTP {$status}): {$errorMessage}"];
        }

        $current = $response->json([], []);
        if (! is_array($current) || ! isset($current['name'], $current['sys']['country'], $current['coord']['lat'], $current['coord']['lon'], $current['main']['temp'], $current['wind']['speed'], $current['weather'][0]['id'])) {
            return ['error' => 'Weather response is incomplete.'];
        }

        $weather = $current['weather'][0] ?? [];
        if (! is_array($weather)) {
            $weather = [];
        }

        $minTemp = $current['main']['temp_min'] ?? null;
        $maxTemp = $current['main']['temp_max'] ?? null;
        $humidity = $current['main']['humidity'] ?? null;

        return [
            'location' => [
                'name' => (string) ($current['name'] ?? $city),
                'country' => (string) ($current['sys']['country'] ?? $countryCode),
                'country_code' => (string) ($current['sys']['country'] ?? $countryCode),
                'lat' => (float) ($current['coord']['lat'] ?? 0),
                'lon' => (float) ($current['coord']['lon'] ?? 0),
            ],
            'time' => date('Y-m-d H:i:s', (int) ($current['dt'] ?? time())),
            'temperature' => (float) $current['main']['temp'],
            'wind_speed' => is_numeric($current['wind']['speed']) ? round((float) $current['wind']['speed'], 1) : null,
            'wind_direction' => (float) ($current['wind']['deg'] ?? 0),
            'weather_code' => (int) ($weather['id'] ?? 800),
            'min_temp' => is_numeric($minTemp) ? (float) $minTemp : null,
            'max_temp' => is_numeric($maxTemp) ? (float) $maxTemp : null,
            'precipitation' => is_numeric($humidity) ? (int) $humidity : null,
            'condition_text' => (string) ($weather['description'] ?? ''),
        ];
    }

    private function conditionText(int $code): string
    {
        return match (true) {
            $code === 800 => 'Clear',
            in_array($code, [801], true) => 'Mostly clear',
            in_array($code, [802], true) => 'Partly cloudy',
            in_array($code, [803, 804], true) => 'Cloudy',
            ($code >= 200 && $code <= 232) => 'Thunderstorm',
            ($code >= 300 && $code <= 321) => 'Drizzle',
            ($code >= 500 && $code <= 531) => 'Rain',
            ($code >= 600 && $code <= 622) => 'Snow',
            ($code >= 701 && $code <= 781) => 'Atmosphere',
            ($code >= 900) => 'Thunderstorm',
            default => 'Unknown',
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
