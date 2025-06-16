<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {client_id}';
    protected $description = 'Backup The Database';


    public function handle(DatabaseBackupService $backupService)
    {
        $dbId = $this->argument('client_id');

        // Get DB config for that client from your main DB
        $conn = DB::table('database_connections')->find($dbId);
        if (! $conn) {
            $this->error("DB not found");
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

        $this->info("🔗 Connected. Creating backup...");

        // Call the backup service
        $result = $backupService->backup($connectionName, '/app/backups');

        if ($result['status']) {
            $this->info("Backup complete: " . $result['file']);
        } else {
            $this->error("Backup failed:\n" . $result['error']);
        }

        return 0;
    }
}
