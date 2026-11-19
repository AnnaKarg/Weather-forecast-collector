<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Forecast extends Model
{
    protected $fillable = [
        'provider_id',
        'location_id',
        'fetched_at',
        'forecast_date_time',
        'resolution',
        'temperature',
        'temperature_min',
        'temperature_max',
        'precipitation',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
        'forecast_date_time' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}