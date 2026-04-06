<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\PhpOfficeDocumentProcessor;
use App\ToolInterface;

final class WordToPdfTool implements ToolInterface
{
    use SingleFileLimits;

    public function id(): string
    {
        return 'word-to-pdf';
    }

    public function name(): string
    {
        return 'Word to PDF';
    }

    public function description(): string
    {
        return 'Convert Word (.doc, .docx) to PDF on the server — no LibreOffice needed.';
    }

    public function category(): string
    {
        return 'document';
    }

    public function acceptedMimeTypes(): array
    {
        return [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ];
    }

    public function acceptedExtensions(): array
    {
        return ['doc', 'docx'];
    }

    public function process(string $inputPath, string $originalBasename, array $options = []): array
    {
        $out = $options['_output_path'] ?? null;
        if (!is_string($out)) {
            throw new \InvalidArgumentException('Missing output path.');
        }

        $config = $GLOBALS['app_config'] ?? [];
        $root = (string) ($config['project_root'] ?? dirname(__DIR__, 2));
        $processor = new PhpOfficeDocumentProcessor($root);
        $processor->wordToPdf($inputPath, $out);

        $base = pathinfo($originalBasename, PATHINFO_FILENAME);
        $base = is_string($base) ? $base : 'document';
        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $base);
            if (is_string($clean) && $clean !== '') {
                $base = $clean;
            }
        }
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? $base;
        $base = trim($base) !== '' ? trim($base) : 'document';
        if (strlen($base) > 150) {
            $base = substr($base, 0, 150);
        }
        $dl = $base . '.pdf';
        return [
            'output_path' => $out,
            'download_filename' => $dl,
            'mime' => 'application/pdf',
        ];
    }
}
