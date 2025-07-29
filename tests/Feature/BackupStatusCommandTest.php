<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BackupJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BackupStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_status_command()
    {
        $jobs = BackupJob::factory()->count(3)->create(['mechanism' => 'automated']);

        $this->artisan('backup:status')
            ->expectsOutput("Backup ID: {$jobs[0]->id}")
            ->expectsOutput("Backup ID: {$jobs[1]->id}")
            ->expectsOutput("Backup ID: {$jobs[2]->id}")
            ->assertExitCode(0);
    }

    public function test_backup_status_command_with_no_jobs_founded()
    {
        $this->artisan('backup:status')
            ->expectsOutput("⚠️  No automated backup jobs found.")
            ->assertExitCode(0);
    }
}