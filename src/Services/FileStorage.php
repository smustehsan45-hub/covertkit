<?php

declare(strict_types=1);

namespace App\Services;

final class FileStorage
{
    private string $tempDir;

    private string $uploadDir;

    public function __construct(private array $config)
    {
        $root = rtrim((string) ($config['storage_dir'] ?? ''), DIRECTORY_SEPARATOR);
        $temp = $root . DIRECTORY_SEPARATOR . ($config['temp_subdir'] ?? 'temp');
        $up = $root . DIRECTORY_SEPARATOR . ($config['upload_subdir'] ?? 'uploads');
        foreach ([$root, $temp, $up] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
        }
        $this->tempDir = $temp;
        $this->uploadDir = $up;
    }

    public function tempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * Copy uploaded file to a unique path under temp (never use user-provided names on disk).
     */
    public function storeUpload(string $uploadedTmp, string $extension): string
    {
        $ext = preg_match('/^[a-z0-9]{1,10}$/i', $extension) ? strtolower($extension) : 'bin';
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $this->tempDir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($uploadedTmp, $dest)) {
            if (!copy($uploadedTmp, $dest)) {
                throw new \RuntimeException('Could not store upload.');
            }
            @unlink($uploadedTmp);
        }
        return $dest;
    }

    /** Store arbitrary processed output in temp */
    public function writeOutput(string $contents, string $extension): string
    {
        $ext = preg_match('/^[a-z0-9]{1,10}$/i', $extension) ? strtolower($extension) : 'bin';
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $this->tempDir . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($dest, $contents) === false) {
            throw new \RuntimeException('Could not write output.');
        }
        return $dest;
    }

    public function randomOutputPath(string $extension): string
    {
        $ext = preg_match('/^[a-z0-9]{1,10}$/i', $extension) ? strtolower($extension) : 'bin';
        return $this->tempDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.' . $ext;
    }
}
