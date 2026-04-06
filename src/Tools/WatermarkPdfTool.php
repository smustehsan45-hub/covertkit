<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\PdfToolkitProcessor;
use App\ToolInterface;

final class WatermarkPdfTool implements ToolInterface
{
    use SingleFileLimits;

    public function id(): string
    {
        return 'watermark-pdf';
    }

    public function name(): string
    {
        return 'Watermark PDF';
    }

    public function description(): string
    {
        return 'Add a diagonal text watermark on every page.';
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

        $text = isset($options['watermark_text']) ? trim((string) $options['watermark_text']) : '';
        $opacityPct = isset($options['watermark_opacity']) ? (int) $options['watermark_opacity'] : 35;
        $opacityPct = max(5, min(90, $opacityPct));
        $opacity = $opacityPct / 100.0;

        $c = $GLOBALS['app_config'] ?? [];
        $maxPages = (int) ($c['max_pdf_pages'] ?? 300);

        PdfToolkitProcessor::addWatermark($inputPath, $out, $text, $opacity, $maxPages);

        $base = pathinfo($originalBasename, PATHINFO_FILENAME);
        $base = is_string($base) && $base !== '' ? preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) : 'watermarked';
        if (strlen($base) > 80) {
            $base = substr($base, 0, 80);
        }

        return [
            'output_path' => $out,
            'download_filename' => $base . '-watermarked.pdf',
            'mime' => 'application/pdf',
        ];
    }
}
