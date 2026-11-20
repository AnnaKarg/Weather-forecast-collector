<?php

namespace App\Console\Commands;

use App\Models\Forecast;
use App\Models\Location;
use App\Models\Provider;
use App\Services\Weather\OpenMeteoService;
use App\Services\Weather\WeatherApiService;
use App\Services\Weather\WeatherProviderInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchForecastsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'forecasts:fetch';

    /**
     * The console command description.
     */
    protected $description = 'Fetch weather forecasts from all configured providers for all locations';

    public function handle(
        OpenMeteoService $openMeteoService,
        WeatherApiService $weatherApiService
    ): int {
        /** @var WeatherProviderInterface[] $services */
        $services = [
            $openMeteoService,
            $weatherApiService,
        ];

        $locations = Location::all();

        if ($locations->isEmpty()) {
            $this->warn('No locations found in the database. Run seeders first.');
            return self::SUCCESS;
        }

        $fetchedAt = now('UTC');
        $nowString = $fetchedAt->toDateTimeString();
        $totalInserted = 0;

        $this->info("Starting forecast collection at {$nowString} UTC...");

        foreach ($services as $service) {
            $providerName = $service->getProviderName();
            $provider = Provider::where('name', $providerName)->first();

            if (! $provider) {
                $this->error("Provider '{$providerName}' not found in database.");
                continue;
            }

            foreach ($locations as $location) {
                try {
                    $records = $service->fetchForecasts($location, $fetchedAt);

                    if (empty($records)) {
                        $this->warn("No records returned from {$providerName} for {$location->name}.");
                        continue;
                    }

                    // Attach provider_id and timestamps for bulk insert
                    $rows = array_map(function (array $record) use ($provider, $nowString) {
                        return array_merge($record, [
                            'provider_id' => $provider->id,
                            'created_at' => $nowString,
                            'updated_at' => $nowString,
                        ]);
                    }, $records);

                    Forecast::insert($rows);

                    $count = count($rows);
                    $totalInserted += $count;
                    $this->line("✓ {$providerName} -> {$location->name}: {$count} records saved.");
                } catch (Throwable $e) {
                    $this->error("✗ {$providerName} -> {$location->name}: {$e->getMessage()}");
                    Log::error('Weather fetch failed', [
                        'provider' => $providerName,
                        'location' => $location->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Completed! Total forecast records inserted: {$totalInserted}");

        return self::SUCCESS;
    }
}