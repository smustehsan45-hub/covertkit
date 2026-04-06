<?php

declare(strict_types=1);

namespace App\Services;

use App\ToolInterface;

final class UploadValidator
{
    public function __construct(
        private int $maxBytes
    ) {
    }

    /**
     * @return array{ok: true, tmp_path: string, original_name: string, size: int, mime: string}|array{ok: false, error: string}
     */
    public function validateFileArray(array $file, ToolInterface $tool): array
    {
        if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
            return ['ok' => false, 'error' => 'Invalid upload payload.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $this->uploadErrorMessage((int) $file['error'])];
        }

        $size = (int) $file['size'];
        if ($size <= 0) {
            return ['ok' => false, 'error' => 'Empty file.'];
        }
        if ($size > $this->maxBytes) {
            return ['ok' => false, 'error' => 'File exceeds maximum allowed size (' . $this->formatBytes($this->maxBytes) . ').'];
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Upload verification failed.'];
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowedExt = $tool->acceptedExtensions();
        if ($allowedExt !== [] && !in_array($ext, $allowedExt, true)) {
            return ['ok' => false, 'error' => 'File extension not allowed for this tool.'];
        }

        $mime = $this->detectMime($tmp);
        $allowed = $tool->acceptedMimeTypes();
        if ($allowed !== [] && !in_array($mime, $allowed, true)) {
            $fallback = $mime === 'application/octet-stream' && $allowedExt !== [] && in_array($ext, $allowedExt, true);
            if (!$fallback) {
                return ['ok' => false, 'error' => 'File type not allowed for this tool.'];
            }
        }

        return [
            'ok' => true,
            'tmp_path' => $tmp,
            'original_name' => basename((string) $file['name']),
            'size' => $size,
            'mime' => $mime,
        ];
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $m = finfo_file($f, $path) ?: 'application/octet-stream';
                finfo_close($f);
                return $m;
            }
        }
        return 'application/octet-stream';
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large.',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            default => 'Upload failed.',
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024) . ' KB';
    }
}
