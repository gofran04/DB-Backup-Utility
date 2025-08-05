<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DatabaseConnection;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackupJob>
 */
class BackupJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 day', 'now');
        $end = (clone $start)->modify('+5 minutes');

        //match service login
        $connectionName = 'temp_' . uniqid();
        $dbName = 'TechFlex'; 
        $filename = $dbName . '_' . $connectionName . '_backup_' . now()->format('Ymd_His') . '.sql';
       
        // Change path depending on environment
        $backupDir = app()->environment('testing')
            ? 'test-backups/'
            : 'backups/';
            
        $path = $backupDir . $filename;

        return [
            'database_connection_id' => DatabaseConnection::factory(['db_name' => $dbName]), 
            'backup_path'   => $path,
            'status'        => 'completed',
            'mechanism'     => 'manual',
            'started_at'    => $start,
            'completed_at'  => $end,
            'file_size'     => $this->faker->numberBetween(100_000, 1_000_000), // in bytes
            'error_message' => null, // or add fake message for failed jobs
        ];
    }
}
