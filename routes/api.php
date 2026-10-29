<?php

use App\Http\Controllers\MetricsController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StatusController::class, 'info'])->name('info');
Route::get('/health', [StatusController::class, 'health'])->name('health');
Route::get('/ready', [StatusController::class, 'ready'])->name('ready');
Route::get('/metrics', MetricsController::class)->name('metrics');
