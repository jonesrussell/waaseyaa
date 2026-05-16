<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\EntityStorage\Event\AfterDeleteEvent;

/**
 * Unit tests for the WP07 / FR-039 additive {@see AfterDeleteEvent::affectedLangcodes()}
 * payload.
 *
 * Mirrors {@see AfterSaveEventAffectedLangcodesTest} for the delete surface. The
 * patch is strictly backwards-compatible:
 *
 * - Existing callers that pass only the single positional arg ($entity) compile
 *   unchanged and observe {@see AfterDeleteEvent::affectedLangcodes()} returning null.
 * - The new second positional arg accepts the sorted, unique list of langcodes
 *   whose translation rows were just deleted by the M-006 translatable write path.
 */
#[CoversClass(AfterDeleteEvent::class)]
final class AfterDeleteEventAffectedLangcodesTest extends TestCase
{
    #[Test]
    public function defaultsAffectedLangcodesToNullForExistingSingleArgCallers(): void
    {
        $event = new AfterDeleteEvent($this->createStubEntity());

        self::assertNull(
            $event->affectedLangcodes(),
            'Pre-WP07 single-arg callers must observe affectedLangcodes() === null.',
        );
    }

    #[Test]
    public function exposesAffectedLangcodesWhenProvidedAsSecondPositionalArg(): void
    {
        $event = new AfterDeleteEvent($this->createStubEntity(), ['en', 'mi-tle']);

        self::assertSame(['en', 'mi-tle'], $event->affectedLangcodes());
    }

    #[Test]
    public function exposesAffectedLangcodesViaNamedArg(): void
    {
        $event = new AfterDeleteEvent(
            entityValue: $this->createStubEntity(),
            affectedLangcodes: ['fr'],
        );

        self::assertSame(['fr'], $event->affectedLangcodes());
    }

    #[Test]
    public function emptyArrayIsHonouredAsExplicitNoAffectedLangcodes(): void
    {
        $event = new AfterDeleteEvent($this->createStubEntity(), []);

        self::assertSame([], $event->affectedLangcodes());
        self::assertNotNull($event->affectedLangcodes());
    }

    #[Test]
    public function preservesPriorEntityAccessor(): void
    {
        $entity = $this->createStubEntity();
        $event = new AfterDeleteEvent($entity, ['en']);

        self::assertSame($entity, $event->entity());
    }

    private function createStubEntity(): EntityInterface
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
}
