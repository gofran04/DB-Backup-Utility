<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\File;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Crypt;

class ListDBProfilesCommandTest extends TestCase
{
    protected string $testConfigDir;
    protected string $testConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a temp config path to avoid messing with real user config
        $this->testConfigDir = base_path('tests/temp-config');
        $this->testConfigPath = $this->testConfigDir . '/config.json';

        // Ensure directory exists and create empty config file
        if (!File::exists($this->testConfigDir)) {
            File::makeDirectory($this->testConfigDir, 0755, true);
        }
        File::put($this->testConfigPath, json_encode(['profiles' => []], JSON_PRETTY_PRINT));

        // Mock ConfigService to use our test config path
        $this->app->bind(ConfigService::class, function () {
            $mock = new class($this->testConfigDir, $this->testConfigPath) extends \App\Services\ConfigService {
                public function __construct($dir, $path)
                {
                    $this->configDir = $dir;
                    $this->configPath = $path;
                }
            };
            return $mock;
        });
    }

    protected function tearDown(): void
    {
        // Clean up temp config after test
        if (File::exists($this->testConfigPath)) {
            File::delete($this->testConfigPath);
        }
        if (File::exists($this->testConfigDir)) {
            File::deleteDirectory($this->testConfigDir);
        }
        parent::tearDown();
    }

    public function test_list_profiles()
    {
        $configService = $this->app->make(ConfigService::class);

        // Save a dummy profile first
        $configService->saveProfiles([
            'temp_profile' => [
                'driver'   => 'mysql',
                'host'     => '127.0.0.1',
                'port'     => 3306,
                'database' => 'db1',
                'username' => 'user1',
                'password' => Crypt::encryptString('secret1'),
            ],
        ]);

        $this->artisan('backup:config:list')
        ->expectsOutput("🗂  Saved profiles:")
        ->expectsOutput("- temp_profile (Driver: mysql, DB: db1)")
        ->assertExitCode(0);
    }

    public function test_list_profiles_from_empty_config_file()
    {
        $this->artisan('backup:config:list')
        ->expectsOutput("No profiles found.")
        ->assertExitCode(0);
    }

    public function test_list_profiles_with_missing_config_file()
    {
        // Delete config to simulate missing file
        if (file_exists($this->testConfigPath)) {
            unlink($this->testConfigPath);
        }

        $this->artisan('backup:config:list')
            ->expectsOutput('No profiles found.')
            ->assertExitCode(0);
    }
}
