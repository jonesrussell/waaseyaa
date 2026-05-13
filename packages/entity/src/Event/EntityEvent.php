<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Waaseyaa\Entity\EntityInterface;

/**
 * Lifecycle event carrying an entity through PRE_/POST_INSERT/UPDATE/DELETE phases.
 *
 * Not declared `final`: {@see TranslationEvent} narrows this event for per-translation
 * dispatches (PRE_TRANSLATION_INSERT/UPDATE/DELETE and POST_ counterparts). The
 * {@see $langcode} property is `null` for entity-level events and a non-empty BCP-47
 * tag for translation-scoped events. Listeners may narrow via `instanceof
 * TranslationEvent` when they need translation-specific context.
 *
 * Backward-compatible: existing two-arg callers (`new EntityEvent($entity, $original)`)
 * continue to work; `$langcode` defaults to `null`.
 *
 * @api
 */
class EntityEvent extends Event
{
    public function __construct(
        public readonly EntityInterface $entity,
        public readonly ?EntityInterface $originalEntity = null,
        public readonly ?string $langcode = null,
    ) {}
}
