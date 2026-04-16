<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($path)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

$kernel = new \Waaseyaa\Foundation\Kernel\HttpKernel(dirname(__DIR__));
$kernel->handle()->send();
