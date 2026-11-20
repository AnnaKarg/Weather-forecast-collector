<?php

namespace App\Services\Weather;

use App\Models\Location;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WeatherApiService implements WeatherProviderInterface
{
    public function getProviderName(): string
    {
        return 'WeatherAPI';
    }

    public function fetchForecasts(Location $location, CarbonInterface $fetchedAt): array
    {
        $baseUrl = config('services.weather_api.base_url');
        $apiKey = config('services.weather_api.key');

        if (empty($apiKey)) {
            throw new RuntimeException('WeatherAPI key is not configured.');
        }

        $response = Http::timeout(15)
            ->retry(2, 500)
            ->get("{$baseUrl}/forecast.json", [
                'key' => $apiKey,
                'q' => "{$location->latitude},{$location->longitude}",
                'days' => 3,
                'aqi' => 'no',
                'alerts' => 'no',
            ]);

        if ($response->failed()) {
            throw new RuntimeException("WeatherAPI request failed with status {$response->status()}.");
        }

        $forecastDays = $response->json('forecast.forecastday', []);
        $records = [];

        foreach ($forecastDays as $dayData) {
            // Normalize daily summary
            $records[] = [
                'location_id' => $location->id,
                'fetched_at' => $fetchedAt->toDateTimeString(),
                'forecast_date_time' => Carbon::parse($dayData['date'], 'UTC')->startOfDay()->toDateTimeString(),
                'resolution' => 'daily',
                'temperature' => $dayData['day']['avgtemp_c'] ?? null,
                'temperature_min' => $dayData['day']['mintemp_c'] ?? null,
                'temperature_max' => $dayData['day']['maxtemp_c'] ?? null,
                'precipitation' => $dayData['day']['totalprecip_mm'] ?? null,
            ];

            // Normalize hourly entries inside each day (using time_epoch for exact UTC)
            foreach ($dayData['hour'] ?? [] as $hourData) {
                $records[] = [
                    'location_id' => $location->id,
                    'fetched_at' => $fetchedAt->toDateTimeString(),
                    'forecast_date_time' => Carbon::createFromTimestampUTC($hourData['time_epoch'])->toDateTimeString(),
                    'resolution' => 'hourly',
                    'temperature' => $hourData['temp_c'] ?? null,
                    'temperature_min' => null,
                    'temperature_max' => null,
                    'precipitation' => $hourData['precip_mm'] ?? null,
                ];
            }
        }

        return $records;
    }
}