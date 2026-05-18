<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Schema\Ai;

/**
 * JSON-Schema definition for the scalar `config.ai.*` keys.
 *
 * Authoritative shape: `kitty-specs/agent-executor-01KRWPK7/data-model.md`
 * § "Config entities > Scalar config keys".
 *
 * | Key | Type | Default |
 * |---|---|---|
 * | `config.ai.run_retention_days` | int | 30 |
 * | `config.ai.hitl_timeout_seconds` | int | 300 |
 * | `config.ai.max_runtime_seconds` | int | 600 |
 * | `config.ai.transcript_max_bytes` | int | 262144 |
 * | `config.ai.hitl_poll_interval_ms` | int | 1000 |
 *
 * Defaults live in `defaults/ai.yaml`; `bin/check-ingestion-defaults`
 * keeps the two in sync.
 *
 * @api
 */
final class ScalarsConfig
{
    public const string CONFIG_NAME = 'config.ai';

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'run_retention_days' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 30,
                ],
                'hitl_timeout_seconds' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 300,
                ],
                'max_runtime_seconds' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 600,
                ],
                'transcript_max_bytes' => [
                    'type' => 'integer',
                    'minimum' => 1024,
                    'default' => 262144,
                ],
                'hitl_poll_interval_ms' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1000,
                ],
            ],
        ];
    }
}
