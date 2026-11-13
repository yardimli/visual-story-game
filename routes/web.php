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
    Route::put('/stories/{story}/audio/categories',[\App\Http\Controllers\AudioController::class,'saveCategories']);
    Route::put('/stories/{story}/audio/sounds/{sound}',[\App\Http\Controllers\AudioController::class,'categorize']);
    Route::post('/stories/{story}/audio/jobs/{job}/start',[\App\Http\Controllers\AudioManageController::class,'start']);
    Route::put('/stories/{story}/audio/jobs/{job}',[\App\Http\Controllers\AudioManageController::class,'update']);
    Route::delete('/stories/{story}/audio/jobs/{job}',[\App\Http\Controllers\AudioManageController::class,'destroyJob']);
    Route::get('/stories/{story}/audio/sounds/{sound}/usage',[\App\Http\Controllers\AudioManageController::class,'usage']);
    Route::delete('/stories/{story}/audio/sounds/{sound}',[\App\Http\Controllers\AudioManageController::class,'destroySound']);
    Route::get('/stories/{story}/audio',[\App\Http\Controllers\AudioController::class,'index'])->name('audio.index');
    Route::put('/stories/{story}/audio/settings',[\App\Http\Controllers\AudioController::class,'settings'])->name('audio.settings');
    Route::get('/stories/{story}/audio/voices',[\App\Http\Controllers\AudioController::class,'voices']);
    Route::post('/stories/{story}/audio/speech',[\App\Http\Controllers\AudioController::class,'speech'])->middleware('throttle:6,1,audio-speech:');
    Route::get('/stories/{story}/audio/search',[\App\Http\Controllers\AudioController::class,'search'])->middleware('throttle:60,1,audio-search:')->name('audio.search');
    Route::post('/stories/{story}/audio/import',[\App\Http\Controllers\AudioController::class,'add'])->name('audio.add');
    Route::post('/stories/{story}/audio/generate',[\App\Http\Controllers\AudioController::class,'generate'])->middleware('throttle:6,1,audio-generate:')->name('audio.generate');
    Route::get('/audio/{sound}',[\App\Http\Controllers\AudioController::class,'file'])->name('audio.file');

    Route::get('/stories/{story}/flags',[\App\Http\Controllers\StoryStateController::class,'show'])->name('flags.show');
    Route::put('/stories/{story}/flags',[\App\Http\Controllers\StoryStateController::class,'update'])->name('flags.update');
    Route::get('/stories/{story}/pages/{page}/extractions',[\App\Http\Controllers\SceneExtractionController::class,'index'])->name('extractions.index');
    Route::post('/stories/{story}/pages/{page}/extractions',[\App\Http\Controllers\SceneExtractionController::class,'store'])->name('extractions.store');
    Route::post('/stories/{story}/pages/{page}/extractions/{extraction}/outputs/{output}',[\App\Http\Controllers\SceneExtractionController::class,'output'])->name('extractions.output');
    Route::post('/stories/{story}/pages/{page}/extractions/{extraction}/activate',[\App\Http\Controllers\SceneExtractionController::class,'activate'])->name('extractions.activate');
    Route::delete('/stories/{story}/image-drafts/{image}',[\App\Http\Controllers\SceneController::class,'deleteDraft'])->name('images.draft.delete');
    Route::put('/stories/{story}/image-order',[\App\Http\Controllers\StoryStateController::class,'order'])->name('images.order');
    Route::get('/stories/{story}/inventory',[\App\Http\Controllers\InventoryController::class,'show'])->name('inventory.show');
    Route::put('/stories/{story}/inventory',[\App\Http\Controllers\InventoryController::class,'update'])->name('inventory.update');
    Route::get('/stories/{story}/pages/{page}/image-studio',[\App\Http\Controllers\SceneController::class,'studio'])->name('images.studio');
    Route::get('/stories/{story}/pages/{page}/visuals',[\App\Http\Controllers\SceneController::class,'show'])->name('visuals.show');
    Route::put('/stories/{story}/pages/{page}/visuals',[\App\Http\Controllers\SceneController::class,'update'])->name('visuals.update');
    Route::patch('/stories/{story}/pages/{page}/images/{image}',[\App\Http\Controllers\SceneController::class,'moveImage'])->name('images.move');
    Route::post('/stories/{story}/pages/{page}/images/{image}/retry',[\App\Http\Controllers\SceneController::class,'retry'])->middleware('throttle:6,1,image-generation:')->name('images.retry');
    Route::post('/stories/{story}/pages/{page}/image-drafts',[\App\Http\Controllers\SceneController::class,'draft'])->name('images.draft');
    Route::post('/stories/{story}/pages/{page}/images',[\App\Http\Controllers\SceneController::class,'generate'])->middleware('throttle:6,1,image-generation:')->name('images.generate');
    Route::post('/stories/{story}/messages/{message}/changes',[\App\Http\Controllers\CompanionChangesController::class,'store'])->name('companion.changes');
    Route::delete('/stories/{story}/messages/{message}',[ChatController::class,'destroy'])->name('chat.destroy');
    Route::delete('/companion/messages/{message}',[ChatController::class,'destroyCompanion'])->name('companion.destroy');
    Route::get('/companion', [ChatController::class,'history'])->name('companion.history');
    Route::post('/companion', [ChatController::class,'companion'])->middleware('throttle:10,1,ai-chat:')->name('companion.store');
    Route::post('/stories',[StudioController::class,'store'])->name('stories.store');
    Route::get('/stories/{story}/edit',[StudioController::class,'edit'])->name('stories.edit');
    Route::put('/stories/{story}',[StudioController::class,'update'])->name('stories.update');
    Route::put('/stories/{story}/pages/{page}',[StudioController::class,'page'])->name('pages.update');
    Route::post('/stories/{story}/pages/manage',[StudioController::class,'structure'])->name('pages.manage');
    Route::post('/stories/{story}/apply/{message}',[StudioController::class,'apply'])->name('stories.apply');
    Route::post('/stories/{story}/restore/{revision}',[StudioController::class,'restore'])->name('stories.restore');
    Route::post('/stories/{story}/image-name',[\App\Http\Controllers\ImageNameController::class,'store'])->middleware('throttle:10,1,ai-image-name:')->name('images.name');
    Route::post('/stories/{story}/assets',[AssetController::class,'store'])->name('assets.store');
    Route::patch('/assets/{asset}',[AssetController::class,'update'])->name('assets.update');
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
