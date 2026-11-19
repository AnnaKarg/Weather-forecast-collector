<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Provider;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Location::firstOrCreate(
            ['name' => 'Thessaloniki'],
            [
                'latitude' => 40.6401000,
                'longitude' => 22.9444000,
            ]
        );

        Provider::firstOrCreate(['name' => 'Open-Meteo']);
        Provider::firstOrCreate(['name' => 'WeatherAPI']);
    }
}