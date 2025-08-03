<?php

namespace App\Services\Contracts;

interface DatabaseAdapterInterface
{
    public function backupViaDbId(String $outputPath);

    public function backupViaProfile(string $outputPath);

    public function testConnection();

    public function restore(string $filePath);

    public function createDatabaseIfNotExists(): bool;
}
