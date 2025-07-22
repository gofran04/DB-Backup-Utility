<?php

namespace App\Services\Contracts;

interface DatabaseAdapterInterface
{
    public function backupViaDbId(String $outputPath);

    public function backupViaProfile(array $profile,string $outputPath);

    public function testConnection();

    public function restore(string $filePath);

}
