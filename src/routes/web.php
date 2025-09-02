<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\OpportunityController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Opportunity resource routes
Route::resource('opportunities', OpportunityController::class)
    ->middleware('auth')->except(['index', 'show']);
