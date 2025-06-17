<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBackupJobRequest;
use App\Http\Requests\UpdateBackupJobRequest;
use App\Models\BackupJob;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Http\Resources\BackupJobResource;

class BackupJobController extends Controller
{
    public function index()
    {
        $backup_jobs = BackupJob::all();

        return BackupJobResource::collection($backup_jobs);
    }

    public function store(StoreBackupJobRequest $request)
    {
        
        $input = $request->validated();

        $backupJob = BackupJob::create([
            'database_connection_id' => $input['db_id'],
            'status'                 => 'pending',
            'started_at'             => now()
        ]);

        // connect to db
        $conn = DB::table('database_connections')->find($input['db_id']);
        if (! $conn) {
            echo("❌ DB not found");
            return 1;
        }

        // Create a temporary connection config
        $connectionName = 'temp_' . uniqid();

        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $conn->host,
            'port' => $conn->port,
            'database' => $conn->db_name,
            'username' => $conn->username,
            'password' => $conn->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        DB::purge($connectionName);
        DB::reconnect($connectionName);

        // Run backup via backup service
        try {
            $result = DatabaseBackupService::backup($connectionName,'/app/backups');
           
            // Update job record with success
            $backupJob->update([
                'status'       => 'completed',
                'backup_path'  => $result['file_path'],
                'file_size'    => $result['file_size'],
                'completed_at' => now()
                ]);
            } catch (\Exception $e) { // Update job record with failure
                $backupJob->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now()
                ]);
            }finally {
                DB::disconnect($connectionName); // clean up connection
            }

        return response()->json($backupJob);
    }

    public function show(BackupJob $backupJob)
    {
        return new BackupJobResource($backupJob);
    }

    public function destroy(BackupJob $backupJob)
    {
        $backupJob->delete();
        return response('The Backup Job has been deleted');
    }
}
