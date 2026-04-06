<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\PdfToolkitProcessor;
use App\ToolInterface;

final class SplitPdfTool implements ToolInterface
{
    use SingleFileLimits;

    public function id(): string
    {
        return 'split-pdf';
    }

    public function name(): string
    {
        return 'Split PDF';
    }

    public function description(): string
    {
        return 'Split a PDF into smaller PDFs (by page count) and download as a ZIP.';
    }

    public function category(): string
    {
        return 'pdf';
    }

    public function acceptedMimeTypes(): array
    {
        return ['application/pdf'];
    }

    public function acceptedExtensions(): array
    {
        return ['pdf'];
    }

    public function process(string $inputPath, string $originalBasename, array $options = []): array
    {
        $out = $options['_output_path'] ?? null;
        if (!is_string($out)) {
            throw new \InvalidArgumentException('Missing output path.');
        }

        $every = isset($options['split_every']) ? (int) $options['split_every'] : 1;
        $every = max(1, min(100, $every));

        $c = $GLOBALS['app_config'] ?? [];
        $maxPages = (int) ($c['max_pdf_pages'] ?? 300);

        if (!extension_loaded('zip')) {
            throw new \RuntimeException('PHP zip extension is required for split. Enable extension=zip in php.ini.');
        }

        PdfToolkitProcessor::splitToZip($inputPath, $out, $every, $maxPages);

        $base = pathinfo($originalBasename, PATHINFO_FILENAME);
        $base = is_string($base) && $base !== '' ? preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) : 'split';
        if (strlen($base) > 80) {
            $base = substr($base, 0, 80);
        }

        return [
            'output_path' => $out,
            'download_filename' => $base . '-split.zip',
            'mime' => 'application/zip',
        ];
    }
}
