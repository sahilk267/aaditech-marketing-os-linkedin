<?php

use App\Http\Controllers\WorkerController;
use App\Http\Controllers\WorkerJobsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/worker')->middleware(['throttle:30,1','worker.auth'])->group(function (): void {
    Route::post('/register', [WorkerController::class,'register']);
    Route::post('/heartbeat', [WorkerController::class,'heartbeat']);
    Route::post('/jobs/claim',[WorkerJobsController::class,'claim']);
    Route::post('/jobs/{id}/start',[WorkerJobsController::class,'start'])->whereNumber('id');
    Route::post('/jobs/{id}/renew',[WorkerJobsController::class,'renew'])->whereNumber('id');
    Route::post('/jobs/{id}/complete',[WorkerJobsController::class,'complete'])->whereNumber('id');
    Route::post('/jobs/{id}/fail',[WorkerJobsController::class,'fail'])->whereNumber('id');
    Route::get('/jobs/{id}/context',[WorkerJobsController::class,'context'])->whereNumber('id');
    Route::post('/jobs/{id}/publish',[WorkerJobsController::class,'executePublication'])->whereNumber('id');
    Route::get('/config',[WorkerJobsController::class,'config']);
    Route::get('/ai/models',[WorkerJobsController::class,'models']);
    Route::post('/ai/usage',[WorkerJobsController::class,'usage']);
    Route::post('/ai/catalog',[WorkerJobsController::class,'syncCatalog']);
});

Route::post('v1/worker/tick', [\App\Http\Controllers\WorkerTickController::class, 'tick'])->middleware(['throttle:6,1','worker.auth']);
