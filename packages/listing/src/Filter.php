<?php

declare(strict_types=1);

namespace Waaseyaa\Listing;

/**
 * Ergonomic factory surface for {@see FilterDefinition} construction.
 *
 * Static-only — instances cannot be constructed.
 *
 * @api
 */
final class Filter
{
    private function __construct() {}

    public static function eq(string $field, mixed $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::EQ, $value);
    }

    public static function neq(string $field, mixed $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::NEQ, $value);
    }

    public static function lt(string $field, mixed $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::LT, $value);
    }

    public static function lte(string $field, mixed $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::LTE, $value);
    }

    public static function gt(string $field, mixed $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::GT, $value);
    }

    public static function gte(string $field, mixed $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::GTE, $value);
    }

    /**
     * @param list<mixed> $values
     */
    public static function in(string $field, array $values): FilterDefinition
    {
        return new FilterDefinition($field, Operator::IN, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public static function notIn(string $field, array $values): FilterDefinition
    {
        return new FilterDefinition($field, Operator::NOT_IN, $values);
    }

    public static function isNull(string $field): FilterDefinition
    {
        return new FilterDefinition($field, Operator::IS_NULL, null);
    }

    public static function isNotNull(string $field): FilterDefinition
    {
        return new FilterDefinition($field, Operator::IS_NOT_NULL, null);
    }

    public static function between(string $field, mixed $low, mixed $high): FilterDefinition
    {
        return new FilterDefinition($field, Operator::BETWEEN, [$low, $high]);
    }

    public static function startsWith(string $field, string $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::STARTS_WITH, $value);
    }

    public static function contains(string $field, string $value): FilterDefinition
    {
        return new FilterDefinition($field, Operator::CONTAINS, $value);
    }

    /**
     * Canonical langcode filter (FR-046 / R-09).
     *
     * The `langcode` field name is canonical across translatable entity types.
     */
    public static function langcode(string $code): FilterDefinition
    {
        return new FilterDefinition('langcode', Operator::EQ, $code);
    }

    /**
     * Return a copy of $base bound to URL parameter $param.
     */
    public static function exposed(FilterDefinition $base, string $param): FilterDefinition
    {
        return $base->withExposed($param);
    }
}
