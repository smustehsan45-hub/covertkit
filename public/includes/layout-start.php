<?php

declare(strict_types=1);

use App\Helpers\View;

$title = $pageTitle ?? 'ConvertKit';
$desc = $pageDescription ?? 'Fast, simple file conversion in your browser.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= View::e($desc) ?>">
    <title><?= View::e($title) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <div class="container header-inner">
            <a class="logo" href="index.php"><?= View::e($config['app_name'] ?? 'ConvertKit') ?></a>
            <nav class="nav" aria-label="Main">
                <a href="index.php#image-tools">Images</a>
                <a href="index.php#document-tools">Documents</a>
                <a href="index.php#pdf-tools">PDF</a>
            </nav>
        </div>
    </header>
    <main id="main" class="main">
