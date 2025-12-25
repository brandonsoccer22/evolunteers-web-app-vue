<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PublicOpportunityController;

//use App\Http\Controllers\OpportunityController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/opportunities', [PublicOpportunityController::class, 'index'])
->name('opportunities.index')
->middleware('auth');


require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
