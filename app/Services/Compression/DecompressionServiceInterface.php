<?php
namespace App\Services\Compression;

interface DecompressionServiceInterface
{
    public function decompress(string $gzFilePath): string;
}