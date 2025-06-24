<?php

namespace App\Console\Commands;

use App\Services\ConfigService;
use Illuminate\Console\Command;

class RemoveDBProfile extends Command
{
    protected $signature = 'backup:config:remove {profile}';
    protected $description = 'Remove a saved database configuration profile';

    public function handle()
    {
        $profile = $this->argument('profile');
        $configService = app(ConfigService::class);
        $profiles = $configService->loadProfiles();

        if (!isset($profiles[$profile])) {
            $this->error("Profile '$profile' not found.");
            return;
        }

        if (!$this->confirm("Are you sure you want to delete the profile '$profile'?")) {
            $this->info('Cancelled.');
            return;
        }

        unset($profiles[$profile]);
        $configService->saveProfiles($profiles);

        $this->info("✅ Profile '$profile' removed successfully.");
    }
}
