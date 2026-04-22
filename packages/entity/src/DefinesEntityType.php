<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

/**
 * Entity classes that expose a static {@see EntityType} for attribute-driven registration.
 *
 * Used when {@see \Waaseyaa\Foundation\Attribute\AsEntityType} is present on the class and
 * {@see \Waaseyaa\Foundation\Kernel\Bootstrap\ProviderRegistry} auto-registration is enabled.
 */
interface DefinesEntityType
{
    public static function entityType(): EntityType;
}
