<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::prefix('api')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/session', [AuthController::class, 'session']);

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::apiResource('/buku', BookController::class)
            ->except(['show'])
            ->parameters(['buku' => 'book']);
        Route::apiResource('/anggota', MemberController::class)
            ->except(['show'])
            ->parameters(['anggota' => 'member']);
        Route::get('/transaksi', [LoanController::class, 'index']);
        Route::post('/transaksi', [LoanController::class, 'store']);
        Route::patch('/transaksi/{loan}/kembali', [LoanController::class, 'returnBook']);
        Route::delete('/transaksi/{loan}', [LoanController::class, 'destroy']);
    });
});
