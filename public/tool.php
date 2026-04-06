<?php

declare(strict_types=1);

use App\Helpers\View;

require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'init.php';

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$tool = $registry->get($id);
if ($tool === null) {
    http_response_code(404);
    exit('Tool not found.');
}

$pageTitle = $tool->name() . ' — ' . ($config['app_name'] ?? 'ConvertKit');
$pageDescription = $tool->description();
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'layout-start.php';

$csrf = app_csrf_token();
$maxBytes = (int) ($config['max_upload_bytes'] ?? 0);
$acceptExts = implode(',', $tool->acceptedExtensions());
$acceptAttr = implode(',', array_map(static function (string $e): string {
    return '.' . $e;
}, $tool->acceptedExtensions()));
$isMulti = $tool->minFiles() > 1;
$minFiles = $tool->minFiles();
$maxFiles = $tool->maxFiles();
?>
        <div class="container tool-page">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a>
                <span aria-hidden="true">/</span>
                <span><?= View::e($tool->name()) ?></span>
            </nav>

            <header class="tool-header">
                <h1><?= View::e($tool->name()) ?></h1>
                <p class="tool-desc"><?= View::e($tool->description()) ?></p>
            </header>

            <div
                class="converter-panel"
                id="converter"
                data-tool-id="<?= View::e($tool->id()) ?>"
                data-csrf="<?= View::e($csrf) ?>"
                data-max-bytes="<?= (string) $maxBytes ?>"
                data-accept-ext="<?= View::e($acceptExts) ?>"
                data-accept-attr="<?= View::e($acceptAttr) ?>"
                data-multi="<?= $isMulti ? '1' : '0' ?>"
                data-min-files="<?= (string) $minFiles ?>"
                data-max-files="<?= (string) $maxFiles ?>"
                data-api-url="api/convert.php"
            >
                <div
                    class="dropzone"
                    id="dropzone"
                    tabindex="0"
                    role="button"
                    aria-label="<?= $isMulti ? 'Drop PDF files here or browse' : 'Drop file here or browse' ?>"
                >
                    <input
                        type="file"
                        id="file-input"
                        class="visually-hidden"
                        aria-label="<?= $isMulti ? 'Choose PDF files' : 'Choose file' ?>"
                        accept="<?= View::e($acceptAttr) ?>"
                        <?= $isMulti ? 'multiple' : '' ?>
                    >
                    <div class="dropzone-inner">
                        <span class="dropzone-icon" aria-hidden="true">↑</span>
                        <p>
                            <?php if ($isMulti): ?>
                                <strong>Drag &amp; drop</strong> PDFs here (order is kept), or <span class="link-like">browse</span>
                            <?php else: ?>
                                <strong>Drag &amp; drop</strong> your file here, or <span class="link-like">browse</span>
                            <?php endif; ?>
                        </p>
                        <p class="hint">
                            <?php if ($isMulti): ?>
                                Need <?= (int) $minFiles ?>–<?= (int) $maxFiles ?> files · <?= View::e(implode(', ', $tool->acceptedExtensions())) ?> · <?= View::e((string) round($maxBytes / 1048576, 1)) ?> MB each
                            <?php else: ?>
                                Accepted: <?= View::e(implode(', ', $tool->acceptedExtensions())) ?> · Max <?= View::e((string) round($maxBytes / 1048576, 1)) ?> MB
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (in_array($tool->id(), ['image-compress', 'png-to-jpg'], true)): ?>
                    <div class="field">
                        <label for="quality">Quality (1–100)</label>
                        <input type="range" id="quality" name="quality" min="1" max="100" value="<?= $tool->id() === 'png-to-jpg' ? '90' : '75' ?>">
                        <output id="quality-out" for="quality"><?= $tool->id() === 'png-to-jpg' ? '90' : '75' ?></output>
                    </div>
                <?php endif; ?>

                <?php if ($tool->id() === 'image-resize'): ?>
                    <div class="field">
                        <label for="max_side">Max longest side (px)</label>
                        <select id="max_side" name="max_side">
                            <option value="800">800</option>
                            <option value="1200">1200</option>
                            <option value="1920" selected>1920</option>
                            <option value="2560">2560</option>
                            <option value="3840">3840</option>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($tool->id() === 'split-pdf'): ?>
                    <div class="field">
                        <label for="split_every">Pages per split file</label>
                        <select id="split_every" name="split_every">
                            <option value="1" selected>1 (each page → separate PDF)</option>
                            <option value="2">2</option>
                            <option value="5">5</option>
                            <option value="10">10</option>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($tool->id() === 'compress-pdf'): ?>
                    <div class="field">
                        <label for="compress_level">Compression</label>
                        <select id="compress_level" name="compress_level">
                            <option value="screen">Smallest (screen)</option>
                            <option value="ebook" selected>Balanced (ebook)</option>
                            <option value="printer">Higher quality (printer)</option>
                            <option value="prepress">Highest (prepress)</option>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($tool->id() === 'watermark-pdf'): ?>
                    <div class="field field-stack">
                        <label for="watermark_text">Watermark text</label>
                        <input type="text" id="watermark_text" name="watermark_text" maxlength="200" placeholder="e.g. CONFIDENTIAL" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="watermark_opacity">Opacity (5–90%)</label>
                        <input type="range" id="watermark_opacity" name="watermark_opacity" min="5" max="90" value="35">
                        <output id="watermark-opacity-out" for="watermark_opacity">35</output>
                    </div>
                <?php endif; ?>

                <div class="file-meta" id="file-meta" hidden>
                    <span class="name" id="file-name"></span>
                    <span class="size" id="file-size"></span>
                    <span class="status" id="file-status"></span>
                </div>

                <p class="error-msg" id="error-msg" role="alert" hidden></p>

                <div class="actions">
                    <button type="button" class="btn primary" id="btn-convert" disabled>Convert</button>
                    <a class="btn secondary" id="btn-download" href="#" hidden>Download result</a>
                    <a class="btn ghost" id="btn-result-page" href="#" hidden>Result page</a>
                </div>

                <div class="progress-wrap" id="progress-wrap" hidden aria-live="polite">
                    <div class="progress-bar" id="progress-bar" style="--p: 0%"></div>
                    <span class="progress-label" id="progress-label">Processing…</span>
                </div>
            </div>
        </div>
<?php
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'layout-end.php';
