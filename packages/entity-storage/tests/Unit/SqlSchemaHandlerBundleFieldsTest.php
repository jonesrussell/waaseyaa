<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\EntityStorage\Tests\Fixtures\TestStorageEntity;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;

#[CoversClass(SqlSchemaHandler::class)]
final class SqlSchemaHandlerBundleFieldsTest extends TestCase
{
    private DBALDatabase $database;
    private EntityType $groupType;
    private FieldDefinitionRegistry $registry;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();
        // SQLite enforces FK constraints only when the pragma is on.
        // PRAGMA cannot be set through a prepared-statement path, so reach the
        // underlying Connection and issue it as an unprepared DDL statement.
        $this->database->getConnection()->executeStatement('PRAGMA foreign_keys = ON');

        $this->groupType = new EntityType(
            id: 'group',
            label: 'Group',
            class: TestStorageEntity::class,
            keys: [
                'id' => 'gid',
                'uuid' => 'uuid',
                'bundle' => 'type',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
            bundleEntityType: 'group_type',
        );

        $this->registry = new FieldDefinitionRegistry();
    }

    #[Test]
    public function ensureTableCreatesSubtableOnlyForNonEmptyBundles(): void
    {
        $this->registry->registerBundleFields('group', 'business', [
            'email' => new FieldDefinition(
                name: 'email',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
            'phone' => new FieldDefinition(
                name: 'phone',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
        ]);
        // 'organization' has no registered fields — empty bundle.

        $handler = $this->makeHandler(['business', 'organization']);
        $handler->ensureTable();

        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('group'), 'base table created');
        self::assertTrue($schema->tableExists('group__business'), 'non-empty bundle subtable created');
        self::assertFalse($schema->tableExists('group__organization'), 'empty bundle skipped');
        self::assertTrue($schema->fieldExists('group__business', 'email'));
        self::assertTrue($schema->fieldExists('group__business', 'phone'));
        self::assertTrue($schema->fieldExists('group__business', 'gid'), 'subtable PK shares id key');
    }

    #[Test]
    public function ensureTableIsIdempotent(): void
    {
        $this->registry->registerBundleFields('group', 'business', [
            new FieldDefinition(
                name: 'email',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
        ]);

        $handler = $this->makeHandler(['business']);
        $handler->ensureTable();
        $handler->ensureTable();  // must not throw, must not duplicate

        self::assertTrue($this->database->schema()->tableExists('group__business'));
    }

    #[Test]
    public function additiveColumnAddedOnReRunWithNewField(): void
    {
        // Phase 1: install with one field.
        $this->registry->registerBundleFields('group', 'business', [
            new FieldDefinition(
                name: 'email',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
        ]);
        $this->makeHandler(['business'])->ensureTable();
        self::assertTrue($this->database->schema()->fieldExists('group__business', 'email'));
        self::assertFalse($this->database->schema()->fieldExists('group__business', 'phone'));

        // Phase 2: register an additional field and re-run.
        $this->registry->registerBundleFields('group', 'business', [
            new FieldDefinition(
                name: 'phone',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
        ]);
        $this->makeHandler(['business'])->ensureTable();

        self::assertTrue($this->database->schema()->fieldExists('group__business', 'phone'));
        self::assertTrue($this->database->schema()->fieldExists('group__business', 'email'));
    }

    #[Test]
    public function emptyToNonEmptyBundleTransitionCreatesSubtable(): void
    {
        // Phase 1: bundle registered but with no fields.
        $this->makeHandler(['business'])->ensureTable();
        self::assertFalse($this->database->schema()->tableExists('group__business'));

        // Phase 2: add a field and re-run.
        $this->registry->registerBundleFields('group', 'business', [
            new FieldDefinition(
                name: 'email',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
        ]);
        $this->makeHandler(['business'])->ensureTable();

        self::assertTrue($this->database->schema()->tableExists('group__business'));
        self::assertTrue($this->database->schema()->fieldExists('group__business', 'email'));
    }

    #[Test]
    public function foreignKeyCascadesOnBaseRowDelete(): void
    {
        $this->registry->registerBundleFields('group', 'business', [
            new FieldDefinition(
                name: 'email',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
        ]);
        $this->makeHandler(['business'])->ensureTable();

        $this->database->insert('group')
            ->fields(['uuid', 'type', 'label', 'langcode', '_data'])
            ->values([
                'uuid' => 'uuid-1',
                'type' => 'business',
                'label' => 'Acme',
                'langcode' => 'en',
                '_data' => '{}',
            ])
            ->execute();
        $baseRow = iterator_to_array(
            $this->database->query('SELECT gid FROM "group" WHERE uuid = ?', ['uuid-1']),
        );
        $gid = (int) ((array) $baseRow[0])['gid'];

        $this->database->insert('group__business')
            ->fields(['gid', 'email'])
            ->values(['gid' => $gid, 'email' => 'hi@acme.example'])
            ->execute();

        $before = iterator_to_array(
            $this->database->query('SELECT COUNT(*) AS c FROM group__business', []),
        );
        self::assertSame(1, (int) ((array) $before[0])['c']);

        $this->database->delete('group')
            ->condition('gid', $gid)
            ->execute();

        $after = iterator_to_array(
            $this->database->query('SELECT COUNT(*) AS c FROM group__business', []),
        );
        self::assertSame(0, (int) ((array) $after[0])['c'], 'FK cascade removed subtable row');
    }

    #[Test]
    public function bundleIdentifierWithDoubleUnderscoreThrows(): void
    {
        $handler = $this->makeHandler(['ok']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved separator "__"');

        $handler->bundleSubtableName('business__nested');
    }

    #[Test]
    public function ensureTableWithoutRegistryOrEnumeratorSkipsBundleLoop(): void
    {
        $handler = new SqlSchemaHandler($this->groupType, $this->database);
        $handler->ensureTable();

        self::assertTrue($this->database->schema()->tableExists('group'));
        self::assertFalse($this->database->schema()->tableExists('group__business'));
    }

    /**
     * @param list<string> $bundles
     */
    private function makeHandler(array $bundles): SqlSchemaHandler
    {
        return new SqlSchemaHandler(
            $this->groupType,
            $this->database,
            $this->registry,
            static fn (): iterable => $bundles,
        );
    }
}
