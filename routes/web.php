<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'spa')->name('home');
Route::view('login', 'spa')->name('login');
Route::view('dashboard', 'spa')->name('dashboard');
Route::view('knowledge', 'spa')->name('knowledge');

Route::view('settings/{section?}', 'spa')
    ->where('section', 'profile|security|appearance')
    ->name('settings');
