<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForecastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'location' => $this->location->name,
            'provider' => $this->provider->name,
            'resolution' => $this->resolution,
            'forecast_date_time' => $this->forecast_date_time?->toIso8601String(),
            'temperature_c' => $this->temperature !== null ? (float) $this->temperature : null,
            'temperature_min_c' => $this->temperature_min !== null ? (float) $this->temperature_min : null,
            'temperature_max_c' => $this->temperature_max !== null ? (float) $this->temperature_max : null,
            'precipitation_mm' => $this->precipitation !== null ? (float) $this->precipitation : null,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}