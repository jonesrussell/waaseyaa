<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Controller;

use Waaseyaa\Api\CodifiedContext\CodifiedContextSessionRow;
use Waaseyaa\Api\CodifiedContext\CodifiedContextSessionStoreInterface;

/**
 * API controller for codified context session data.
 *
 * JSON payloads use camelCase keys aligned with the admin SPA TypeScript types
 * ({@see useCodifiedContext}).
 */
final class CodifiedContextController
{
    public function __construct(private readonly ?CodifiedContextSessionStoreInterface $store = null) {}

    /**
     * GET /api/telescope/agent-context/sessions (legacy alias: /api/telescope/codified-context/sessions)
     *
     * Groups cc_session entries by session_id, merges start/end, enriches with drift scores and event counts.
     *
     * @param array<string, mixed> $query
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function listSessions(array $query = []): array
    {
        if ($this->store === null) {
            return ['data' => []];
        }

        $sessionRows = $this->store->queryByEventType('cc_session', limit: 500);
        $eventRows = $this->store->queryByEventType('cc_event', limit: 2000);
        $eventCounts = $this->countEventsBySession($eventRows);

        $aggregates = [];
        foreach ($sessionRows as $row) {
            $sessionId = $row->sessionId;
            $data = $row->data;
            if (!isset($aggregates[$sessionId])) {
                $aggregates[$sessionId] = [
                    'sessionId' => $sessionId,
                    'repoHash' => '',
                    'startedAt' => null,
                    'endedAt' => null,
                    'durationMs' => null,
                    'eventCount' => $eventCounts[$sessionId] ?? 0,
                    'latestDriftScore' => null,
                    'latestSeverity' => null,
                ];
            }

            if ($this->isSessionStartRow($data)) {
                $repo = $data['repo_hash'] ?? $data['repoHash'] ?? '';
                if (is_string($repo) && $repo !== '') {
                    $aggregates[$sessionId]['repoHash'] = $repo;
                }
                $started = self::createdAtAtom($row->createdAt);
                $prevStart = $aggregates[$sessionId]['startedAt'];
                if ($prevStart === null || $started < $prevStart) {
                    $aggregates[$sessionId]['startedAt'] = $started;
                }
            }

            if ($this->isSessionEndRow($data)) {
                $aggregates[$sessionId]['endedAt'] = self::createdAtAtom($row->createdAt);
                if (isset($data['duration_ms']) && is_numeric($data['duration_ms'])) {
                    $aggregates[$sessionId]['durationMs'] = (int) $data['duration_ms'];
                }
                if (isset($data['event_count']) && is_numeric($data['event_count'])) {
                    $aggregates[$sessionId]['eventCount'] = (int) $data['event_count'];
                }
            }

            if (isset($data['drift_score'])) {
                $aggregates[$sessionId]['latestDriftScore'] = self::normalizeDriftScoreInt($data['drift_score']);
            }
            if (isset($data['severity']) && is_string($data['severity'])) {
                $aggregates[$sessionId]['latestSeverity'] = $data['severity'];
            }
        }

        $earliestSessionCreated = [];
        foreach ($sessionRows as $row) {
            $atom = self::createdAtAtom($row->createdAt);
            $sid = $row->sessionId;
            if (!isset($earliestSessionCreated[$sid]) || $atom < $earliestSessionCreated[$sid]) {
                $earliestSessionCreated[$sid] = $atom;
            }
        }

        foreach (array_keys($aggregates) as $sessionId) {
            $validations = $this->store->queryBySession($sessionId, limit: 50);
            foreach ($validations as $entry) {
                if ($entry->type !== 'cc_validation') {
                    continue;
                }
                $vd = $entry->data;
                if (isset($vd['drift_score'])) {
                    $aggregates[$sessionId]['latestDriftScore'] = self::normalizeDriftScoreInt($vd['drift_score']);
                }
                if (isset($vd['severity']) && is_string($vd['severity'])) {
                    $aggregates[$sessionId]['latestSeverity'] = $vd['severity'];
                }
            }
        }

        $out = [];
        foreach ($aggregates as $sessionId => $row) {
            $startedAt = $row['startedAt'] ?? $earliestSessionCreated[$sessionId] ?? self::epochAtom();
            $fromEnd = $row['eventCount'];
            $fromScan = $eventCounts[$sessionId] ?? 0;
            $out[] = [
                'id' => $sessionId,
                'sessionId' => $sessionId,
                'repoHash' => $row['repoHash'],
                'startedAt' => $startedAt,
                'endedAt' => $row['endedAt'],
                'durationMs' => $row['durationMs'],
                'eventCount' => max($fromEnd, $fromScan),
                'latestDriftScore' => $row['latestDriftScore'],
                'latestSeverity' => $row['latestSeverity'],
            ];
        }

        return ['data' => $out];
    }

    /**
     * GET /api/telescope/agent-context/sessions/{sessionId} (legacy: …/codified-context/…)
     *
     * @return array{data: array<string, mixed>|null}
     */
    public function getSession(string $sessionId): array
    {
        if ($this->store === null) {
            return ['data' => null];
        }

        $list = $this->listSessions();
        foreach ($list['data'] as $row) {
            if (($row['sessionId'] ?? '') === $sessionId) {
                return ['data' => $row];
            }
        }

        return ['data' => null];
    }

    /**
     * GET /api/telescope/agent-context/sessions/{sessionId}/events (legacy: …/codified-context/…)
     *
     * Returns only cc_event type entries for the session.
     *
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function getSessionEvents(string $sessionId): array
    {
        if ($this->store === null) {
            return ['data' => []];
        }

        $allEntries = $this->store->queryBySession($sessionId, limit: 500);
        $events = [];

        foreach ($allEntries as $entry) {
            if ($entry->type !== 'cc_event') {
                continue;
            }

            $data = $entry->data;
            $semantic = $data['event_type'] ?? '';
            $eventType = is_string($semantic) && $semantic !== ''
                ? str_replace('_', '.', $semantic)
                : $entry->type;

            $events[] = [
                'id' => $entry->id,
                'sessionId' => $entry->sessionId,
                'eventType' => $eventType,
                'createdAt' => self::createdAtAtom($entry->createdAt),
                'data' => $data,
            ];
        }

        return ['data' => $events];
    }

    /**
     * GET /api/telescope/agent-context/sessions/{sessionId}/validation (legacy: …/codified-context/…)
     *
     * Returns latest cc_validation payload as a {@see ValidationReport}-shaped object (camelCase).
     *
     * @return array{data: array<string, mixed>|null}
     */
    public function getSessionValidation(string $sessionId): array
    {
        if ($this->store === null) {
            return ['data' => null];
        }

        $allEntries = $this->store->queryBySession($sessionId, limit: 100);

        foreach ($allEntries as $entry) {
            if ($entry->type !== 'cc_validation') {
                continue;
            }

            return ['data' => self::mapValidationReportCamel($sessionId, $entry)];
        }

        return ['data' => null];
    }

    /**
     * @param list<CodifiedContextSessionRow> $eventRows
     * @return array<string, int>
     */
    private function countEventsBySession(array $eventRows): array
    {
        $counts = [];
        foreach ($eventRows as $row) {
            if ($row->type !== 'cc_event') {
                continue;
            }
            $sid = $row->sessionId;
            $counts[$sid] = ($counts[$sid] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isSessionStartRow(array $data): bool
    {
        $phase = $data['phase'] ?? null;
        $eventType = $data['event_type'] ?? null;

        return $phase === 'start' || $eventType === 'session_start';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isSessionEndRow(array $data): bool
    {
        $phase = $data['phase'] ?? null;
        $eventType = $data['event_type'] ?? null;

        return $phase === 'end' || $eventType === 'session_end';
    }

    private static function createdAtAtom(\DateTimeImmutable $at): string
    {
        return $at->format(\DateTimeInterface::ATOM);
    }

    private static function epochAtom(): string
    {
        return (new \DateTimeImmutable('@0'))->format(\DateTimeInterface::ATOM);
    }

    private static function normalizeDriftScoreInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $f = (float) $value;
        if ($f > 0.0 && $f <= 1.0) {
            return (int) round($f * 100.0);
        }

        return (int) round($f);
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapValidationReportCamel(string $sessionId, CodifiedContextSessionRow $entry): array
    {
        $d = $entry->data;
        $componentsIn = $d['components'] ?? [];
        $componentsIn = is_array($componentsIn) ? $componentsIn : [];

        $semantic = $componentsIn['semantic_alignment'] ?? $componentsIn['semanticAlignment'] ?? 0;
        $structural = $componentsIn['structural_score'] ?? $componentsIn['structural_checks'] ?? $componentsIn['structuralChecks'] ?? 0;
        $contradiction = $componentsIn['contradiction_score'] ?? $componentsIn['contradiction_checks'] ?? $componentsIn['contradictionChecks'] ?? 0;

        $issuesOut = [];
        $issuesIn = $d['issues'] ?? [];
        if (is_array($issuesIn)) {
            foreach ($issuesIn as $issue) {
                if (is_string($issue)) {
                    $issuesOut[] = [
                        'type' => 'issue',
                        'message' => $issue,
                        'severity' => 'low',
                    ];
                } elseif (is_array($issue)) {
                    $issuesOut[] = [
                        'type' => (string) ($issue['type'] ?? 'issue'),
                        'message' => (string) ($issue['message'] ?? ''),
                        'severity' => (string) ($issue['severity'] ?? 'low'),
                    ];
                }
            }
        }

        $drift = $d['drift_score'] ?? $d['driftScore'] ?? null;

        return [
            'sessionId' => $sessionId,
            'driftScore' => self::normalizeDriftScoreInt($drift) ?? 0,
            'components' => [
                'semantic_alignment' => is_numeric($semantic) ? (int) round((float) $semantic) : 0,
                'structural_checks' => is_numeric($structural) ? (int) round((float) $structural) : 0,
                'contradiction_checks' => is_numeric($contradiction) ? (int) round((float) $contradiction) : 0,
            ],
            'issues' => $issuesOut,
            'recommendation' => (string) ($d['recommendation'] ?? ''),
            'validatedAt' => self::createdAtAtom($entry->createdAt),
        ];
    }
}
