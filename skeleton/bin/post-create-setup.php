<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envExample = $root . '/.env.example';
$envFile = $root . '/.env';

if (!file_exists($envFile) && file_exists($envExample)) {
    $content = file_get_contents($envExample);
    $secret = bin2hex(random_bytes(32));
    $appName = ucwords(str_replace(['-', '_'], ' ', basename($root)));
    if (str_contains($appName, ' ')) {
        $appName = '"' . $appName . '"';
    }
    $content = str_replace('WAASEYAA_JWT_SECRET=', "WAASEYAA_JWT_SECRET={$secret}", $content);
    if (str_contains($content, 'APP_NAME=Waaseyaa')) {
        $content = str_replace('APP_NAME=Waaseyaa', "APP_NAME={$appName}", $content);
    } else {
        fwrite(STDERR, "Warning: Could not set APP_NAME — placeholder not found in .env.example.\n");
    }
    file_put_contents($envFile, $content);
}

echo "\n";
$dir = basename($root);

echo "  \033[32mWaaseyaa project ready.\033[0m\n";
echo "\n";
echo "  \033[33mcd {$dir}\033[0m\n";
echo "  \033[33mcomposer run dev\033[0m      Start the dev server\n";
echo "  \033[33mbin/waaseyaa list\033[0m     See all commands\n";
echo "\n";
