<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Tools;

/**
 * Thrown when {@see ToolRegistryInterface::get()} is called for an
 * unknown tool name.
 *
 * @api
 */
final class ToolNotFoundException extends \RuntimeException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('No agent tool registered under name "%s".', $name));
    }
}
