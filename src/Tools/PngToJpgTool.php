<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\ImageProcessor;
use App\ToolInterface;

final class PngToJpgTool implements ToolInterface
{
    use SingleFileLimits;

    private ImageProcessor $processor;

    public function __construct()
    {
        $this->processor = new ImageProcessor();
    }

    public function id(): string
    {
        return 'png-to-jpg';
    }

    public function name(): string
    {
        return 'PNG to JPG';
    }

    public function description(): string
    {
        return 'Convert PNG images to JPEG. Transparency is flattened to white.';
    }

    public function category(): string
    {
        return 'image';
    }

    public function acceptedMimeTypes(): array
    {
        return ['image/png'];
    }

    public function acceptedExtensions(): array
    {
        return ['png'];
    }

    public function process(string $inputPath, string $originalBasename, array $options = []): array
    {
        $out = $options['_output_path'] ?? null;
        if (!is_string($out)) {
            throw new \InvalidArgumentException('Missing output path.');
        }
        $quality = isset($options['quality']) ? (int) $options['quality'] : 90;
        $this->processor->pngToJpg($inputPath, $out, $quality);
        $dl = pathinfo($originalBasename, PATHINFO_FILENAME) . '.jpg';
        return ['output_path' => $out, 'download_filename' => $dl, 'mime' => 'image/jpeg'];
    }
}
