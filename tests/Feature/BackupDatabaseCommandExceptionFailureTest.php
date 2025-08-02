<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use App\Services\ConfigService;
use App\Exceptions\BackupFailedException;
use App\Services\DatabaseBackupService;
use Mockery;

class BackupDatabaseCommandExceptionFailureTest extends TestCase
{
    use RefreshDatabase;
    protected string $testConfigDir;
    protected string $testConfigPath;


    protected function setUp():void
    {
        parent::setUp();
  
        $testBackupPath = storage_path('app/test-backups');
        File::ensureDirectoryExists($testBackupPath);
        Config::set('backup.storage_path', $testBackupPath);

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
        File::deleteDirectory(storage_path('app/test-backups'));
        // Clean up temp config after test
        if (File::exists($this->testConfigPath)) {
            File::delete($this->testConfigPath);
        }
        if (File::exists($this->testConfigDir)) {
            File::deleteDirectory($this->testConfigDir);
        }

        Mockery::close();
        parent::tearDown();
    }

    public function test_backup_fails_and_exception_thrown_and_caught()
    {
        $mock = Mockery::mock('overload:' . DatabaseBackupService::class);
        $mock->shouldReceive('backupUsingDbId')
            ->andThrow(new BackupFailedException('Backup Operation Failed.', 'failed'));

        $db_connection = DatabaseConnection::factory()->create();

        $this->artisan('db:backup',['id' => $db_connection->id])
            // ->expectsOutput('❌ Backup failed: Backup Operation Failed.')
            ->assertExitCode(1);//failure


    }
}