<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

/**
 * Operator vocabulary for {@see FilterDefinition}.
 *
 * Backing strings are stable surface — used in cache-key emission and
 * `var/manifest.php` round-trip. Future operators are additive.
 *
 * @api
 */
enum Operator: string
{
    case EQ = 'eq';
    case NEQ = 'neq';
    case LT = 'lt';
    case LTE = 'lte';
    case GT = 'gt';
    case GTE = 'gte';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';
    case BETWEEN = 'between';
    case STARTS_WITH = 'starts_with';
    case CONTAINS = 'contains';
}
