<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Migration\Ledger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\LedgerSchema\V2_0001_add_checksum_columns;
use Waaseyaa\Foundation\Schema\Diff\OpKind;

#[CoversClass(V2_0001_add_checksum_columns::class)]
final class V2_0001_add_checksum_columns_Test extends TestCase
{
    #[Test]
    public function migrationIdIsLockedAndQ1Compliant(): void
    {
        $migration = new V2_0001_add_checksum_columns();

        self::assertSame(
            'waaseyaa/foundation:v2:ledger-add-checksum-columns',
            $migration->migrationId(),
        );
        self::assertSame('waaseyaa/foundation', $migration->package());
        self::assertSame([], $migration->dependencies());
    }

    #[Test]
    public function planAddsBothNullableVarcharColumnsToLedgerTable(): void
    {
        $plan = (new V2_0001_add_checksum_columns())->plan();

        self::assertCount(2, $plan->root->ops);

        foreach ($plan->root->ops as $op) {
            self::assertSame(OpKind::AddColumn, $op->kind());
        }

        $canonical = $plan->toCanonical();
        self::assertSame(
            [
                'ops' => [
                    [
                        'column' => 'checksum',
                        'kind' => 'add_column',
                        'spec' => [
                            'default' => null,
                            'length' => 64,
                            'nullable' => true,
                            'type' => 'varchar',
                        ],
                        'table' => 'waaseyaa_migrations',
                    ],
                    [
                        'column' => 'diff_hash',
                        'kind' => 'add_column',
                        'spec' => [
                            'default' => null,
                            'length' => 64,
                            'nullable' => true,
                            'type' => 'varchar',
                        ],
                        'table' => 'waaseyaa_migrations',
                    ],
                ],
            ],
            $canonical,
        );
    }

    #[Test]
    public function checksumIsStableAcrossInstances(): void
    {
        $a = (new V2_0001_add_checksum_columns())->plan();
        $b = (new V2_0001_add_checksum_columns())->plan();

        self::assertSame($a->checksum(), $b->checksum());
        self::assertSame(64, strlen($a->checksum()));
    }
}
