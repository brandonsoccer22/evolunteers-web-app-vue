<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\OpportunityController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth','verified'])->name('dashboard');

// Opportunity resource routes
Route::resource('opportunities', OpportunityController::class)
    ->middleware(['auth','verified']);//->except(['index', 'show']);

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
