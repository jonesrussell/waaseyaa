<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migrate;

/**
 * Renders {@see VerifyOutcome} in either a human-readable text form or
 * structured JSON.
 *
 * **JSON shape (locked):**
 *
 * ```json
 * {
 *   "kind": "verify",
 *   "results": [
 *     {
 *       "migration": "waaseyaa/groups:v2:add-archived-flag",
 *       "status": "match",
 *       "stored_checksum": "...",
 *       "computed_checksum": "..."
 *     }
 *   ],
 *   "summary": {
 *     "match": 1, "mismatch": 0, "unknown": 0, "orphan": 0
 *   }
 * }
 * ```
 *
 * `stored_checksum` and `computed_checksum` are JSON nulls when absent.
 * The CLI exit code is non-zero when `summary.mismatch + summary.orphan
 * > 0`; consumers parsing this JSON may use the same rule.
 */
final readonly class VerifyFormatter
{
    public function __construct(private OutputSanitizer $sanitizer) {}

    public function toText(VerifyOutcome $outcome): string
    {
        $lines = ['Verify report (checksum-vs-source comparison):', ''];

        if ($outcome->rows === []) {
            $lines[] = '  (ledger empty)';
        }

        foreach ($outcome->rows as $row) {
            $stored = $row->storedChecksum ?? '—';
            $computed = $row->computedChecksum ?? '—';
            $lines[] = sprintf(
                '  [%s] %s  stored=%s  computed=%s',
                $row->status,
                $this->sanitizer->sanitize($row->migration),
                $this->shorten($stored),
                $this->shorten($computed),
            );
        }

        $lines[] = '';
        $lines[] = sprintf(
            'Summary: match=%d mismatch=%d unknown=%d orphan=%d',
            $outcome->summary->match,
            $outcome->summary->mismatch,
            $outcome->summary->unknown,
            $outcome->summary->orphan,
        );

        if ($outcome->summary->hasFailure()) {
            $lines[] = 'STATUS: FAIL — drift or orphans detected.';
        } else {
            $lines[] = 'STATUS: OK';
        }

        return implode("\n", $lines) . "\n";
    }

    public function toJson(VerifyOutcome $outcome): string
    {
        $results = [];
        foreach ($outcome->rows as $row) {
            $results[] = [
                'migration' => $this->sanitizer->sanitize($row->migration),
                'status' => $row->status,
                'stored_checksum' => $row->storedChecksum,
                'computed_checksum' => $row->computedChecksum,
            ];
        }

        $payload = [
            'kind' => 'verify',
            'results' => $results,
            'summary' => [
                'match' => $outcome->summary->match,
                'mismatch' => $outcome->summary->mismatch,
                'unknown' => $outcome->summary->unknown,
                'orphan' => $outcome->summary->orphan,
            ],
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function shorten(string $hash): string
    {
        if (strlen($hash) <= 12) {
            return $hash;
        }
        return substr($hash, 0, 12) . '…';
    }
}
