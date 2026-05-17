<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use Waaseyaa\EntityStorage\Schema\RevisionTableBuilder;

/**
 * sql-blob binding of the two-axis storage contract (M-004 / WP02).
 *
 * Mirrors {@see SqlColumnTwoAxisStorageTest} for the `sql-blob` primary
 * backend. The shared parent (`TwoAxisStorageContract`) asserts that the
 * sql-blob path emits a `_data` JSON column on both `<entity>__revision`
 * and `<entity>__translation__revision` (per FR-003 / contracts/composite-pk.md §8.2).
 */
#[CoversClass(RevisionTableBuilder::class)]
final class SqlBlobTwoAxisStorageTest extends TwoAxisStorageContract
{
    protected function primaryBackendId(): string
    {
        return 'sql-blob';
    }
}
