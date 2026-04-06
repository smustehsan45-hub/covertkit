<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Ensures a file path stays under an allowed directory (handles Windows drive letter case, slashes).
 */
final class PathSecurity
{
    public static function fileIsInsideDirectory(string $filePath, string $directoryPath): bool
    {
        if ($filePath === '' || $directoryPath === '') {
            return false;
        }
        if (!is_file($filePath)) {
            return false;
        }

        $dirReal = realpath($directoryPath);
        if ($dirReal === false) {
            return false;
        }

        $fileReal = realpath($filePath);
        if ($fileReal === false) {
            $fileReal = $filePath;
        }

        $dirN = self::normalizePath($dirReal);
        $fileN = self::normalizePath($fileReal);

        if (PHP_OS_FAMILY === 'Windows') {
            $dirN = strtolower($dirN);
            $fileN = strtolower($fileN);
        }

        $dirN = rtrim($dirN, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($fileN, $dirN);
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        return $path;
    }
}
