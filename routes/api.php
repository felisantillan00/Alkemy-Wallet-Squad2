<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---------- Autenticación ----------
    Route::prefix('auth')->group(function () {
        // Rutas públicas
        Route::post('/register', [AuthController::class, 'register']);
    });
});