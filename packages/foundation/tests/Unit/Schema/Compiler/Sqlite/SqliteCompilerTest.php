<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\CompiledMigrationPlan;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCapabilities;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompilerException;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;
use Waaseyaa\Foundation\Schema\Compiler\Step\AlterTableAddColumn;
use Waaseyaa\Foundation\Schema\Compiler\Step\CreateIndex;
use Waaseyaa\Foundation\Schema\Compiler\Step\ExecuteStatement;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\AlterColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameTable;

#[CoversClass(SqliteCompiler::class)]
final class SqliteCompilerTest extends TestCase
{
    /**
     * Locked SHA-256 over the single-AddColumn fixture's compiled plan.
     * Changes here propagate to every recorded `diff_hash` in production
     * ledgers — see {@see \Waaseyaa\Foundation\Tests\Unit\Schema\Diff\CompositeDiffTest}
     * for the same warning.
     */
    private const SINGLE_ADD_COLUMN_HASH = '7935f5b003091c8dc262fadd0313561f1108ab40ed0cece653d8320a66ad9492';

    private const MULTI_OP_HASH = 'dc1edb5692bbabb2e89bc3d95fbf08b70b9aaf344b9bef02f61826b6c02e98e9';

    private const EMPTY_PLAN_HASH = '4430e7786edc0f8419f02e909c15422ebf572287a58132d8f6f33250ce053121';

    private const COMPOSITE_UNIQUE_INDEX_HASH = '25de56df87704523b045ac210a9b60cfcb4e38a04ee2c6175298dcc5e1d574a5';

    #[Test]
    public function compilesSingleAddColumnToAlterTableStep(): void
    {
        $diff = new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]);

        $plan = SqliteCompiler::forVersion('3.40.0')->compile($diff);

        self::assertCount(1, $plan->steps);
        self::assertInstanceOf(AlterTableAddColumn::class, $plan->steps[0]);
        self::assertSame('alter_table_add_column', $plan->steps[0]->kind());
        self::assertSame('widgets', $plan->steps[0]->table);
        self::assertSame('archived_at', $plan->steps[0]->column);
        self::assertSame(
            'ALTER TABLE "widgets" ADD COLUMN "archived_at" INTEGER',
            $plan->steps[0]->sql(),
        );
    }

    #[Test]
    public function singleAddColumnHasGoldenDiffHash(): void
    {
        $diff = new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]);

        $plan = SqliteCompiler::forVersion('3.40.0')->compile($diff);

        self::assertSame(
            '{"steps":[{"column":"archived_at","kind":"alter_table_add_column","sql":"ALTER TABLE \"widgets\" ADD COLUMN \"archived_at\" INTEGER","table":"widgets"}]}',
            $plan->toCanonicalJson(),
        );
        self::assertSame(self::SINGLE_ADD_COLUMN_HASH, $plan->diffHash());
    }

    #[Test]
    public function compilesMultiOpCompositePreservingOrder(): void
    {
        $diff = new CompositeDiff([
            new AddColumn('widgets', 'name', new ColumnSpec(type: 'varchar', nullable: false, default: 'unknown', length: 64)),
            new AddIndex('widgets', ['name']),
            new RenameColumn('widgets', 'name', 'title'),
            new RenameTable('widgets', 'gizmos'),
        ]);

        $plan = SqliteCompiler::forVersion('3.40.0')->compile($diff);

        self::assertCount(4, $plan->steps);
        self::assertInstanceOf(AlterTableAddColumn::class, $plan->steps[0]);
        self::assertInstanceOf(CreateIndex::class, $plan->steps[1]);
        self::assertInstanceOf(ExecuteStatement::class, $plan->steps[2]);
        self::assertInstanceOf(ExecuteStatement::class, $plan->steps[3]);
        self::assertSame(self::MULTI_OP_HASH, $plan->diffHash());
    }

    #[Test]
    public function compilesEmptyDiffToEmptyPlan(): void
    {
        $plan = SqliteCompiler::forVersion('3.40.0')->compile(CompositeDiff::empty());

        self::assertTrue($plan->isEmpty());
        self::assertSame('{"steps":[]}', $plan->toCanonicalJson());
        self::assertSame(self::EMPTY_PLAN_HASH, $plan->diffHash());
    }

    #[Test]
    public function compilesCompositeUniqueIndexWithGoldenHash(): void
    {
        $diff = new CompositeDiff([
            new AddIndex('users', ['email', 'tenant_id'], unique: true),
        ]);

        $plan = SqliteCompiler::forVersion('3.40.0')->compile($diff);

        self::assertSame(self::COMPOSITE_UNIQUE_INDEX_HASH, $plan->diffHash());
    }

    #[Test]
    public function compilingTwiceProducesIdenticalDiffHash(): void
    {
        $diff = new CompositeDiff([
            new AddColumn('widgets', 'name', new ColumnSpec(type: 'varchar', nullable: false, default: 'unknown', length: 64)),
            new AddIndex('widgets', ['name']),
            new RenameColumn('widgets', 'name', 'title'),
            new RenameTable('widgets', 'gizmos'),
        ]);

        $first = SqliteCompiler::forVersion('3.40.0')->compile($diff);
        $second = SqliteCompiler::forVersion('3.40.0')->compile($diff);

        self::assertSame($first->diffHash(), $second->diffHash());
        self::assertSame($first->toCanonicalJson(), $second->toCanonicalJson());
    }

    #[Test]
    public function freshCompilerInstancePerCompileIsDeterministic(): void
    {
        // A second compiler instance constructed from a freshly-derived
        // capability set models the "two separate processes" determinism
        // requirement from §5.2 within a single test process.
        $diff = new CompositeDiff([
            new AddColumn('widgets', 'archived_at', new ColumnSpec(type: 'int', nullable: true)),
        ]);

        $a = (new SqliteCompiler(SqliteCapabilities::forVersion('3.40.0')))->compile($diff);
        $b = (new SqliteCompiler(SqliteCapabilities::forVersion('3.40.0')))->compile($diff);

        self::assertSame($a->diffHash(), $b->diffHash());
    }

    #[Test]
    public function reorderedOpsProduceDifferentDiffHash(): void
    {
        $a = SqliteCompiler::forVersion('3.40.0')->compile(new CompositeDiff([
            new AddColumn('widgets', 'a', new ColumnSpec(type: 'int', nullable: true)),
            new AddColumn('widgets', 'b', new ColumnSpec(type: 'int', nullable: true)),
        ]));
        $b = SqliteCompiler::forVersion('3.40.0')->compile(new CompositeDiff([
            new AddColumn('widgets', 'b', new ColumnSpec(type: 'int', nullable: true)),
            new AddColumn('widgets', 'a', new ColumnSpec(type: 'int', nullable: true)),
        ]));

        self::assertNotSame($a->diffHash(), $b->diffHash());
    }

    #[Test]
    public function renameColumnRejectedOnSqliteBelow325(): void
    {
        $diff = new CompositeDiff([
            new RenameColumn('widgets', 'name', 'title'),
        ]);

        try {
            SqliteCompiler::forVersion('3.24.0')->compile($diff);
            self::fail('Expected SqliteCompilerException for old SQLite version.');
        } catch (SqliteCompilerException $e) {
            self::assertSame(
                SqliteDiagnosticCode::RenameColumnUnsupportedSqliteLt325,
                $e->diagnosticCode(),
            );
            self::assertStringContainsString('3.24.0', $e->getMessage());
            self::assertStringContainsString('RENAME COLUMN', $e->getMessage());
        }
    }

    #[Test]
    public function renameColumnAllowedOnSqliteAtExactly325(): void
    {
        $diff = new CompositeDiff([
            new RenameColumn('widgets', 'name', 'title'),
        ]);

        $plan = SqliteCompiler::forVersion('3.25.0')->compile($diff);

        self::assertCount(1, $plan->steps);
        self::assertInstanceOf(ExecuteStatement::class, $plan->steps[0]);
    }

    #[Test]
    public function unimplementedOpsThrowOperationNotImplemented(): void
    {
        $unimplemented = [
            new AlterColumn('widgets', 'name', new ColumnSpec(type: 'text', nullable: true)),
            new DropColumn('widgets', 'archived'),
        ];

        foreach ($unimplemented as $op) {
            try {
                SqliteCompiler::forVersion('3.40.0')->compile(new CompositeDiff([$op]));
                self::fail('Expected SqliteCompilerException for unimplemented op kind ' . $op->kind()->value);
            } catch (SqliteCompilerException $e) {
                self::assertSame(
                    SqliteDiagnosticCode::OperationNotImplemented,
                    $e->diagnosticCode(),
                );
                self::assertStringContainsString($op->kind()->value, $e->getMessage());
            }
        }
    }

    #[Test]
    public function compilerIsReadonlyAndFinal(): void
    {
        $reflection = new \ReflectionClass(SqliteCompiler::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function compileReturnsCompiledMigrationPlanInstance(): void
    {
        $plan = SqliteCompiler::forVersion('3.40.0')->compile(CompositeDiff::empty());

        self::assertInstanceOf(CompiledMigrationPlan::class, $plan);
    }
}
