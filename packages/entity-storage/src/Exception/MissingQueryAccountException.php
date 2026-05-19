<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

use Waaseyaa\Entity\EntityTypeInterface;

/**
 * @api
 *
 * Raised by {@see \Waaseyaa\EntityStorage\SqlEntityQuery::execute()} when access
 * checking is enabled (the default for every new query) but no account has been
 * bound via {@see \Waaseyaa\Entity\Storage\EntityQueryInterface::setAccount()}.
 *
 * This is the fail-closed default: silent access bypass is rejected by design.
 *
 * Resolution paths (both surfaced in the exception message so debugging from a
 * stack trace alone is sufficient):
 *
 *   1. Bind the acting account before executing — `$query->setAccount($account)`.
 *   2. For trusted system contexts, opt out explicitly — `$query->accessCheck(false)`.
 *
 * Construction is restricted to the {@see self::forQuery()} named factory so
 * the message shape stays uniform across the codebase.
 */
final class MissingQueryAccountException extends \RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Build the exception for a query targeting the given entity type.
     *
     * @api
     */
    public static function forQuery(EntityTypeInterface $entityType): self
    {
        return new self(sprintf(
            'Cannot execute SqlEntityQuery for entity type "%s": access checking is enabled '
            . 'but no account is bound. Call setAccount() before execute(), or call '
            . 'accessCheck(false) for system contexts.',
            $entityType->id(),
        ));
    }
}
