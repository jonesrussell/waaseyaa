<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($path)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
try {
    (new \Symfony\Component\Dotenv\Dotenv())->loadEnv($projectRoot . '/.env');
} catch (\Symfony\Component\Dotenv\Exception\FormatException|\Symfony\Component\Dotenv\Exception\PathException $e) {
    http_response_code(500);
    error_log('Waaseyaa: Failed to load .env: ' . $e->getMessage());
    echo 'Application configuration error. Check server logs.';
    exit(1);
}

$kernel = new \Waaseyaa\Foundation\Kernel\HttpKernel($projectRoot);
$response = $kernel->handle();
$response->send();
