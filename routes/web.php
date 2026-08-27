<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::view('/', 'spa')->name('home');
Route::view('login', 'spa')->name('login');
Route::view('dashboard', 'spa')->name('dashboard');
Route::view('knowledge', 'spa')->name('knowledge');

Route::view('settings/{section?}', 'spa')
    ->where('section', 'profile|security|appearance|employees')
    ->name('settings');

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::view('docs/api', 'spa')->name('api-docs.ui');
    Route::get('docs/openapi.json', ApiDocumentationController::class)->name('api-docs.spec');
});
