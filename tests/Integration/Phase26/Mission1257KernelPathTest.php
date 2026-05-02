<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase26;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Exception\EntityTypeRegistrationCollisionException;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\Foundation\Diagnostic\BootDiagnosticReport;
use Waaseyaa\Foundation\Diagnostic\DiagnosticCode;
use Waaseyaa\Foundation\Diagnostic\HealthChecker;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\LogLevel;

/**
 * Mission #1257 (entity-storage-hardening) WP11 — kernel-path integration lock.
 *
 * The mission's charter calls for a single end-to-end test that exercises
 * every hardened invariant in one place. WP11 is the lock; the mission does
 * not accept without it. Each test method below pins one of the hardened
 * conventions (K1-K7) at the same boundary the production runtime crosses:
 *
 *   - K1 (WP03): bundle subtable naming flows through the canonical static
 *     helper, and the structural guard rejects bundle ids carrying the
 *     reserved separator at registration time.
 *   - K2 (WP04): a `FieldStorage::Data` core field round-trips through the
 *     `_data` JSON blob on both write and read paths; legacy columns cannot
 *     shadow the registry hint.
 *   - K3 (WP05): query-builder conditions against integer-typed `_data`
 *     fields coerce numeric-string values per the declared FieldDefinition
 *     type, and IN-set elements are coerced individually.
 *   - K4 (WP06): when a registered bundle's subtable is absent at load time,
 *     `SqlEntityStorage` emits a single `[MISSING_BUNDLE_SUBTABLE]` notice
 *     per `(entity_type, bundle)` for the lifetime of the storage instance.
 *   - K6 (WP08): `HealthChecker` (kernel-adjacent L0 component, exempted via
 *     `bin/check-package-layers`) surfaces both `MISSING_BUNDLE_SUBTABLE`
 *     and `ORPHAN_BUNDLE_SUBTABLE` diagnostic codes from `checkSchemaDrift()`.
 *   - K7 (WP07-B): `EntityTypeRegistrationCollisionException::duplicate()`
 *     names both registrants and both classes in its message body.
 *
 * Tenancy via `EntityType` (C1, WP10) is intentionally out of scope here —
 * WP10 is unscheduled and ratifies the contract. A follow-on extension to
 * this file picks up the tenancy assertion once WP10 lands.
 *
 * Components are wired in the same order `AbstractKernel` wires them. No
 * filesystem fixture or kernel boot is required — the integration is the
 * sequence (registry → schema → storage → query → health-check), not the
 * provider-discovery shell.
 */
#[CoversNothing]
final class Mission1257KernelPathTest extends TestCase
{
    private const string ENTITY_TYPE_ID = 'mission1257_widget';
    private const string BUNDLE_ENTITY_TYPE_ID = 'mission1257_widget_type';

    private DBALDatabase $database;
    private FieldDefinitionRegistry $registry;
    private EntityTypeManager $entityTypeManager;
    private EntityType $entityType;
    private SqlSchemaHandler $schemaHandler;
    private SqlEntityStorage $storage;
    private Mission1257SpyLogger $logger;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite(':memory:');
        $this->registry = new FieldDefinitionRegistry();
        $this->logger = new Mission1257SpyLogger();

        $this->entityTypeManager = new EntityTypeManager(
            new EventDispatcher(),
            null,
            null,
            $this->registry,
        );

        $this->entityType = new EntityType(
            id: self::ENTITY_TYPE_ID,
            label: 'Mission #1257 Widget',
            class: Mission1257Widget::class,
            keys: [
                'id' => 'wid',
                'uuid' => 'uuid',
                'bundle' => 'type',
                'label' => 'name',
                'langcode' => 'langcode',
            ],
            bundleEntityType: self::BUNDLE_ENTITY_TYPE_ID,
        );

        $this->entityTypeManager->registerEntityType($this->entityType, registrant: self::class);

        // Register a `FieldStorage::Data`-stored core integer field. This is
        // the field WP04 (read symmetry) and WP05 (numeric coercion) both
        // exercise. Registered through the registry directly because
        // `#[Field]` does not yet carry a `stored:` parameter.
        $this->registry->registerCoreFields(
            self::ENTITY_TYPE_ID,
            [
                'rank' => new FieldDefinition(
                    name: 'rank',
                    type: 'integer',
                    targetEntityTypeId: self::ENTITY_TYPE_ID,
                    defaultValue: 0,
                    label: 'Rank',
                    stored: FieldStorage::Data,
                ),
            ],
        );

        $this->schemaHandler = new SqlSchemaHandler(
            $this->entityType,
            $this->database,
            $this->registry,
        );
        $this->schemaHandler->ensureTable();

        $this->storage = new SqlEntityStorage(
            $this->entityType,
            $this->database,
            new EventDispatcher(),
            $this->registry,
            $this->logger,
        );

        // HealthChecker requires a project root with `storage/framework/`.
        $this->projectRoot = sys_get_temp_dir() . '/wp11_kernel_' . uniqid();
        mkdir($this->projectRoot . '/storage/framework', 0755, true);
    }

    protected function tearDown(): void
    {
        // Clear the FieldDefinitionRegistry singleton on ContentEntityBase so
        // subsequent tests do not see this fixture's registry leak across the
        // class-level static.
        $registryProperty = new \ReflectionProperty(ContentEntityBase::class, 'fieldRegistry');
        $registryProperty->setValue(null, null);

        if (!isset($this->projectRoot) || !is_dir($this->projectRoot)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->projectRoot);
    }

    // ------------------------------------------------------------------
    // K1 (WP03) — bundle subtable naming
    // ------------------------------------------------------------------

    #[Test]
    public function k1_resolveSubtableNameProducesCanonicalDoubleUnderscoreFormat(): void
    {
        self::assertSame(
            self::ENTITY_TYPE_ID . '__alpha',
            SqlSchemaHandler::resolveSubtableName(self::ENTITY_TYPE_ID, 'alpha'),
        );

        self::assertSame(
            self::ENTITY_TYPE_ID . '__alpha',
            SqlSchemaHandler::resolveSubtableName(self::ENTITY_TYPE_ID, 'alpha', self::ENTITY_TYPE_ID),
        );
    }

    #[Test]
    public function k1_addBundleFieldsRejectsReservedSeparatorInBundleId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved separator "__"');

        $this->entityTypeManager->addBundleFields(
            self::ENTITY_TYPE_ID,
            'alpha__nested',
            [
                'gizmo_code' => new FieldDefinition(
                    name: 'gizmo_code',
                    type: 'string',
                    targetEntityTypeId: self::ENTITY_TYPE_ID,
                    targetBundle: 'alpha__nested',
                ),
            ],
        );
    }

    // ------------------------------------------------------------------
    // K2 (WP04) — read/write symmetry for FieldStorage::Data
    // ------------------------------------------------------------------

    #[Test]
    public function k2_dataStoredCoreFieldRoundTripsThroughDataBlob(): void
    {
        $this->entityTypeManager->addBundleFields(self::ENTITY_TYPE_ID, 'alpha', [
            'gizmo_code' => new FieldDefinition(
                name: 'gizmo_code',
                type: 'string',
                targetEntityTypeId: self::ENTITY_TYPE_ID,
                targetBundle: 'alpha',
            ),
        ]);
        $this->schemaHandler->ensureTable();

        $entity = new Mission1257Widget([
            'name' => 'Symmetric',
            'type' => 'alpha',
            'rank' => 7,
            'gizmo_code' => 'SYM-1',
        ]);
        $this->storage->save($entity);

        // Write side: `rank` lives in `_data`, never as a base column.
        $row = $this->database->getConnection()->fetchAssociative(
            'SELECT _data FROM "' . self::ENTITY_TYPE_ID . '" WHERE wid = :wid',
            ['wid' => $entity->id()],
        );
        self::assertIsArray($row);

        $data = json_decode((string) $row['_data'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('rank', $data);
        self::assertSame(7, $data['rank']);

        $columns = $this->database->getConnection()->fetchAllAssociative(
            'PRAGMA table_info("' . self::ENTITY_TYPE_ID . '")',
        );
        $columnNames = array_column($columns, 'name');
        self::assertNotContains(
            'rank',
            $columnNames,
            'FieldStorage::Data fields must not be materialized as base columns.',
        );

        // Read side: condition() resolves via `json_extract(_data, ...)`,
        // never through a column lookup.
        $ids = $this->storage->getQuery()
            ->condition('rank', 7)
            ->execute();
        self::assertSame([$entity->id()], $ids);
    }

    // ------------------------------------------------------------------
    // K3 (WP05) — _data value coercion in query builder
    // ------------------------------------------------------------------

    #[Test]
    public function k3_conditionCoercesNumericStringToDeclaredIntegerType(): void
    {
        $entity = new Mission1257Widget([
            'name' => 'Coerce',
            'type' => 'alpha',
            'rank' => 13,
        ]);
        $this->storage->save($entity);

        // Control: integer binding works.
        $idsInt = $this->storage->getQuery()
            ->condition('rank', 13)
            ->execute();
        self::assertSame([$entity->id()], $idsInt, 'integer binding (control)');

        // The mission anchor (#1257): numeric-string against integer-typed
        // `_data` field. Pre-WP05 returned no rows.
        $idsString = $this->storage->getQuery()
            ->condition('rank', '13')
            ->execute();
        self::assertSame(
            [$entity->id()],
            $idsString,
            'numeric-string condition() must coerce per declared FieldDefinition type — '
            . 'callers must not need to know storage shape (Minoo `(int)` workaround removable).',
        );
    }

    #[Test]
    public function k3_inSetAgainstDataIntegerFieldCoercesEachElement(): void
    {
        $a = new Mission1257Widget(['name' => 'A', 'type' => 'alpha', 'rank' => 1]);
        $b = new Mission1257Widget(['name' => 'B', 'type' => 'alpha', 'rank' => 2]);
        $c = new Mission1257Widget(['name' => 'C', 'type' => 'alpha', 'rank' => 3]);
        $this->storage->save($a);
        $this->storage->save($b);
        $this->storage->save($c);

        $ids = $this->storage->getQuery()
            ->condition('rank', ['1', 3], 'IN')
            ->execute();
        sort($ids);

        self::assertSame([$a->id(), $c->id()], $ids);
    }

    // ------------------------------------------------------------------
    // K4 (WP06) — bundle-load drift logging
    // ------------------------------------------------------------------

    #[Test]
    public function k4_loadEmitsMissingBundleSubtableNoticeOncePerBundle(): void
    {
        // Register the bundle AFTER the schema is materialized. This is the
        // drift state the load notice surfaces — registry knows about the
        // bundle, but the subtable was never created.
        $this->entityTypeManager->addBundleFields(self::ENTITY_TYPE_ID, 'alpha', [
            'gizmo_code' => new FieldDefinition(
                name: 'gizmo_code',
                type: 'string',
                targetEntityTypeId: self::ENTITY_TYPE_ID,
                targetBundle: 'alpha',
            ),
        ]);

        // Seed two `alpha` rows directly to bypass the save-side notice and
        // isolate the load-side cadence assertion.
        $this->database->insert(self::ENTITY_TYPE_ID)
            ->fields(['uuid', 'type', 'name', 'langcode', '_data'])
            ->values(['uuid' => 'a', 'type' => 'alpha', 'name' => 'A', 'langcode' => 'en', '_data' => '{}'])
            ->execute();
        $this->database->insert(self::ENTITY_TYPE_ID)
            ->fields(['uuid', 'type', 'name', 'langcode', '_data'])
            ->values(['uuid' => 'b', 'type' => 'alpha', 'name' => 'B', 'langcode' => 'en', '_data' => '{}'])
            ->execute();

        $entities = $this->storage->loadMultiple([1, 2]);
        self::assertCount(2, $entities, 'Base rows still load when subtable is missing — drift is non-fatal.');

        $missing = $this->logger->messagesContaining('[MISSING_BUNDLE_SUBTABLE]');
        self::assertCount(
            1,
            $missing,
            'Load path must emit one notice per (entity_type, bundle) — not one per row.',
        );
        self::assertStringContainsString(self::ENTITY_TYPE_ID, $missing[0]);
        self::assertStringContainsString('alpha', $missing[0]);

        // Memoization: subsequent loads of the same bundle must not re-log.
        $this->storage->loadMultiple([1, 2]);
        self::assertCount(
            1,
            $this->logger->messagesContaining('[MISSING_BUNDLE_SUBTABLE]'),
            'Notice must be memoized for the lifetime of the storage instance.',
        );
    }

    // ------------------------------------------------------------------
    // K6 (WP08) — HealthChecker layer-exempt diagnostics
    // ------------------------------------------------------------------

    #[Test]
    public function k6_healthCheckerSurfacesMissingBundleSubtableDiagnostic(): void
    {
        $this->entityTypeManager->addBundleFields(self::ENTITY_TYPE_ID, 'alpha', [
            'gizmo_code' => new FieldDefinition(
                name: 'gizmo_code',
                type: 'string',
                targetEntityTypeId: self::ENTITY_TYPE_ID,
                targetBundle: 'alpha',
            ),
        ]);
        // Intentionally do NOT call ensureTable() again — leaves the subtable
        // absent, which is exactly what `MISSING_BUNDLE_SUBTABLE` reports.

        $health = $this->newHealthChecker();
        $codes = self::diagnosticCodes($health->checkSchemaDrift());

        self::assertContains(
            DiagnosticCode::MISSING_BUNDLE_SUBTABLE,
            $codes,
            'HealthChecker must surface MISSING_BUNDLE_SUBTABLE when a registered bundle has no subtable.',
        );
    }

    #[Test]
    public function k6_healthCheckerSurfacesOrphanBundleSubtableDiagnostic(): void
    {
        // Materialize an unregistered subtable directly via DDL — simulating
        // a bundle whose fields were removed from the registry but whose
        // storage table lingers.
        $orphanTable = self::ENTITY_TYPE_ID . '__ghost';
        $this->database->getConnection()->executeStatement(
            'CREATE TABLE "' . $orphanTable . '" (wid INTEGER PRIMARY KEY)',
        );

        $health = $this->newHealthChecker();
        $codes = self::diagnosticCodes($health->checkSchemaDrift());

        self::assertContains(
            DiagnosticCode::ORPHAN_BUNDLE_SUBTABLE,
            $codes,
            'HealthChecker must surface ORPHAN_BUNDLE_SUBTABLE for a subtable matching the {base}__% pattern with no registered bundle fields.',
        );
    }

    // ------------------------------------------------------------------
    // K7 (WP07-B) — duplicate-registration message names both registrants
    // ------------------------------------------------------------------

    #[Test]
    public function k7_duplicateRegistrationExceptionNamesBothRegistrantsAndClasses(): void
    {
        $duplicateType = new EntityType(
            id: self::ENTITY_TYPE_ID,
            label: 'Same class, second registration',
            class: Mission1257Widget::class,
            keys: $this->entityType->getKeys(),
            bundleEntityType: self::BUNDLE_ENTITY_TYPE_ID,
        );

        try {
            $this->entityTypeManager->registerEntityType(
                $duplicateType,
                registrant: 'SecondRegistrant',
            );
            self::fail('Expected EntityTypeRegistrationCollisionException');
        } catch (EntityTypeRegistrationCollisionException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('[ENTITY_TYPE_DUPLICATE]', $message);
            self::assertStringContainsString(self::ENTITY_TYPE_ID, $message);
            self::assertStringContainsString(self::class, $message, 'Existing registrant must be named.');
            self::assertStringContainsString('SecondRegistrant', $message, 'Incoming registrant must be named.');
            self::assertStringContainsString(Mission1257Widget::class, $message, 'Both registrant classes must appear.');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function newHealthChecker(): HealthChecker
    {
        $bootReport = new BootDiagnosticReport(
            registeredTypes: [self::ENTITY_TYPE_ID => $this->entityType],
            disabledTypeIds: [],
            schemaCompatibility: [],
        );

        return new HealthChecker(
            bootReport: $bootReport,
            database: $this->database,
            entityTypeManager: $this->entityTypeManager,
            projectRoot: $this->projectRoot,
            logger: $this->logger,
            fieldRegistry: $this->registry,
        );
    }

    /**
     * @param iterable<\Waaseyaa\Foundation\Diagnostic\HealthCheckResult> $results
     * @return list<DiagnosticCode>
     */
    private static function diagnosticCodes(iterable $results): array
    {
        $codes = [];
        foreach ($results as $result) {
            if ($result->code !== null) {
                $codes[] = $result->code;
            }
        }
        return $codes;
    }
}

#[ContentEntityType(id: 'mission1257_widget')]
#[ContentEntityKeys(id: 'wid', uuid: 'uuid', bundle: 'type', label: 'name', langcode: 'langcode')]
final class Mission1257Widget extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}

/**
 * In-memory logger used by both `SqlEntityStorage` (load-side notices) and
 * `HealthChecker` (drift logging) within this fixture. Messages are stored
 * verbatim so tests can assert on the diagnostic code prefix.
 */
final class Mission1257SpyLogger implements LoggerInterface
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
            static fn(string $m): bool => str_contains($m, $needle),
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
