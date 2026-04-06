<?php

declare(strict_types=1);

namespace App\Tools;

trait SingleFileLimits
{
    public function minFiles(): int
    {
        return 1;
    }

    public function maxFiles(): int
    {
        return 1;
    }
}
