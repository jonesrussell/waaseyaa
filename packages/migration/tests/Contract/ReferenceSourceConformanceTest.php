<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Tests\Fixtures\CsvSource;
use Waaseyaa\Migration\Testing\SourceConformanceTestCase;

/**
 * Reference conformance test: runs {@see SourceConformanceTestCase} against
 * the framework's own {@see CsvSource} fixture (FR-052).
 *
 * Proves the conformance base works end-to-end — every gate (C1..C8) inherits
 * from the abstract suite and runs against a real {@see CsvSource} pointed
 * at a real CSV file.
 *
 * The large fixture is generated at test-time in `setUp()` (deterministic
 * via `mt_srand(42)`) so we never commit a 50 MB binary blob to git.
 *
 * @spec FR-049 — source conformance gates
 * @spec FR-051 — streaming memory bound
 * @spec FR-052 — CsvSource as reference
 */
#[CoversNothing]
final class ReferenceSourceConformanceTest extends SourceConformanceTestCase
{
    private const string FIXTURE_SOURCE_TYPE = 'csv';

    /**
     * Number of rows generated for the large fixture. ~200k rows of
     * ~250-byte payloads land between 40 and 60 MB — comfortably above
     * the C5 memory budget so the gate actually exercises streaming.
     */
    private const int LARGE_FIXTURE_ROW_COUNT = 200_000;

    private string $largeFixturePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->largeFixturePath = \sys_get_temp_dir()
            . '/waaseyaa_migration_conformance_large_'
            . \bin2hex(\random_bytes(8))
            . '.csv';
        self::generateLargeFixture($this->largeFixturePath, self::LARGE_FIXTURE_ROW_COUNT);
    }

    protected function tearDown(): void
    {
        if ($this->largeFixturePath !== '' && \is_file($this->largeFixturePath)) {
            @\unlink($this->largeFixturePath);
        }
        $this->largeFixturePath = '';

        parent::tearDown();
    }

    protected function buildPluginUnderTest(): SourcePluginInterface
    {
        return new CsvSource(
            filePath: $this->buildSmallFixturePath(),
            keyFields: ['id'],
            sourceType: self::FIXTURE_SOURCE_TYPE,
        );
    }

    protected function buildSmallFixturePath(): string
    {
        return \dirname(__DIR__) . '/Fixtures/data/conformance-small.csv';
    }

    protected function buildLargeFixturePath(): string
    {
        return $this->largeFixturePath;
    }

    protected function buildPluginForFixture(string $fixturePath): SourcePluginInterface
    {
        return new CsvSource(
            filePath: $fixturePath,
            keyFields: ['id'],
            sourceType: self::FIXTURE_SOURCE_TYPE,
        );
    }

    protected function buildPluginUnderTestForLargeFixture(): SourcePluginInterface
    {
        return $this->buildPluginForFixture($this->largeFixturePath);
    }

    /**
     * Deterministic large-CSV generator. `mt_srand(42)` keeps content stable
     * across runs so debugging is reproducible. Each row is ~250 bytes; at
     * 200k rows the file lands in the ~40-60 MB range.
     */
    private static function generateLargeFixture(string $targetPath, int $rowCount): void
    {
        $handle = \fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(\sprintf(
                'ReferenceSourceConformanceTest: could not open temporary file %s for writing.',
                $targetPath,
            ));
        }

        try {
            \fputcsv($handle, ['id', 'title', 'body', 'value_int'], ',', '"', '\\');
            \mt_srand(42);

            for ($i = 1; $i <= $rowCount; $i++) {
                $title = \sprintf('Title #%d (%s)', $i, \bin2hex(\pack('N', \mt_rand(0, 0x7FFFFFFF))));
                $body = \str_repeat(
                    \sprintf('row-%d-payload-%s ', $i, \bin2hex(\pack('N', \mt_rand(0, 0x7FFFFFFF)))),
                    4,
                );
                \fputcsv(
                    $handle,
                    [(string) $i, $title, $body, (string) ($i * 7)],
                    ',',
                    '"',
                    '\\',
                );
            }
        } finally {
            \fclose($handle);
        }
    }
}
