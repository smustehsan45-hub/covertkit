<?php

declare(strict_types=1);

ob_start();

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json_send(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$csrf = $_POST['csrf'] ?? '';
if (!app_verify_csrf(is_string($csrf) ? $csrf : null)) {
    app_json_send(['success' => false, 'error' => 'Invalid session. Refresh the page and try again.'], 403);
}

$toolId = isset($_POST['tool_id']) ? (string) $_POST['tool_id'] : '';
$tool = $registry->get($toolId);
if ($tool === null) {
    app_json_send(['success' => false, 'error' => 'Unknown tool.'], 400);
}

$storedPaths = [];
$firstOriginal = '';

if ($tool->minFiles() > 1) {
    $list = app_normalize_files_upload($_FILES['files'] ?? null);
    if (count($list) < $tool->minFiles()) {
        app_json_send(['success' => false, 'error' => 'Please select at least ' . $tool->minFiles() . ' PDF files.'], 400);
    }
    if (count($list) > $tool->maxFiles()) {
        app_json_send(['success' => false, 'error' => 'Too many files (maximum ' . $tool->maxFiles() . ').'], 400);
    }

    $maxMergeBytes = (int) ($config['max_merge_total_bytes'] ?? (100 * 1048576));
    $sum = 0;
    foreach ($list as $item) {
        if (($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            app_json_send(['success' => false, 'error' => 'One of the uploads failed. Try again.'], 400);
        }
        $sum += (int) ($item['size'] ?? 0);
    }
    if ($toolId === 'merge-pdf' && $sum > $maxMergeBytes) {
        app_json_send(['success' => false, 'error' => 'Combined file size is too large for merge.'], 400);
    }

    try {
        foreach ($list as $item) {
            $check = $validator->validateFileArray($item, $tool);
            if (!$check['ok']) {
                app_json_send(['success' => false, 'error' => $check['error']], 400);
            }
            /** @var array{tmp_path: string, original_name: string, size: int, mime: string} $check */
            $ext = strtolower(pathinfo($check['original_name'], PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = 'pdf';
            }
            $storedPaths[] = $storage->storeUpload($check['tmp_path'], $ext);
            if ($firstOriginal === '') {
                $firstOriginal = $check['original_name'];
            }
        }
    } catch (\Throwable $e) {
        foreach ($storedPaths as $p) {
            @unlink($p);
        }
        app_json_send(['success' => false, 'error' => 'Could not store uploaded files.'], 500);
    }
} else {
    if (!isset($_FILES['file'])) {
        app_json_send(['success' => false, 'error' => 'No file uploaded.'], 400);
    }

    $check = $validator->validateFileArray($_FILES['file'], $tool);
    if (!$check['ok']) {
        app_json_send(['success' => false, 'error' => $check['error']], 400);
    }

    /** @var array{tmp_path: string, original_name: string, size: int, mime: string} $check */
    $ext = strtolower(pathinfo($check['original_name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = match ($tool->id()) {
            'jpg-to-png' => 'jpg',
            'png-to-jpg' => 'png',
            'split-pdf', 'compress-pdf', 'watermark-pdf', 'pdf-to-word' => 'pdf',
            default => 'bin',
        };
    }

    try {
        $storedPaths[] = $storage->storeUpload($check['tmp_path'], $ext);
    } catch (\Throwable $e) {
        app_json_send(['success' => false, 'error' => 'Could not store file.'], 500);
    }
    $firstOriginal = $check['original_name'];
}

$outExt = app_output_extension($tool, $firstOriginal);
$outputPath = $storage->randomOutputPath($outExt);

$options = [
    '_output_path' => $outputPath,
];

if ($tool->minFiles() > 1) {
    $options['_paths'] = $storedPaths;
}

if (isset($_POST['quality'])) {
    $options['quality'] = max(1, min(100, (int) $_POST['quality']));
}
if (isset($_POST['max_side'])) {
    $options['max_side'] = max(32, min(8192, (int) $_POST['max_side']));
}
if (isset($_POST['split_every'])) {
    $options['split_every'] = max(1, min(100, (int) $_POST['split_every']));
}
if (isset($_POST['compress_level'])) {
    $lvl = strtolower(trim((string) $_POST['compress_level']));
    $options['compress_level'] = in_array($lvl, ['screen', 'ebook', 'printer', 'prepress'], true) ? $lvl : 'ebook';
}
if (isset($_POST['watermark_text'])) {
    $options['watermark_text'] = (string) $_POST['watermark_text'];
}
if (isset($_POST['watermark_opacity'])) {
    $options['watermark_opacity'] = max(5, min(90, (int) $_POST['watermark_opacity']));
}

try {
    $result = $tool->process($storedPaths[0], $firstOriginal, $options);
} catch (\Throwable $e) {
    foreach ($storedPaths as $p) {
        @unlink($p);
    }
    @unlink($outputPath);
    error_log('[convert] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $docTools = ['pdf-to-word', 'word-to-pdf', 'merge-pdf', 'split-pdf', 'compress-pdf', 'watermark-pdf'];
    if (in_array($toolId, $docTools, true)) {
        $userError = $e instanceof \RuntimeException
            ? $e->getMessage()
            : ('Document conversion failed: ' . $e->getMessage());
    } elseif ($e instanceof \RuntimeException) {
        $userError = $e->getMessage();
    } else {
        $userError = 'Conversion failed. Check the file format and try again.';
    }
    if (strlen($userError) > 500) {
        $userError = substr($userError, 0, 497) . '...';
    }

    $payload = ['success' => false, 'error' => $userError];
    if (!empty($config['debug'])) {
        $payload['detail'] = $e->getMessage();
    }
    app_json_send($payload, 500);
}

foreach ($storedPaths as $p) {
    @unlink($p);
}

$outPath = $result['output_path'] ?? '';
if ($outPath === '' || !is_file($outPath) || filesize($outPath) === 0) {
    error_log('[convert] Empty or missing output: ' . $outPath);
    @unlink($outPath);
    $payload = ['success' => false, 'error' => 'Conversion produced no output file.'];
    if (!empty($config['debug'])) {
        $payload['detail'] = 'output_path=' . $outPath;
    }
    app_json_send($payload, 500);
}

try {
    $downloadId = app_register_download($config, $result['output_path'], $result['download_filename'], $result['mime']);
} catch (\Throwable $e) {
    @unlink($result['output_path']);
    error_log('[convert register] ' . $e->getMessage());
    $payload = ['success' => false, 'error' => 'Could not prepare download.'];
    if (!empty($config['debug'])) {
        $payload['detail'] = $e->getMessage();
    }
    app_json_send($payload, 500);
}

app_json_send([
    'success' => true,
    'download_id' => $downloadId,
    'filename' => (string) ($result['download_filename'] ?? 'download'),
]);
