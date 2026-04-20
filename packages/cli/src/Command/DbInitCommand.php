<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Discovery\PackageManifestCompiler;
use Waaseyaa\Foundation\Kernel\EnvLoader;
use Waaseyaa\Foundation\Migration\MigrationLoader;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;

/**
 * Sanctioned first-deploy database initializer.
 *
 * Resolves the SQLite path from the same config chain the HTTP kernel uses,
 * creates the file and its parent directory if missing, and runs all pending
 * migrations through the standard Migrator. Safe to run on every deploy: the
 * Migrator skips already-applied migrations. Refuses to touch a database that
 * exists but does not look Waaseyaa-initialized.
 *
 * Runs outside the normal kernel boot so it can execute under APP_ENV=production
 * without tripping the DatabaseBootstrapper production guard. See ConsoleKernel::shouldUseMinimalConsole.
 */
#[AsCommand(
    name: 'db:init',
    description: 'Initialize the database on first deploy and apply pending migrations.',
)]
final class DbInitCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without creating files or running migrations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        EnvLoader::load($this->projectRoot . '/.env');
        $config = $this->loadConfig();
        $dbPath = $this->resolveDatabasePath($config);

        if ($dryRun) {
            return $this->reportDryRun($dbPath, $output);
        }

        if (!$this->ensureParentDirectory($dbPath, $output)) {
            return self::FAILURE;
        }

        $lockHandle = $this->acquireLock($dbPath, $output);
        if ($lockHandle === null) {
            return self::FAILURE;
        }

        try {
            $fresh = !is_file($dbPath);
            if ($fresh) {
                if (!$this->createDatabaseFile($dbPath, $output)) {
                    return self::FAILURE;
                }
                $output->writeln(sprintf('Created database at %s.', $dbPath));
            } else {
                $output->writeln(sprintf('Database already present at %s.', $dbPath));
            }

            $database = DBALDatabase::createSqlite($dbPath);
            $connection = $database->getConnection();

            if (!$fresh && !$this->looksWaaseyaaInitialized($connection)) {
                $output->writeln(sprintf('<error>Database at %s exists but does not look Waaseyaa-initialized (no waaseyaa_migrations table).</error>', $dbPath));
                $output->writeln('<error>Refusing to touch it. Move the file aside (e.g. mv waaseyaa.sqlite waaseyaa.sqlite.bak) and re-run db:init.</error>');
                return self::FAILURE;
            }

            $repository = new MigrationRepository($connection);
            $repository->createTable();

            $manifest = (new PackageManifestCompiler(
                basePath: $this->projectRoot,
                storagePath: $this->projectRoot . '/storage',
            ))->load();
            $loader = new MigrationLoader($this->projectRoot, $manifest);
            $migrations = $loader->loadAll();

            $migrator = new Migrator($connection, $repository);
            $result = $migrator->run($migrations);

            if ($result->count === 0) {
                $output->writeln('No pending migrations.');
            } else {
                foreach ($result->migrations as $name) {
                    $output->writeln(sprintf('  Migrated: %s', $name));
                }
                $label = $result->count === 1 ? 'migration' : 'migrations';
                $output->writeln(sprintf('Ran %d %s.', $result->count, $label));
            }

            $output->writeln('<info>Database ready.</info>');
            return self::SUCCESS;
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $configFile = $this->projectRoot . '/config/waaseyaa.php';
        if (is_file($configFile)) {
            $loaded = require $configFile;
            if (is_array($loaded)) {
                return $loaded;
            }
        }
        return [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveDatabasePath(array $config): string
    {
        $configured = $config['database'] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $env = getenv('WAASEYAA_DB');
        if (is_string($env) && $env !== '') {
            return $this->absolutize($env);
        }

        return $this->projectRoot . '/storage/waaseyaa.sqlite';
    }

    private function absolutize(string $path): string
    {
        if ($path === ':memory:' || str_starts_with($path, '/')) {
            return $path;
        }
        return rtrim($this->projectRoot, '/') . '/' . ltrim($path, './');
    }

    private function reportDryRun(string $dbPath, OutputInterface $output): int
    {
        $output->writeln('<comment>--dry-run: no changes will be made.</comment>');
        $output->writeln(sprintf('Database path: %s', $dbPath));

        $parent = dirname($dbPath);
        $output->writeln(sprintf('Parent directory: %s (%s)', $parent, is_dir($parent) ? 'exists' : 'would be created'));

        if ($dbPath === ':memory:') {
            $output->writeln('Target is in-memory; nothing to persist.');
            return self::SUCCESS;
        }

        if (!is_file($dbPath)) {
            $output->writeln('Database file: absent (would be created).');
            $output->writeln('Would run all pending migrations on the new database.');
            return self::SUCCESS;
        }

        try {
            $database = DBALDatabase::createSqlite($dbPath);
            $connection = $database->getConnection();
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Cannot open existing database: %s</error>', $e->getMessage()));
            return self::FAILURE;
        }

        if (!$this->looksWaaseyaaInitialized($connection)) {
            $output->writeln('<error>Database file exists but is not Waaseyaa-initialized.</error>');
            $output->writeln('<error>db:init would refuse. Move the file aside and re-run.</error>');
            return self::FAILURE;
        }

        $repository = new MigrationRepository($connection);
        $repository->createTable();
        $manifest = (new PackageManifestCompiler(
            basePath: $this->projectRoot,
            storagePath: $this->projectRoot . '/storage',
        ))->load();
        $migrations = (new MigrationLoader($this->projectRoot, $manifest))->loadAll();

        $pending = [];
        foreach ($migrations as $package => $set) {
            foreach ($set as $name => $_migration) {
                if (!$repository->hasRun($name)) {
                    $pending[] = $name;
                }
            }
        }

        $output->writeln('Database file: present and initialized.');
        if ($pending === []) {
            $output->writeln('No pending migrations.');
        } else {
            $output->writeln(sprintf('Would run %d pending migration(s):', count($pending)));
            foreach ($pending as $name) {
                $output->writeln(sprintf('  - %s', $name));
            }
        }

        return self::SUCCESS;
    }

    private function ensureParentDirectory(string $dbPath, OutputInterface $output): bool
    {
        if ($dbPath === ':memory:') {
            return true;
        }

        $parent = dirname($dbPath);
        if (!is_dir($parent)) {
            if (!@mkdir($parent, 0o755, recursive: true) && !is_dir($parent)) {
                $output->writeln(sprintf('<error>Cannot create parent directory: %s</error>', $parent));
                $output->writeln(sprintf('<error>Expected writable by user: %s (uid %d).</error>', $this->processUserName(), $this->processUid()));
                return false;
            }
        }

        if (!is_writable($parent)) {
            $output->writeln(sprintf('<error>Parent directory is not writable: %s</error>', $parent));
            $output->writeln(sprintf('<error>Expected writable by user: %s (uid %d). Fix directory permissions and retry.</error>', $this->processUserName(), $this->processUid()));
            return false;
        }

        return true;
    }

    private function createDatabaseFile(string $dbPath, OutputInterface $output): bool
    {
        if ($dbPath === ':memory:') {
            return true;
        }

        if (@touch($dbPath) === false) {
            $output->writeln(sprintf('<error>Cannot create database file: %s</error>', $dbPath));
            $output->writeln(sprintf('<error>Expected writable by user: %s (uid %d). Fix permissions and retry.</error>', $this->processUserName(), $this->processUid()));
            return false;
        }

        return true;
    }

    private function looksWaaseyaaInitialized(\Doctrine\DBAL\Connection $connection): bool
    {
        try {
            return $connection->createSchemaManager()->tablesExist(['waaseyaa_migrations']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return resource|null
     */
    private function acquireLock(string $dbPath, OutputInterface $output)
    {
        if ($dbPath === ':memory:') {
            $memoryHandle = fopen('php://memory', 'r+');
            return $memoryHandle === false ? null : $memoryHandle;
        }

        $parent = dirname($dbPath);
        if (!is_dir($parent)) {
            @mkdir($parent, 0o755, recursive: true);
        }

        $lockPath = $parent . '/.db-init.lock';
        $handle = @fopen($lockPath, 'c');
        if ($handle === false) {
            $output->writeln(sprintf('<error>Cannot open lock file: %s</error>', $lockPath));
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            $output->writeln(sprintf('<error>Another db:init is in progress (lock held on %s). Exiting.</error>', $lockPath));
            return null;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function releaseLock($handle): void
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    private function processUserName(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            if (is_array($info)) {
                return $info['name'];
            }
        }
        $user = getenv('USER');
        if (!is_string($user) || $user === '') {
            $user = getenv('USERNAME');
        }
        return is_string($user) && $user !== '' ? $user : '(unknown)';
    }

    private function processUid(): int
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }
        return -1;
    }
}
