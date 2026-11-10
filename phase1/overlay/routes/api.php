<?php

use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/worker')->middleware(['throttle:30,1','worker.auth'])->group(function (): void {
    Route::post('/register', [WorkerController::class,'register']);
    Route::post('/heartbeat', [WorkerController::class,'heartbeat']);
});
