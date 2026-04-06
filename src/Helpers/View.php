<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
