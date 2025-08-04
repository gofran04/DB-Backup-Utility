<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class BackupStorageService
{
    public function getStorageReport(): array
    {
        $backupsPath = config('backup.storage_path') . '/backups';
        $files = File::allFiles($backupsPath);

        if (empty($files)) {
            return [];
        }

        $totalSize = 0;
        $report = [];

        foreach ($files as $file) {
            $size = $file->getSize();
            $totalSize += $size;

            $report[] = [
                'path' => $file->getRelativePathname(),
                'size' => $size,
                'modified_at' => $file->getMTime(),
            ];
        }

        return [
            'count' => count($report),
            'total_size' => $totalSize,
            'largest' => collect($report)->sortByDesc('size')->first(),
            'smallest' => collect($report)->sortBy('size')->first(),
            'files' => $report,
        ];
    }
}