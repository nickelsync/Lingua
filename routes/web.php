<?php

use Illuminate\Support\Facades\Route;

// Public landing page (server-rendered blade for SEO)
Route::view('/', 'landing')->name('landing');

// SPA entry: all /app and /admin routes render the Vue application.
Route::view('/app/{any?}', 'spa')->where('any', '.*');
Route::view('/admin/{any?}', 'spa')->where('any', '.*');
Route::view('/login', 'spa');
Route::view('/register', 'spa');
Route::view('/recover', 'spa');
Route::view('/u/{username}', 'spa');
Route::view('/offline', 'spa');
