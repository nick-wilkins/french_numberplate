<?php

use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::get('state', [SyncController::class, 'show'])->name('api.state');
Route::post('sync', [SyncController::class, 'store'])->name('api.sync');
