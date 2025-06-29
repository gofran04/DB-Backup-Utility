<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreBackupRequest;
use App\Services\RestoreBackupService;
use App\Services\ConfigService;
use Illuminate\Http\JsonResponse;


class RestoreBackupController extends Controller
{

    function restoreBackup(RestoreBackupRequest $request)
    {
        $validated = $request->validated();
    
        $service = new RestoreBackupService(new ConfigService());
        $result = $service->restore($validated);

        return response()->json([$result['data'], $result['status']]);
    }

}