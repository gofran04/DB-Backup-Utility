<?php

namespace App\Enums;

class ScheduleFrequency
{
    public const OPTIONS = [
        'every_minute'    => '* * * * *',
        'every_5_minutes' => '*/5 * * * *',
        'hourly'          => '0 * * * *',
        'daily'           => '0 0 * * *',
        'weekly'          => '0 0 * * 0',
        'monthly'         => '0 0 1 * *',
    ];
}
