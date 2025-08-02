<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class BackupDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
  
        $testBackupPath = storage_path('app/test-backups');
        File::ensureDirectoryExists($testBackupPath);
        Config::set('backup.storage_path', $testBackupPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-backups'));
        parent::tearDown();
    }

    public function test_backup_db_via_command_successfully_using_db_id()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $this->artisan('db:backup',['id' => $db_connection->id])
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: $db_connection->id)")
            ->expectsOutput("✅ Backup successful!")
            ->assertExitCode(0);
    }
}