<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->timestamp('fetched_at');
            $table->timestamp('forecast_date_time');
            $table->string('resolution'); // 'hourly' or 'daily'
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('temperature_min', 5, 2)->nullable();
            $table->decimal('temperature_max', 5, 2)->nullable();
            $table->decimal('precipitation', 6, 2)->nullable();
            $table->timestamps();

            // Composite index to optimize filtering by location, resolution, and time windows
            $table->index(['location_id', 'resolution', 'forecast_date_time', 'fetched_at']);

            // Prevent duplicate entries for the exact same provider, location, target time, and fetch run
            $table->unique(
                ['provider_id', 'location_id', 'resolution', 'forecast_date_time', 'fetched_at'],
                'forecasts_unique_snapshot'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};