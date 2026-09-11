<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---------- Autenticación ----------
    Route::prefix('auth')->group(function () {
                    // Rutas públicas
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

              // Rutas protegidas: requieren un JWT válido
        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // ---------- Perfil del usuario autenticado ----------
    Route::middleware('auth:api')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
    });
});