<?php

declare(strict_types=1);

use App\Helpers\View;

require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'init.php';

$pageTitle = ($config['app_name'] ?? 'ConvertKit') . ' — File conversion tools';
$pageDescription = 'Convert images and documents online. JPG, PNG, PDF, Word — fast and mobile-friendly.';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'layout-start.php';

$imageTools = $registry->byCategory('image');
$docTools = $registry->byCategory('document');
$pdfTools = $registry->byCategory('pdf');
?>
        <section class="hero">
            <div class="container">
                <h1>Convert files in seconds</h1>
                <p class="lead">Upload, convert, download — no account required. Images, documents, and PDF utilities in one place.</p>
            </div>
        </section>

        <section class="container section" id="image-tools">
            <h2>Image tools</h2>
            <ul class="tool-grid" role="list">
                <?php foreach ($imageTools as $t): ?>
                    <li>
                        <a class="tool-card" href="tool.php?id=<?= View::e($t->id()) ?>">
                            <span class="tool-card-title"><?= View::e($t->name()) ?></span>
                            <span class="tool-card-desc"><?= View::e($t->description()) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="container section" id="document-tools">
            <h2>Document tools</h2>
            <ul class="tool-grid" role="list">
                <?php foreach ($docTools as $t): ?>
                    <li>
                        <a class="tool-card" href="tool.php?id=<?= View::e($t->id()) ?>">
                            <span class="tool-card-title"><?= View::e($t->name()) ?></span>
                            <span class="tool-card-desc"><?= View::e($t->description()) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="container section" id="pdf-tools">
            <h2>PDF tools</h2>
            <ul class="tool-grid" role="list">
                <?php foreach ($pdfTools as $t): ?>
                    <li>
                        <a class="tool-card" href="tool.php?id=<?= View::e($t->id()) ?>">
                            <span class="tool-card-title"><?= View::e($t->name()) ?></span>
                            <span class="tool-card-desc"><?= View::e($t->description()) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="container section muted">
            <h2>Roadmap</h2>
            <p>FFmpeg video and audio tools, WebP conversion, and queued jobs for heavy files can plug into the same architecture.</p>
        </section>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'layout-end.php';
