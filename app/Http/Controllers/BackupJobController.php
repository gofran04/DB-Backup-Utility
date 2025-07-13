<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBackupJobRequest;
use App\Models\BackupJob;
use App\Services\DatabaseBackupService;
use App\Services\BackupLoggerService;
use App\Http\Resources\BackupJobResource;
use Illuminate\Support\Facades\Log;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\BackupFailedException;
use App\Exceptions\CompresionFailedException;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponseTrait;
use App\Models\DatabaseConnection;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;


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

        $db_connection = DatabaseConnection::findOrFail($input['db_id']);

        $backupJob = BackupJob::create([
            'database_connection_id' => $input['db_id'],
            'status'                 => 'pending',
            'mechanism'              => 'manual',
            'started_at'             => now()
        ]);

        // Log at the start of backup:
        BackupLoggerService::logStart($backupJob);

        // Run backup via backup service
        try {
            // Use factory to resolve correct adapter
            $adapterFactory = new DatabaseAdapterFactory();
            $adapter = $adapterFactory->make($db_connection);

            // get concrete implementation that was bound to this interface
            $compressor = app(CompressionServiceInterface::class);
            
            // Run backup
            $backupService = new DatabaseBackupService($adapter,$compressor);
            $backupResult = $backupService->backup($db_connection, 'backups');// pass the absolute path 
           
            $backupJob->update([ // Update job record with success
                'status'       => 'completed',
                'backup_path'  => $backupResult['relative_path'],
                'file_size'    => $backupResult['file_size'],
                'completed_at' => now()
                ]);

            // log after backup operation success:
            BackupLoggerService::logSuccess($backupJob, $backupResult['relative_path'], $backupResult['file_size']);
           
            } catch (DatabaseConnectionException $e) {
                return $this->errorResponse(
                    'Database connection failed',
                    [
                        'type'    => $e->getType(),
                        'message' => $e->getMessage()
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            } catch (BackupFailedException $e) { // Update job record with failure
                $backupJob->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now()
                ]);

                // On failure:
                BackupLoggerService::logFailure($backupJob, $e);

                return $this->errorResponse(
                    'Backup failed',
                    [
                        'type'    => $e->getType(),
                        'message' => $e->getMessage(),
                    ],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }catch (CompresionFailedException $e) {
                return $this->errorResponse(
                    'Compression failed',
                    [
                        'type'    => $e->getType(),
                        'message' => $e->getMessage()
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
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
