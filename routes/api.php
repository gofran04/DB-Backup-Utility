<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\BackupJobController;
use App\Http\Controllers\RestoreBackupController;
use App\Services\TestDatabaseConnectionService;

Route::apiResource('database-connections',DatabaseConnectionController::class);
Route::apiResource('backup-jobs',BackupJobController::class)->except(['update']);
Route::get('test-db-connection/{id}',[TestDatabaseConnectionService::class,'testConnection']);
Route::post('restore',[RestoreBackupController::class,'restoreBackup']);