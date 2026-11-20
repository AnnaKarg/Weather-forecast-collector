<?php

namespace App\Services\Weather;

use App\Models\Location;
use Carbon\CarbonInterface;

interface WeatherProviderInterface
{
    /**
     * Return the provider's name as stored in the database.
     */
    public function getProviderName(): string;

    /**
     * Fetch and normalize hourly and daily forecasts for a given location.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchForecasts(Location $location, CarbonInterface $fetchedAt): array;
}