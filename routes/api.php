<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('video/worker')->middleware(\App\Http\Middleware\VideoToken::class.':worker')->group(function () {
    Route::post('claim', [\App\Http\Controllers\VideoWorkerController::class, 'claim']);
    Route::get('{video}/frames/{frame}', [\App\Http\Controllers\VideoWorkerController::class, 'frame']);
    Route::post('{video}/heartbeat', [\App\Http\Controllers\VideoWorkerController::class, 'heartbeat']);
    Route::post('{video}/complete', [\App\Http\Controllers\VideoWorkerController::class, 'complete']);
});
Route::prefix('video/relay')->middleware(\App\Http\Middleware\VideoToken::class.':relay')->group(function () {
    Route::post('/', [\App\Http\Controllers\VideoRelayController::class, 'store']);
    Route::get('{video}', [\App\Http\Controllers\VideoRelayController::class, 'show']);
    Route::get('{video}/file', [\App\Http\Controllers\VideoRelayController::class, 'file']);
});
