<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Backend\BackendRegistrar;
use Waaseyaa\EntityStorage\CoordinatorLifecycleDispatcher;
use Waaseyaa\EntityStorage\Event\AfterDeleteEvent;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\SaveContext;

/**
 * WP07 / FR-039: backfill of {@see AfterSaveEvent::affectedLangcodes()} and
 * {@see AfterDeleteEvent::affectedLangcodes()} by the M-006 translatable
 * write path.
 *
 * NOTE on file name: the WP07 owned_files manifest lists this test as
 * `SqlStorageDriverAffectedLangcodesTest`. The actual dispatch site is the
 * {@see CoordinatorLifecycleDispatcher} — `SqlStorageDriver` is a pure I/O
 * layer that does not dispatch lifecycle events (see the class docblock and
 * `EntityRepository` for the per-driver dispatch site). The test name is
 * preserved to match the manifest; the {@see CoversClass} attribute pins the
 * test to the class actually exercised.
 */
#[CoversClass(CoordinatorLifecycleDispatcher::class)]
#[CoversClass(AfterSaveEvent::class)]
#[CoversClass(AfterDeleteEvent::class)]
final class SqlStorageDriverAffectedLangcodesTest extends TestCase
{
    #[Test]
    public function saveTranslatableEntityBackfillsAffectedLangcodes(): void
    {
        $captured = $this->dispatchSave(
            translationOps: ['en' => 'update', 'mi-tle' => 'insert'],
        );

        self::assertInstanceOf(AfterSaveEvent::class, $captured);
        self::assertSame(
            ['en', 'mi-tle'],
            $captured->affectedLangcodes(),
            'Translatable save with two language ops must backfill both langcodes (sorted).',
        );
    }

    #[Test]
    public function saveBackfillReturnsSortedUniqueList(): void
    {
        $captured = $this->dispatchSave(
            translationOps: ['mi-tle' => 'update', 'en' => 'update'],
        );

        self::assertSame(
            ['en', 'mi-tle'],
            $captured?->affectedLangcodes(),
            'Affected langcodes must be sorted regardless of insertion order.',
        );
    }

    #[Test]
    public function saveNonTranslatableLeavesAffectedLangcodesNull(): void
    {
        $captured = $this->dispatchSave(translationOps: []);

        self::assertInstanceOf(AfterSaveEvent::class, $captured);
        self::assertNull(
            $captured->affectedLangcodes(),
            'Non-translatable save (no translation ops) must leave affectedLangcodes null.',
        );
    }

    #[Test]
    public function deleteTranslatableBackfillsAllExistingLangcodes(): void
    {
        $captured = $this->dispatchDelete(translationLangcodes: ['mi-tle', 'en', 'fr']);

        self::assertInstanceOf(AfterDeleteEvent::class, $captured);
        self::assertSame(
            ['en', 'fr', 'mi-tle'],
            $captured->affectedLangcodes(),
            'Translatable delete must backfill the sorted, unique list of langcodes.',
        );
    }

    #[Test]
    public function deleteNonTranslatableLeavesAffectedLangcodesNull(): void
    {
        $captured = $this->dispatchDelete(translationLangcodes: []);

        self::assertInstanceOf(AfterDeleteEvent::class, $captured);
        self::assertNull(
            $captured->affectedLangcodes(),
            'Non-translatable delete (empty langcode list) must leave affectedLangcodes null.',
        );
    }

    /**
     * @param array<string, string> $translationOps
     */
    private function dispatchSave(array $translationOps): ?AfterSaveEvent
    {
        $dispatcher = new EventDispatcher();
        $captured = null;
        $dispatcher->addListener(
            AfterSaveEvent::class,
            static function (AfterSaveEvent $event) use (&$captured): void {
                $captured = $event;
            },
        );

        $coordinator = $this->makeDispatcher($dispatcher);

        // Empty $groups skips backend fan-out so the test does not need a
        // configured BackendRegistrar — the dispatch is what we are pinning.
        $coordinator->save(
            entity: $this->makeEntity(),
            entityType: $this->makeEntityType(),
            groups: [],
            primaryId: 'noop',
            saveContext: SaveContext::default(),
            isNewRevision: false,
            translationOps: $translationOps,
        );

        return $captured;
    }

    /**
     * @param list<string> $translationLangcodes
     */
    private function dispatchDelete(array $translationLangcodes): ?AfterDeleteEvent
    {
        $dispatcher = new EventDispatcher();
        $captured = null;
        $dispatcher->addListener(
            AfterDeleteEvent::class,
            static function (AfterDeleteEvent $event) use (&$captured): void {
                $captured = $event;
            },
        );

        $coordinator = $this->makeDispatcher($dispatcher);

        $coordinator->delete(
            entity: $this->makeEntity(),
            entityType: $this->makeEntityType(),
            groups: [],
            translationLangcodes: $translationLangcodes,
        );

        return $captured;
    }

    private function makeDispatcher(EventDispatcher $dispatcher): CoordinatorLifecycleDispatcher
    {
        $registrar = new BackendRegistrar([], []);
        $registrar->build();

        return new CoordinatorLifecycleDispatcher($registrar, $dispatcher);
    }

    private function makeEntity(): EntityInterface
    {
        return new class implements EntityInterface {
            public function id(): int|string|null
            {
                return 1;
            }

            public function uuid(): string
            {
                return '00000000-0000-0000-0000-000000000001';
            }

            public function label(): string
            {
                return 'stub';
            }

            public function getEntityTypeId(): string
            {
                return 'stub';
            }

            public function bundle(): string
            {
                return 'stub';
            }

            public function isNew(): bool
            {
                return false;
            }

            public function get(string $name): mixed
            {
                return null;
            }

            public function set(string $name, mixed $value): static
            {
                return $this;
            }

            public function toArray(): array
            {
                return [];
            }

            public function language(): string
            {
                return 'en';
            }
        };
    }

    private function makeEntityType(): EntityTypeInterface
    {
        return new EntityType(
            id: 'stub',
            label: 'Stub',
            class: \stdClass::class,
            keys: ['id' => 'id'],
        );
    }
}
