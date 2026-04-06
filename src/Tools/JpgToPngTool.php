<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\ImageProcessor;
use App\ToolInterface;

final class JpgToPngTool implements ToolInterface
{
    use SingleFileLimits;

    private ImageProcessor $processor;

    public function __construct()
    {
        $this->processor = new ImageProcessor();
    }

    public function id(): string
    {
        return 'jpg-to-png';
    }

    public function name(): string
    {
        return 'JPG to PNG';
    }

    public function description(): string
    {
        return 'Convert JPEG images to PNG format.';
    }

    public function category(): string
    {
        return 'image';
    }

    public function acceptedMimeTypes(): array
    {
        return ['image/jpeg'];
    }

    public function acceptedExtensions(): array
    {
        return ['jpg', 'jpeg'];
    }

    public function process(string $inputPath, string $originalBasename, array $options = []): array
    {
        $out = $options['_output_path'] ?? null;
        if (!is_string($out)) {
            throw new \InvalidArgumentException('Missing output path.');
        }
        $this->processor->jpgToPng($inputPath, $out);
        $dl = pathinfo($originalBasename, PATHINFO_FILENAME) . '.png';
        return ['output_path' => $out, 'download_filename' => $dl, 'mime' => 'image/png'];
    }
}
