<?php

declare(strict_types=1);

namespace Waaseyaa\Relationship;

final class RelationshipParameterValidator
{
    /**
     * @param mixed $relationshipTypes
     * @return list<string>
     */
    public function normalizeRelationshipTypes(mixed $relationshipTypes): array
    {
        if (!is_array($relationshipTypes)) {
            return [];
        }

        $normalized = [];
        foreach ($relationshipTypes as $relationshipType) {
            if (!is_string($relationshipType)) {
                continue;
            }
            $value = trim($relationshipType);
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    public function normalizeStatus(mixed $status): string
    {
        if (!is_string($status)) {
            return 'published';
        }
        $value = strtolower(trim($status));

        return in_array($value, ['published', 'unpublished', 'all'], true) ? $value : 'published';
    }

    public function normalizeLimit(mixed $limit, int $default): int
    {
        if (!is_numeric($limit)) {
            return $default;
        }

        return max(1, min(100, (int) $limit));
    }

    public function normalizeOffset(mixed $offset): int
    {
        if (!is_numeric($offset)) {
            return 0;
        }

        return max(0, (int) $offset);
    }

    public function normalizeDirection(mixed $direction): string
    {
        if (!is_string($direction)) {
            return 'both';
        }
        $value = strtolower(trim($direction));
        if (!in_array($value, ['outbound', 'inbound', 'both'], true)) {
            return 'both';
        }

        return $value;
    }

    public function normalizeTemporal(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        $parsed = strtotime($trimmed);
        if ($parsed === false) {
            return null;
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $edge
     */
    public function edgeOverlapsWindow(array $edge, ?int $from, ?int $to): bool
    {
        if ($from === null && $to === null) {
            return true;
        }

        $start = is_numeric($edge['start_date'] ?? null) ? (int) $edge['start_date'] : null;
        $end = is_numeric($edge['end_date'] ?? null) ? (int) $edge['end_date'] : null;

        if ($to !== null && $start !== null && $start > $to) {
            return false;
        }

        if ($from !== null && $end !== null && $end < $from) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $edge
     */
    public function timelineSortDate(array $edge): int
    {
        $start = is_numeric($edge['start_date'] ?? null) ? (int) $edge['start_date'] : null;
        if ($start !== null) {
            return $start;
        }

        $end = is_numeric($edge['end_date'] ?? null) ? (int) $edge['end_date'] : null;
        if ($end !== null) {
            return $end;
        }

        return PHP_INT_MAX;
    }
}
