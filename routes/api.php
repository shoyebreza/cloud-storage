<?php

use App\Http\Controllers\Api\StorageSummaryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserFileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::apiResource('users', UserController::class);

Route::apiResource('users.files', UserFileController::class)->only(['index', 'store']);

Route::get('users/{user}/storage-summary', StorageSummaryController::class);

// You can define your API routes here.
