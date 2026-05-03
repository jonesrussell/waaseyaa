<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Schema\Compiler;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\Executor\V2PlanExecutor;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCapabilities;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompilerException;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode;
use Waaseyaa\Foundation\Schema\Compiler\Validation\PlanPolicy;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;
use Waaseyaa\Foundation\Schema\Migration\MigrationPlan;

/**
 * Mission #529 / WP08 / T045: rename-like cases.
 *
 * Locks the §3.3 invariant: rename is NEVER inferred from drop+add. An
 * explicit RenameColumn op survives end-to-end on SQLite ≥ 3.25; the
 * compiler refuses on older versions with the stable diagnostic code;
 * a drop+add composite never silently coalesces into a rename — the
 * SQL plan contains both ops as separate steps.
 */
#[CoversNothing]
final class RenameOpsTest extends TestCase
{
    #[Test]
    public function explicitRenameWorksOnSqlite325Plus(): void
    {
        [$connection, , $migrator] = self::harness('3.40.0');
        $connection->executeStatement('CREATE TABLE widgets (id INTEGER PRIMARY KEY, status TEXT)');

        $migrator->run([], [self::v2('waaseyaa/test:v2:rename', new CompositeDiff([
            new RenameColumn('widgets', 'status', 'state'),
        ]))]);

        $cols = array_column(
            $connection->executeQuery('PRAGMA table_info(widgets)')->fetchAllAssociative(),
            'name',
        );
        self::assertContains('state', $cols);
        self::assertNotContains('status', $cols);
    }

    #[Test]
    public function explicitRenameFailsOnSqlitePre325WithStableCode(): void
    {
        $compiler = new SqliteCompiler(SqliteCapabilities::forVersion('3.20.0'));

        $diff = new CompositeDiff([new RenameColumn('widgets', 'status', 'state')]);

        $thrown = null;
        try {
            $compiler->compile($diff);
        } catch (SqliteCompilerException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(
            SqliteDiagnosticCode::RenameColumnUnsupportedSqliteLt325,
            $thrown->diagnosticCode(),
        );
    }

    #[Test]
    public function dropPlusAddIsCompiledAsTwoSeparateStepsNotCoalesced(): void
    {
        // Verify the compiler emits exactly two CompiledStep DTOs and
        // does not produce a single RENAME-equivalent step.
        $compiler = SqliteCompiler::forVersion('3.40.0');
        $diff = new CompositeDiff([
            new DropColumn('widgets', 'status'),
            new AddColumn('widgets', 'state', new ColumnSpec(type: 'varchar', nullable: false, length: 32)),
        ]);

        $plan = $compiler->compile($diff, new PlanPolicy(allowDestructive: true));

        self::assertCount(2, $plan->steps);
        self::assertStringContainsString('DROP COLUMN "status"', $plan->steps[0]->sql());
        self::assertStringContainsString('ADD COLUMN "state"', $plan->steps[1]->sql());
        self::assertStringNotContainsString('RENAME', $plan->steps[0]->sql());
        self::assertStringNotContainsString('RENAME', $plan->steps[1]->sql());
    }

    /**
     * @return array{0: \Doctrine\DBAL\Connection, 1: MigrationRepository, 2: Migrator}
     */
    private static function harness(string $sqliteVersion): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repo = new MigrationRepository($connection);
        $repo->createTable();
        $migrator = new Migrator(
            $connection,
            $repo,
            new V2PlanExecutor($connection, SqliteCompiler::forVersion($sqliteVersion)),
        );
        return [$connection, $repo, $migrator];
    }

    private static function v2(string $id, CompositeDiff $root): MigrationInterfaceV2
    {
        return new class ($id, $root) implements MigrationInterfaceV2 {
            public function __construct(private readonly string $id, private readonly CompositeDiff $root) {}
            public function migrationId(): string
            {
                return $this->id;
            }
            public function package(): string
            {
                return 'waaseyaa/test';
            }
            public function dependencies(): array
            {
                return [];
            }
            public function plan(): MigrationPlan
            {
                return new MigrationPlan(migrationId: $this->id, package: 'waaseyaa/test', dependencies: [], root: $this->root);
            }
        };
    }
}
