<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BackupSchedule;

class BackupScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         BackupSchedule::create([
            'db_connection_id'   => 5, 
            'frequency'       => 'every minute',
            'cron_expression' => '* * * * *', 
            'enabled'         => true,
        ]);

         BackupSchedule::create([
            'db_connection_id'   => 6, 
            'frequency'       => 'every minute',
            'cron_expression' => '* * * * *', 
            'enabled'         => true,
        ]);
    }
}
