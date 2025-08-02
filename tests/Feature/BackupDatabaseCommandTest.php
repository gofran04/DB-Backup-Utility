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

    public function test_backup_db_via_command_fails_when_using_not_found_db_id()
    {
        $this->artisan('db:backup',['id' => 9999]) // using not found db
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: 9999)")
            ->expectsOutput("❌ No database connection found with ID: 9999")
            ->assertExitCode(1);//failure
    }
}