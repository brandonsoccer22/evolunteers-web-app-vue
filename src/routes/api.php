<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Middleware\SlidingApiTokenExpiration;

use App\Http\Controllers\Admin\AdminOpportunityController;

Route::name('api.')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    })->name('health');

    Route::post('/login', [ApiController::class, 'login'])->name('login');

    Route::group(['middleware' => [SlidingApiTokenExpiration::class]], function () {
        Route::get('/test', [ApiController::class, 'test'])->name('test');

        Route::group(['prefix' => 'opportunities'], function () {
        Route::get('create', [AdminOpportunityController::class, 'showCreate'])
            ->middleware(['auth','verified'])
            ->name('showCreate');
        Route::post('', [AdminOpportunityController::class, 'create'])
            ->middleware(['auth','verified'])
            ->name('create');
        // Route::get('{opportunity}/edit', [AdminOpportunityController::class, 'showEdit'])
        //     ->middleware(['auth','verified'])
        //     ->name('showEdit');
        Route::patch('{opportunity}', [AdminOpportunityController::class, 'update'])
            ->middleware(['auth','verified'])
            ->name('update');
        Route::delete('{opportunity}', [AdminOpportunityController::class, 'delete'])
            ->middleware(['auth','verified'])
            ->name('delete');
    })->as('opportunities.');


    });
});




