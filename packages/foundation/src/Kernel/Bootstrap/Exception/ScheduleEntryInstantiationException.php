<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap\Exception;

/**
 * Thrown when ScheduleEntryRegistry cannot instantiate a ScheduleEntriesInterface class.
 *
 * This is a boot-time fatal error. Kernel boot fails immediately; no silent logging.
 *
 * @api
 */
final class ScheduleEntryInstantiationException extends \RuntimeException
{
    public static function fromUnresolvableDependency(
        string $fqcn,
        string $paramName,
        string $dependencyType,
    ): self {
        return new self(sprintf(
            "Failed to boot schedule entry '%s':\n" .
            "  Cannot resolve constructor parameter '\$%s' of type '%s'.\n" .
            "  Ensure a service provider binds '%s' before kernel boot.\n" .
            '  See: docs/specs/operations-playbooks.md#schedule-entries',
            $fqcn,
            $paramName,
            $dependencyType,
            $dependencyType,
        ));
    }

    public static function fromThrowable(string $fqcn, \Throwable $cause): self
    {
        return new self(sprintf(
            "Failed to boot schedule entry '%s': %s",
            $fqcn,
            $cause->getMessage(),
        ), 0, $cause);
    }
}
