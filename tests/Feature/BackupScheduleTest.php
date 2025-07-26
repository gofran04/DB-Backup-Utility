<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\BackupSchedule;

class BackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_all_db_schedules()
    {
        BackupSchedule::factory()->count(8)->create();
        $response = $this->getJson('api/backup-schedules');

        $response->assertOk();
        $this->assertDatabaseCount('backup_schedules', 8);
    }

    public function test_return_specific_schedule()
    {
        $schedule = BackupSchedule::factory()->create();
        $response = $this->getJson('api/backup-schedules/'.$schedule->id);

        $response->assertOk();
        $this->assertDatabaseCount('backup_schedules', 1);

        $response->assertJson([
                'data' => [
                    'id' => $schedule->id,
                ],
            ]);
    }

    
    
}