<?php

/**
 * Application configuration.
 * Phase 2: increase max_upload_bytes, add queue/redis settings, FFmpeg paths.
 */
return [
    'app_name' => 'ConvertKit',
    /** Set APP_DEBUG=1 in the environment to include error details in JSON (dev only). */
    'debug' => getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true',
    'max_upload_bytes' => (int) ((getenv('MAX_UPLOAD_MB') ?: '20')) * 1024 * 1024,
    'temp_ttl_seconds' => 3600,
    'session_download_ttl' => 3600,

    // Absolute project root (where composer.json and /vendor live)
    'project_root' => dirname(__DIR__),

    // Paths under project root
    'storage_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage',
    'temp_subdir' => 'temp',
    'upload_subdir' => 'uploads',

    /** Merge PDF: max files and combined upload size */
    'max_merge_pdf_files' => 25,
    'max_merge_total_bytes' => 100 * 1024 * 1024,
    /** Total pages limit per job (merge sum or single PDF split/watermark) */
    'max_pdf_pages' => 300,
    /** Optional full path to Ghostscript (gs / gswin64c) for better PDF compression */
    'ghostscript_binary' => getenv('GHOSTSCRIPT_PATH') ?: null,

    'tools' => [
        // Phase 1 — implemented
        'jpg-to-png' => \App\Tools\JpgToPngTool::class,
        'png-to-jpg' => \App\Tools\PngToJpgTool::class,
        'image-compress' => \App\Tools\ImageCompressTool::class,
        'image-resize' => \App\Tools\ImageResizeTool::class,
        'pdf-to-word' => \App\Tools\PdfToWordTool::class,
        'word-to-pdf' => \App\Tools\WordToPdfTool::class,
        'merge-pdf' => \App\Tools\MergePdfTool::class,
        'split-pdf' => \App\Tools\SplitPdfTool::class,
        'compress-pdf' => \App\Tools\CompressPdfTool::class,
        'watermark-pdf' => \App\Tools\WatermarkPdfTool::class,
    ],
];
