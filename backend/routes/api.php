<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ReminderController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('categories', CategoryController::class);

    // Custom reminder routes
    Route::get('/reminders/calendario/{anio}/{mes}', [ReminderController::class, 'calendario']);
    Route::get('/reminders/proximos', [ReminderController::class, 'proximos']);
    Route::get('/reminders/hoy', [ReminderController::class, 'hoy']);
    Route::get('/reminders/vencidos', [ReminderController::class, 'vencidos']);
    Route::patch('/reminders/{reminder}/estado', [ReminderController::class, 'updateEstado']);

    Route::apiResource('reminders', ReminderController::class);
});
