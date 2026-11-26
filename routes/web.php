<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AiJournalController;
use App\Http\Controllers\AiReflectionController;
use App\Http\Controllers\ArtworkController;
use App\Http\Controllers\HandFilterController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
})->name('landing');

Route::post('/locale', [SettingsController::class, 'updateLocale'])->name('settings.locale');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Journal
    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::get('/journal/create', [JournalController::class, 'create'])->name('journal.create');
    Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
    Route::get('/journal/{journal}/edit', [JournalController::class, 'edit'])->name('journal.edit');
    Route::put('/journal/{journal}', [JournalController::class, 'update'])->name('journal.update');
    Route::delete('/journal/{journal}', [JournalController::class, 'destroy'])->name('journal.destroy');

    // Targets
    Route::get('/targets', [TargetController::class, 'index'])->name('targets.index');
    Route::post('/targets', [TargetController::class, 'store'])->name('targets.store');
    Route::patch('/targets/{target}/toggle', [TargetController::class, 'toggle'])->name('targets.toggle');
    Route::delete('/targets/{target}', [TargetController::class, 'destroy'])->name('targets.destroy');

    // Recap
    Route::get('/recap', [RecapController::class, 'index'])->name('recap.index');
    Route::post('/recap', [RecapController::class, 'store'])->name('recap.store');
    Route::post('/recap/ai/generate', [AiReflectionController::class, 'generate'])->middleware('throttle:ai')->name('recap.ai.generate');
    Route::post('/recap/ai/{generation}/save', [AiReflectionController::class, 'save'])->name('recap.ai.save');

    // History
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('/history/{diary}/recap', [RecapController::class, 'show'])->name('history.recap');

    // Games
    Route::get('/games', [GameController::class, 'index'])->name('games.index');
    Route::get('/games/bubble-pop', [GameController::class, 'bubblePop'])->name('games.bubblePop');
    Route::post('/games/score', [GameController::class, 'saveScore'])->name('games.saveScore');

    // WeDi AI
    Route::get('/ai', [AiChatController::class, 'index'])->name('ai.index');
    Route::post('/ai', [AiChatController::class, 'store'])->name('ai.store');
    Route::delete('/ai', [AiChatController::class, 'destroyAll'])->name('ai.destroyAll');
    Route::post('/ai/journal-assist', [AiJournalController::class, 'assist'])->middleware('throttle:ai')->name('ai.journal');
    Route::get('/ai/{conversation}', [AiChatController::class, 'show'])->name('ai.show');
    Route::post('/ai/{conversation}/messages', [AiChatController::class, 'message'])->middleware('throttle:ai')->name('ai.message');
    Route::delete('/ai/{conversation}', [AiChatController::class, 'destroy'])->name('ai.destroy');

    // Hand Gesture Drawing
    Route::get('/draw', [ArtworkController::class, 'index'])->name('draw.index');
    Route::get('/draw/gallery', [ArtworkController::class, 'gallery'])->name('draw.gallery');
    Route::post('/draw/artworks', [ArtworkController::class, 'store'])->name('draw.store');
    Route::put('/draw/artworks/{artwork}', [ArtworkController::class, 'update'])->name('draw.update');
    Route::get('/draw/artworks/{artwork}/image', [ArtworkController::class, 'image'])->name('draw.image');
    Route::delete('/draw/artworks/{artwork}', [ArtworkController::class, 'destroy'])->name('draw.destroy');

    // Hand Filter Studio
    Route::get('/filter', [HandFilterController::class, 'index'])->name('filter.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance');
    Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
});
