<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use Waaseyaa\EntityStorage\Schema\RevisionTableBuilder;

/**
 * sql-column binding of the two-axis storage contract (M-004 / WP01).
 */
#[CoversClass(RevisionTableBuilder::class)]
final class SqlColumnTwoAxisStorageTest extends TwoAxisStorageContract
{
    protected function primaryBackendId(): string
    {
        return 'sql-column';
    }
}
