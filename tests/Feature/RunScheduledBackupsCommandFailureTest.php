<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BackupSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use App\Exceptions\BackupFailedException;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ScheduledBackupFailed;
use Mockery;

class RunScheduledBackupsCommandFailureTest extends TestCase
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
        Mockery::close();
    }

    public function test_throws_BackupFailedException_and_catches_it()
    {
        // STEP 1: Start with faking notifications. should be in the beginning
        Notification::fake();

        $task = BackupSchedule::factory()->create([
            'frequency'       => 'every_minute',
            'cron_expression' => '* * * * *'
        ]);

        $mock = Mockery::mock('overload:' . DatabaseBackupService::class);
        $mock->shouldReceive('backupUsingDbId')
            ->andThrow(new BackupFailedException('Backup Operation Failed.', 'failed'));


        $this->artisan('backup:schedule')
            ->expectsOutput("Running backup for schedule ID: {$task->id}")
            ->expectsOutput('❌ Backup failed: Backup Operation Failed.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('backup_jobs', [
            'database_connection_id' => $task->dbConnection->id,
            'status'                 => 'failed',
        ]);


        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            ScheduledBackupFailed::class
        );
    }
}