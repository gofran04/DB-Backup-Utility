<?php

namespace App\Services\Installer;

class EnvironmentCheckService 
{
    public function checkPhpVersion(): array
    {
        $minVersion = $this->getPhpRequirementFromComposer(base_path('composer.json'));
        if (!$minVersion) {
            return ['status' => false, 'message' => "⚠️ No PHP version requirement found in composer.json."];
        }

        $cleanVersion = preg_replace('/[^0-9.]/', '', $minVersion);
        $installedVersion = phpversion();

        if (version_compare($installedVersion, $cleanVersion, '>=')) {
            return ['status' => true, 'message' => "✅ PHP version $installedVersion meets the requirement ($minVersion)."];
        }
        return ['status' => false, 'message' => "❌ PHP version $installedVersion does not meet the requirement ($minVersion)."];
    }

    public function checkMySqlAvailability(): array
    {
        $mysqlCmd = (stripos(PHP_OS, 'WIN') === 0) ? "where mysql" : "which mysql";
        $mysqldumpCmd = (stripos(PHP_OS, 'WIN') === 0) ? "where mysqldump" : "which mysqldump";

        exec($mysqlCmd, $out1, $status1);
        exec($mysqldumpCmd, $out2, $status2);

        if ($status1 === 0 && $status2 === 0) {
            return ['status' => true, 'message' => "✅ MySQL and mysqldump are available."];
        }
        return ['status' => false, 'message' => "❌ MySQL or mysqldump is missing."];
    }

    public function checkPostgresAvailability(): array
    {
        $psqlCmd = (stripos(PHP_OS, 'WIN') === 0) ? "where psql" : "which psql";
        $pgDumpCmd = (stripos(PHP_OS, 'WIN') === 0) ? "where pg_dump" : "which pg_dump";

        exec($psqlCmd, $out1, $status1);
        exec($pgDumpCmd, $out2, $status2);

        if ($status1 === 0 && $status2 === 0) {
            return ['status' => true, 'message' => "✅ PostgreSQL and pg_dump are available."];
        }
        return ['status' => false, 'message' => "❌ PostgreSQL or pg_dump is missing."];
    }


    public function checkStorageWritable(string $path = null): array
    {
        $path = $path ?? config('backup.storage_path').'/backup';

        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        if (is_writable($path)) {
            return ['status' => true, 'message' => "✅ Storage path is writable: $path"];
        }
        return ['status' => false, 'message' => "❌ Storage path is not writable: $path"];
    }

    function getPhpRequirementFromComposer(string $composerPath): ?string
    {
        if (!file_exists($composerPath)) {
            return null;
        }

        $composerData = json_decode(file_get_contents($composerPath), true);
        
        return $composerData['require']['php'] ?? null;
    }
}