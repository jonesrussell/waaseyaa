<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Testing\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface;
use Waaseyaa\EntityStorage\Query\EntityQuery;
use Waaseyaa\Field\FieldDefinition;

/**
 * @api
 *
 * Abstract contract test harness for {@see FieldStorageBackendInterface}.
 *
 * Any class implementing `FieldStorageBackendInterface` SHOULD subclass this
 * harness and pass all inherited tests. Subclasses provide a backend instance
 * (wired to an in-memory SQLite database) and a fixture entity with a known
 * stable id. The harness then exercises the five contract obligations:
 *
 * 1. `id()` returns a stable, non-empty string (idempotent).
 * 2. Full read/write/delete round-trip succeeds.
 * 3. Writing the same value twice yields identical stored state (idempotent write).
 * 4. `supportsQuery()` returns the correct boolean for the given field.
 * 5. `delete()` cascades: a subsequent `read()` returns null.
 *
 * ## Usage in a concrete test class
 *
 * ```php
 * #[CoversNothing]
 * final class MySqlBlobConformanceTest extends FieldStorageBackendContractTestCase
 * {
 *     protected function createBackend(): FieldStorageBackendInterface
 *     {
 *         $db = DBALDatabase::createSqlite();
 *         // …set up schema…
 *         return new SqlBlobBackend($db, 'test_entity', 'id', 'test_entity');
 *     }
 *
 *     protected function prepareFixtureEntity(): EntityInterface
 *     {
 *         return new TestStorageEntity(['id' => '1', 'label' => 'Fixture']);
 *     }
 *
 *     protected function fixtureField(): FieldDefinition
 *     {
 *         return new FieldDefinition(name: 'label', type: 'string');
 *     }
 *
 *     protected function fixtureValue(): mixed { return 'hello'; }
 *     protected function alternateValue(): mixed { return 'world'; }
 *
 *     protected function supportsQueryField(): FieldDefinition
 *     {
 *         return new FieldDefinition(name: 'label', type: 'string');
 *     }
 *
 *     protected function expectSupportsQuery(): bool { return false; } // blob never supports queries
 * }
 * ```
 *
 * ## Placement rule
 *
 * This file lives under `testing/` (NOT `src/`) and is registered via
 * `autoload-dev` only. Placing it under `src/` would expose the
 * `PHPUnit\Framework\TestCase` parent to production class scans in
 * `PackageManifestCompiler`, crashing consumer kernel boot.
 *
 * @see FieldStorageBackendInterface
 */
#[CoversNothing]
abstract class FieldStorageBackendContractTestCase extends TestCase
{
    // -------------------------------------------------------------------------
    // Template methods — subclasses MUST implement these
    // -------------------------------------------------------------------------

    /**
     * Return a fully constructed backend instance ready for testing.
     *
     * The backend MUST be wired to an in-memory SQLite database. The fixture
     * entity returned by {@see prepareFixtureEntity()} MUST already have a row
     * in the entity table so that `read()` and `write()` can operate.
     */
    abstract protected function createBackend(): FieldStorageBackendInterface;

    /**
     * Return a fixture entity with a stable, non-null id.
     *
     * The entity row MUST exist in the database before the test harness is
     * called. Use {@see setUp()} in the concrete class to persist it.
     */
    abstract protected function prepareFixtureEntity(): EntityInterface;

    /**
     * Return the FieldDefinition that the backend SHOULD store for the fixture entity.
     *
     * The field name MUST correspond to a column or blob key that the backend
     * can handle for the entity table used by {@see createBackend()}.
     */
    abstract protected function fixtureField(): FieldDefinition;

    /**
     * Return the primary test value to write via `write()`.
     *
     * Must be a non-null value compatible with {@see fixtureField()}'s type.
     */
    abstract protected function fixtureValue(): mixed;

    /**
     * Return a second distinct value to verify idempotent-re-write behaviour.
     *
     * Must differ from {@see fixtureValue()} so the test can verify the
     * second write overwrites the first.
     */
    abstract protected function alternateValue(): mixed;

    /**
     * Return the FieldDefinition used for the `supportsQuery()` contract test.
     *
     * May be the same as {@see fixtureField()} or a different field depending
     * on what the backend is expected to support.
     */
    abstract protected function supportsQueryField(): FieldDefinition;

    /**
     * Return the expected result of `supportsQuery()` for {@see supportsQueryField()}.
     *
     * `sql-blob` returns false for all fields; `sql-column` returns true for
     * non-vector field types.
     */
    abstract protected function expectSupportsQuery(): bool;

    // -------------------------------------------------------------------------
    // Shared state — built in setUp()
    // -------------------------------------------------------------------------

    private FieldStorageBackendInterface $backend;
    private EntityInterface $entity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backend = $this->createBackend();
        $this->entity  = $this->prepareFixtureEntity();
    }

    // -------------------------------------------------------------------------
    // Contract test: T063-1 — id stability
    // -------------------------------------------------------------------------

    /**
     * `id()` MUST return a stable non-empty string; two calls return the same value.
     */
    #[Test]
    public function idIsStableString(): void
    {
        $first  = $this->backend->id();
        $second = $this->backend->id();

        self::assertIsString($first, 'id() must return a string');
        self::assertNotEmpty($first, 'id() must not return an empty string');
        self::assertSame($first, $second, 'id() must be idempotent');
    }

    // -------------------------------------------------------------------------
    // Contract test: T063-2 — read/write/delete round-trip
    // -------------------------------------------------------------------------

    /**
     * A full CRUD round-trip: write → read → delete → read returns null.
     *
     * Verifies FR-049 (conformance suite coverage) and FR-050 (round-trips).
     */
    #[Test]
    public function readWriteDeleteRoundTrip(): void
    {
        $field = $this->fixtureField();
        $value = $this->fixtureValue();

        // Write the value.
        $this->backend->write($this->entity, $field, $value);

        // Read it back — must equal the written value.
        $stored = $this->backend->read($this->entity, $field);
        self::assertEquals($value, $stored, 'read() must return the value previously written');

        // Delete and verify the field is gone.
        $this->backend->delete($this->entity);
        $afterDelete = $this->backend->read($this->entity, $field);

        // After delete the backend must return null (field no longer stored).
        self::assertNull($afterDelete, 'read() after delete() must return null');
    }

    // -------------------------------------------------------------------------
    // Contract test: T063-3 — idempotent re-write
    // -------------------------------------------------------------------------

    /**
     * Writing the same field twice yields the same state as writing once.
     *
     * Specifically: writing value A then value B must produce B (not A+B or an error).
     * Verifies the `write()` idempotency clause in {@see FieldStorageBackendInterface}.
     */
    #[Test]
    public function idempotentRewrite(): void
    {
        $field    = $this->fixtureField();
        $first    = $this->fixtureValue();
        $second   = $this->alternateValue();

        // Write the first value.
        $this->backend->write($this->entity, $field, $first);
        // Overwrite with the second value.
        $this->backend->write($this->entity, $field, $second);

        $stored = $this->backend->read($this->entity, $field);
        self::assertEquals($second, $stored, 'Overwriting a field must produce the new value, not a duplicate');

        // Writing second value again must not corrupt the state.
        $this->backend->write($this->entity, $field, $second);
        $storedAgain = $this->backend->read($this->entity, $field);
        self::assertEquals($second, $storedAgain, 'Writing the same value twice must be idempotent');
    }

    // -------------------------------------------------------------------------
    // Contract test: T063-4 — supportsQuery contract
    // -------------------------------------------------------------------------

    /**
     * `supportsQuery()` returns the declared value for the given field and a null-query.
     *
     * The spec requires that callers invoke `supportsQuery()` at definition-validation
     * time with the actual query object. Because {@see EntityQuery} is a marker
     * interface (WP06 enriches it), we pass an anonymous implementation here.
     *
     * Verifies FR-050 (supportsQuery semantics).
     */
    #[Test]
    public function supportsQueryContract(): void
    {
        $field = $this->supportsQueryField();
        $query = new class implements EntityQuery {};

        $result = $this->backend->supportsQuery($field, $query);

        self::assertIsBool($result, 'supportsQuery() must return bool');
        self::assertSame(
            $this->expectSupportsQuery(),
            $result,
            sprintf(
                'Backend "%s" expected supportsQuery() = %s for field type "%s"',
                $this->backend->id(),
                $this->expectSupportsQuery() ? 'true' : 'false',
                $field->getType(),
            ),
        );
    }

    // -------------------------------------------------------------------------
    // Contract test: T063-5 — delete cascade
    // -------------------------------------------------------------------------

    /**
     * `delete()` removes all values the backend holds for the entity.
     *
     * Called a second time (cascade / idempotent delete) must not throw.
     * After deletion `read()` must return null for both the fixture field
     * and the alternate field (verifying that the whole entity is cleared,
     * not just the last-written field).
     *
     * Verifies the cascade clause in `delete()` documentation.
     */
    #[Test]
    public function deleteCascade(): void
    {
        $field   = $this->fixtureField();
        $value   = $this->fixtureValue();

        $this->backend->write($this->entity, $field, $value);

        // Primary delete.
        $this->backend->delete($this->entity);

        self::assertNull(
            $this->backend->read($this->entity, $field),
            'After delete(), read() on the written field must return null',
        );

        // Second delete must be idempotent (no exception).
        $threw = false;
        try {
            $this->backend->delete($this->entity);
        } catch (\Throwable) {
            $threw = true;
        }

        self::assertFalse($threw, 'A second delete() call must not throw (idempotent cascade)');
    }
}
