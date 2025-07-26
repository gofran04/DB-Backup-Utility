<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DatabaseConnection;
use App\Enums\ScheduleFrequency;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackupJob>
 */
class BackupScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frequency = $this->faker->randomElement(array_keys(ScheduleFrequency::OPTIONS));
        return [
            'db_connection_id' => DatabaseConnection::factory(), 
            'frequency'        => $frequency,
            'cron_expression'  => ScheduleFrequency::OPTIONS[$frequency],
            'enabled'          => true,
        ];
    }
}
