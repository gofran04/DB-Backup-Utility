<?php

namespace App\Console\Commands;

use App\Services\ConfigService;
use Illuminate\Console\Command;

class ListDBProfiles extends Command
{
    protected $signature = 'backup:config:list';
    protected $description = 'List all saved database configuration profiles';

    public function handle()
    {
        $configService = app(ConfigService::class);
        $profiles = $configService->loadProfiles();

        if (empty($profiles)) {
            $this->warn('No profiles found.');
            return;
        }

        $this->info("🗂  Saved profiles:");
        foreach ($profiles as $name => $config) {
            $this->line("- $name (Driver: {$config['driver']}, DB: {$config['database']})");
        }
    }
}

