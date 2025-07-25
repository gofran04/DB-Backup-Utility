<?php

namespace App\Http\Controllers;

use App\Exceptions\RestoreFailedException;
use App\Http\Requests\RestoreBackupRequest;
use App\Services\RestoreBackupService;
use App\Services\ConfigService;
use App\Models\DatabaseConnection;
use App\Factories\DatabaseAdapterFactory;
use App\Traits\ApiResponseTrait;
use App\Services\Compression\DecompressionServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class RestoreBackupController extends Controller
{
    use ApiResponseTrait;

    function restoreBackup(RestoreBackupRequest $request)
    {
        $validated = $request->validated();

        $filePath = null;
        $pathToBackupFile = $validated['file'];
        if (str_ends_with($pathToBackupFile, '.gz')) 
        {
            $decompressor = app(DecompressionServiceInterface::class);
            try {
               $filePath = $decompressor->decompress($pathToBackupFile);
            } catch (RestoreFailedException $e) {
                return $this->errorResponse(
                    'Decompression failed',
                    [
                        'type'    => $e->getType(),
                        'message' => $e->getMessage()
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        } else {
            $filePath = $pathToBackupFile;
        }
        $validated['file'] = $filePath;

        // Use factory to resolve correct adapter
        $adapterFactory = new DatabaseAdapterFactory();
        $adapter = null;
        if (isset($validated['db_id'])) 
        {
            $connection = DatabaseConnection::findOrFail($validated['db_id']);
            $adapter = $adapterFactory->make($connection);
        } elseif (isset($validated['db_profile'])) {
            $profileName = $request->input('db_profile'); 
            $configService = new ConfigService();
            $profile = $configService->getProfile($profileName);
            if (!$profile) {
                 return $this->errorResponse(
                    'Restore DB failed',
                    [
                        'message' => 'Profile: '. $profileName. ' not found',
                    ],404);
            }

            $adapter = $adapterFactory->makeFromProfile($profile);
        }

        $restore_service = new RestoreBackupService(new ConfigService(), $adapter);
        
        try{// Run restore operation
            $restore_result = $restore_service->restore($validated);
        }catch(RestoreFailedException $e){
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