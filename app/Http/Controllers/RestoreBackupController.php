<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreBackupRequest;
use App\Traits\ApiResponseTrait;
use App\Jobs\ProcessRestore;

class RestoreBackupController extends Controller
{
    use ApiResponseTrait;

    function restoreBackup(RestoreBackupRequest $request)
    {
        $validated = $request->validated();

        // Dispatch the job with all validated data
        ProcessRestore::dispatch($validated)->onQueue('restore');

        return $this->successResponse(
            null,
            'Restore process started in background.'
        );
    }
}