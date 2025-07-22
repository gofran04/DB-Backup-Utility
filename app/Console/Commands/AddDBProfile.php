<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;

class AddDBProfile extends Command
{
    protected $signature = 'backup:config:add {driver} {profile}';
    protected $description = 'Add a new database configuration profile';
    
    protected ConfigService $configService;

    public function __construct(ConfigService $configService)
    {
        parent::__construct();
        $this->configService = $configService;
    }

    public function handle()
    {
        $driver = $this->argument('driver');
        $profile = $this->argument('profile');

        $this->info("Creating new profile: $profile ($driver)");

        $input = [
            'driver'   => $driver,
            'host'     => $this->ask('Database host', '127.0.0.1'),
            'port'     => $this->ask('Port', 3306),
            'database' => $this->ask('Database name'),
            'username' => $this->ask('Username'),
            'password' => $this->secret('Password'),
        ];

        $validator = Validator::make($input, [
            'driver'   => 'required|in:mysql,postgresql,pgsql,postgres',
            'host'     => 'required|string',
            'port'     => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->error('Invalid input:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("- $error");
            }
            return 1;
        }

        // Encrypt the password before storing it
        $input['password'] = Crypt::encryptString($input['password']);

        $profiles = $this->configService->loadProfiles();

        if (isset($profiles[$profile])) {
            if (!$this->confirm("Profile '$profile' already exists. Overwrite?", false)) {
                $this->warn('Cancelled.');
                return 1;
            }
        }

        $profiles[$profile] = $input;
        $this->configService->saveProfiles($profiles);

        $this->info("✅ Profile '$profile' saved successfully.");
        return 0;
    }
}
