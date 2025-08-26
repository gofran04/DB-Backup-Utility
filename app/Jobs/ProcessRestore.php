<?php

namespace App\Jobs;

use App\Exceptions\RestoreFailedException;
use App\Factories\DatabaseAdapterFactory;
use App\Models\DatabaseConnection;
use App\Services\ConfigService;
use App\Services\RestoreBackupService;
use App\Services\Compression\DecompressionServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Traits\ApiResponseTrait;
use Throwable;

class ProcessRestore implements ShouldQueue
{
    use Queueable, ApiResponseTrait;

    public $timeout = 1200; // 20 minutes
    public $tries = 2;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(DatabaseAdapterFactory $adapterFactory, ConfigService $configService)
    {
        try {
            Log::info('Restore job started', ['payload' => $this->data]);

            $validated = $this->data;
            $filePath = $validated['file'];

            // ✅ Decompression if file is .gz
            if (str_ends_with($filePath, '.gz')) {
                $decompressor = app(DecompressionServiceInterface::class);
                $filePath = $decompressor->decompress($filePath);
            }
            $validated['file'] = $filePath;

            // ✅ Build adapter (from db_id or profile)
            if (isset($validated['db_id'])) {
                $connection = DatabaseConnection::findOrFail($validated['db_id']);
                $adapter = $adapterFactory->make($connection);
            } else {
                $profileName = $validated['db_profile'];
                $profile = $configService->getProfile($profileName);
                if (!$profile) {
                    throw new RestoreFailedException("Profile not found: {$profileName}", 'profile_missing');
                }
                if (!$profile) {
                 return $this->errorResponse(
                    'Restore DB failed',
                    [
                        'message' => 'Profile: '. $profileName. ' not found',
                    ],404);
                }
                $adapter = $adapterFactory->makeFromProfile($profile);
            }

            // ✅ Run restore
            $restoreService = new RestoreBackupService($configService, $adapter);
            $restoreService->restore($validated);

            Log::info('✅ Restore completed successfully', ['file' => $filePath]);

        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    protected function handleFailure($e)
    {
        Log::error('❌ Restore job failed', [
            'message' => $e->getMessage(),
            'type'    => $e instanceof RestoreFailedException ? $e->getType() : 'unknown',
            'trace'   => $e->getTraceAsString()
        ]);

        throw $e; 
    }

    public function failed(Throwable $e): void
    {
        // Called by Laravel when job exhausts all attempts
        Log::error("Restore job permanently failed after retries");
    }
}