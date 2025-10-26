<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\AccountController;

Route::prefix('v1')->group(function () {
    // Operações bancárias
    Route::post('/deposit', [TransactionController::class, 'deposit']);
    Route::post('/withdraw', [TransactionController::class, 'withdraw']);
    Route::get('/balance', [BalanceController::class, 'show']);
    Route::get('/accounts', [AccountController::class, 'index']);
});