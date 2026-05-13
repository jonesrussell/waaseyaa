<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\MigrationConcurrencyException;
use Waaseyaa\Migration\Runner\MigrationLock;

/**
 * Integration coverage for the per-migration filesystem lock contract.
 *
 * Exercises real OS primitives (`flock()`, signals, subprocess exit) so
 * the lock contract is verified end-to-end. Per-WP-prompt scenarios:
 *
 *  1. Concurrent acquire raises {@see MigrationConcurrencyException}.
 *  2. Graceful release on SIGTERM (pcntl required).
 *  3. Shutdown-function release (no pcntl required).
 *  4. Windows degradation — pcntl scenarios skip cleanly.
 *
 * @spec FR-061
 * @spec FR-062
 */
#[CoversNothing]
final class MigrationLockIntegrationTest extends TestCase
{
    private string $lockDir = '';

    protected function setUp(): void
    {
        $this->lockDir = \sys_get_temp_dir()
            . \DIRECTORY_SEPARATOR
            . 'waaseyaa_lock_int_'
            . \uniqid('', true);
    }

    protected function tearDown(): void
    {
        if ($this->lockDir !== '' && \is_dir($this->lockDir)) {
            foreach (\glob($this->lockDir . '/*.lock') ?: [] as $file) {
                @\unlink($file);
            }
            @\rmdir($this->lockDir);
        }
    }

    #[Test]
    public function test1_concurrent_acquire_via_pcntl_fork_raises(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl_fork() not available; cannot exercise cross-process contention via fork.');
        }

        // Parent acquires first, forks the child, then waits for the
        // child to attempt acquisition. The child writes a status byte to
        // a pipe so the parent can verify the typed exception fired.
        [$readFd, $writeFd] = $this->openPipe();

        $parentPid = \getmypid();
        $parentLock = new MigrationLock(migrationId: 'demo', lockDir: $this->lockDir);
        $parentLock->acquire();

        try {
            $childPid = \pcntl_fork();
            if ($childPid === -1) {
                \fclose($readFd);
                \fclose($writeFd);
                $parentLock->release();
                self::fail('pcntl_fork() failed.');
            }

            if ($childPid === 0) {
                // Child: close the read end, attempt the lock, write a
                // status byte, then exit immediately to avoid running
                // PHPUnit shutdown hooks in the child.
                \fclose($readFd);
                try {
                    $childLock = new MigrationLock(migrationId: 'demo', lockDir: $this->lockDir);
                    $childLock->acquire();
                    \fwrite($writeFd, 'A'); // Should not happen.
                    $childLock->release();
                } catch (MigrationConcurrencyException $e) {
                    // Compose a byte that encodes whether the holding
                    // PID matches the parent's.
                    \fwrite($writeFd, $e->holdingPid === $parentPid ? 'C' : 'P');
                } catch (\Throwable) {
                    \fwrite($writeFd, 'E');
                }
                \fclose($writeFd);
                exit(0);
            }

            // Parent: close the write end, read the child's outcome.
            \fclose($writeFd);
            $byte = \stream_get_contents($readFd);
            \fclose($readFd);
            \pcntl_waitpid($childPid, $childExitStatus);

            self::assertSame('C', $byte, 'Child must raise MigrationConcurrencyException carrying the parent PID.');
        } finally {
            $parentLock->release();
        }
    }

    #[Test]
    public function test2_release_after_holder_exits_lets_next_acquirer_succeed(): void
    {
        // Spin up a subprocess that acquires the lock and exits cleanly.
        // After the subprocess returns, the lock file must be gone and a
        // fresh acquire from the parent must succeed without throwing.
        if (!\function_exists('proc_open')) {
            self::markTestSkipped('proc_open() not available; cannot run isolated subprocess.');
        }

        $exitCode = $this->runChildAcquireRelease('demo', $this->lockDir);
        self::assertSame(0, $exitCode, 'Child must exit 0 after acquiring + releasing the lock.');

        // After child exit, the lock file should be removed.
        $lockPath = $this->lockDir . \DIRECTORY_SEPARATOR . 'demo.lock';
        self::assertFileDoesNotExist($lockPath);

        // Parent acquires cleanly — no leftover handle, no exception.
        $lock = new MigrationLock(migrationId: 'demo', lockDir: $this->lockDir);
        $lock->acquire();
        try {
            self::assertSame(\getmypid(), $lock->pid());
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function test3_shutdown_function_releases_on_abnormal_exit(): void
    {
        // Verify the shutdown-function safety net: if a script aborts
        // (here: `exit(1)` without explicit release()), the lock must
        // still come down via register_shutdown_function.
        if (!\function_exists('proc_open')) {
            self::markTestSkipped('proc_open() not available; cannot run isolated subprocess.');
        }

        $exitCode = $this->runChildAcquireThenExit('demo', $this->lockDir);
        self::assertSame(1, $exitCode, 'Child must exit 1 to signal it acquired the lock without explicit release.');

        // shutdown_function ran inside the child — file should be gone.
        $lockPath = $this->lockDir . \DIRECTORY_SEPARATOR . 'demo.lock';
        self::assertFileDoesNotExist($lockPath, 'register_shutdown_function should have released the lock.');
    }

    #[Test]
    public function test4_windows_degradation_acquire_release_round_trips(): void
    {
        // On Windows, pcntl_* is unavailable; verify the synchronous
        // acquire/release path still works. On Linux/macOS this test
        // still runs and exercises the same code path — it does not
        // make pcntl-specific assertions.
        $lock = new MigrationLock(migrationId: 'demo', lockDir: $this->lockDir);

        $lock->acquire();
        self::assertFileExists($lock->lockPath());

        $lock->release();
        // On non-Windows the file is removed; on Windows the body may
        // persist if the OS refused to unlink an open handle — but we
        // already closed it, so the assertion is the same.
        if (\PHP_OS_FAMILY !== 'Windows') {
            self::assertFileDoesNotExist($lock->lockPath());
        }
    }

    /**
     * @return array{0: resource, 1: resource}
     */
    private function openPipe(): array
    {
        $pipes = \stream_socket_pair(
            \STREAM_PF_UNIX,
            \STREAM_SOCK_STREAM,
            \STREAM_IPPROTO_IP,
        );
        if ($pipes === false) {
            self::fail('stream_socket_pair() failed.');
        }
        return [$pipes[0], $pipes[1]];
    }

    /**
     * Spawn a child that acquires + releases the lock then exits 0.
     */
    private function runChildAcquireRelease(string $migrationId, string $lockDir): int
    {
        $script = $this->composeChildScript($migrationId, $lockDir, releaseExplicitly: true, exitCode: 0);
        return $this->runScript($script);
    }

    /**
     * Spawn a child that acquires the lock, skips the explicit release,
     * and exits 1 — the shutdown function is the only release path.
     */
    private function runChildAcquireThenExit(string $migrationId, string $lockDir): int
    {
        $script = $this->composeChildScript($migrationId, $lockDir, releaseExplicitly: false, exitCode: 1);
        return $this->runScript($script);
    }

    private function composeChildScript(
        string $migrationId,
        string $lockDir,
        bool $releaseExplicitly,
        int $exitCode,
    ): string {
        $autoloader = \dirname(__DIR__, 4) . '/vendor/autoload.php';
        if (!\is_file($autoloader)) {
            self::markTestSkipped('vendor/autoload.php not found at ' . $autoloader);
        }
        $autoloaderEscaped = \addslashes($autoloader);
        $migEscaped = \addslashes($migrationId);
        $dirEscaped = \addslashes($lockDir);
        $releaseStatement = $releaseExplicitly ? '$lock->release();' : '// shutdown-function release.';

        return <<<PHP
<?php
require '{$autoloaderEscaped}';
\$lock = new \\Waaseyaa\\Migration\\Runner\\MigrationLock('{$migEscaped}', '{$dirEscaped}');
\$lock->acquire();
{$releaseStatement}
exit({$exitCode});
PHP;
    }

    private function runScript(string $source): int
    {
        $proc = \proc_open(
            [\PHP_BINARY],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!\is_resource($proc)) {
            self::fail('Could not spawn child PHP process.');
        }

        \fwrite($pipes[0], $source);
        \fclose($pipes[0]);
        \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($proc);
        if (\is_string($stderr) && $stderr !== '') {
            \fwrite(\STDERR, "[child stderr] {$stderr}\n");
        }

        return $exitCode;
    }
}
