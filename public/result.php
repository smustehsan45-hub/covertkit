<?php

declare(strict_types=1);

use App\Helpers\View;

require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'init.php';

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$valid = $id !== '' && preg_match('/^[a-f0-9]{48}$/', $id) && isset($_SESSION['downloads'][$id]);

$pageTitle = 'Your file is ready — ' . ($config['app_name'] ?? 'ConvertKit');
$pageDescription = 'Download your converted file.';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'layout-start.php';
?>
        <div class="container result-page">
            <?php if ($valid): ?>
                <?php $fn = (string) ($_SESSION['downloads'][$id]['filename'] ?? 'download'); ?>
                <h1>Conversion complete</h1>
                <p class="lead">Your file <strong><?= View::e($fn) ?></strong> is ready.</p>
                <p><a class="btn primary" href="download.php?id=<?= View::e($id) ?>">Download</a></p>
                <p class="hint">The link works once; grab your file before leaving this page.</p>
            <?php else: ?>
                <h1>Nothing to download</h1>
                <p>This download is missing or already used. Start a new conversion from the <a href="index.php">homepage</a>.</p>
            <?php endif; ?>
        </div>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'layout-end.php';
