<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Forecast;
use App\Models\Location;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'provider' => ['nullable', 'string', 'max:100'],
            'resolution' => ['nullable', 'in:hourly,daily'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'latest_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Forecast::with([
            'location:id,name,latitude,longitude',
            'provider:id,name',
        ]);

        if (! empty($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        if (! empty($validated['provider'])) {
            $query->whereHas('provider', function ($q) use ($validated) {
                $q->where('name', 'ilike', $validated['provider']);
            });
        }

        if (! empty($validated['resolution'])) {
            $query->where('resolution', $validated['resolution']);
        }

        if (! empty($validated['date'])) {
            $query->whereDate('forecast_date_time', $validated['date']);
        }

        if (! empty($validated['latest_only'])) {
            $latestFetchedAt = Forecast::max('fetched_at');
            if ($latestFetchedAt) {
                $query->where('fetched_at', $latestFetchedAt);
            }
        }

        $perPage = $validated['per_page'] ?? 50;

        $forecasts = $query
            ->orderBy('forecast_date_time')
            ->orderBy('provider_id')
            ->paginate($perPage);

        return \App\Http\Resources\ForecastResource::collection($forecasts)->response();
    }

    public function metadata(): JsonResponse
    {
        return response()->json([
            'locations' => Location::select('id', 'name', 'latitude', 'longitude')->get(),
            'providers' => Provider::select('id', 'name')->get(),
        ]);
    }

}