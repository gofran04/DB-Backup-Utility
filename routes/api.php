<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\BackupJobController;
use App\Http\Controllers\RestoreBackupController;
use App\Http\Controllers\BackupScheduleController;
use App\Http\Controllers\TestDBConnectionController;

Route::apiResource('database-connections',DatabaseConnectionController::class);
Route::apiResource('backup-jobs',BackupJobController::class)->except(['update']);
Route::apiResource('backup-schedules',BackupScheduleController::class);
Route::patch('backup-schedules/{backupSchedule}/toggle',[BackupScheduleController::class,'toggle']);
Route::post('test-db-connection',[TestDBConnectionController::class,'testConnection']);
Route::post('restore',[RestoreBackupController::class,'restoreBackup']);