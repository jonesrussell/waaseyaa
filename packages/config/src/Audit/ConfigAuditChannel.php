<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Audit;

/**
 * Well-known log-channel name for every audit-worthy operation performed by
 * `waaseyaa/config`: `config:export`, `config:import`, `config:reset`, and
 * the `--no-dependency-check` emergency-bypass warning.
 *
 * Operators route this channel into dedicated retention — it carries
 * before/after summaries that are useful long after the originating CLI
 * invocation has scrolled out of the terminal.
 *
 * Stability scope (charter §4.4 amendment, FR-053): the channel name
 * (`'config.audit'`) and the FQCN/`CHANNEL` constant are on stable surface
 * for `waaseyaa/config` v1.x. Adding constants to this class is permitted;
 * renaming or removing `CHANNEL` requires a charter §4 deprecation cycle.
 *
 * **Layer discipline:** this class intentionally does NOT import
 * `Waaseyaa\Foundation\Log\LoggerInterface`. The config package sits at
 * Layer 1; app wiring (or a Layer 6 CLI kernel) is responsible for fanning
 * audit-events into a `LoggerInterface` instance bound to this channel.
 *
 * @api
 */
final class ConfigAuditChannel
{
    /**
     * Canonical channel name for `LoggerInterface::info()` / `::warning()`
     * calls that record `config:*` operations.
     */
    public const CHANNEL = 'config.audit';

    /**
     * Instantiation is forbidden — this class is a namespace for the
     * `CHANNEL` constant only.
     */
    private function __construct() {}
}
