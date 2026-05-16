<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\SaveContext;

/**
 * Unit tests for the WP07 / FR-039 additive {@see AfterSaveEvent::affectedLangcodes()}
 * payload.
 *
 * The patch is strictly backwards-compatible:
 *
 * - Existing callers that pass only the three pre-WP07 positional args
 *   ($entity, $saveContext, $newRevision) compile unchanged and observe
 *   {@see AfterSaveEvent::affectedLangcodes()} returning null.
 * - The new fourth positional arg accepts a sorted, unique list of langcodes
 *   when the M-006 translatable write path is active.
 */
#[CoversClass(AfterSaveEvent::class)]
final class AfterSaveEventAffectedLangcodesTest extends TestCase
{
    #[Test]
    public function defaultsAffectedLangcodesToNullForExistingThreeArgCallers(): void
    {
        $entity = $this->createStubEntity();
        $context = SaveContext::default();

        $event = new AfterSaveEvent($entity, $context, false);

        self::assertNull(
            $event->affectedLangcodes(),
            'Pre-WP07 three-arg callers must observe affectedLangcodes() === null.',
        );
    }

    #[Test]
    public function exposesAffectedLangcodesWhenProvidedAsFourthPositionalArg(): void
    {
        $entity = $this->createStubEntity();
        $context = SaveContext::default();

        $event = new AfterSaveEvent($entity, $context, true, ['en', 'mi-tle']);

        self::assertSame(['en', 'mi-tle'], $event->affectedLangcodes());
    }

    #[Test]
    public function exposesAffectedLangcodesViaNamedArg(): void
    {
        $entity = $this->createStubEntity();
        $context = SaveContext::default();

        $event = new AfterSaveEvent(
            entityValue: $entity,
            saveContextValue: $context,
            newRevision: false,
            affectedLangcodes: ['fr'],
        );

        self::assertSame(['fr'], $event->affectedLangcodes());
    }

    #[Test]
    public function emptyArrayIsHonouredAsExplicitNoAffectedLangcodes(): void
    {
        // Distinct from null: explicit empty list means "the dispatching
        // driver intentionally reported no affected langcodes" rather than
        // "no per-langcode information available".
        $entity = $this->createStubEntity();
        $context = SaveContext::default();

        $event = new AfterSaveEvent($entity, $context, false, []);

        self::assertSame([], $event->affectedLangcodes());
        self::assertNotNull($event->affectedLangcodes());
    }

    #[Test]
    public function preservesPriorAccessors(): void
    {
        // Smoke: the additive patch must not break entity(), saveContext(),
        // or isNewRevision() for callers that read them via the existing
        // method surface.
        $entity = $this->createStubEntity();
        $context = SaveContext::default();

        $event = new AfterSaveEvent($entity, $context, true, ['en']);

        self::assertSame($entity, $event->entity());
        self::assertSame($context, $event->saveContext());
        self::assertTrue($event->isNewRevision());
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
