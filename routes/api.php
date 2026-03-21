<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\UserController;

Route::apiResource('users', UserController::class);

// You can define your API routes here.
