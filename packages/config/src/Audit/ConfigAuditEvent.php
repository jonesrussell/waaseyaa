<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Audit;

/**
 * Immutable structured payload for one entry on the `config.audit` log
 * channel (FR-043, FR-053).
 *
 * Produced by the framework-side write paths — {@see \Waaseyaa\Config\Sync\ConfigExporter},
 * {@see \Waaseyaa\Config\Sync\ConfigImporter}, and
 * {@see \Waaseyaa\Config\Sync\ConfigResetter} — and rendered into a
 * `LoggerInterface::info()` (or `::warning()`) call by the application's
 * logging wiring. The audit consumer receives the value object verbatim in
 * the log record's context array, which lets downstream retention/SIEM
 * tooling key off the fields without parsing the human-readable message.
 *
 * The `beforeAfterDigest` field is the SHA-256 of the literal string
 * `before:after` where `before` and `after` are the canonical YAML
 * serializations of the entity on each side of the operation. It is a
 * tamper-evidence helper — operators correlating audit log entries with
 * subsequent sync-store commits can verify integrity without retaining
 * full payloads. `null` when the operation does not pertain to a single
 * entity (e.g. `config:import` run summary).
 *
 * Stability scope (charter §5.5, §4.4 amendment): the class FQCN, the
 * `OP_*` constants, public properties, and constructor signature are on
 * stable surface for `waaseyaa/config` v1.x. New `OP_*` constants may be
 * added; renames or removals require deprecation.
 *
 * @api
 */
final readonly class ConfigAuditEvent
{
    /** Operation code emitted by `config:export`. */
    public const OP_EXPORT = 'export';

    /** Operation code emitted by `config:import`. */
    public const OP_IMPORT = 'import';

    /** Operation code emitted by `config:reset`. */
    public const OP_RESET = 'reset';

    /**
     * @param string                $operation         One of the `OP_*` constants.
     * @param string|null           $actor             User identifier or CLI invoker; `null` for system events.
     * @param string|null           $entityRef         `<entity_type>.<entity_id>` for entity-scoped events; `null` for run-summary events.
     * @param string|null           $beforeAfterDigest SHA-256 of the literal `"before:after"` payload (canonical-YAML on each side); `null` for non-entity events.
     * @param int                   $timestamp         Unix epoch seconds at the moment the event was emitted.
     * @param array<string, mixed>  $context           Free-form structured context (file counts, `--dry-run` flag, `--yes` flag, etc.).
     */
    public function __construct(
        public string $operation,
        public ?string $actor,
        public ?string $entityRef,
        public ?string $beforeAfterDigest,
        public int $timestamp,
        public array $context,
    ) {
        if (!\in_array($operation, [self::OP_EXPORT, self::OP_IMPORT, self::OP_RESET], true)) {
            throw new \InvalidArgumentException(sprintf(
                'ConfigAuditEvent operation "%s" is not one of the documented OP_* constants.',
                $operation,
            ));
        }
    }

    /**
     * Compute the canonical `beforeAfterDigest` value for the given
     * before/after YAML payloads.
     *
     * Either side may be `null` (representing "absent" — e.g. before-side
     * on a fresh import, after-side on a deletion). `null` serializes as
     * the literal four-byte string `"null"` for digest stability.
     */
    public static function digest(?string $beforeYaml, ?string $afterYaml): string
    {
        return hash(
            'sha256',
            ($beforeYaml ?? 'null') . ':' . ($afterYaml ?? 'null'),
        );
    }
}
