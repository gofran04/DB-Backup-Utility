<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\BackupSchedule;
use App\Enums\ScheduleFrequency;
use App\Models\DatabaseConnection;

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

    public function test_store_new_schedule()
    {
        $frequency = fake()->randomElement(array_keys(ScheduleFrequency::OPTIONS));
        $db_connection = DatabaseConnection::factory()->create();
        $data = [
            'db_connection_id' => $db_connection->id, 
            'frequency'        => $frequency,
            'cron_expression'  => ScheduleFrequency::OPTIONS[$frequency],
            'enabled'          => true
        ];

        $response = $this->postJson('api/backup-schedules/',$data);

        $response->assertStatus(201);
        $this->assertDatabaseCount('backup_schedules', 1);
        $this->assertDatabaseHas('backup_schedules', [
            'db_connection_id' => $db_connection->id, 
        ]);
        $response->assertJsonFragment([
            'frequency'        => $frequency,
        ]);
    }
}