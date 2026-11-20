<?php

use App\Http\Controllers\Api\ForecastController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/forecasts', [ForecastController::class, 'index']);
    Route::get('/metadata', [ForecastController::class, 'metadata']);
});