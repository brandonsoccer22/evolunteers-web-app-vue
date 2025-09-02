<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Middleware\SlidingApiTokenExpiration;

use App\Http\Controllers\OpportunityController;

 Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
})->name('health');

 Route::post('/login', [ApiController::class, 'login'])->name('login');

 Route::group(['middleware' => [SlidingApiTokenExpiration::class]], function () {
     Route::get('/test', [ApiController::class, 'test'])->name('test');

      Route::group(['middleware' => [SlidingApiTokenExpiration::class]], function () {
        // Opportunity resource routes
        Route::resource('opportunities', OpportunityController::class);
    });
});


