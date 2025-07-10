<?php

namespace App\Services\Compression;

interface CompressionServiceInterface
{
    public function compress(string $filePath, int $level = 6): string;
}