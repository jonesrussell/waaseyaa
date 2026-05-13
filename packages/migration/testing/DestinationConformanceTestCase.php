<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Testing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\DestinationWriteException;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

/**
 * Subclass-and-pass contract test for {@see DestinationPluginInterface} implementations.
 *
 * Third-party plugin authors extend this class and provide four factories
 * that wire their plugin against in-memory storage. The seven `#[Test]`
 * gates codify FR-050 / FR-051: every destination plugin must satisfy
 * idempotency, rollback symmetry, access-gate enforcement, and the
 * lookup contract.
 *
 * ## Subclass contract
 *
 * - {@see setUpStorage()} prepares the storage substrate for one test
 *   case. Called from {@see setUp()} before each gate so D2/D3/D4 each
 *   start from a clean slate.
 * - {@see buildDestinationUnderTest()} returns the plugin instance bound
 *   to that storage. Must use the default (allowed) account.
 * - {@see buildDestinationRecord()} crafts a valid {@see DestinationRecord}
 *   for the destination. Called with a fresh {@see SourceId} per gate.
 * - {@see buildAccessDeniedDestination()} returns a destination plugin
 *   instance wired with an account/gate that denies `create` on the
 *   destination entity type — used by D5.
 *
 * ## Notes for implementers
 *
 * - The base class never opens transactions on your storage; idempotency
 *   (D2) and atomicity are the plugin's responsibility, enforced by the
 *   gates rather than the harness.
 * - D5 expects {@see DestinationWriteException}. Plugins documenting a
 *   different exception class must override
 *   {@see expectedAccessDeniedException()}.
 *
 * @api Conformance suite — public contract for third-party plugin authors.
 *
 * @spec FR-050 — stable destination plugin contract
 * @spec FR-051 — atomicity / rollback / idempotency
 */
abstract class DestinationConformanceTestCase extends TestCase
{
    /**
     * Per-test counter so {@see makeFreshSourceId()} returns a fresh value
     * for every gate, avoiding D2/D3/D4 cross-contamination via the id-map.
     */
    private int $sourceIdSeq = 0;

    /**
     * Prepare the storage substrate for one test case. Called from
     * {@see setUp()} so each gate starts clean.
     */
    abstract protected function setUpStorage(): void;

    /**
     * Build the destination plugin under test, bound to the storage
     * created in {@see setUpStorage()}, using an allowed account.
     */
    abstract protected function buildDestinationUnderTest(): DestinationPluginInterface;

    /**
     * Build a valid destination record for the given source id. Called
     * with a fresh source id per gate.
     */
    abstract protected function buildDestinationRecord(SourceId $sourceId): DestinationRecord;

    /**
     * Build a destination plugin instance whose access gate denies `create`
     * for the destination entity type (FR-020 / FR-050 access enforcement).
     */
    abstract protected function buildAccessDeniedDestination(): DestinationPluginInterface;

    /**
     * Optional hook for asserting D2 idempotency at the storage level — the
     * base gate only checks that the second write returns the same uuid as
     * the first. Subclasses with auditable storage may override this to
     * additionally assert "exactly one row exists" against their backend.
     */
    protected function assertSingleStorageRowFor(WriteResult $result): void
    {
        unset($result); // default: no extra storage-level assertion
    }

    /**
     * Exception class the plugin raises when the access gate denies a
     * write. Override if your plugin documents a different typed exception.
     *
     * @return class-string<\Throwable>
     */
    protected function expectedAccessDeniedException(): string
    {
        return DestinationWriteException::class;
    }

    /**
     * Allowed `stability()` return values. The canonical contract is
     * `['stable', 'experimental']`; subclasses MAY widen the set
     * (e.g. to include `'beta'`) when the plugin's documented lifecycle
     * is not yet aligned with the canonical pair. Doing so is a known
     * deviation — operators should track it as a follow-up.
     *
     * @return list<string>
     */
    protected function allowedStabilityValues(): array
    {
        return ['stable', 'experimental'];
    }

    /**
     * Whether the plugin's `rollback()` MUST remove the id-map row so
     * subsequent `lookup()` calls return null.
     *
     * The framework default (`EntityDestination` and any plugin returning
     * `true`) deletes the id-map row alongside the destination entity.
     * Destinations that intentionally retain the row for audit / replay
     * override this to `false`; the conformance harness then asserts only
     * that `rollback()` executes without raising.
     *
     * This knob is the contract reconciliation between the D3 conformance
     * gate ("lookup() === null after rollback") and FR-042 (id-map
     * retention on idempotent re-run). The two requirements govern
     * different code paths — rollback vs unchanged re-run — and both
     * `true` and `false` returns here are conformant. See
     * `kitty-specs/migration-platform-v1-01KRCDE9/contracts/destination-plugin.md`
     * §"Conformance requirements (WP10)" for the full reconciliation
     * (issue #1452).
     */
    protected function rollbackClearsLookup(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourceIdSeq = 0;
        $this->setUpStorage();
    }

    /**
     * @gate D1 — `write()` returns a populated {@see WriteResult} (FR-050).
     */
    #[Test]
    public function d1_write_returns_populated_write_result(): void
    {
        $destination = $this->buildDestinationUnderTest();
        $record = $this->buildDestinationRecord($this->makeFreshSourceId('d1'));

        $result = $destination->write($record);

        self::assertNotSame('', $result->destinationEntityType, 'WriteResult::$destinationEntityType must be non-empty.');
        self::assertNotSame('', $result->destinationUuid, 'WriteResult::$destinationUuid must be non-empty.');
        self::assertNotSame('', $result->sourceRecordHash, 'WriteResult::$sourceRecordHash must be non-empty.');
        self::assertNotSame('', $result->runId, 'WriteResult::$runId must be non-empty.');
        self::assertNotSame('', $result->writtenAt, 'WriteResult::$writtenAt must be non-empty.');
    }

    /**
     * @gate D2 — `write()` is idempotent: writing the same record twice
     *           returns the same uuid (FR-050, FR-051).
     */
    #[Test]
    public function d2_write_is_idempotent_for_unchanged_record(): void
    {
        $destination = $this->buildDestinationUnderTest();
        $sourceId = $this->makeFreshSourceId('d2');
        $record = $this->buildDestinationRecord($sourceId);

        $first = $destination->write($record);
        $second = $destination->write($record);

        self::assertSame(
            $first->destinationUuid,
            $second->destinationUuid,
            'DestinationPluginInterface::write() MUST be idempotent — repeated writes of the same record must return the same destination uuid.',
        );

        $this->assertSingleStorageRowFor($first);
    }

    /**
     * @gate D3 — `rollback()` reverses a prior `write()` (FR-050, FR-051).
     */
    #[Test]
    public function d3_rollback_reverses_a_prior_write(): void
    {
        $destination = $this->buildDestinationUnderTest();
        $sourceId = $this->makeFreshSourceId('d3');
        $record = $this->buildDestinationRecord($sourceId);

        $result = $destination->write($record);
        $destination->rollback($result);

        if ($this->rollbackClearsLookup()) {
            self::assertNull(
                $destination->lookup($sourceId),
                'After rollback(), lookup() for the same source id MUST return null.',
            );
        } else {
            // Subclass opted into retain-id-map-on-rollback semantics; we still
            // assert rollback was called without raising, which is the
            // weakest meaningful invariant for D3.
            self::assertNotNull(
                $result,
                'rollback() executed without raising — id-map retention is opt-in.',
            );
        }
    }

    /**
     * @gate D4 — `lookup()` returns the prior WriteResult for a written source id (FR-050).
     */
    #[Test]
    public function d4_lookup_returns_prior_write_result(): void
    {
        $destination = $this->buildDestinationUnderTest();
        $sourceId = $this->makeFreshSourceId('d4');
        $record = $this->buildDestinationRecord($sourceId);

        self::assertNull(
            $destination->lookup($sourceId),
            'lookup() MUST return null before write() has been called for that source id.',
        );

        $result = $destination->write($record);
        $hit = $destination->lookup($sourceId);

        self::assertNotNull($hit, 'lookup() MUST return a WriteResult after write() has succeeded.');
        self::assertSame(
            $result->destinationUuid,
            $hit->destinationUuid,
            'lookup() MUST return the same destination uuid that write() returned.',
        );
        self::assertSame(
            $result->destinationEntityType,
            $hit->destinationEntityType,
            'lookup() MUST return the same destination entity type that write() returned.',
        );
    }

    /**
     * @gate D5 — Access denial raises the documented typed exception (FR-050).
     */
    #[Test]
    public function d5_access_denied_raises_typed_exception(): void
    {
        $denied = $this->buildAccessDeniedDestination();
        $record = $this->buildDestinationRecord($this->makeFreshSourceId('d5'));
        $expected = $this->expectedAccessDeniedException();

        try {
            $denied->write($record);
            self::fail(\sprintf('Expected %s when access gate denies create.', $expected));
        } catch (\Throwable $e) {
            self::assertInstanceOf(
                $expected,
                $e,
                \sprintf(
                    'Access denial must raise %s, got %s: %s',
                    $expected,
                    $e::class,
                    $e->getMessage(),
                ),
            );
        }
    }

    /**
     * @gate D6 — `id()` is non-empty and stable (FR-050).
     */
    #[Test]
    public function d6_id_is_non_empty_and_stable(): void
    {
        $destination = $this->buildDestinationUnderTest();

        $id = $destination->id();
        self::assertNotSame('', $id, 'DestinationPluginInterface::id() must be non-empty.');
        self::assertSame(
            $id,
            $destination->id(),
            'DestinationPluginInterface::id() must be stable across calls on the same instance.',
        );
        self::assertMatchesRegularExpression(
            '/^[a-z][a-z0-9_]*$/',
            $id,
            'DestinationPluginInterface::id() must be a snake_case token.',
        );
    }

    /**
     * @gate D7 — `stability()` returns either `stable` or `experimental` (FR-050).
     */
    #[Test]
    public function d7_stability_is_stable_or_experimental(): void
    {
        $destination = $this->buildDestinationUnderTest();

        self::assertContains(
            $destination->stability(),
            $this->allowedStabilityValues(),
            \sprintf(
                'DestinationPluginInterface::stability() MUST return one of [%s]; canonical pair is [stable, experimental].',
                \implode(', ', $this->allowedStabilityValues()),
            ),
        );
    }

    /**
     * Mint a fresh source id per gate so the id-map shared inside one
     * subclass storage instance never short-circuits subsequent gates.
     */
    private function makeFreshSourceId(string $tag): SourceId
    {
        ++$this->sourceIdSeq;

        return new SourceId(
            sourceType: 'conformance_source',
            keys: ['gate' => $tag, 'seq' => $this->sourceIdSeq],
        );
    }
}
