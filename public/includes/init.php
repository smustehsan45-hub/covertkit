<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$config = $GLOBALS['app_config'];
$registry = new \App\ToolRegistry($config);
$storage = new \App\Services\FileStorage($config);
$validator = new \App\Services\UploadValidator((int) $config['max_upload_bytes']);

function app_csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function app_verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function app_output_extension(\App\ToolInterface $tool, string $originalBasename): string
{
    return match ($tool->id()) {
        'jpg-to-png' => 'png',
        'png-to-jpg' => 'jpg',
        'pdf-to-word' => 'docx',
        'word-to-pdf' => 'pdf',
        'merge-pdf', 'compress-pdf', 'watermark-pdf' => 'pdf',
        'split-pdf' => 'zip',
        'image-compress', 'image-resize' => strtolower(pathinfo($originalBasename, PATHINFO_EXTENSION) ?: 'jpg'),
        default => 'bin',
    };
}

/**
 * Normalize PHP's $_FILES entry for single or multiple upload (name="files[]").
 *
 * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
 */
function app_normalize_files_upload(?array $field): array
{
    if ($field === null || !isset($field['tmp_name'])) {
        return [];
    }
    if (!is_array($field['tmp_name'])) {
        return [[
            'name' => (string) ($field['name'] ?? ''),
            'type' => (string) ($field['type'] ?? ''),
            'tmp_name' => (string) $field['tmp_name'],
            'error' => (int) ($field['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($field['size'] ?? 0),
        ]];
    }
    $out = [];
    $n = count($field['tmp_name']);
    for ($i = 0; $i < $n; $i++) {
        $out[] = [
            'name' => (string) ($field['name'][$i] ?? ''),
            'type' => (string) ($field['type'][$i] ?? ''),
            'tmp_name' => (string) $field['tmp_name'][$i],
            'error' => (int) ($field['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($field['size'][$i] ?? 0),
        ];
    }

    return $out;
}

function app_register_download(array $config, string $path, string $filename, string $mime): string
{
    $tempDir = rtrim((string) ($config['storage_dir'] ?? ''), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . ($config['temp_subdir'] ?? 'temp');

    if (!\App\Helpers\PathSecurity::fileIsInsideDirectory($path, $tempDir)) {
        throw new \RuntimeException(
            'Invalid output path (file missing or not under temp). temp_dir=' . $tempDir . ' path=' . $path
        );
    }

    $fileReal = realpath($path);
    if ($fileReal === false) {
        $fileReal = $path;
    }

    $id = bin2hex(random_bytes(24));
    $_SESSION['downloads'][$id] = [
        'path' => $fileReal,
        'filename' => basename($filename),
        'mime' => $mime,
        'expires' => time() + (int) ($config['session_download_ttl'] ?? 3600),
    ];
    return $id;
}

/**
 * Send JSON and exit. Discards any accidental output (warnings) so the client always gets valid JSON.
 */
function app_json_send(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        $buffer = ob_get_clean();
        if (is_string($buffer) && trim($buffer) !== '') {
            error_log('[app_json_send] stripped ' . strlen($buffer) . ' bytes of output');
        }
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');

    $flags = JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'error' => 'Server could not build a valid response.',
        ], $flags);
    }

    echo $json;
    exit;
}
