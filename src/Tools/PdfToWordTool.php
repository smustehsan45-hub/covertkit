<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\PhpOfficeDocumentProcessor;
use App\ToolInterface;

final class PdfToWordTool implements ToolInterface
{
    use SingleFileLimits;

    public function id(): string
    {
        return 'pdf-to-word';
    }

    public function name(): string
    {
        return 'PDF to Word';
    }

    public function description(): string
    {
        return 'Extract text from PDF into an editable .docx. Layout and images are not preserved; scanned PDFs need OCR elsewhere.';
    }

    public function category(): string
    {
        return 'document';
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

        $config = $GLOBALS['app_config'] ?? [];
        $root = (string) ($config['project_root'] ?? dirname(__DIR__, 2));
        $processor = new PhpOfficeDocumentProcessor($root);
        $processor->pdfToDocx($inputPath, $out);

        $dl = pathinfo($originalBasename, PATHINFO_FILENAME) . '.docx';
        return [
            'output_path' => $out,
            'download_filename' => $dl,
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }
}
