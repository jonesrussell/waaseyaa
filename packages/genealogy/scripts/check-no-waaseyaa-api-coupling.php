<?php

declare(strict_types=1);

/**
 * Enforces: no hard PHP coupling to waaseyaa/api in production genealogy sources.
 * Fails if any .php file under src/ contains Waaseyaa\Api\ (imports, type hints, etc.).
 */

$root = dirname(__DIR__) . '/src';
$violations = [];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $contents = file_get_contents($path);
    if ($contents === false) {
        continue;
    }
    // Strip block comments and docblocks loosely to reduce false positives from example text in docs
    $stripped = preg_replace('!/\*.*?\*/!s', '', $contents) ?? $contents;
    if (str_contains($stripped, 'Waaseyaa\\Api\\')) {
        $violations[] = $path;
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Genealogy package must not reference Waaseyaa\\\\Api\\\\ in src/ (optional JSON:API is host-owned).\n");
    foreach ($violations as $v) {
        fwrite(STDERR, "  - {$v}\n");
    }
    exit(1);
}

exit(0);
