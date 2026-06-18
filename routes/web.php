<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Pages\Auth\Login;
use App\Http\Controllers\AdminController;
use App\Support\SupabaseStorage;

Route::get('/', Login::class)->name('login');

Route::get('/storage/{path}', function (string $path) {
    $url = SupabaseStorage::legacyStorageUrl($path);

    abort_if(!$url, 404);

    return redirect()->away($url);
})->where('path', '.*');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'login'])->name('login');
    Route::post('/login', [AdminController::class, 'authenticate'])->name('authenticate');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('users.status');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');

    Route::get('/genres', [AdminController::class, 'genres'])->name('genres.index');
    Route::post('/genres', [AdminController::class, 'storeGenre'])->name('genres.store');
    Route::put('/genres/{genre}', [AdminController::class, 'updateGenre'])->name('genres.update');
    Route::delete('/genres/{genre}', [AdminController::class, 'destroyGenre'])->name('genres.destroy');

    Route::get('/tracks', [AdminController::class, 'tracks'])->name('tracks.index');
    Route::get('/tracks/create', [AdminController::class, 'createTrack'])->name('tracks.create');
    Route::post('/tracks', [AdminController::class, 'storeTrack'])->name('tracks.store');
    Route::get('/tracks/{track}/edit', [AdminController::class, 'editTrack'])->name('tracks.edit');
    Route::put('/tracks/{track}', [AdminController::class, 'updateTrack'])->name('tracks.update');
    Route::patch('/tracks/{track}/status', [AdminController::class, 'updateTrackStatus'])->name('tracks.status');
    Route::delete('/tracks/{track}', [AdminController::class, 'destroyTrack'])->name('tracks.destroy');

    Route::get('/albums', [AdminController::class, 'albums'])->name('albums.index');
    Route::get('/albums/create', [AdminController::class, 'createAlbum'])->name('albums.create');
    Route::post('/albums', [AdminController::class, 'storeAlbum'])->name('albums.store');
    Route::get('/albums/{album}/edit', [AdminController::class, 'editAlbum'])->name('albums.edit');
    Route::put('/albums/{album}', [AdminController::class, 'updateAlbum'])->name('albums.update');
    Route::patch('/albums/{album}/status', [AdminController::class, 'updateAlbumStatus'])->name('albums.status');
    Route::delete('/albums/{album}', [AdminController::class, 'destroyAlbum'])->name('albums.destroy');
});
