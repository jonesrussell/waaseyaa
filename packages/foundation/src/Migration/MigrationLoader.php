<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration;

use Composer\InstalledVersions;
use Waaseyaa\Foundation\Discovery\PackageManifest;

final class MigrationLoader
{
    public function __construct(
        private readonly string $basePath,
        private readonly PackageManifest $manifest,
    ) {}

    /**
     * @return array<string, array<string, Migration>> package => [name => Migration]
     */
    public function loadAll(): array
    {
        $migrations = [];

        foreach ($this->manifest->migrations as $package => $path) {
            $resolved = $this->resolvePackageMigrationDirectory($package, $path);
            $loaded = $this->loadFromDirectory($resolved, $package);
            if ($loaded !== []) {
                $migrations[$package] = $loaded;
            }
        }

        $appDir = $this->basePath . '/migrations';
        $appMigrations = $this->loadFromDirectory($appDir, 'app');
        if ($appMigrations !== []) {
            $migrations['app'] = $appMigrations;
        }

        return $migrations;
    }

    private function resolvePackageMigrationDirectory(string $packageName, string $path): string
    {
        if ($path === '') {
            return $path;
        }
        if (is_dir($path)) {
            return $path;
        }
        if (class_exists(InstalledVersions::class)) {
            $install = InstalledVersions::getInstallPath($packageName);
            if (is_string($install) && $install !== '') {
                $candidate = $install . '/' . ltrim($path, '/');
                if (is_dir($candidate)) {
                    return $candidate;
                }
            }
        }

        return $this->basePath . '/vendor/' . $packageName . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, Migration> name => Migration
     */
    private function loadFromDirectory(string $directory, string $package): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.php');
        if ($files === false || $files === []) {
            return [];
        }

        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $name = $package . ':' . $filename;
            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new \RuntimeException(sprintf(
                    'Migration file "%s" must return an instance of %s.',
                    $file,
                    Migration::class,
                ));
            }

            $migrations[$name] = $migration;
        }

        return $migrations;
    }
}
