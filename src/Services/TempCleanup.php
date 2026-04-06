<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deletes files under storage/temp older than TTL. Run via cron:
 * php scripts/cleanup-temp.php
 */
final class TempCleanup
{
    public function __construct(
        private string $tempDir,
        private int $maxAgeSeconds
    ) {
    }

    public function run(): int
    {
        if (!is_dir($this->tempDir)) {
            return 0;
        }
        $now = time();
        $removed = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($now - $file->getMTime() > $this->maxAgeSeconds) {
                if (@unlink($file->getPathname())) {
                    ++$removed;
                }
            }
        }
        return $removed;
    }
}
