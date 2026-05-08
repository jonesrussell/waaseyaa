<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\Command\Make\AbstractMakeHandler;
use Waaseyaa\Foundation\Discovery\PackageManifest;

final class MakeMigrationHandler extends AbstractMakeHandler
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?PackageManifest $manifest = null,
    ) {}

    public function execute(CliIO $io): int
    {
        $name = (string) $io->argument('name');
        $createTable = $io->option('create');
        $modifyTable = $io->option('table');
        $package = $io->option('package');

        $table = $createTable ?? $modifyTable ?? $this->guessTableName($name);

        $rendered = $this->renderStub('migration', [
            'table' => (string) $table,
        ]);

        $timestamp = date('Ymd_His');
        $filename = "{$timestamp}_{$name}.php";

        $targetDir = $this->resolveMigrationDirectory(
            $package !== null ? (string) $package : null,
            $io,
        );
        if ($targetDir === null) {
            return 1;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        file_put_contents($targetPath, $rendered);

        $relativePath = str_starts_with($targetDir, $this->projectRoot)
            ? substr($targetDir, strlen($this->projectRoot) + 1) . '/' . $filename
            : $targetPath;
        $io->writeln("Created: {$relativePath}");

        return 0;
    }

    private function guessTableName(string $name): string
    {
        $name = strtolower($name);
        // Strip common prefixes/suffixes.
        $name = preg_replace('/^(create|add|modify|update|alter)_/', '', $name) ?? $name;
        $name = preg_replace('/_(table|column|index)$/', '', $name) ?? $name;

        return $name;
    }

    private function resolveMigrationDirectory(?string $package, CliIO $io): ?string
    {
        if ($package === null) {
            return $this->projectRoot . '/migrations';
        }

        if ($this->manifest === null) {
            $io->error('PackageManifest not available. Cannot resolve package migration directory.');
            return null;
        }

        $packageMigrations = $this->manifest->migrations;
        if (!isset($packageMigrations[$package])) {
            $io->error("Package '{$package}' has no registered migration directory.");
            return null;
        }

        return $packageMigrations[$package];
    }
}
