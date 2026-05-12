<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Backend;

use Waaseyaa\Database\SelectInterface;
use Waaseyaa\Field\FieldDefinition;

/**
 * @api
 *
 * Translates the existing project query operator set into DBAL WHERE clauses
 * for the sql-column backend (T029).
 *
 * ## EntityQuery dependency boundary
 *
 * `EntityQuery` is a marker interface (WP01) and carries no operator methods
 * until WP06 enriches it. This translator targets the operator set that the
 * existing `SqlEntityQuery` already uses in its condition() / `IN` / `LIKE`
 * handling — which is the agreed-upon vocabulary for v0.x.
 *
 * Operators translated here:
 *   =, !=, <, <=, >, >=, IN, NOT IN, LIKE, NOT LIKE, IS NULL, IS NOT NULL
 *
 * The `contains` operator is mapped to `LIKE '%...%'` with wildcard escaping,
 * matching SqlEntityQuery::CONTAINS handling (mission #1257 WP05, K3).
 *
 * ## LIKE wildcard escaping
 *
 * Per the project gotcha: `DBALSelect::condition()` appends `ESCAPE '\'` for
 * LIKE/NOT LIKE operators automatically. This translator escapes `%` and `_`
 * in user input **before** the value reaches the query builder so the
 * per-column query logic is consistent with the legacy path.
 *
 * @internal Used only by SqlColumnBackend.
 */
final class SqlColumnQueryTranslator
{
    /**
     * Apply a single condition to a DBAL select builder.
     *
     * @param SelectInterface $select    The select query to apply the condition to.
     * @param FieldDefinition $field     The field being filtered.
     * @param string          $operator  One of: =, !=, <, <=, >, >=, IN, NOT IN,
     *                                   LIKE, NOT LIKE, IS NULL, IS NOT NULL, CONTAINS.
     * @param mixed           $value     The condition value (ignored for IS NULL/IS NOT NULL).
     *
     * @return SelectInterface The modified select (may be same or new instance depending on impl).
     *
     * @throws \InvalidArgumentException For unsupported operator strings.
     */
    public function apply(
        SelectInterface $select,
        FieldDefinition $field,
        string $operator,
        mixed $value,
    ): SelectInterface {
        $fieldName = $field->getName();
        $op = strtoupper($operator);

        return match ($op) {
            'IS NULL'     => $select->isNull($fieldName),
            'IS NOT NULL' => $select->isNotNull($fieldName),
            'IN'          => $select->condition($fieldName, $this->ensureArray($value), 'IN'),
            'NOT IN'      => $select->condition($fieldName, $this->ensureArray($value), 'NOT IN'),
            'LIKE'        => $select->condition($fieldName, (string) $value, 'LIKE'),
            'NOT LIKE'    => $select->condition($fieldName, (string) $value, 'NOT LIKE'),
            'CONTAINS'    => $select->condition(
                $fieldName,
                '%' . $this->escapeLikeWildcards((string) $value) . '%',
                'LIKE',
            ),
            'STARTS_WITH' => $select->condition(
                $fieldName,
                $this->escapeLikeWildcards((string) $value) . '%',
                'LIKE',
            ),
            '=', '=='     => $select->condition($fieldName, $this->coerce($field, $value), '='),
            '!=', '<>'    => $select->condition($fieldName, $this->coerce($field, $value), '!='),
            '<'           => $select->condition($fieldName, $this->coerce($field, $value), '<'),
            '<='          => $select->condition($fieldName, $this->coerce($field, $value), '<='),
            '>'           => $select->condition($fieldName, $this->coerce($field, $value), '>'),
            '>='          => $select->condition($fieldName, $this->coerce($field, $value), '>='),
            default       => throw new \InvalidArgumentException(sprintf(
                'SqlColumnQueryTranslator: unsupported operator "%s" for field "%s". '
                . 'Supported: =, !=, <, <=, >, >=, IN, NOT IN, LIKE, NOT LIKE, IS NULL, IS NOT NULL, CONTAINS.',
                $operator,
                $fieldName,
            )),
        };
    }

    /**
     * Return true when the operator is one this translator handles.
     *
     * @api
     */
    public function supportsOperator(string $operator): bool
    {
        return in_array(strtoupper($operator), [
            '=', '==', '!=', '<>', '<', '<=', '>', '>=',
            'IN', 'NOT IN',
            'LIKE', 'NOT LIKE',
            'IS NULL', 'IS NOT NULL',
            'CONTAINS', 'STARTS_WITH',
        ], true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Escape LIKE wildcard characters in user input.
     *
     * DBALSelect appends `ESCAPE '\'` for LIKE conditions automatically.
     * We escape `%` and `_` here so they are treated as literals.
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    /**
     * Coerce a condition value to match the field's declared type.
     *
     * This mirrors SqlEntityQuery::coerceConditionValue() for the column path:
     * ensures bool fields get 0/1, int fields get integers, etc. so the
     * SQLite type affinity comparisons commute correctly.
     */
    private function coerce(FieldDefinition $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($field->getType())) {
            'int', 'integer', 'bigint', 'entity_reference' => (int) $value,
            'bool', 'boolean'                               => (int) ((bool) $value),
            'float', 'decimal', 'numeric'                   => is_string($value) ? $value : (float) $value,
            default                                         => $value,
        };
    }

    /**
     * Ensure the value is an array (for IN/NOT IN conditions).
     *
     * @return array<mixed>
     */
    private function ensureArray(mixed $value): array
    {
        return is_array($value) ? $value : [$value];
    }
}
