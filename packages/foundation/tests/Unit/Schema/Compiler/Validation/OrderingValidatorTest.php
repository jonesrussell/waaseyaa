<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Schema\Compiler\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Schema\Compiler\Validation\IllegalOpOrderException;
use Waaseyaa\Foundation\Schema\Compiler\Validation\OrderingValidator;
use Waaseyaa\Foundation\Schema\Compiler\Validation\ValidationDiagnosticCode;
use Waaseyaa\Foundation\Schema\Diff\AddColumn;
use Waaseyaa\Foundation\Schema\Diff\AddIndex;
use Waaseyaa\Foundation\Schema\Diff\ColumnSpec;
use Waaseyaa\Foundation\Schema\Diff\CompositeDiff;
use Waaseyaa\Foundation\Schema\Diff\DropColumn;
use Waaseyaa\Foundation\Schema\Diff\RenameColumn;

#[CoversClass(OrderingValidator::class)]
final class OrderingValidatorTest extends TestCase
{
    private static function validator(): OrderingValidator
    {
        return new OrderingValidator();
    }

    #[Test]
    public function emptyCompositePasses(): void
    {
        $this->expectNotToPerformAssertions();

        self::validator()->validate(CompositeDiff::empty());
    }

    #[Test]
    public function addColumnThenAddIndexOnSameColumnPasses(): void
    {
        $this->expectNotToPerformAssertions();

        self::validator()->validate(new CompositeDiff([
            new AddColumn('users', 'email', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
            new AddIndex('users', ['email']),
        ]));
    }

    #[Test]
    public function addIndexReferencingPreExistingColumnPasses(): void
    {
        // 'created_at' is never added in this composite — assumed
        // pre-existing. Per v1 scope this is allowed; verify mode
        // catches the case where it isn't actually pre-existing.
        $this->expectNotToPerformAssertions();

        self::validator()->validate(new CompositeDiff([
            new AddIndex('users', ['created_at']),
        ]));
    }

    #[Test]
    public function addIndexBeforeAddColumnInSameCompositeFails(): void
    {
        $thrown = null;
        try {
            self::validator()->validate(new CompositeDiff([
                new AddIndex('users', ['email']),
                new AddColumn('users', 'email', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
            ]));
        } catch (IllegalOpOrderException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(ValidationDiagnosticCode::IllegalOpOrder->value, $thrown->diagnosticCode);
        self::assertStringContainsString('AddIndex on "users" referencing column "email"', $thrown->getMessage());
        self::assertStringContainsString('added later in the same composite', $thrown->getMessage());
    }

    #[Test]
    public function addIndexCompositeForwardReferenceFlagsTheRightColumn(): void
    {
        $thrown = null;
        try {
            self::validator()->validate(new CompositeDiff([
                new AddColumn('users', 'email', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
                new AddIndex('users', ['email', 'tenant_id']),
                new AddColumn('users', 'tenant_id', new ColumnSpec(type: 'int', nullable: false)),
            ]));
        } catch (IllegalOpOrderException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertStringContainsString('tenant_id', $thrown->getMessage());
    }

    #[Test]
    public function duplicateAddColumnFails(): void
    {
        $this->expectException(IllegalOpOrderException::class);

        self::validator()->validate(new CompositeDiff([
            new AddColumn('users', 'email', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
            new AddColumn('users', 'email', new ColumnSpec(type: 'text', nullable: true)),
        ]));
    }

    #[Test]
    public function renameColumnThenAddColumnOnRenamedTargetFails(): void
    {
        // After RENAME COLUMN name → title, an AddColumn(title) would
        // collide with the renamed-to name.
        $thrown = null;
        try {
            self::validator()->validate(new CompositeDiff([
                new AddColumn('users', 'name', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
                new RenameColumn('users', 'name', 'title'),
                new AddColumn('users', 'title', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
            ]));
        } catch (IllegalOpOrderException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(ValidationDiagnosticCode::IllegalOpOrder->value, $thrown->diagnosticCode);
        self::assertStringContainsString('rename collision', $thrown->getMessage());
    }

    #[Test]
    public function renameColumnThenAddColumnOnSourceNameIsAllowed(): void
    {
        // RENAME a → b makes 'a' available as a fresh column name.
        $this->expectNotToPerformAssertions();

        self::validator()->validate(new CompositeDiff([
            new AddColumn('users', 'name', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
            new RenameColumn('users', 'name', 'old_name'),
            new AddColumn('users', 'name', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
        ]));
    }

    #[Test]
    public function dropAfterRenameOfSameColumnFails(): void
    {
        // DropColumn 'name' after RenameColumn 'name' → 'title' should
        // fail — 'name' no longer exists under that label.
        $thrown = null;
        try {
            self::validator()->validate(new CompositeDiff([
                new AddColumn('users', 'name', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
                new RenameColumn('users', 'name', 'title'),
                new DropColumn('users', 'name'),
            ]));
        } catch (IllegalOpOrderException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertStringContainsString('renamed earlier in this composite', $thrown->getMessage());
    }

    #[Test]
    public function doubleRenameOfSameSourceFails(): void
    {
        $thrown = null;
        try {
            self::validator()->validate(new CompositeDiff([
                new AddColumn('users', 'name', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
                new RenameColumn('users', 'name', 'title'),
                new RenameColumn('users', 'name', 'label'),
            ]));
        } catch (IllegalOpOrderException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertStringContainsString('renamed away', $thrown->getMessage());
    }

    #[Test]
    public function tablesAreScopedSeparately(): void
    {
        // 'email' on table A and 'email' on table B should not collide.
        $this->expectNotToPerformAssertions();

        self::validator()->validate(new CompositeDiff([
            new AddColumn('users', 'email', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
            new AddColumn('contacts', 'email', new ColumnSpec(type: 'varchar', nullable: false, length: 255)),
        ]));
    }
}
