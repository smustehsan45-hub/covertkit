<?php

declare(strict_types=1);

$vendorAutoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_readable($vendorAutoload)) {
    require $vendorAutoload;
}

$configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
if (!is_readable($configPath)) {
    http_response_code(500);
    exit('Configuration missing.');
}

$GLOBALS['app_config'] = require $configPath;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});
