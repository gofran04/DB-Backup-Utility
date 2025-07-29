<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Mockery;

class AddDBProfileCommandTest extends TestCase
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

    public function test_add_new_profile()
    {
        $this->artisan('backup:config:add', ['driver' => 'mysql', 'profile' => 'testprofile'])
            ->expectsQuestion('Database host', 'localhost')
            ->expectsQuestion('Port', '3306')
            ->expectsQuestion('Database name', 'testdb')
            ->expectsQuestion('Username', 'testuser')
            ->expectsQuestion('Password', 'testpass')
            ->expectsOutput("Creating new profile: testprofile (mysql)")
            ->expectsOutput("✅ Profile 'testprofile' saved successfully.")
            ->assertExitCode(0);

        // Verify profile saved and password is encrypted
        $configService = $this->app->make(ConfigService::class);
        $profiles = $configService->loadProfiles();

        $this->assertArrayHasKey('testprofile', $profiles);
        $profile = $profiles['testprofile'];
        $this->assertEquals('mysql', $profile['driver']);
        $this->assertEquals('localhost', $profile['host']);
        $this->assertEquals(3306, (int) $profile['port']);
        $this->assertEquals('testdb', $profile['database']);
        $this->assertEquals('testuser', $profile['username']);

        // Password should be encrypted, so decrypt and check
        $decryptedPassword = Crypt::decryptString($profile['password']);
        $this->assertEquals('testpass', $decryptedPassword);
    }

    public function test_validation_fails()
    {
        // Pass invalid driver to trigger validation failure
        $this->artisan('backup:config:add', [
            'driver' => 'invalid',  // causes early failure, so it will never ask about other data(db,host,port,..) so no need to add them in the test, and if i add the it will cause error like:"Question "Database host" was not asked."
            'profile' => 'failprofile'
        ])
        ->expectsOutput('Creating new profile: failprofile (invalid)')
        ->expectsOutput('❌ Unsupported driver: invalid')
        ->assertExitCode(1);
    }

    public function test_add_new_profile_using_existed_profile_name_and_allow_overwrinting()
    {
        $configService = $this->app->make(ConfigService::class);

        // Save a dummy profile first
        $configService->saveProfiles([
            'existprofile' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'db1',
                'username' => 'user1',
                'password' => Crypt::encryptString('secret1'),
            ],
        ]);

        // Run command with existing profile, confirm overwrite
        $this->artisan('backup:config:add', ['driver' => 'mysql', 'profile' => 'existprofile'])
            ->expectsQuestion('Database host', 'localhost')
            ->expectsQuestion('Port', '3306')
            ->expectsQuestion('Database name', 'testdb')
            ->expectsQuestion('Username', 'testuser')
            ->expectsQuestion('Password', 'testpass')
            ->expectsConfirmation("Profile 'existprofile' already exists. Overwrite?", 'yes')
            ->expectsOutput("Creating new profile: existprofile (mysql)")
            ->expectsOutput("✅ Profile 'existprofile' saved successfully.")
            ->assertExitCode(0);
    }

    public function test_add_new_profile_using_existed_profile_name_and_not_allow_overwrinting()
    {
        $configService = $this->app->make(ConfigService::class);

        // Save a dummy profile first
        $configService->saveProfiles([
            'existprofile' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'db1',
                'username' => 'user1',
                'password' => Crypt::encryptString('secret1'),
            ],
        ]);

        // Run command with existing profile, confirm overwrite
        $this->artisan('backup:config:add', ['driver' => 'mysql', 'profile' => 'existprofile'])
            ->expectsQuestion('Database host', 'localhost')
            ->expectsQuestion('Port', '3306')
            ->expectsQuestion('Database name', 'testdb')
            ->expectsQuestion('Username', 'testuser')
            ->expectsQuestion('Password', 'testpass')
            ->expectsConfirmation("Profile 'existprofile' already exists. Overwrite?", 'no')
            ->expectsOutput("Cancelled.")
            ->assertExitCode(1);
    }
}
