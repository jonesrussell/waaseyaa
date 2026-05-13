<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Exception;

/**
 * Thrown when a FieldDefinition violates a structural invariant.
 *
 * @api
 */
final class InvalidFieldDefinitionException extends \InvalidArgumentException
{
    /**
     * A field marked translatable was registered on a non-translatable entity type.
     */
    public static function translatableOnNonTranslatableEntityType(
        string $fieldName,
        string $entityTypeId,
    ): self {
        return new self(\sprintf(
            'Field "%s" is marked translatable but entity type "%s" does not support translations. '
            . 'Either set translatable: false on the field or enable translations on the entity type.',
            $fieldName,
            $entityTypeId,
        ));
    }

    /**
     * A system key field (id, uuid, langcode, default_langcode, revision) was marked translatable.
     *
     * System key fields are shared across all translations and must never be per-translation.
     */
    public static function systemKeyMarkedTranslatable(string $fieldName): self
    {
        return new self(\sprintf(
            'Field "%s" is a system key field and cannot be marked translatable. '
            . 'System key fields (id, uuid, langcode, default_langcode, revision) are shared across all translations.',
            $fieldName,
        ));
    }
}
