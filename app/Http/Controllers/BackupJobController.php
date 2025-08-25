<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBackupJobRequest;
use App\Models\BackupJob;
use App\Http\Resources\BackupJobResource;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponseTrait;
use App\Jobs\ProcessDatabaseBackup;

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
        $data = [
        'status'     => 'pending',
        'mechanism'  => 'manual',
        'started_at' => now()
        ];

        if (isset($input['db_id'])) {
            $data['database_connection_id'] = $input['db_id'];
        }elseif (isset($input['db_profile'])) {
            $data['profile_name'] = $input['db_profile'];
        }
       
        $backupJob = BackupJob::create($data);
        
        ProcessDatabaseBackup::dispatch($backupJob->id)->onQueue('backups');

        return $this->successResponse(
            new BackupJobResource($backupJob),
            'Backup job queued successfully',
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
