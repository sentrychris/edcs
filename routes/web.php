<?php

use App\Http\Controllers\FootprintController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('https://edcs.app'));

Route::prefix('footprint')->name('footprint.')->group(function () {
    Route::get('/', [FootprintController::class, 'index'])->name('index');
    Route::post('/echo', [FootprintController::class, 'echo'])->name('echo');
    Route::post('/log', [FootprintController::class, 'log'])->name('log');
});
