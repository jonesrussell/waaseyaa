<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Ingestion;

/**
 * @api
 */
final class SourcePriorityMergeResolver
{
    private const OWNERSHIP_PRIORITY = [
        'first_party' => 0,
        'federated' => 1,
        'third_party' => 2,
    ];

    private const RESERVED_FIELDS = [
        'canonical_id' => true,
        'source_id' => true,
        'source_uri' => true,
        'adapter_type' => true,
        'ownership' => true,
        'synthetic_flag' => true,
        'batch_id' => true,
        'ingested_at' => true,
        'parser_version' => true,
        'adapter_extra' => true,
    ];

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{merged:list<array<string, mixed>>,diagnostics:list<array<string, mixed>>}
     */
    public function merge(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $canonicalId = (string) ($row['canonical_id'] ?? '');
            if ($canonicalId === '') {
                continue;
            }
            $groups[$canonicalId][] = $row;
        }

        ksort($groups);

        $merged = [];
        $diagnostics = [];
        foreach ($groups as $canonicalId => $members) {
            usort($members, fn(array $left, array $right): int => $this->compareMembers($left, $right));

            $winner = $members[0];
            $mergedRow = [
                'canonical_id' => $canonicalId,
                'source_id' => (string) ($winner['source_id'] ?? ''),
                'source_uri' => (string) ($winner['source_uri'] ?? ''),
                'ownership' => (string) ($winner['ownership'] ?? ''),
            ];

            $fieldNames = $this->collectFieldNames($members);
            foreach ($fieldNames as $field) {
                $selectedValue = null;
                $selectedSourceId = '';
                $seenValues = [];
                $seenSourceIds = [];
                foreach ($members as $member) {
                    if (!array_key_exists($field, $member)) {
                        continue;
                    }

                    $value = $member[$field];
                    $sourceId = (string) ($member['source_id'] ?? '');
                    $encodedValue = json_encode($value);
                    if ($encodedValue === false) {
                        continue;
                    }

                    $seenValues[$encodedValue] = $value;
                    $seenSourceIds[] = $sourceId;

                    if ($selectedSourceId === '') {
                        $selectedValue = $value;
                        $selectedSourceId = $sourceId;
                    }
                }

                if ($selectedSourceId !== '') {
                    $mergedRow[$field] = $selectedValue;
                }

                if (count($seenValues) > 1) {
                    sort($seenSourceIds);
                    $diagnostics[] = [
                        'code' => 'merge.field_conflict',
                        'message' => 'Resolved field conflict using source ownership priority.',
                        'location' => '/merge/' . $canonicalId . '/' . $field,
                        'item_index' => null,
                        'context' => [
                            'canonical_id' => $canonicalId,
                            'field' => $field,
                            'winner_source_id' => $selectedSourceId,
                            'member_source_ids' => $seenSourceIds,
                        ],
                    ];
                }
            }

            $merged[] = $mergedRow;
        }

        return ['merged' => $merged, 'diagnostics' => $diagnostics];
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareMembers(array $left, array $right): int
    {
        $leftPriority = self::OWNERSHIP_PRIORITY[(string) ($left['ownership'] ?? 'third_party')] ?? 99;
        $rightPriority = self::OWNERSHIP_PRIORITY[(string) ($right['ownership'] ?? 'third_party')] ?? 99;

        if ($leftPriority !== $rightPriority) {
            return $leftPriority <=> $rightPriority;
        }

        return strcmp((string) ($left['source_id'] ?? ''), (string) ($right['source_id'] ?? ''));
    }

    /**
     * @param list<array<string, mixed>> $members
     * @return list<string>
     */
    private function collectFieldNames(array $members): array
    {
        $fieldSet = [];
        foreach ($members as $member) {
            foreach (array_keys($member) as $field) {
                if (!isset(self::RESERVED_FIELDS[$field])) {
                    $fieldSet[$field] = true;
                }
            }
        }

        $fields = array_keys($fieldSet);
        sort($fields);
        return $fields;
    }
}
