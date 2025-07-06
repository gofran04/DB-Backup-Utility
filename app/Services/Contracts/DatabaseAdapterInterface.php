<?php

namespace App\Services\Contracts;

interface DatabaseAdapterInterface
{
    public function backup(String $outputPath);

    public function testConnection();

    public function restore(string $filePath);

}
