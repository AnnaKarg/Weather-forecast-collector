<?php

namespace App\Services\Weather;

use App\Models\Location;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenMeteoService implements WeatherProviderInterface
{
    public function getProviderName(): string
    {
        return 'Open-Meteo';
    }

    public function fetchForecasts(Location $location, CarbonInterface $fetchedAt): array
    {
        $baseUrl = config('services.open_meteo.base_url');

        $response = Http::timeout(15)
            ->retry(2, 500)
            ->get("{$baseUrl}/forecast", [
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'hourly' => 'temperature_2m,precipitation',
                'daily' => 'temperature_2m_min,temperature_2m_max,precipitation_sum',
                'timezone' => 'UTC',
                'forecast_days' => 7,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Open-Meteo API request failed with status {$response->status()}.");
        }

        $data = $response->json();
        $records = [];

        // Normalize hourly forecasts
        $hourlyTimes = $data['hourly']['time'] ?? [];
        foreach ($hourlyTimes as $index => $time) {
            $records[] = [
                'location_id' => $location->id,
                'fetched_at' => $fetchedAt->toDateTimeString(),
                'forecast_date_time' => Carbon::parse($time, 'UTC')->toDateTimeString(),
                'resolution' => 'hourly',
                'temperature' => $data['hourly']['temperature_2m'][$index] ?? null,
                'temperature_min' => null,
                'temperature_max' => null,
                'precipitation' => $data['hourly']['precipitation'][$index] ?? null,
            ];
        }

        // Normalize daily forecasts
        $dailyTimes = $data['daily']['time'] ?? [];
        foreach ($dailyTimes as $index => $date) {
            $records[] = [
                'location_id' => $location->id,
                'fetched_at' => $fetchedAt->toDateTimeString(),
                'forecast_date_time' => Carbon::parse($date, 'UTC')->startOfDay()->toDateTimeString(),
                'resolution' => 'daily',
                'temperature' => null,
                'temperature_min' => $data['daily']['temperature_2m_min'][$index] ?? null,
                'temperature_max' => $data['daily']['temperature_2m_max'][$index] ?? null,
                'precipitation' => $data['daily']['precipitation_sum'][$index] ?? null,
            ];
        }

        return $records;
    }
}