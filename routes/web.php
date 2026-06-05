<?php

use App\Http\Controllers\TrackerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackerController::class, 'index'])->name('tracker.index');
Route::post('/tracker', [TrackerController::class, 'update'])->name('tracker.update');
Route::post('/tracker/reset', [TrackerController::class, 'reset'])->name('tracker.reset');
