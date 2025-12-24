<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\AdminOpportunityController;

Route::group(['as' => 'admin.', 'prefix' => 'admin'], function () {
    Route::get('dashboard', function () {
        return Inertia::render('admin/Dashboard');
    })
    ->middleware(['auth','verified'])
    ->name('dashboard');

    //Opportunity resource routes
    // Route::resource('opportunities', AdminOpportunityController::class)
    //     ->middleware(['auth','verified']);//->except(['index', 'show']);

    Route::group(['as' => 'opportunities.', 'prefix' => 'opportunities'], function () {
         Route::get('', [AdminOpportunityController::class, 'index'])
            ->name('index');
        Route::get('create', [AdminOpportunityController::class, 'showCreate'])
            ->name('showCreate');
        Route::post('', [AdminOpportunityController::class, 'create'])
            ->name('create');
        // Route::get('{opportunity}/edit', [AdminOpportunityController::class, 'showEdit'])
        //     ->middleware(['auth','verified'])
        //     ->name('showEdit');
        Route::patch('{opportunity}', [AdminOpportunityController::class, 'update'])
            ->name('update');
        Route::delete('{opportunity}', [AdminOpportunityController::class, 'delete'])
            ->name('delete');
    });
})->middleware(['auth','verified']);
