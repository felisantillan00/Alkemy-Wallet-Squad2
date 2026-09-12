<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\DepositController;


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
        Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
    });

    // ---------- Cuenta del usuario autenticado ----------
    Route::middleware('auth:api')->group(function(){
        Route::get('/account', [AccountController::class, 'show']);
    });

    // ---------- Depósitos ----------
    Route::middleware('auth:api')->group(function () {
        Route::post('/deposits', [DepositController::class, 'store']);
    });
});