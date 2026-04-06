#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron example (hourly): php C:\path\to\tool\scripts\cleanup-temp.php
 * Linux: 0 * * * * php /var/www/tool/scripts/cleanup-temp.php
 */

$root = dirname(__DIR__);
require $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$config = $GLOBALS['app_config'];
$temp = rtrim((string) $config['storage_dir'], DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . ($config['temp_subdir'] ?? 'temp');
$ttl = (int) ($config['temp_ttl_seconds'] ?? 3600);

$cleanup = new \App\Services\TempCleanup($temp, $ttl);
$removed = $cleanup->run();

echo date('c') . " Removed {$removed} stale temp file(s).\n";
