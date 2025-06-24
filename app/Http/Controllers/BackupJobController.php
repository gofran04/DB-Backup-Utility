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
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponseTrait;

class BackupJobController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $backup_jobs = BackupJob::all();

        return $this->successResponse(
            BackupJobResource::collection($backup_jobs),
            'Backup Jobs retrieved',Response::HTTP_OK);
    }

    public function store(StoreBackupJobRequest $request)
    {
        $input = $request->validated();

        $backupJob = BackupJob::create([
            'database_connection_id' => $input['db_id'],
            'status'                 => 'pending',
            'started_at'             => now()
        ]);

        // Log at the start of backup:
        Log::info('Backup job started', [
            'backup_job_id'    => $backupJob->id,
            'db_connection_id' => $input['db_id'],
            'db_name'          => $backupJob->databaseConnection->db_name
        ]);

        // Test DB Connection
        try {
            $result = DatabaseConnectionController::createDynamicConnection($input['db_id']);
        }catch(DatabaseConnectionException $e){
            return $this->errorResponse(
                'Database connection failed',
                [
                    'type'    => $e->getType(),
                    'message' => $e->getMessage(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
       
        $connectionName = $result['connectionName'];

        // Run backup via backup service
        try {
            $backupResult = DatabaseBackupService::backup($connectionName,'backups');
           
            $backupJob->update([ // Update job record with success
                'status'       => 'completed',
                'backup_path'  => $backupResult['file_path'],
                'file_size'    => $backupResult['file_size'],
                'completed_at' => now()
                ]);

                // log after backup operation success:
                Log::info('Backup job completed successfully', [
                    'backup_job_id' => $backupJob->id,
                    'file_path'     => $backupResult['file_path'],
                    'file_size'     => $backupResult['file_size'],
                    'duration'      => now()->diffInSeconds($backupJob->started_at)
                ]);
            } catch (BackupFailedException $e) { // Update job record with failure
                $backupJob->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now()
                ]);

                // On failure:
                Log::error('Backup job failed', [
                    'backup_job_id'    => $backupJob->id,
                    'db_connection_id' => $input['db_id'],
                    'error'            => $e->getMessage(),
                    'trace'            => $e->getTraceAsString()
                ]);

                return $this->errorResponse(
                'Backup failed',
                [
                    'type'    => $e->getType(),
                    'message' => $e->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
            }finally {
                DB::disconnect($connectionName); // clean up connection
            }

        return $this->successResponse(
            new BackupJobResource($backupJob->refresh()),
            'Database backup completed successfully',
            Response::HTTP_CREATED
        );
    }

    public function show(BackupJob $backupJob)
    {
        return $this->successResponse(
            new BackupJobResource($backupJob),
            'Backup Job retrieved.',Response::HTTP_OK);
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

                return $this->errorResponse(
                    'Failed to delete the backup file from disk.',
                    ['file_path' => $relativePath],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        } else {
            Log::warning("Backup file not found during delete: {$fullPath}");
        }

        $backupJob->delete();

        return $this->successResponse(
            null,
            $fileWasThere
                ? 'Backup file and record deleted successfully.'
                : 'Backup record deleted. File was already missing.',
            Response::HTTP_OK
        );
    }
}
