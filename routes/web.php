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

Route::view('/scenes/north-of-the-north', 'scenes.north-of-the-north')->name('scenes.north');

Route::get('/game-ui/{theme}/{asset}', [\App\Http\Controllers\GuiThemeController::class, 'asset'])->where('theme', '[a-z0-9_-]+')->where('asset', '[a-f0-9]{40}')->name('game.ui.asset');

Route::get('/dashboard', [StudioController::class,'index'])->middleware('auth')->name('dashboard');

Route::get('/p/{token}', [\App\Http\Controllers\PlayController::class, 'publicShow'])->where('token', '[A-Za-z0-9_-]{22}')->name('public.play');
Route::get('/p/{token}/{kind}/{id}', [\App\Http\Controllers\PublicPlayController::class, 'media'])->where('token', '[A-Za-z0-9_-]{22}')->whereIn('kind', ['image', 'audio', 'video'])->name('public.play.media');

Route::middleware('auth')->group(function () {
    Route::post('/stories/{story}/public-play', [\App\Http\Controllers\PublicPlayController::class, 'enable'])->name('public.play.enable');
    Route::delete('/stories/{story}/public-play', [\App\Http\Controllers\PublicPlayController::class, 'disable']);
    Route::get('/stories/{story}/characters', [\App\Http\Controllers\CharacterController::class, 'index'])->name('characters.index');
    Route::post('/stories/{story}/characters', [\App\Http\Controllers\CharacterController::class, 'save']);
    Route::put('/stories/{story}/characters/{character}', [\App\Http\Controllers\CharacterController::class, 'save']);
    Route::delete('/stories/{story}/characters/{character}', [\App\Http\Controllers\CharacterController::class, 'destroy']);
    Route::post('/stories/{story}/characters/{character}/draft', [\App\Http\Controllers\CharacterController::class, 'draft']);

    Route::get('/stories/{story}/videos', [\App\Http\Controllers\VideoController::class, 'index'])->name('videos.index');
    Route::get('/stories/{story}/videos/status', [\App\Http\Controllers\VideoController::class, 'statuses'])->name('videos.status');
    Route::post('/stories/{story}/videos', [\App\Http\Controllers\VideoController::class, 'store']);
    Route::patch('/stories/{story}/videos/{video}', [\App\Http\Controllers\VideoController::class, 'update']);
    Route::delete('/stories/{story}/videos/{video}', [\App\Http\Controllers\VideoController::class, 'destroy']);
    Route::post('/stories/{story}/videos/{video}/queue', [\App\Http\Controllers\VideoController::class, 'queue']);
    Route::get('/stories/{story}/videos/{video}/frame/{frame}', [\App\Http\Controllers\VideoController::class, 'frame'])->whereIn('frame', ['first','last'])->name('videos.frame');
    Route::get('/stories/{story}/videos/{video}/file', [\App\Http\Controllers\VideoController::class, 'file'])->name('videos.file');

    Route::get('/stories/{story}/play',[\App\Http\Controllers\PlayController::class,'show'])->name('stories.play');
    Route::put('/stories/{story}/audio/categories',[\App\Http\Controllers\AudioController::class,'saveCategories']);
    Route::put('/stories/{story}/audio/sounds/{sound}',[\App\Http\Controllers\AudioController::class,'categorize']);
    Route::post('/stories/{story}/audio/jobs/{job}/start',[\App\Http\Controllers\AudioManageController::class,'start']);
    Route::put('/stories/{story}/audio/jobs/{job}',[\App\Http\Controllers\AudioManageController::class,'update']);
    Route::delete('/stories/{story}/audio/jobs/{job}',[\App\Http\Controllers\AudioManageController::class,'destroyJob']);
    Route::get('/stories/{story}/audio/sounds/{sound}/usage',[\App\Http\Controllers\AudioManageController::class,'usage']);
    Route::delete('/stories/{story}/audio/sounds/{sound}',[\App\Http\Controllers\AudioManageController::class,'destroySound']);
    Route::post('/stories/{story}/pages/{page}/containers/prompt',[\App\Http\Controllers\ContainerPromptController::class,'store'])->name('containers.prompt');
    Route::get('/stories/{story}/pages/{page}/containers',[\App\Http\Controllers\ContainerController::class,'index'])->name('containers.index');
    Route::post('/stories/{story}/pages/{page}/containers',[\App\Http\Controllers\ContainerController::class,'store']);
    Route::delete('/stories/{story}/pages/{page}/containers',[\App\Http\Controllers\ContainerController::class,'destroy'])->name('containers.delete');
    Route::patch('/stories/{story}/pages/{page}/containers/{plan}',[\App\Http\Controllers\ContainerController::class,'rename'])->name('containers.rename');
    Route::patch('/stories/{story}/pages/{page}/extractions/{extraction}',[\App\Http\Controllers\SceneExtractionController::class,'rename'])->name('extractions.rename');
    Route::post('/stories/{story}/pages/{page}/containers/{plan}/prepare',[\App\Http\Controllers\ContainerController::class,'prepare'])->name('containers.prepare');
    Route::post('/stories/{story}/pages/{page}/containers/{plan}/activate',[\App\Http\Controllers\ContainerController::class,'activate'])->name('containers.activate');
    Route::get('/stories/{story}/audio',[\App\Http\Controllers\AudioController::class,'index'])->name('audio.index');
    Route::put('/stories/{story}/audio/settings',[\App\Http\Controllers\AudioController::class,'settings'])->name('audio.settings');
    Route::get('/stories/{story}/audio/voices',[\App\Http\Controllers\AudioController::class,'voices']);
    Route::post('/stories/{story}/audio/speech',[\App\Http\Controllers\AudioController::class,'speech'])->middleware('throttle:6,1,audio-speech:');
    Route::get('/stories/{story}/audio/search',[\App\Http\Controllers\AudioController::class,'search'])->middleware('throttle:60,1,audio-search:')->name('audio.search');
    Route::post('/stories/{story}/audio/import',[\App\Http\Controllers\AudioController::class,'add'])->name('audio.add');
    Route::post('/stories/{story}/audio/upload',[\App\Http\Controllers\AudioController::class,'upload'])->name('audio.upload');
    Route::post('/stories/{story}/audio/generate',[\App\Http\Controllers\AudioController::class,'generate'])->middleware('throttle:6,1,audio-generate:')->name('audio.generate');
    Route::get('/audio/{sound}',[\App\Http\Controllers\AudioController::class,'file'])->name('audio.file');

    Route::get('/stories/{story}/flags',[\App\Http\Controllers\StoryStateController::class,'show'])->name('flags.show');
    Route::put('/stories/{story}/flags/{flag}',[\App\Http\Controllers\StoryStateController::class,'rename'])->name('flags.rename');
    Route::get('/stories/{story}/flags/{flag}/uses',[\App\Http\Controllers\StoryStateController::class,'uses'])->name('flags.uses');
    Route::delete('/stories/{story}/flags/{flag}',[\App\Http\Controllers\StoryStateController::class,'destroy'])->name('flags.delete');
    Route::put('/stories/{story}/flags',[\App\Http\Controllers\StoryStateController::class,'update'])->name('flags.update');
    Route::get('/stories/{story}/pages/{page}/extractions',[\App\Http\Controllers\SceneExtractionController::class,'index'])->name('extractions.index');
    Route::post('/stories/{story}/pages/{page}/extractions',[\App\Http\Controllers\SceneExtractionController::class,'store'])->name('extractions.store');
    Route::post('/stories/{story}/pages/{page}/extractions/{extraction}/outputs/{output}',[\App\Http\Controllers\SceneExtractionController::class,'output'])->name('extractions.output');
    Route::delete('/stories/{story}/pages/{page}/extractions/{extraction}',[\App\Http\Controllers\SceneExtractionController::class,'destroy'])->name('extractions.delete');
    Route::post('/stories/{story}/pages/{page}/extractions/{extraction}/activate',[\App\Http\Controllers\SceneExtractionController::class,'activate'])->name('extractions.activate');
    Route::delete('/stories/{story}/image-drafts/{image}',[\App\Http\Controllers\SceneController::class,'deleteDraft'])->name('images.draft.delete');
    Route::post('/stories/{story}/state-images', [\App\Http\Controllers\SceneStateImageController::class, 'store'])->name('images.states');
    Route::put('/stories/{story}/image-categories', [\App\Http\Controllers\ImageCategoryController::class, 'save'])->name('images.categories');
    Route::put('/stories/{story}/image-categories/{job}', [\App\Http\Controllers\ImageCategoryController::class, 'assign']);
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
    Route::get('/stories/{story}/chat', [ChatController::class, 'storyHistory'])->name('chat.history');
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
