<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseConnectionController;

Route::get('database-connections/test',[DatabaseConnectionController::class, 'testConnection']);
Route::apiResource('database-connections',DatabaseConnectionController::class);


