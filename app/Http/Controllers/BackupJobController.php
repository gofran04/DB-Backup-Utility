<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBackupJobRequest;
use App\Models\BackupJob;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\BackupJobResource;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\DatabaseConnectionController;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\BackupFailedException;

class BackupJobController extends Controller
{
    public function index()
    {
        $backup_jobs = BackupJob::all();

        return BackupJobResource::collection($backup_jobs);
    }

    public function store(StoreBackupJobRequest $request)
    {
        $input = $request->validated();

        $backupJob = BackupJob::create([
            'database_connection_id' => $input['db_id'],
            'status'                 => 'pending',
            'started_at'             => now()
        ]);

        // Test DB Connection
        try {
            $result = DatabaseConnectionController::createDynamicConnection($input['db_id']);
            if (! $result['status']) 
            {
                return response()->json([
                    'message' => 'Database connection failed before backup opeartion start.',
                    'error'   => $result['error'],
                ], 422);
            }
        }catch(DatabaseConnectionException $e){
            return response()->json([
                'message'       => 'Database connection failed',
                'error_type'    => $e->getType(),
                'error_message' => $e->getMessage(),
            ], 422);
        }
       
        $connectionName = $result['connectionName'];

        // Run backup via backup service
        try {
            $result = DatabaseBackupService::backup($connectionName,'backups');
           
            // Update job record with success
            $backupJob->update([
                'status'       => 'completed',
                'backup_path'  => $result['file_path'],
                'file_size'    => $result['file_size'],
                'completed_at' => now()
                ]);
            } catch (BackupFailedException $e) { // Update job record with failure
                $backupJob->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now()
                ]);

                return response()->json([
                    'message'       => 'Backup failed',
                    'error_type'    => $e->getType(),
                    'error_message' => $e->getMessage(),
                ], 500); 
            }finally {
                DB::disconnect($connectionName); // clean up connection
            }

        return response()->json($backupJob);
    }

    public function show(BackupJob $backupJob)
    {
        return new BackupJobResource($backupJob);
    }

    public function destroy(BackupJob $backupJob)
    {
        $relativePath = trim($backupJob->backup_path);
        $fullPath = storage_path('app/' . $relativePath);

        $fileWasThere = file_exists($fullPath); // ← Check BEFORE deleting
        if ($fileWasThere) 
        {
            if (!@unlink($fullPath)) {
                Log::error("Failed to delete backup file: {$fullPath}");

                return response()->json([
                    'message'   => 'Failed to delete the backup file from disk.',
                    'file_path' => $relativePath,
                ], 500);
            }
        } else {
            Log::warning("Backup file not found during delete: {$fullPath}");
        }

        $backupJob->delete();

        return response()->json([
            'message' => $fileWasThere
                ? 'Backup file and record deleted successfully.'
                : 'Backup record deleted. File was already missing.',
        ]);
    }
}
