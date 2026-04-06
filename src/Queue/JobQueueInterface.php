<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * Phase 2: implement with Redis, database, or a worker process for FFmpeg / heavy PDF jobs.
 * enqueue() returns a job id; poll status until complete, then expose download token.
 */
interface JobQueueInterface
{
    public function enqueue(string $toolId, string $inputPath, array $options = []): string;

    public function status(string $jobId): string;
}
