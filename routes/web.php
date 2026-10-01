<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\SearchProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy']);
    Route::get('/', [LeadController::class, 'index']);
    Route::get('/leads/create', [LeadController::class, 'create']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::get('/leads/{lead}', [LeadController::class, 'show']);
    Route::put('/leads/{lead}', [LeadController::class, 'update']);
    Route::get('/leads/{lead}/property', [LeadController::class, 'editProperty']);
    Route::put('/leads/{lead}/property', [LeadController::class, 'saveProperty']);
    Route::post('/leads/{lead}/activities', [LeadController::class, 'activity']);
    Route::get('/leads/{lead}/onoffice', [LeadController::class, 'preview']);
    Route::post('/leads/{lead}/onoffice', [LeadController::class, 'transfer'])->middleware('throttle:5,1');
    Route::get('/profiles', [SearchProfileController::class, 'index']);
    Route::post('/profiles', [SearchProfileController::class, 'save']);
    Route::put('/profiles/{profile}', [SearchProfileController::class, 'save']);
    Route::post('/profiles/{profile}/run', [SearchProfileController::class, 'run']);
    Route::post('/geocode', [SearchProfileController::class, 'geocode'])->middleware('throttle:10,1');
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'save']);
    Route::put('/users/{user}',[UserController::class, 'save']);
});
