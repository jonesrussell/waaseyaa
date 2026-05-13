<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Architecture;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Forbids named kernel subclasses under tests/**.
 *
 * Bootstrap-variant proliferation — parallel "kernel-ish" helpers
 * (mocked kernel, partial boot, in-memory manifest stubs) — was the
 * root smell that masked the alpha.148–152 adoption-chain failure.
 * The canonical pattern for kernel-path tests is the anonymous
 * subclass exposing publicBoot():
 *
 *     $kernel = new class($projectRoot) extends AbstractKernel {
 *         public function publicBoot(): void { $this->boot(); }
 *     };
 *     $kernel->publicBoot();
 *
 * Anonymous classes are NOT matched by this guard (they appear as
 * `new class(...) extends Foo` in source, not `class X extends Foo`).
 * Named subclasses are — they are the drift shape this rule prevents.
 *
 * If you genuinely need a named subclass (e.g. a shared KernelTestCase
 * base), place it in a non-tests/ location (packages/testing/src/ or a
 * dedicated testing/ directory with composer autoload-dev registration)
 * and import it. Then this guard still holds.
 */
#[CoversNothing]
final class NoKernelSubclassesInTestsTest extends TestCase
{
    private const TESTS_ROOT = __DIR__ . '/..';

    private const FORBIDDEN_PARENTS = [
        'AbstractKernel',
        'HttpKernel',
        'ConsoleKernel',
    ];

    #[Test]
    public function noNamedKernelSubclassesExistUnderTests(): void
    {
        $pattern = '/^\s*(?:final\s+|abstract\s+)?class\s+[A-Za-z_][A-Za-z0-9_]*\s+extends\s+(' . implode('|', self::FORBIDDEN_PARENTS) . ')\b/m';

        $offenders = [];
        foreach ($this->phpFilesUnder(self::TESTS_ROOT) as $file) {
            if (realpath($file) === realpath(__FILE__)) {
                continue;
            }
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }
            if (preg_match($pattern, $contents, $matches) === 1) {
                $offenders[] = sprintf('%s (extends %s)', $file, $matches[1]);
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf(
                "Found %d named kernel subclass(es) under tests/. Use the anonymous-subclass + publicBoot() pattern from KernelBundleSubtableMaterializationTest instead.\n  - %s",
                count($offenders),
                implode("\n  - ", $offenders),
            ),
        );
    }

    /**
     * @return iterable<string>
     */
    private function phpFilesUnder(string $dir): iterable
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            // Skip third-party code installed under tests/ (e.g. the packaged-form
            // skeleton fixture installs the published framework into its own vendor/).
            // The architecture rule applies to first-party tests only.
            $pathname = $file->getPathname();
            if (str_contains($pathname, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            yield $pathname;
        }
    }
}
