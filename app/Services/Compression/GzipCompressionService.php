<?php

namespace App\Services\Compression;

use App\Exceptions\CompresionFailedException;

class GzipCompressionService implements CompressionServiceInterface
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
}
