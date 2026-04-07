<?php

declare(strict_types=1);

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
