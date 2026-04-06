<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\ImageProcessor;
use App\ToolInterface;

final class ImageResizeTool implements ToolInterface
{
    use SingleFileLimits;

    private ImageProcessor $processor;

    public function __construct()
    {
        $this->processor = new ImageProcessor();
    }

    public function id(): string
    {
        return 'image-resize';
    }

    public function name(): string
    {
        return 'Image resizer';
    }

    public function description(): string
    {
        return 'Resize images so the longest side does not exceed your chosen maximum.';
    }

    public function category(): string
    {
        return 'image';
    }

    public function acceptedMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    }

    public function acceptedExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }

    public function process(string $inputPath, string $originalBasename, array $options = []): array
    {
        $out = $options['_output_path'] ?? null;
        if (!is_string($out)) {
            throw new \InvalidArgumentException('Missing output path.');
        }
        $max = isset($options['max_side']) ? (int) $options['max_side'] : 1920;
        $this->processor->resizeMaxDimension($inputPath, $out, $max);
        $ext = strtolower(pathinfo($originalBasename, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
        return [
            'output_path' => $out,
            'download_filename' => pathinfo($originalBasename, PATHINFO_FILENAME) . '.' . $ext,
            'mime' => $mime,
        ];
    }
}
