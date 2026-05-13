<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Entity\Event\TranslationEvent;

/**
 * WP08 — TranslationEvent payload shape and listener narrowing.
 *
 * Verifies:
 * - langcode is exposed both via the {@see TranslationEvent::$langcode} field
 *   and the inherited {@see EntityEvent::$langcode} field (which is non-null
 *   for translation events and null for entity-level events).
 * - EntityEvent stays unsealed: TranslationEvent extends EntityEvent.
 * - originalEntity remains optional.
 * - Listeners can narrow via `instanceof TranslationEvent`.
 * - Six new event-name constants are present on EntityEvents.
 */
#[CoversClass(TranslationEvent::class)]
#[CoversClass(EntityEvent::class)]
#[CoversClass(EntityEvents::class)]
final class TranslationEventTest extends TestCase
{
    #[Test]
    public function translation_event_extends_entity_event(): void
    {
        $entity = new TranslationEventTestEntity(['id' => '1', 'title' => 'hi']);
        $event = new TranslationEvent($entity, 'en');

        self::assertInstanceOf(EntityEvent::class, $event);
    }

    #[Test]
    public function translation_event_exposes_langcode_via_method_and_inherited_field(): void
    {
        $entity = new TranslationEventTestEntity(['id' => '1', 'title' => 'hi']);
        $event = new TranslationEvent($entity, 'oj');

        self::assertSame('oj', $event->langcode(), 'TranslationEvent::langcode() must return the constructor langcode.');
        self::assertSame('oj', $event->langcode, 'EntityEvent::$langcode (inherited) must be set to the same value.');
        self::assertSame($entity, $event->entity);
        self::assertNull($event->originalEntity);
    }

    #[Test]
    public function translation_event_carries_original_entity_when_supplied(): void
    {
        $entity = new TranslationEventTestEntity(['id' => '1', 'title' => 'new']);
        $original = new TranslationEventTestEntity(['id' => '1', 'title' => 'old']);
        $event = new TranslationEvent($entity, 'fr-CA', $original);

        self::assertSame($original, $event->originalEntity);
        self::assertSame('fr-CA', $event->langcode());
        self::assertSame('fr-CA', $event->langcode);
    }

    #[Test]
    public function plain_entity_event_has_null_langcode_by_default(): void
    {
        $entity = new TranslationEventTestEntity(['id' => '1']);
        $event = new EntityEvent($entity);

        self::assertNull($event->langcode);
        self::assertFalse($event instanceof TranslationEvent);
    }

    #[Test]
    public function entity_event_accepts_explicit_null_langcode_for_backward_compat(): void
    {
        $entity = new TranslationEventTestEntity(['id' => '1']);
        $original = new TranslationEventTestEntity(['id' => '1', 'title' => 'old']);

        // Two-arg call (pre-WP08 signature) still works.
        $legacy = new EntityEvent($entity, $original);
        self::assertSame($entity, $legacy->entity);
        self::assertSame($original, $legacy->originalEntity);
        self::assertNull($legacy->langcode);
    }

    #[Test]
    public function listener_can_narrow_via_instanceof(): void
    {
        $entity = new TranslationEventTestEntity(['id' => '5']);
        $plain = new EntityEvent($entity);
        $translation = new TranslationEvent($entity, 'en');

        $narrowed = [];
        foreach ([$plain, $translation] as $event) {
            if ($event instanceof TranslationEvent) {
                $narrowed[] = $event->langcode();
            }
        }

        self::assertSame(['en'], $narrowed);
    }

    #[Test]
    public function six_new_translation_event_constants_are_registered(): void
    {
        self::assertSame('waaseyaa.entity.pre_translation_insert', EntityEvents::PRE_TRANSLATION_INSERT->value);
        self::assertSame('waaseyaa.entity.post_translation_insert', EntityEvents::POST_TRANSLATION_INSERT->value);
        self::assertSame('waaseyaa.entity.pre_translation_update', EntityEvents::PRE_TRANSLATION_UPDATE->value);
        self::assertSame('waaseyaa.entity.post_translation_update', EntityEvents::POST_TRANSLATION_UPDATE->value);
        self::assertSame('waaseyaa.entity.pre_translation_delete', EntityEvents::PRE_TRANSLATION_DELETE->value);
        self::assertSame('waaseyaa.entity.post_translation_delete', EntityEvents::POST_TRANSLATION_DELETE->value);
    }

    #[Test]
    public function translation_event_is_final(): void
    {
        $reflection = new \ReflectionClass(TranslationEvent::class);
        self::assertTrue($reflection->isFinal(), 'TranslationEvent must be final.');
    }

    #[Test]
    public function entity_event_is_not_final_so_translation_event_can_extend_it(): void
    {
        $reflection = new \ReflectionClass(EntityEvent::class);
        self::assertFalse($reflection->isFinal(), 'EntityEvent must NOT be final so subclasses like TranslationEvent can extend it.');
    }
}

/**
 * @internal Test fixture entity.
 */
final class TranslationEventTestEntity extends ContentEntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct($values, 'translation_event_test', ['id' => 'id']);
    }
}
