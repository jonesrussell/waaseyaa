<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Exception;

final class InvalidEntityTypeException extends \InvalidArgumentException
{
    public static function missingLangcodeKey(string $entityTypeId): self
    {
        return new self(\sprintf(
            'Translatable entity type "%s" must declare a "langcode" entity key.',
            $entityTypeId,
        ));
    }

    public static function missingDefaultLangcodeKey(string $entityTypeId): self
    {
        return new self(\sprintf(
            'Translatable entity type "%s" must declare a "default_langcode" entity key.',
            $entityTypeId,
        ));
    }

    public static function translatableEntityClassNotImplementingInterface(
        string $entityTypeId,
        string $entityClass,
    ): self {
        return new self(\sprintf(
            'Translatable entity type "%s" registered class "%s" must implement TranslatableInterface.',
            $entityTypeId,
            $entityClass,
        ));
    }
}
