<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\EntityStorage\Exception\BundleAmbiguousFieldException;
use Waaseyaa\EntityStorage\Exception\UnknownFieldException;

/**
 * SQL-based entity query implementation.
 *
 * Wraps the database select query builder to provide entity-level
 * querying with conditions, sorting, ranges, and counting.
 *
 * When a FieldDefinitionRegistry is provided, field references in conditions
 * and sorts are resolved per docs/specs/bundle-scoped-fields.md §Query: core
 * fields stay on the base table, bundle-scoped fields trigger an INNER JOIN
 * against the `{base}__{bundle}` subtable (deduplicated per bundle), and
 * field names that exist in multiple bundles must be narrowed by an explicit
 * bundle condition (or by a sibling bundle-scoped condition that uniquely
 * identifies the bundle).
 */
final class SqlEntityQuery implements EntityQueryInterface
{
    private readonly string $tableName;
    private readonly string $idKey;
    private readonly ?string $bundleKey;

    /** @var array<int, array{field: string, value: mixed, operator: string}> */
    private array $conditions = [];

    /** @var array<int, array{field: string, direction: string}> */
    private array $sorts = [];

    private ?int $rangeOffset = null;
    private ?int $rangeLimit = null;
    private bool $isCount = false;

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct(
        private readonly EntityTypeInterface $entityType,
        private readonly DatabaseInterface $database,
        private readonly ?SqlEntityQueryResultCache $resultCache = null,
        private readonly ?FieldDefinitionRegistryInterface $fieldRegistry = null,
    ) {
        $this->tableName = $this->entityType->id();
        $keys = $this->entityType->getKeys();
        $this->idKey = $keys['id'] ?? 'id';
        $this->bundleKey = $keys['bundle'] ?? null;
    }

    public function condition(string $field, mixed $value, string $operator = '='): static
    {
        $this->conditions[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator,
        ];

        return $this;
    }

    public function exists(string $field): static
    {
        $this->conditions[] = [
            'field' => $field,
            'value' => null,
            'operator' => 'IS NOT NULL',
        ];

        return $this;
    }

    public function notExists(string $field): static
    {
        $this->conditions[] = [
            'field' => $field,
            'value' => null,
            'operator' => 'IS NULL',
        ];

        return $this;
    }

    public function sort(string $field, string $direction = 'ASC'): static
    {
        $this->sorts[] = [
            'field' => $field,
            'direction' => strtoupper($direction),
        ];

        return $this;
    }

    public function range(int $offset, int $limit): static
    {
        $this->rangeOffset = $offset;
        $this->rangeLimit = $limit;

        return $this;
    }

    public function count(): static
    {
        $this->isCount = true;

        return $this;
    }

    public function accessCheck(bool $check = true): static
    {
        // No-op in v0.1.0 — access checking is not implemented yet.
        return $this;
    }

    /**
     * Resolve a field name to its SQL expression.
     *
     * With a routing map (produced by {@see routeFields()}), the result is
     * qualified with the base or subtable alias so that it is unambiguous
     * across the JOINed subtables. Without routing, the legacy unqualified
     * form is returned.
     *
     * @param array<string, ?string> $routing Map of field name to target
     *        bundle (null for core/base), as produced by routeFields().
     */
    private function resolveField(string $field, array $routing = []): string
    {
        if (\array_key_exists($field, $routing)) {
            $bundle = $routing[$field];
            $targetTable = $bundle === null ? $this->tableName : ($this->tableName . '__' . $bundle);
            $quotedAlias = $this->database->quoteIdentifier($targetTable);

            $cacheKey = $targetTable . "\0" . $field;
            if (!isset($this->columnCache[$cacheKey])) {
                $this->columnCache[$cacheKey] = $this->database->schema()->fieldExists($targetTable, $field);
            }
            if ($this->columnCache[$cacheKey]) {
                return $quotedAlias . '.' . $field;
            }

            return 'json_extract(' . $quotedAlias . '._data, \'$.' . $field . '\')';
        }

        if (!isset($this->columnCache[$field])) {
            $this->columnCache[$field] = $this->database->schema()->fieldExists($this->tableName, $field);
        }

        if ($this->columnCache[$field]) {
            return $field;
        }

        return "json_extract(_data, '\$." . $field . "')";
    }

    /**
     * Resolve every referenced field to a routing target before SQL emission.
     *
     * Returns a tuple of the field→bundle map and the set of bundles whose
     * subtables must be joined. Throws per the spec's ambiguity and
     * unknown-field rules. When no registry is present, or when the registry
     * has no entries for this entity type, returns an empty routing so that
     * callers fall back to the legacy unqualified behavior.
     *
     * @return array{routing: array<string, ?string>, requiredJoins: list<string>}
     */
    private function routeFields(): array
    {
        if ($this->fieldRegistry === null) {
            return ['routing' => [], 'requiredJoins' => []];
        }

        $entityTypeId = $this->entityType->id();
        $coreFields = $this->fieldRegistry->coreFieldsFor($entityTypeId);
        $bundleNames = $this->fieldRegistry->bundleNamesFor($entityTypeId);

        if ($coreFields === [] && $bundleNames === []) {
            return ['routing' => [], 'requiredJoins' => []];
        }

        $impliedBundle = $this->determineImpliedBundle($bundleNames);

        $routing = [];
        $requiredJoins = [];

        $referenced = [];
        foreach ($this->conditions as $c) {
            $referenced[$c['field']] = true;
        }
        foreach ($this->sorts as $s) {
            $referenced[$s['field']] = true;
        }

        foreach (array_keys($referenced) as $name) {
            if ($name === $this->bundleKey || \array_key_exists($name, $coreFields)) {
                $routing[$name] = null;
                continue;
            }

            $bundlesDefining = $this->fieldRegistry->bundlesDefiningField($entityTypeId, $name);

            if ($bundlesDefining === []) {
                // Fallback: accept any name that is a base-table schema column.
                // This mirrors the ContentEntityBase registry fallback invariant
                // (§Resolution) — EntityType keys (id, uuid, bundle, label,
                // langcode) and any columns declared via EntityType::fieldDefinitions
                // remain queryable even when the type has not been fully
                // registered through EntityTypeManager. _data blob fields, by
                // contrast, are only queryable once explicitly registered.
                if ($this->database->schema()->fieldExists($this->tableName, $name)) {
                    $routing[$name] = null;
                    continue;
                }

                throw new UnknownFieldException(\sprintf(
                    'Field "%s" is not registered for entity type "%s". '
                    . 'Declare it as a core field on the EntityType or register it '
                    . 'via FieldDefinitionRegistry::registerBundleFields().',
                    $name,
                    $entityTypeId,
                ));
            }

            if (\count($bundlesDefining) === 1) {
                $target = $bundlesDefining[0];
            } elseif ($impliedBundle !== null && \in_array($impliedBundle, $bundlesDefining, true)) {
                $target = $impliedBundle;
            } else {
                throw BundleAmbiguousFieldException::forField(
                    $name,
                    $entityTypeId,
                    $bundlesDefining,
                    $this->bundleKey ?? 'bundle',
                );
            }

            $routing[$name] = $target;
            $requiredJoins[$target] = true;
        }

        return [
            'routing' => $routing,
            'requiredJoins' => array_keys($requiredJoins),
        ];
    }

    /**
     * Derive the single bundle (if any) that the query's conditions narrow to.
     *
     * Each condition either narrows the allowed bundle set (explicit bundle-key
     * condition, or a condition on a field that only one bundle registers) or
     * leaves it untouched. When the intersection collapses to exactly one
     * bundle, that bundle is the implied bundle used to resolve otherwise-
     * ambiguous references — per §Query "another bundle-scoped condition in
     * the same query that implies the bundle uniquely."
     *
     * @param array<int, string> $bundleNames
     */
    private function determineImpliedBundle(array $bundleNames): ?string
    {
        if ($bundleNames === [] || $this->fieldRegistry === null) {
            return null;
        }

        $allowed = array_fill_keys($bundleNames, true);

        foreach ($this->conditions as $c) {
            $narrowed = null;
            $op = strtoupper($c['operator']);
            $value = $c['value'];
            $field = $c['field'];

            if ($field === $this->bundleKey) {
                if (($op === '=' || $op === '==') && \is_string($value)) {
                    $narrowed = [$value];
                } elseif ($op === 'IN' && \is_array($value)) {
                    $strings = array_values(array_filter($value, 'is_string'));
                    if ($strings !== []) {
                        $narrowed = $strings;
                    }
                }
            } else {
                $defining = $this->fieldRegistry->bundlesDefiningField(
                    $this->entityType->id(),
                    $field,
                );
                if (\count($defining) === 1) {
                    $narrowed = $defining;
                }
            }

            if ($narrowed === null) {
                continue;
            }

            $allowed = array_intersect_key($allowed, array_fill_keys($narrowed, true));
            if ($allowed === []) {
                return null;
            }
        }

        if (\count($allowed) !== 1) {
            return null;
        }

        return array_key_first($allowed);
    }

    /**
     * Deterministic fingerprint for {@see SqlEntityQueryResultCache}.
     */
    private function buildCacheFingerprint(): string
    {
        $payload = [
            'conditions' => $this->conditions,
            'sorts' => $this->sorts,
            'rangeOffset' => $this->rangeOffset,
            'rangeLimit' => $this->rangeLimit,
            'isCount' => $this->isCount,
        ];

        return hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Execute the query and return entity IDs.
     *
     * When count() has been called, returns a single-element array with the count.
     *
     * @return array<int|string>
     */
    public function execute(): array
    {
        $entityTypeId = $this->entityType->id();
        $fingerprint = $this->resultCache !== null ? $this->buildCacheFingerprint() : null;

        if ($fingerprint !== null) {
            $cached = $this->resultCache->get($entityTypeId, $fingerprint);
            if ($cached !== null) {
                return $cached;
            }
        }

        $routed = $this->routeFields();
        $routing = $routed['routing'];
        $requiredJoins = $routed['requiredJoins'];

        $select = $this->database->select($this->tableName);

        if ($this->isCount) {
            $select = $select->countQuery();
        } else {
            $select->addField($this->tableName, $this->idKey);
        }

        foreach ($requiredJoins as $bundle) {
            $subtable = $this->tableName . '__' . $bundle;
            $baseQuoted = $this->database->quoteIdentifier($this->tableName);
            $subQuoted = $this->database->quoteIdentifier($subtable);
            $select->join(
                $subtable,
                $subtable,
                $baseQuoted . '.' . $this->idKey . ' = ' . $subQuoted . '.' . $this->idKey,
            );
        }

        // Apply conditions.
        foreach ($this->conditions as $condition) {
            $operator = strtoupper($condition['operator']);
            $field = $this->resolveField($condition['field'], $routing);

            if ($operator === 'IS NULL') {
                $select->isNull($field);
            } elseif ($operator === 'IS NOT NULL') {
                $select->isNotNull($field);
            } elseif ($operator === 'IN') {
                $values = is_array($condition['value']) ? $condition['value'] : [$condition['value']];
                $select->condition($field, $values, 'IN');
            } elseif ($operator === 'CONTAINS') {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], (string) $condition['value']);
                $select->condition($field, '%' . $escaped . '%', 'LIKE');
            } elseif ($operator === 'STARTS_WITH') {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], (string) $condition['value']);
                $select->condition($field, $escaped . '%', 'LIKE');
            } else {
                $select->condition($field, $condition['value'], $condition['operator']);
            }
        }

        // Apply sorts.
        foreach ($this->sorts as $sort) {
            $select->orderBy($this->resolveField($sort['field'], $routing), $sort['direction']);
        }

        // Apply range.
        if ($this->rangeLimit !== null) {
            $select->range($this->rangeOffset ?? 0, $this->rangeLimit);
        }

        $result = $select->execute();

        if ($this->isCount) {
            $countResult = [0];
            foreach ($result as $row) {
                $row = (array) $row;
                $countResult = [(int) ($row['count'] ?? 0)];
                break;
            }
            if ($fingerprint !== null) {
                $this->resultCache->set($entityTypeId, $fingerprint, $countResult);
            }

            return $countResult;
        }

        $ids = [];
        foreach ($result as $row) {
            $row = (array) $row;
            $id = $row[$this->idKey];
            // Preserve integer IDs as integers.
            if (is_numeric($id) && (int) $id == $id) {
                $id = (int) $id;
            }
            $ids[] = $id;
        }

        if ($fingerprint !== null) {
            $this->resultCache->set($entityTypeId, $fingerprint, $ids);
        }

        return $ids;
    }
}
