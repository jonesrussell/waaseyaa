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

    /**
     * A historical revision was loaded via `getTranslation($langcode)->loadRevision($vid)`
     * and the caller attempted to save it. Historical revisions are read-only;
     * the caller must load the current revision and save that instead.
     *
     * Stable error code: `'historical_revision_write'` (FR-040, FR-041).
     *
     * @see https://kitty-specs/entity-storage-translatable-revisions-01KRCDEE/contracts/exception-surface.md §3.2
     */
    public static function historicalRevisionWrite(int $vid, string $langcode): self
    {
        $message = \sprintf(
            'Cannot save a historical revision (vid=%d, langcode=%s); load the current revision and save that.',
            $vid,
            $langcode,
        );

        $instance = new self($message);
        // \DomainException's code is int by default; expose the stable string code
        // by reflecting it onto the protected `code` property (PHP allows int or string).
        $reflection = new \ReflectionProperty(\Exception::class, 'code');
        $reflection->setValue($instance, 'historical_revision_write');

        return $instance;
    }
}
