<?php

namespace Tests\Feature\ConsoleTests;

use Tests\TestCase;
use App\Models\BackupJob;
use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class BackupCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigDir;
    protected string $testConfigPath;

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

    public function test_cleanup_old_backups_by_keeping_only_N_backups()
    {
        $db_connection = DatabaseConnection::factory()->create();
        BackupJob::factory()->count(6)->create([
            'database_connection_id' => $db_connection->id,
            'created_at'             => now()->subDays(10)
        ]);

        $keepLast = 'p';
        $this->artisan('backup:cleanup',['--keep-last' => $keepLast])            
            ->expectsOutput("🧹 Cleaning up: Keeping only last {$keepLast} backups...")
            ->expectsOutput("✅ Cleanup completed.")
            ->assertExitCode(0);

        $this->assertCount(5, BackupJob::withoutTrashed()->get());
    }

    public function test_cleanup_old_backups_by_deleting_backups_older_than_N_days()
    {
        $db_connection = DatabaseConnection::factory()->create();
        BackupJob::factory()->count(4)->create([
            'database_connection_id' => $db_connection->id,
            'created_at'             => now()->subDays(10)
        ]);

        BackupJob::factory()->create([
            'database_connection_id' => $db_connection->id,
            'created_at'             => now()->subDays(1)
        ]);

        $olderThanDays = 10;
        $this->artisan('backup:cleanup',['--older-than-days' => $olderThanDays])            
            ->expectsOutput("🧹 Cleaning up: Removing backups older than {$olderThanDays} days...")
            ->expectsOutput("✅ Cleanup completed.")
            ->assertExitCode(0);

        $this->assertCount(1, BackupJob::withoutTrashed()->get());
    }
}
