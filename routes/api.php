<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/**
 * Mobile API v1 — Sanctum 토큰 인증
 * 자동 prefix: /api
 */

Route::prefix('v1')->group(function () {
    // 공개 (비인증)
    Route::prefix('auth')->group(function () {
        Route::post('phone/send',    [AuthController::class, 'sendPhoneCode']);
        Route::post('phone/verify',  [AuthController::class, 'verifyPhoneCode']);
        Route::post('register',      [AuthController::class, 'register']);
        Route::post('login',         [AuthController::class, 'login']);
    });

    // 인증 필요
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me',           [AuthController::class, 'me']);

        // 도서
        Route::get('books',             [BookController::class, 'index']);
        Route::get('books/isbn/{isbn}', [BookController::class, 'showByIsbn']);

        // 주문
        Route::get('orders',                  [OrderController::class, 'index']);
        Route::post('orders',                 [OrderController::class, 'store']);
        Route::get('orders/{order}',          [OrderController::class, 'show']);
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm']);
        Route::post('orders/{order}/accept',  [OrderController::class, 'accept']);
    });
});
