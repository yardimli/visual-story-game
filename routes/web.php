<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\{StudioController,AiSettingsController,ChatController,AssetController};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [StudioController::class,'index'])->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/stories/{story}/pages/{page}/image-studio',[\App\Http\Controllers\SceneController::class,'studio'])->name('images.studio');
    Route::get('/stories/{story}/pages/{page}/visuals',[\App\Http\Controllers\SceneController::class,'show'])->name('visuals.show');
    Route::put('/stories/{story}/pages/{page}/visuals',[\App\Http\Controllers\SceneController::class,'update'])->name('visuals.update');
    Route::patch('/stories/{story}/pages/{page}/images/{image}',[\App\Http\Controllers\SceneController::class,'moveImage'])->name('images.move');
    Route::post('/stories/{story}/pages/{page}/images',[\App\Http\Controllers\SceneController::class,'generate'])->middleware('throttle:6,1,image-generation:')->name('images.generate');
    Route::post('/stories/{story}/messages/{message}/changes',[\App\Http\Controllers\CompanionChangesController::class,'store'])->name('companion.changes');
    Route::get('/companion', [ChatController::class,'history'])->name('companion.history');
    Route::post('/companion', [ChatController::class,'companion'])->middleware('throttle:10,1,ai-chat:')->name('companion.store');
    Route::post('/stories',[StudioController::class,'store'])->name('stories.store');
    Route::get('/stories/{story}/edit',[StudioController::class,'edit'])->name('stories.edit');
    Route::put('/stories/{story}',[StudioController::class,'update'])->name('stories.update');
    Route::put('/stories/{story}/pages/{page}',[StudioController::class,'page'])->name('pages.update');
    Route::post('/stories/{story}/pages/manage',[StudioController::class,'structure'])->name('pages.manage');
    Route::post('/stories/{story}/apply/{message}',[StudioController::class,'apply'])->name('stories.apply');
    Route::post('/stories/{story}/restore/{revision}',[StudioController::class,'restore'])->name('stories.restore');
    Route::post('/stories/{story}/assets',[AssetController::class,'store'])->name('assets.store');
    Route::get('/assets/{asset}',[AssetController::class,'show'])->name('assets.show');
    Route::post('/stories/{story}/chat',[ChatController::class,'store'])->middleware('throttle:10,1,ai-chat:')->name('chat.store');
    Route::get('/ai/image-endpoints',[ChatController::class,'imageEndpoints'])->middleware('throttle:120,1,image-catalog:')->name('ai.image-endpoints');
    Route::get('/ai/models',[ChatController::class,'models'])->middleware('throttle:30,1,model-catalog:')->name('ai.models');
    Route::get('/ai/settings',[AiSettingsController::class,'edit'])->name('ai.edit');
    Route::get('/ai/calls/{call}',[AiSettingsController::class,'call'])->whereNumber('call')->name('ai.calls.show');
    Route::put('/ai/settings',[AiSettingsController::class,'update'])->middleware('throttle:6,1,ai-settings:')->name('ai.update');
    Route::delete('/ai/settings',[AiSettingsController::class,'destroy'])->middleware('throttle:6,1,ai-settings:')->name('ai.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
