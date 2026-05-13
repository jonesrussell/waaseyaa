<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Exception;

/**
 * Thrown for all translation-related runtime failures.
 *
 * Use the static factory methods to produce instances with formatted messages.
 * Raw instantiation via `new self(...)` is possible but discouraged outside this class.
 *
 * @api
 */
final class EntityTranslationException extends \DomainException
{
    /**
     * The requested translation langcode does not exist on this entity.
     */
    public static function translationNotFound(string $langcode): self
    {
        return new self(\sprintf('Translation for langcode "%s" does not exist.', $langcode));
    }

    /**
     * The default translation cannot be removed.
     */
    public static function cannotRemoveDefault(string $langcode): self
    {
        return new self(\sprintf(
            'Cannot remove the default translation "%s". Change the default langcode first.',
            $langcode,
        ));
    }

    /**
     * A langcode is required but was not set on the entity.
     */
    public static function langcodeRequired(): self
    {
        return new self('The entity has no default_langcode value. Set one before calling translation methods.');
    }

    /**
     * The entity type does not support translations.
     */
    public static function notTranslatable(string $entityTypeId): self
    {
        return new self(\sprintf(
            'Entity type "%s" is not translatable. Enable translations in the EntityType definition.',
            $entityTypeId,
        ));
    }

    /**
     * A translation for the given langcode already exists.
     */
    public static function translationAlreadyExists(string $langcode): self
    {
        return new self(\sprintf('A translation for langcode "%s" already exists.', $langcode));
    }
}
