<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\PdfToolkitProcessor;
use App\ToolInterface;

final class CompressPdfTool implements ToolInterface
{
    use SingleFileLimits;

    public function id(): string
    {
        return 'compress-pdf';
    }

    public function name(): string
    {
        return 'Compress PDF';
    }

    public function description(): string
    {
        return 'Reduce PDF file size. Uses Ghostscript when available; otherwise rewrites the PDF (results vary).';
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

        $level = isset($options['compress_level']) ? (string) $options['compress_level'] : 'ebook';
        if (!in_array($level, ['screen', 'ebook', 'printer', 'prepress'], true)) {
            $level = 'ebook';
        }

        $c = $GLOBALS['app_config'] ?? [];
        $gs = $c['ghostscript_binary'] ?? null;
        $gs = is_string($gs) && $gs !== '' ? $gs : null;

        PdfToolkitProcessor::compress($inputPath, $out, $level, $gs);

        $base = pathinfo($originalBasename, PATHINFO_FILENAME);
        $base = is_string($base) && $base !== '' ? preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) : 'compressed';
        if (strlen($base) > 80) {
            $base = substr($base, 0, 80);
        }

        return [
            'output_path' => $out,
            'download_filename' => $base . '-compressed.pdf',
            'mime' => 'application/pdf',
        ];
    }
}
