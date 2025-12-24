<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\AdminOpportunityController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminOrganizationController;

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

    Route::group(['as' => 'users.', 'prefix' => 'users'], function () {
        Route::get('', [AdminUserController::class, 'index'])->name('index');
        Route::get('create', [AdminUserController::class, 'createForm'])->name('create');
        Route::post('', [AdminUserController::class, 'store'])->name('store');
        Route::get('{user}', [AdminUserController::class, 'show'])->name('show');
        Route::patch('{user}', [AdminUserController::class, 'update'])->name('update');
        Route::delete('{user}', [AdminUserController::class, 'destroy'])->name('destroy');
        Route::post('{user}/organizations/{organization}', [AdminUserController::class, 'attachOrganization'])->name('organizations.attach');
        Route::delete('{user}/organizations/{organization}', [AdminUserController::class, 'detachOrganization'])->name('organizations.detach');
    });

    Route::get('organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
})->middleware(['auth','verified']);
