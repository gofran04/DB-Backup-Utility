<?php

namespace App\Services\Compression;

use App\Exceptions\CompresionFailedException;
use App\Exceptions\RestoreFailedException;


class GzipCompressionService implements CompressionServiceInterface, DecompressionServiceInterface
{
    public function compress(string $filePath, int $level = 6): string
    {
        if (!file_exists($filePath)) {
            throw new CompresionFailedException("File not found: {$filePath}");
        }

        $gzPath = $filePath . '.gz';

        $fileData = file_get_contents($filePath);

        if ($fileData === false) {
            throw new CompresionFailedException("Failed to read file: {$filePath}");
        }

        $gzData = gzencode($fileData, $level);

        if ($gzData === false) {
            throw new CompresionFailedException("Failed to compress file: {$filePath}");
        }

        $written = file_put_contents($gzPath, $gzData);

        if ($written === false) {
            throw new CompresionFailedException("Failed to write compressed file: {$gzPath}");
        }

        return $gzPath;
    }

    public function decompress(string $gzFilePath): string
    {
        $fullPath = storage_path('app/' . ltrim($gzFilePath, '/'));
        if (!file_exists($fullPath)) {
            throw new RestoreFailedException("Backup file not found: {$fullPath}", 'file_not_found');
        }

        $sqlFilePath = preg_replace('/\.gz$/', '', $fullPath);

        $gzData = file_get_contents($fullPath);
        if ($gzData === false) {
            throw new RestoreFailedException("Failed to read compressed file: {$fullPath}", 'read_failed');
        }

        $sqlData = gzdecode($gzData);
        if ($sqlData === false) {
            throw new RestoreFailedException("Failed to decompress file: {$fullPath}", 'decompression_failed');
        }

        $written = file_put_contents($sqlFilePath, $sqlData);
        if ($written === false) {
            throw new RestoreFailedException("Failed to write decompressed file: {$fullPath}", 'write_failed');
        }

        return $sqlFilePath;
    }
}
