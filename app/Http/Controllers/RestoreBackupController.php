<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreBackupRequest;
use App\Services\RestoreBackupService;
use App\Services\ConfigService;
use App\Models\DatabaseConnection;
use App\Factories\DatabaseAdapterFactory;
use App\Traits\ApiResponseTrait;

class RestoreBackupController extends Controller
{
    use ApiResponseTrait;

    function restoreBackup(RestoreBackupRequest $request)
    {
        $validated = $request->validated();
        $connection = DatabaseConnection::findOrFail($validated['db_id']);

        // Use factory to resolve correct adapter
        $adapterFactory = new DatabaseAdapterFactory();
        $adapter = $adapterFactory->make($connection);

        // Run backup
        $restore_service = new RestoreBackupService(new ConfigService(), $adapter);
        try{
            $restore_result = $restore_service->restore($validated);
        }catch(\Exception $e){
            return $this->errorResponse(
                    'Restore DB failed',
                    [
                        'message' => $e->getMessage(),
                    ],
                );
        }

        return $this->successResponse(
            null,
            $restore_result['message'],
            200,
        );
    }
}