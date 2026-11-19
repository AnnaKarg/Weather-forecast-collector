<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $fillable = [
        'name',
    ];

    public function forecasts(): HasMany
    {
        return $this->hasMany(Forecast::class);
    }
}