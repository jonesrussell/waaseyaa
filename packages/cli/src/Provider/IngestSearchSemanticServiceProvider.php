<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\IngestDashboardHandler;
use Waaseyaa\CLI\Handler\IngestRunHandler;
use Waaseyaa\CLI\Handler\SearchReindexHandler;
use Waaseyaa\CLI\Handler\SemanticRefreshHandler;
use Waaseyaa\CLI\Handler\SemanticWarmHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class IngestSearchSemanticServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'ingest:run',
            description: 'Run deterministic structured/unstructured ingestion and emit mapped content payloads',
            options: [
                new OptionDefinition(
                    name: 'input',
                    shortcut: 'i',
                    mode: OptionMode::Required,
                    description: 'Input file path (.json, .txt, .md)',
                ),
                new OptionDefinition(
                    name: 'format',
                    shortcut: 'f',
                    mode: OptionMode::Required,
                    description: 'Input format: auto|structured|unstructured',
                    default: 'auto',
                ),
                new OptionDefinition(
                    name: 'default-bundle',
                    mode: OptionMode::Required,
                    description: 'Default bundle for mapped nodes',
                    default: 'node',
                ),
                new OptionDefinition(
                    name: 'default-workflow-state',
                    mode: OptionMode::Required,
                    description: 'Default workflow state',
                    default: 'draft',
                ),
                new OptionDefinition(
                    name: 'author-id',
                    mode: OptionMode::Required,
                    description: 'Mapped author UID',
                    default: '1',
                ),
                new OptionDefinition(
                    name: 'timestamp',
                    mode: OptionMode::Required,
                    description: 'Deterministic ingest timestamp',
                    default: '1735689600',
                ),
                new OptionDefinition(
                    name: 'batch-id',
                    mode: OptionMode::Required,
                    description: 'Batch idempotency key (defaults to deterministic hash)',
                ),
                new OptionDefinition(
                    name: 'policy',
                    mode: OptionMode::Required,
                    description: 'Ingestion policy: atomic_fail_fast|validate_only',
                    default: 'atomic_fail_fast',
                ),
                new OptionDefinition(
                    name: 'source',
                    mode: OptionMode::Required,
                    description: 'Source identifier for audit metadata',
                    default: 'manual://default',
                ),
                new OptionDefinition(
                    name: 'infer-relationships',
                    mode: OptionMode::None,
                    description: 'Infer candidate relationships from ingested text (review-safe defaults)',
                ),
                new OptionDefinition(
                    name: 'authoring-assist',
                    mode: OptionMode::None,
                    description: 'Emit deterministic AI-assisted authoring suggestions',
                ),
                new OptionDefinition(
                    name: 'refresh-baseline',
                    mode: OptionMode::Required,
                    description: 'Optional baseline snapshot JSON path for refresh change detection',
                ),
                new OptionDefinition(
                    name: 'refresh-snapshot-output',
                    mode: OptionMode::Required,
                    description: 'Optional output path for current refresh snapshot JSON',
                ),
                new OptionDefinition(
                    name: 'output',
                    shortcut: 'o',
                    mode: OptionMode::Required,
                    description: 'Optional mapped output file (.json)',
                ),
                new OptionDefinition(
                    name: 'diagnostics-output',
                    mode: OptionMode::Required,
                    description: 'Optional diagnostics output file (.json)',
                ),
            ],
            handler: [IngestRunHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'ingest:dashboard',
            description: 'Build deterministic editorial dashboard summaries from ingest artifacts',
            options: [
                new OptionDefinition(
                    name: 'input',
                    shortcut: 'i',
                    mode: OptionMode::Array_,
                    description: 'Ingest artifact JSON path(s) (repeat or comma-separated)',
                ),
                new OptionDefinition(
                    name: 'glob',
                    mode: OptionMode::Required,
                    description: 'Optional glob pattern for ingest artifact JSON files',
                ),
                new OptionDefinition(
                    name: 'output',
                    shortcut: 'o',
                    mode: OptionMode::Required,
                    description: 'Optional path to write dashboard payload',
                ),
                new OptionDefinition(
                    name: 'json',
                    mode: OptionMode::None,
                    description: 'Emit machine-readable JSON payload',
                ),
            ],
            handler: [IngestDashboardHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'search:reindex',
            description: 'Rebuild the search index from all indexable entities',
            options: [
                new OptionDefinition(
                    name: 'batch-size',
                    shortcut: 'b',
                    mode: OptionMode::Required,
                    description: 'Entities per batch',
                    default: '100',
                ),
            ],
            handler: [SearchReindexHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'semantic:warm',
            description: 'Warm semantic embeddings for deterministic read paths',
            options: [
                new OptionDefinition(
                    name: 'type',
                    shortcut: 't',
                    mode: OptionMode::Array_,
                    description: 'Entity type ID(s) to warm (repeat option or pass comma-separated values)',
                    default: ['node'],
                ),
                new OptionDefinition(
                    name: 'limit',
                    shortcut: 'l',
                    mode: OptionMode::Required,
                    description: 'Per-type candidate limit (0 = no limit)',
                    default: '0',
                ),
                new OptionDefinition(
                    name: 'json',
                    mode: OptionMode::None,
                    description: 'Emit the full warming report as JSON',
                ),
            ],
            handler: [SemanticWarmHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'semantic:refresh',
            description: 'Run resumable semantic index refresh batches',
            options: [
                new OptionDefinition(
                    name: 'type',
                    shortcut: 't',
                    mode: OptionMode::Array_,
                    description: 'Entity type ID(s) to refresh (repeat option or pass comma-separated values)',
                    default: ['node'],
                ),
                new OptionDefinition(
                    name: 'batch-size',
                    shortcut: 'b',
                    mode: OptionMode::Required,
                    description: 'Maximum entities per batch execution',
                    default: '200',
                ),
                new OptionDefinition(
                    name: 'cursor',
                    mode: OptionMode::Required,
                    description: 'Resume cursor JSON (e.g. {"type_index":0,"offset":200})',
                ),
                new OptionDefinition(
                    name: 'until-complete',
                    mode: OptionMode::None,
                    description: 'Keep running batches until the refresh completes',
                ),
                new OptionDefinition(
                    name: 'json',
                    mode: OptionMode::None,
                    description: 'Emit machine-readable JSON output',
                ),
            ],
            handler: [SemanticRefreshHandler::class, 'execute'],
        );
    }
}
