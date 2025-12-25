<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\AdminOpportunityController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminOrganizationController;

Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => ['auth', 'verified', 'role:Admin,Organization Manager']], function () {
    Route::get('dashboard', function () {
        return Inertia::render('admin/Dashboard');
    })
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
        Route::get('{opportunity}', [AdminOpportunityController::class, 'show'])
            ->name('show');
        Route::post('{opportunity}/organizations/{organization}', [AdminOpportunityController::class, 'attachOrganization'])
            ->name('organizations.attach');
        Route::delete('{opportunity}/organizations/{organization}', [AdminOpportunityController::class, 'detachOrganization'])
            ->name('organizations.detach');
        Route::post('{opportunity}/tags', [AdminOpportunityController::class, 'addTag'])
            ->name('tags.add');
        Route::delete('{opportunity}/tags', [AdminOpportunityController::class, 'removeTag'])
            ->name('tags.remove');
        // Route::get('{opportunity}/edit', [AdminOpportunityController::class, 'showEdit'])
        //     ->middleware(['auth','verified'])
        //     ->name('showEdit');
        Route::patch('{opportunity}', [AdminOpportunityController::class, 'update'])
            ->name('update');
        Route::delete('{opportunity}', [AdminOpportunityController::class, 'delete'])
            ->name('delete');
    });

    Route::group(['as' => 'users.', 'prefix' => 'users', 'middleware' => ['role:Admin']], function () {
        Route::get('', [AdminUserController::class, 'index'])->name('index');
        Route::get('create', [AdminUserController::class, 'createForm'])->name('create');
        Route::post('', [AdminUserController::class, 'store'])->name('store');
        Route::get('{user}', [AdminUserController::class, 'show'])->name('show');
        Route::patch('{user}', [AdminUserController::class, 'update'])->name('update');
        Route::delete('{user}', [AdminUserController::class, 'destroy'])->name('destroy');
        Route::post('{user}/organizations/{organization}', [AdminUserController::class, 'attachOrganization'])->name('organizations.attach');
        Route::delete('{user}/organizations/{organization}', [AdminUserController::class, 'detachOrganization'])->name('organizations.detach');
    });

    Route::group(['as' => 'organizations.', 'prefix' => 'organizations'], function () {
        Route::get('', [AdminOrganizationController::class, 'index'])->name('index');
        Route::get('create', [AdminOrganizationController::class, 'showCreate'])->name('create');
        Route::post('', [AdminOrganizationController::class, 'store'])->name('store');
        Route::get('{organization}', [AdminOrganizationController::class, 'show'])->name('show');
        Route::patch('{organization}', [AdminOrganizationController::class, 'update'])->name('update');
        Route::delete('{organization}', [AdminOrganizationController::class, 'destroy'])->name('destroy');
        Route::post('{organization}/users/{user}', [AdminOrganizationController::class, 'attachUser'])->name('users.attach');
        Route::delete('{organization}/users/{user}', [AdminOrganizationController::class, 'detachUser'])->name('users.detach');
    });
});
