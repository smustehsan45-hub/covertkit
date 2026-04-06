<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'init.php';

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if ($id === '' || !preg_match('/^[a-f0-9]{48}$/', $id)) {
    http_response_code(400);
    exit('Invalid request.');
}

$entry = $_SESSION['downloads'][$id] ?? null;
if (!is_array($entry)) {
    http_response_code(404);
    exit('Download not found or expired.');
}

if (time() > (int) ($entry['expires'] ?? 0)) {
    unset($_SESSION['downloads'][$id]);
    @unlink((string) ($entry['path'] ?? ''));
    http_response_code(410);
    exit('Download expired.');
}

$path = (string) ($entry['path'] ?? '');
$filename = (string) ($entry['filename'] ?? 'download');
$mime = (string) ($entry['mime'] ?? 'application/octet-stream');

if (!is_file($path) || !is_readable($path)) {
    unset($_SESSION['downloads'][$id]);
    http_response_code(404);
    exit('File missing.');
}

$tempDir = rtrim((string) ($config['storage_dir'] ?? ''), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . ($config['temp_subdir'] ?? 'temp');
if (!\App\Helpers\PathSecurity::fileIsInsideDirectory($path, $tempDir)) {
    http_response_code(403);
    exit('Forbidden.');
}

unset($_SESSION['downloads'][$id]);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
@unlink($path);
exit;
