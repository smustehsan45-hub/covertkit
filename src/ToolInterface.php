<?php

declare(strict_types=1);

namespace App;

/**
 * Contract for conversion tools. Phase 2: add supportsAsync(), estimateCost(), etc.
 */
interface ToolInterface
{
    public function id(): string;

    public function name(): string;

    public function description(): string;

    public function category(): string;

    /** @return list<string> MIME types accepted */
    public function acceptedMimeTypes(): array;

    /** @return list<string> file extensions (lowercase, no dot) for client hint */
    public function acceptedExtensions(): array;

    /** Minimum number of uploaded files for this tool (e.g. merge PDF = 2). */
    public function minFiles(): int;

    /** Maximum number of uploaded files (e.g. 25 for merge). */
    public function maxFiles(): int;

    /**
     * @param array<string, mixed> $options Tool-specific (e.g. quality, max_width)
     * @return array{output_path: string, download_filename: string, mime: string}
     */
    public function process(string $inputPath, string $originalBasename, array $options = []): array;
}
