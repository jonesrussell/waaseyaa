<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Event;

use Waaseyaa\Entity\EntityInterface;

/**
 * Translation-scoped lifecycle event.
 *
 * Subclass of {@see EntityEvent} dispatched once per translation that is being
 * inserted, updated, or deleted within an entity save. Listeners may narrow via
 * `instanceof TranslationEvent` to obtain the {@see $langcode}.
 *
 * The langcode is always a non-empty BCP-47 tag (e.g. `"en"`, `"oj"`,
 * `"fr-CA"`) — translation events without a target language are not valid.
 *
 * @api
 */
final class TranslationEvent extends EntityEvent
{
    public function __construct(
        EntityInterface $entity,
        string $langcode,
        ?EntityInterface $originalEntity = null,
    ) {
        if ($langcode === '') {
            throw new \InvalidArgumentException('TranslationEvent requires a non-empty langcode.');
        }

        parent::__construct($entity, $originalEntity, $langcode);
    }

    /**
     * Translation langcode (non-empty BCP-47 tag).
     *
     * Guaranteed non-null on a {@see TranslationEvent}; the inherited
     * {@see EntityEvent::$langcode} property is typed `?string` to support
     * plain entity-level events that carry no langcode.
     */
    public function langcode(): string
    {
        // \assert keeps the contract clear; constructor already enforced it.
        \assert($this->langcode !== null && $this->langcode !== '');

        return $this->langcode;
    }
}
