<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Migration\Ledger;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\LedgerRow;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\VerifyResult;

#[CoversClass(MigrationRepository::class)]
#[CoversClass(VerifyResult::class)]
#[CoversClass(LedgerRow::class)]
final class VerifyChecksumTest extends TestCase
{
    #[Test]
    public function verifyReturnsMissingWhenNoRow(): void
    {
        $repo = self::repo();

        self::assertSame(
            VerifyResult::Missing,
            $repo->verifyChecksum('does/not:exist', 'abc'),
        );
    }

    #[Test]
    public function verifyReturnsUnknownForNullStoredChecksum(): void
    {
        $repo = self::repo();
        $repo->record('legacy/foo:001_init', 'legacy/foo', batch: 1);

        self::assertSame(
            VerifyResult::Unknown,
            $repo->verifyChecksum('legacy/foo:001_init', 'whatever'),
        );
    }

    #[Test]
    public function verifyReturnsMatchOnExactHashAgreement(): void
    {
        $repo = self::repo();
        $checksum = str_repeat('a', 64);
        $repo->record('a/b:v2:c', 'a/b', batch: 1, checksum: $checksum, diffHash: str_repeat('b', 64));

        self::assertSame(VerifyResult::Match, $repo->verifyChecksum('a/b:v2:c', $checksum));
    }

    #[Test]
    public function verifyReturnsMismatchOnHashDisagreement(): void
    {
        $repo = self::repo();
        $repo->record('a/b:v2:c', 'a/b', batch: 1, checksum: str_repeat('a', 64), diffHash: str_repeat('b', 64));

        self::assertSame(
            VerifyResult::Mismatch,
            $repo->verifyChecksum('a/b:v2:c', str_repeat('z', 64)),
        );
    }

    #[Test]
    public function allWithChecksumsReturnsTypedRows(): void
    {
        $repo = self::repo();
        $repo->record('legacy/foo:001_init', 'legacy/foo', batch: 1);
        $repo->record('a/b:v2:c', 'a/b', batch: 1, checksum: str_repeat('a', 64), diffHash: str_repeat('b', 64));

        $rows = $repo->allWithChecksums();
        self::assertCount(2, $rows);

        $byMigration = [];
        foreach ($rows as $row) {
            $byMigration[$row->migration] = $row;
        }

        self::assertNull($byMigration['legacy/foo:001_init']->checksum);
        self::assertNull($byMigration['legacy/foo:001_init']->diffHash);
        self::assertSame(str_repeat('a', 64), $byMigration['a/b:v2:c']->checksum);
        self::assertSame(str_repeat('b', 64), $byMigration['a/b:v2:c']->diffHash);
    }

    #[Test]
    public function verifyResultEnumLocksStableValues(): void
    {
        // Stable strings used by the verify CLI / dashboards.
        self::assertSame('match', VerifyResult::Match->value);
        self::assertSame('mismatch', VerifyResult::Mismatch->value);
        self::assertSame('unknown', VerifyResult::Unknown->value);
        self::assertSame('missing', VerifyResult::Missing->value);
    }

    private static function repo(): MigrationRepository
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repo = new MigrationRepository($connection);
        $repo->createTable();

        return $repo;
    }
}
