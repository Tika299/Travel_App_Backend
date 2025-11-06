<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\GoogleController;
use App\Http\Controllers\Api\Auth\SocialAuthController;

Route::get('/', function () {
    return view('welcome');
});

// login google
Route::get('/api/auth/google/redirect', [GoogleController::class, 'redirect']);
Route::get('/api/auth/google/callback', [GoogleController::class, 'callback']);

//login facebook
Route::get('/api/auth/facebook/redirect', [SocialAuthController::class, 'redirectToFacebook']);
Route::get('/api/auth/facebook/callback', [SocialAuthController::class, 'handleFacebookCallback']);
