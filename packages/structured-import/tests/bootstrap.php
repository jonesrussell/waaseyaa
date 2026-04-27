<?php

declare(strict_types=1);

// Load the root autoloader (all waaseyaa/* packages are registered there).
// __DIR__ is packages/structured-import/tests — walk up three levels to reach the monorepo root.
require_once __DIR__ . '/../../../vendor/autoload.php';

// Register the structured-import package namespaces since this package is not yet
// in the root composer.json. Registration is needed for standalone
// `phpunit -c packages/structured-import/phpunit.xml` runs.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Waaseyaa\\StructuredImport\\';
    $len = \strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $relative = str_replace('\\', '/', $relative);

    // Check src/ first, then tests/.
    $srcFile = __DIR__ . '/../src/' . $relative . '.php';
    if (file_exists($srcFile)) {
        require $srcFile;
        return;
    }

    $testFile = __DIR__ . '/' . $relative . '.php';
    if (file_exists($testFile)) {
        require $testFile;
    }
});
