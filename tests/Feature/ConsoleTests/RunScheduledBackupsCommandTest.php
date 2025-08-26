<?php

namespace Tests\Feature\ConsoleTests;

use Tests\TestCase;
use App\Models\BackupSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

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
            ->assertExitCode(0);

        $this->assertDatabaseHas('backup_jobs', [
            'database_connection_id' => $task->dbConnection->id,
            'mechanism'              => 'automated',
        ]);
    }

    public function test_nothing_runs_if_schedule_is_not_due()
    {
        // Force the date to the 15th of the month, so nothing will run, because created task has frequency= monthly(at the first day of the month only)
        Carbon::setTestNow(Carbon::create(null, null, 15, 18, 0, 0)); 

        BackupSchedule::factory()->create([
            'frequency'       => 'monthly',
            'cron_expression' => '0 0 1 * *', 
            'enabled'         => true,
        ]);

        $this->artisan('backup:schedule')
            ->assertExitCode(0);
        
        $this->assertDatabaseCount('backup_jobs', 0); // No jobs created
    }
    public function test_multiple_schedules_created_but_only_one_executed()
    {
        // Force the date to the 15th of the month, so nothing will run, because created task has frequency= monthly(at the first day of the month only)
        Carbon::setTestNow(Carbon::create(null, null, 15, 18, 0, 0)); 

        // this task will executed
        $task1 = BackupSchedule::factory()->create([
            'frequency'       => 'every_minute',
            'cron_expression' => '* * * * *'
        ]);

        // this task will not executed
        $task2 = BackupSchedule::factory()->create([
            'frequency'       => 'monthly',
            'cron_expression' => '0 0 1 * *', 
        ]);

        $this->artisan('backup:schedule')
            ->assertExitCode(0);

        $this->assertDatabaseHas('backup_jobs', [
            'database_connection_id' => $task1->dbConnection->id,
            'status'                 => 'completed',
        ]);

        $this->assertDatabaseMissing('backup_jobs', [
            'database_connection_id' => $task2->dbConnection->id,
        ]);
    }
}