<?php

declare(strict_types=1);

namespace Waaseyaa\Relationship;

use Waaseyaa\Database\PdoDatabase;
use Waaseyaa\Entity\EntityTypeManagerInterface;

final class RelationshipTraversalService
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly PdoDatabase $database,
    ) {}

    /**
     * @param array{
     *   direction?: 'outbound'|'inbound'|'both',
     *   relationship_types?: list<string>,
     *   status?: 'published'|'unpublished'|'all',
     *   at?: int|string|null,
     *   limit?: int|null
     * } $options
     * @return list<Relationship>
     */
    public function traverse(string $entityType, int|string $entityId, array $options = []): array
    {
        $direction = $this->normalizeDirection($options['direction'] ?? 'both');
        $status = $this->normalizeStatus($options['status'] ?? 'published');
        $relationshipTypes = $this->normalizeRelationshipTypes($options['relationship_types'] ?? []);
        $at = $this->normalizeTemporal($options['at'] ?? null);
        $limit = $this->normalizeLimit($options['limit'] ?? null);

        $conditions = [];
        $args = [];
        $id = (string) $entityId;

        if ($direction === 'outbound') {
            $conditions[] = '((from_entity_type = ? AND from_entity_id = ?) OR (directionality = ? AND to_entity_type = ? AND to_entity_id = ?))';
            array_push($args, $entityType, $id, 'bidirectional', $entityType, $id);
        } elseif ($direction === 'inbound') {
            $conditions[] = '((to_entity_type = ? AND to_entity_id = ?) OR (directionality = ? AND from_entity_type = ? AND from_entity_id = ?))';
            array_push($args, $entityType, $id, 'bidirectional', $entityType, $id);
        } else {
            $conditions[] = '((from_entity_type = ? AND from_entity_id = ?) OR (to_entity_type = ? AND to_entity_id = ?))';
            array_push($args, $entityType, $id, $entityType, $id);
        }

        if ($status === 'published') {
            $conditions[] = 'status = ?';
            $args[] = 1;
        } elseif ($status === 'unpublished') {
            $conditions[] = 'status = ?';
            $args[] = 0;
        }

        if ($relationshipTypes !== []) {
            $placeholders = implode(', ', array_fill(0, count($relationshipTypes), '?'));
            $conditions[] = "relationship_type IN ({$placeholders})";
            array_push($args, ...$relationshipTypes);
        }

        $sql = 'SELECT rid FROM relationship';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY rid ASC';

        $rows = iterator_to_array($this->database->query($sql, $args), false);
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn(array $row): int => (int) ($row['rid'] ?? 0), $rows);
        $storage = $this->entityTypeManager->getStorage('relationship');
        $loaded = $storage->loadMultiple($ids);

        $result = [];
        foreach ($ids as $idValue) {
            $entity = $loaded[$idValue] ?? null;
            if ($entity instanceof Relationship) {
                $result[] = $entity;
            }
        }

        if ($at !== null) {
            $result = array_values(array_filter(
                $result,
                fn(Relationship $relationship): bool => $this->isActiveAt($relationship, $at),
            ));
        }

        usort($result, function (Relationship $a, Relationship $b): int {
            $statusCmp = $this->statusSortValue($b) <=> $this->statusSortValue($a);
            if ($statusCmp !== 0) {
                return $statusCmp;
            }

            $weightCmp = $this->compareOptionalNumbersDesc($a->get('weight'), $b->get('weight'));
            if ($weightCmp !== 0) {
                return $weightCmp;
            }

            $startCmp = $this->compareOptionalTemporalsAsc($a->get('start_date'), $b->get('start_date'));
            if ($startCmp !== 0) {
                return $startCmp;
            }

            return (int) $a->id() <=> (int) $b->id();
        });

        if ($limit !== null) {
            $result = array_slice($result, 0, $limit);
        }

        return $result;
    }

    private function normalizeDirection(mixed $direction): string
    {
        if (!is_string($direction)) {
            return 'both';
        }
        $value = strtolower(trim($direction));
        if (in_array($value, ['outbound', 'inbound', 'both'], true)) {
            return $value;
        }
        return 'both';
    }

    private function normalizeStatus(mixed $status): string
    {
        if (!is_string($status)) {
            return 'published';
        }
        $value = strtolower(trim($status));
        if (in_array($value, ['published', 'unpublished', 'all'], true)) {
            return $value;
        }
        return 'published';
    }

    /**
     * @param mixed $types
     * @return list<string>
     */
    private function normalizeRelationshipTypes(mixed $types): array
    {
        if (!is_array($types)) {
            return [];
        }

        $normalized = [];
        foreach ($types as $type) {
            if (!is_string($type)) {
                continue;
            }
            $value = trim($type);
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeTemporal(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (ctype_digit($trimmed)) {
                return (int) $trimmed;
            }
            $timestamp = strtotime($trimmed);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return null;
    }

    private function normalizeLimit(mixed $limit): ?int
    {
        if ($limit === null || $limit === '') {
            return null;
        }
        if (!is_numeric($limit)) {
            return null;
        }
        $value = (int) $limit;
        if ($value <= 0) {
            return null;
        }
        return $value;
    }

    private function isActiveAt(Relationship $relationship, int $at): bool
    {
        $start = $this->normalizeTemporal($relationship->get('start_date'));
        $end = $this->normalizeTemporal($relationship->get('end_date'));

        if ($start !== null && $start > $at) {
            return false;
        }
        if ($end !== null && $end < $at) {
            return false;
        }

        return true;
    }

    private function statusSortValue(Relationship $relationship): int
    {
        $status = $relationship->get('status');
        if (is_bool($status)) {
            return $status ? 1 : 0;
        }
        if (is_numeric($status)) {
            return ((int) $status) === 0 ? 0 : 1;
        }
        if (is_string($status)) {
            $normalized = strtolower(trim($status));
            if (in_array($normalized, ['0', 'false'], true)) {
                return 0;
            }
            if (in_array($normalized, ['1', 'true'], true)) {
                return 1;
            }
        }

        return 0;
    }

    private function compareOptionalNumbersDesc(mixed $a, mixed $b): int
    {
        $aNum = is_numeric($a) ? (float) $a : null;
        $bNum = is_numeric($b) ? (float) $b : null;

        if ($aNum === null && $bNum === null) {
            return 0;
        }
        if ($aNum === null) {
            return 1;
        }
        if ($bNum === null) {
            return -1;
        }

        return $bNum <=> $aNum;
    }

    private function compareOptionalTemporalsAsc(mixed $a, mixed $b): int
    {
        $aTs = $this->normalizeTemporal($a);
        $bTs = $this->normalizeTemporal($b);

        if ($aTs === null && $bTs === null) {
            return 0;
        }
        if ($aTs === null) {
            return 1;
        }
        if ($bTs === null) {
            return -1;
        }

        return $aTs <=> $bTs;
    }
}
