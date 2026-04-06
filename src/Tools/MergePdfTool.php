<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\PdfToolkitProcessor;
use App\ToolInterface;

final class MergePdfTool implements ToolInterface
{
    public function id(): string
    {
        return 'merge-pdf';
    }

    public function name(): string
    {
        return 'Merge PDF';
    }

    public function description(): string
    {
        return 'Combine multiple PDFs into one file, in the order you upload them.';
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

    public function minFiles(): int
    {
        return 2;
    }

    public function maxFiles(): int
    {
        $c = $GLOBALS['app_config'] ?? [];

        return max(2, min(50, (int) ($c['max_merge_pdf_files'] ?? 25)));
    }

    public function process(string $inputPath, string $originalBasename, array $options = []): array
    {
        $paths = $options['_paths'] ?? null;
        if (!is_array($paths) || count($paths) < 2) {
            throw new \RuntimeException('Merge requires at least two PDF files.');
        }
        $out = $options['_output_path'] ?? null;
        if (!is_string($out)) {
            throw new \InvalidArgumentException('Missing output path.');
        }

        $c = $GLOBALS['app_config'] ?? [];
        $maxPages = (int) ($c['max_pdf_pages'] ?? 300);
        PdfToolkitProcessor::merge($paths, $out, $maxPages);

        return [
            'output_path' => $out,
            'download_filename' => 'merged.pdf',
            'mime' => 'application/pdf',
        ];
    }
}
