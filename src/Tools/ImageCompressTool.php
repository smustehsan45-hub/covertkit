<?php

declare(strict_types=1);

namespace App\Tools;

use App\Processors\ImageProcessor;
use App\ToolInterface;

final class ImageCompressTool implements ToolInterface
{
    use SingleFileLimits;

    private ImageProcessor $processor;

    public function __construct()
    {
        $this->processor = new ImageProcessor();
    }

    public function id(): string
    {
        return 'image-compress';
    }

    public function name(): string
    {
        return 'Image compressor';
    }

    public function description(): string
    {
        return 'Reduce file size for JPEG, PNG, GIF, or WebP images.';
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
        $quality = isset($options['quality']) ? (int) $options['quality'] : 75;
        $ext = strtolower(pathinfo($originalBasename, PATHINFO_EXTENSION));
        $format = match ($ext) {
            'jpg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            default => 'jpeg',
        };
        $this->processor->compress($inputPath, $out, $format, $quality);
        $mime = match ($format) {
            'jpeg' => 'image/jpeg',
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
