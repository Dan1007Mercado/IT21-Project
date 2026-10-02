<?php

use App\Http\Controllers\Api\SecurityEventController;
use App\Http\Controllers\Api\SecurityBlocklistController;
use App\Http\Controllers\Api\RequestActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['intsec.api', 'throttle:120,1'])->prefix('security')->group(function (): void {
    Route::post('/events', [SecurityEventController::class, 'store']);
    Route::post('/request-activities', [RequestActivityController::class, 'store']);
    Route::get('/blocked-ips', SecurityBlocklistController::class);
});
