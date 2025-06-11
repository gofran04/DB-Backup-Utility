<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseConnectionController;

Route::apiResource('database-connections',DatabaseConnectionController::class);

