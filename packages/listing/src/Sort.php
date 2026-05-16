<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

/**
 * Ergonomic factory surface for {@see SortDefinition} construction.
 *
 * @api
 */
final class Sort
{
    private function __construct() {}

    public static function asc(string $field): SortDefinition
    {
        return new SortDefinition($field, SortDirection::ASC);
    }

    public static function desc(string $field): SortDefinition
    {
        return new SortDefinition($field, SortDirection::DESC);
    }
}
