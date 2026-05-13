<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Discovery;

use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Migration\Log\Channels;
use Waaseyaa\Migration\MigrationDefinition;

/**
 * Scans filesystem paths declared in `config/waaseyaa.php`
 * `migration.manifest_paths` and yields `MigrationDefinition` instances loaded
 * from PHP files that `return new MigrationDefinition(...)` (FR-013).
 *
 * Files are walked in lexicographic order (by full path) for deterministic
 * results across runs and across filesystems with different `readdir` ordering
 * (risk R4 hedge — discovery order must not flip between developers).
 *
 * Symlinked directories are followed by default so apps can co-locate
 * migrations under a `migrations/` tree that links into vendored migration
 * packs.
 *
 * @api
 */
final class FilesystemManifestLoader
{
    private readonly LoggerInterface $logger;

    /**
     * @param list<string> $manifestPaths Absolute filesystem paths to scan. Relative paths raise {@see \InvalidArgumentException}.
     * @param ?LoggerInterface $logger Optional logger. Defaults to {@see NullLogger}.
     *
     * @throws \InvalidArgumentException When any path is not an absolute filesystem path.
     */
    public function __construct(
        private readonly array $manifestPaths,
        ?LoggerInterface $logger = null,
    ) {
        foreach ($manifestPaths as $index => $manifestPath) {
            if ($manifestPath === '') {
                throw new \InvalidArgumentException(\sprintf(
                    'FilesystemManifestLoader::$manifestPaths[%s] must be a non-empty string.',
                    \var_export($index, true),
                ));
            }
            if (!self::isAbsolutePath($manifestPath)) {
                throw new \InvalidArgumentException(\sprintf(
                    'FilesystemManifestLoader::$manifestPaths[%s] must be an absolute filesystem path; got %s.',
                    \var_export($index, true),
                    \var_export($manifestPath, true),
                ));
            }
        }

        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Walk each manifest path, load every `.php` file, assert each returns a
     * {@see MigrationDefinition}, and yield them in lexicographic path order.
     *
     * @return iterable<MigrationDefinition>
     *
     * @throws \InvalidArgumentException When a configured path does not exist.
     * @throws \RuntimeException When a `.php` file does not return a {@see MigrationDefinition} instance.
     */
    public function load(): iterable
    {
        foreach ($this->manifestPaths as $manifestPath) {
            yield from $this->loadFromPath($manifestPath);
        }
    }

    /**
     * @return iterable<MigrationDefinition>
     */
    private function loadFromPath(string $manifestPath): iterable
    {
        if (!\file_exists($manifestPath)) {
            throw new \InvalidArgumentException(\sprintf(
                'FilesystemManifestLoader: configured manifest path does not exist: %s.',
                \var_export($manifestPath, true),
            ));
        }
        if (!\is_dir($manifestPath)) {
            throw new \InvalidArgumentException(\sprintf(
                'FilesystemManifestLoader: configured manifest path is not a directory: %s.',
                \var_export($manifestPath, true),
            ));
        }

        $files = $this->collectPhpFiles($manifestPath);
        if ($files === []) {
            $this->logger->info(
                \sprintf('No migration manifest files found under %s.', $manifestPath),
                [
                    'channel' => Channels::MIGRATION_DISCOVERY,
                    'manifest_path' => $manifestPath,
                ],
            );
            return;
        }

        foreach ($files as $file) {
            yield $this->requireFile($file);
        }
    }

    /**
     * Recursively list every `.php` file under `$root`, sorted lexicographically.
     *
     * @return list<string>
     */
    private function collectPhpFiles(string $root): array
    {
        $directoryIterator = new \RecursiveDirectoryIterator(
            $root,
            \FilesystemIterator::SKIP_DOTS
                | \FilesystemIterator::CURRENT_AS_FILEINFO
                | \FilesystemIterator::FOLLOW_SYMLINKS,
        );
        $iterator = new \RecursiveIteratorIterator(
            $directoryIterator,
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $files = [];
        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            if (\strtolower($entry->getExtension()) !== 'php') {
                continue;
            }
            $real = $entry->getRealPath();
            if ($real === false) {
                continue;
            }
            $files[] = $real;
        }

        \sort($files, \SORT_STRING);

        return $files;
    }

    private function requireFile(string $file): MigrationDefinition
    {
        $loader = static function (string $__waaseyaa_migration_file): mixed {
            return require $__waaseyaa_migration_file;
        };

        $returned = $loader($file);

        if (!$returned instanceof MigrationDefinition) {
            throw new \RuntimeException(\sprintf(
                'FilesystemManifestLoader: file %s must return an instance of %s; got %s.',
                \var_export($file, true),
                MigrationDefinition::class,
                \get_debug_type($returned),
            ));
        }

        return $returned;
    }

    /**
     * True when `$candidate` is an absolute filesystem path on either POSIX
     * (leading `/`) or Windows (drive letter / UNC) systems.
     */
    private static function isAbsolutePath(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }
        if ($candidate[0] === '/' || $candidate[0] === '\\') {
            return true;
        }
        if (\strlen($candidate) >= 3 && \ctype_alpha($candidate[0]) && $candidate[1] === ':' && ($candidate[2] === '\\' || $candidate[2] === '/')) {
            return true;
        }

        return false;
    }
}
