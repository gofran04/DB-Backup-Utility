<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BackupSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class RunScheduledBackupsCommandTest extends TestCase
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

    public function test_run_schedule_backup_tasks()
    {
        $task = BackupSchedule::factory()->create([
            'frequency'       => 'every_minute',
            'cron_expression' => '* * * * *'
        ]);

        $this->artisan('backup:schedule')
            ->expectsOutput("Running backup for schedule ID: {$task->id}")
            ->expectsOutput("✅ Backup completed for connection ID: {$task->dbConnection->id}")
            ->assertExitCode(0);

        $this->assertDatabaseHas('backup_jobs', [
            'database_connection_id' => $task->dbConnection->id,
            'mechanism'              => 'automated',
        ]);
    }
}