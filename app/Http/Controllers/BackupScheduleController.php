<?php

namespace App\Http\Controllers;

use App\Models\BackupSchedule;
use Illuminate\Http\Request;
use App\Http\Resources\BackupScheduleResource;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponseTrait;

class BackupScheduleController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->successResponse(
            BackupScheduleResource::collection(BackupSchedule::all()),
            'Task schedules retrieved',Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        //
    }

    public function show(BackupSchedule $backupSchedule)
    {
        return $this->successResponse(
            new BackupScheduleResource($backupSchedule),
            'Task schedule retrieved.',Response::HTTP_OK);
    }


    public function update(Request $request, BackupSchedule $backupSchedule)
    {
        //
    }

    public function destroy(BackupSchedule $backupSchedule)
    {
        //
    }
}
