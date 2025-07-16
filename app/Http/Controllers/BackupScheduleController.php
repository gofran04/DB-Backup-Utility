<?php

namespace App\Http\Controllers;

use App\Models\BackupSchedule;
use Illuminate\Http\Request;
use App\Http\Resources\BackupScheduleResource;

class BackupScheduleController extends Controller
{

    public function index()
    {
        return BackupScheduleResource::collection(BackupSchedule::all());
    }

    public function store(Request $request)
    {
        //
    }

    public function show(BackupSchedule $backupSchedule)
    {
        //
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
