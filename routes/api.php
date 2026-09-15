<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\DepositController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\MovementController;
use App\Http\Controllers\Api\V1\SavedAccountController;
use App\Http\Controllers\Api\V1\FixedTermInvestmentController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AccountController as AdminAccountController;


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

    // ---------- Transferencias ----------
    Route::middleware('auth:api')->group(function () {
        Route::post('/transfers', [TransferController::class, 'store']);
    });

    // ---------- Movimientos ----------
    Route::middleware('auth:api')->group(function () {
        Route::get('/movements', [MovementController::class, 'index']);
    });

    // ---------- CBUs de terceros guardados ----------
    Route::middleware('auth:api')->group(function () {
        Route::post('/cbu/{cbu}/users/{idUser}', [SavedAccountController::class, 'store']);
        Route::get('/cbu/users/{idUser}', [SavedAccountController::class, 'index']);
        Route::delete('/cbu/{cbu}/users/{idUser}', [SavedAccountController::class, 'destroy']);
    });

    // ---------- Administración (solo rol admin) ----------
    Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::apiResource('accounts', AdminAccountController::class)->except(['create', 'edit']);
    });

    // ---------- Inversiones ----------
    Route::middleware('auth:api')->group(function () {
        Route::post('/investments/fixed-term/simulate', [FixedTermInvestmentController::class, 'simulate']);
    });
});
