<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData\Coercion;

/**
 * Thrown when a value cannot be coerced for entity-compatible primitive/array casts (#1185).
 * @api
 */
final class CoercionException extends \InvalidArgumentException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $field = null,
        public readonly ?string $cast = null,
        public readonly bool $fromStored = true,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidStoredValue(
        string $field,
        string $cast,
        mixed $value,
        ?string $detail = null,
        ?\Throwable $previous = null,
    ): self {
        $message = sprintf('Cannot cast stored value for field "%s" to %s.', $field, $cast);
        if ($detail !== null && $detail !== '') {
            $message .= ' ' . $detail;
        }

        return new self($message, 0, $previous, $field, $cast, true);
    }

    public static function invalidDomainValue(
        string $field,
        string $cast,
        mixed $value,
        ?string $detail = null,
    ): self {
        $message = sprintf('Cannot cast domain value for field "%s" to storage for %s.', $field, $cast);
        if ($detail !== null && $detail !== '') {
            $message .= ' ' . $detail;
        }

        return new self($message, 0, null, $field, $cast, false);
    }
}
