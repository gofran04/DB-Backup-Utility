<?php

namespace Tests\Feature\ConsoleTests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class BackupStorageReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $testBackupPath;

    protected function setUp():void
    {
        parent::setUp();

        $basePath = storage_path('app/test-backups');
        $this->testBackupPath = $basePath . '/backups';
        File::ensureDirectoryExists($this->testBackupPath);
        Config::set('backup.storage_path', $basePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-backups'));
        parent::tearDown();
    }

    public function test_reporting_backup_storage()
    {
        // Prepare a fake backup file
        $fakeFile = $this->testBackupPath . '/test.sql.gz';
        File::put($fakeFile, str_repeat('a', 1024)); // 1 KB

        $this->artisan('backup-storage-report')
            ->expectsOutputToContain("🗂 Total Backups:")
            ->expectsOutputToContain("📦 Total Size:")
            ->expectsOutputToContain("⬆️ Largest:")
            ->expectsOutputToContain("⬇️ Smallest:")
            ->assertExitCode(0);
    }
}
