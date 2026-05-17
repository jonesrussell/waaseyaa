<?php

declare(strict_types=1);

namespace Waaseyaa\Billing;

/**
 * @api
 */
enum PlanTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Business = 'business';
    case Growth = 'growth';
    case Enterprise = 'enterprise';

    /**
     * Resolve a string to a PlanTier, with special handling for "founding" -> Business.
     */
    public static function fromString(string $value): self
    {
        if ($value === 'founding') {
            return self::Business;
        }

        return self::tryFrom($value) ?? self::Free;
    }

    /**
     * Check if a string is a valid tier value (not including aliases like "founding").
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    public function isPaid(): bool
    {
        return $this !== self::Free;
    }
}
