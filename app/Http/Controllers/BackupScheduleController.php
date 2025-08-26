<?php

namespace App\Http\Controllers;

use App\Models\BackupSchedule;
use App\Http\Resources\BackupScheduleResource;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\CreateOrUpdateBackupScheduleRequest;
use App\Enums\ScheduleFrequency;


class BackupScheduleController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->successResponse(
            BackupScheduleResource::collection(BackupSchedule::all()),
            'Task schedules retrieved',Response::HTTP_OK);
    }

    public function store(CreateOrUpdateBackupScheduleRequest $request)
    {
        $validated = $request->validated();
        $cronExpression = ScheduleFrequency::OPTIONS[$validated['frequency']];

        if($request->has('db_connection_id')){
            $schedule = BackupSchedule::create([
                'db_connection_id' => $validated['db_connection_id'],
                'frequency'        => $validated['frequency'],
                'cron_expression'  => $cronExpression,
                'enabled'          => $validated['enabled'] ?? true,
            ]);
        }elseif($request->has('profile_name')){
                $schedule = BackupSchedule::create([
                'profile_name'     => $validated['profile_name'],
                'frequency'        => $validated['frequency'],
                'cron_expression'  => $cronExpression,
                'enabled'          => $validated['enabled'] ?? true,
            ]);
        }

        return $this->successResponse(
            new BackupScheduleResource($schedule),
            'Task Schedule created',201);

    }

    public function show(BackupSchedule $backupSchedule)
    {
        return $this->successResponse(
            new BackupScheduleResource($backupSchedule),
            'Task schedule retrieved.',Response::HTTP_OK);
    }


    public function update(CreateOrUpdateBackupScheduleRequest $request, BackupSchedule $backupSchedule)
    {
        $validated = $request->validated();
        $cronExpression = ScheduleFrequency::OPTIONS[$validated['frequency']];

        if($request->has('db_connection_id')){
            $backupSchedule->update([
                'db_connection_id' => $validated['db_connection_id'],
                'profile_name'     => null,
                'frequency'        => $validated['frequency'],
                'cron_expression'  => $cronExpression,
            ]);
        }elseif($request->has('profile_name')){
                $backupSchedule->update([
                'db_connection_id' => null,
                'profile_name'     => $validated['profile_name'],
                'frequency'        => $validated['frequency'],
                'cron_expression'  => $cronExpression,
            ]);
        }

        return $this->successResponse(
            new BackupScheduleResource($backupSchedule->refresh()),
            'Task Schedule updated successfully',202);
    }

    public function destroy(BackupSchedule $backupSchedule)
    {
        $backupSchedule->delete();
    
        return $this->successResponse(
            null,
            'Task Schedule deleted successfully.',Response::HTTP_OK);
    }

    public function toggle(BackupSchedule $backupSchedule)
    {
        $backupSchedule->update([
            'enabled' => !$backupSchedule->enabled,
        ]);
        return $this->successResponse(
            new BackupScheduleResource($backupSchedule->refresh()),
            'Task Schedule Status Changed successfully',202);
    }
}
