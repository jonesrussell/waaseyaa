<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData;

/**
 * Maps entity {@code $casts} string tokens to {@see TypedDataManager} {@code dataType} names (#1185).
 *
 * JSON {@code array} / {@code json} casts are not {@code map} or {@code list} without a schema;
 * this mapper returns {@code null} for those so callers do not assume a {@see TypedDataManager} primitive.
 * @api
 */
final class CastTokenMapper
{
    public static function toDataType(string $entityCastToken): ?string
    {
        return match ($entityCastToken) {
            'int' => 'integer',
            'float' => 'float',
            'bool' => 'boolean',
            'string' => 'string',
            'array', 'json' => null,
            default => null,
        };
    }
}
