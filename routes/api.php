<?php

use App\Http\Controllers\Auth\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'auth'
], function ($router) {
    Route::post('login', [UserController::class, 'login']);
    Route::post('logout', [UserController::class, 'logout']);
    Route::post('register', [UserController::class, 'register']);
    Route::get('refresh', [UserController::class, 'refresh']);
    Route::get('me', [UserController::class, 'me']);
    Route::post('change-password', [UserController::class, 'changePassword']);
    Route::get('verify-email', [UserController::class, 'verifyEmail']);
    Route::post('/email/verify', [UserController::class, 'verifyEmailAddress']);
    Route::get('google', [UserController::class, 'googleRedirect'])->middleware('web');
    Route::get('google/callback', [UserController::class, 'googleCallback'])->middleware('web');
    // Route::post('forgotPassword', [UserController::class, 'forgotPassword']);
    // Route::get('forgotPassword/{token}', [UserController::class, 'getForgotPassword'])->name('password.reset');
    // Route::post('forgotPassword/{token}', [UserController::class, 'setForgotPassword'])->name('change-password');
});
