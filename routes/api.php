<?php

use App\Http\Controllers\Api\SecurityEventController;
use App\Http\Controllers\Api\SecurityBlocklistController;
use Illuminate\Support\Facades\Route;

Route::middleware('intsec.api')->prefix('security')->group(function (): void {
    Route::post('/events', [SecurityEventController::class, 'store']);
    Route::get('/blocked-ips', SecurityBlocklistController::class);
});
