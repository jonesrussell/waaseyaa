<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Foundation\Log\LoggerInterface;

/**
 * WP06 #1257 (K4 — bundle-load drift logging).
 *
 * When `mergeBundleSubtableRow()` or `mergeBundleSubtableRowsBatch()`
 * encounter a registered (entity_type, bundle) whose subtable is not yet
 * materialized in storage, the load path must emit a single
 * `LoggerInterface::notice()` with diagnostic code `MISSING_BUNDLE_SUBTABLE`
 * — once per `(entity_type, bundle)` for the lifetime of the storage
 * instance — and continue without throwing.
 *
 * Pre-WP06, both load functions silent-skipped, leaving operators with no
 * signal that a registered bundle was returning incomplete entities. This
 * test fixture intentionally orders schema setup BEFORE bundle-field
 * registration so the subtable is missing at load time.
 */
#[CoversNothing]
final class SqlEntityStorageBundleLoadDriftTest extends TestCase
{
    private DBALDatabase $database;
    private FieldDefinitionRegistry $registry;
    private SpyLogger $logger;
    private SqlEntityStorage $storage;
    private EntityType $entityType;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite(':memory:');
        $this->registry = new FieldDefinitionRegistry();
        $this->logger = new SpyLogger();

        $this->entityType = new EntityType(
            id: 'widget',
            label: 'Widget',
            class: \Waaseyaa\EntityStorage\Tests\Fixtures\TestStorageEntity::class,
            keys: [
                'id' => 'wid',
                'uuid' => 'uuid',
                'bundle' => 'type',
                'label' => 'name',
                'langcode' => 'langcode',
            ],
            bundleEntityType: 'widget_type',
        );

        // Build the base table FIRST, with NO bundle fields registered.
        // ensureTable() therefore does not materialize a `widget__gizmo`
        // subtable.
        $schemaHandler = new SqlSchemaHandler(
            $this->entityType,
            $this->database,
            $this->registry,
        );
        $schemaHandler->ensureTable();

        // Now register bundle fields AFTER the schema is in place. The
        // subtable is still absent — exactly the drift state WP06 surfaces.
        $this->registry->registerBundleFields('widget', 'gizmo', [
            'gizmo_code' => new FieldDefinition(
                name: 'gizmo_code',
                type: 'string',
                targetEntityTypeId: 'widget',
                targetBundle: 'gizmo',
            ),
        ]);

        $this->storage = new SqlEntityStorage(
            $this->entityType,
            $this->database,
            new EventDispatcher(),
            $this->registry,
            $this->logger,
        );

        // Seed two `gizmo` rows directly into the base table, skipping
        // SqlEntityStorage::save() so its save-time MISSING_BUNDLE_SUBTABLE
        // notice does not pollute the load-side spy assertion.
        $this->database->insert('widget')
            ->fields(['uuid', 'type', 'name', 'langcode', '_data'])
            ->values(['uuid' => 'a', 'type' => 'gizmo', 'name' => 'A', 'langcode' => 'en', '_data' => '{}'])
            ->execute();
        $this->database->insert('widget')
            ->fields(['uuid', 'type', 'name', 'langcode', '_data'])
            ->values(['uuid' => 'b', 'type' => 'gizmo', 'name' => 'B', 'langcode' => 'en', '_data' => '{}'])
            ->execute();
    }

    #[Test]
    public function loadMultipleEmitsMissingBundleSubtableNoticeOncePerBundle(): void
    {
        $entities = $this->storage->loadMultiple([1, 2]);
        self::assertCount(2, $entities, 'base rows still load even when subtable is missing');

        $missing = $this->logger->messagesContaining('[MISSING_BUNDLE_SUBTABLE]');
        self::assertCount(
            1,
            $missing,
            'A single notice must surface the missing subtable for the (widget, gizmo) pair regardless of how many rows in the batch share that bundle.',
        );
        self::assertStringContainsString('widget', $missing[0]);
        self::assertStringContainsString('gizmo', $missing[0]);

        // Subsequent load must NOT re-log — the notice is memoized for the
        // lifetime of the storage instance.
        $this->storage->loadMultiple([1, 2]);
        $missingAfterRepeat = $this->logger->messagesContaining('[MISSING_BUNDLE_SUBTABLE]');
        self::assertCount(
            1,
            $missingAfterRepeat,
            'The notice must be memoized — re-loading the same bundle must not re-log.',
        );
    }

    #[Test]
    public function loadSingleEntityAlsoEmitsMissingBundleSubtableNotice(): void
    {
        // Sanity: the single-row merge path (`mergeBundleSubtableRow`) must
        // be wired up too; it's a separate code path from the batch path.
        $entity = $this->storage->load(1);
        self::assertNotNull($entity);

        $missing = $this->logger->messagesContaining('[MISSING_BUNDLE_SUBTABLE]');
        self::assertCount(1, $missing, 'Single-row load must also surface the missing subtable.');
    }
}

/**
 * In-memory logger that records every message at every level. Used only
 * in the WP06 test above to assert notice cadence.
 */
final class SpyLogger implements LoggerInterface
{
    /** @var list<string> */
    public array $messages = [];

    /**
     * @return list<string>
     */
    public function messagesContaining(string $needle): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (string $m): bool => str_contains($m, $needle),
        ));
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function log(LogLevel $level, string|\Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
}
