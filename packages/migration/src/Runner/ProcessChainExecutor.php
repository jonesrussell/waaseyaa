<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Runner;

use Waaseyaa\Migration\Exception\ProcessException;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\Plugin\Process\PassThroughProcessor;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\SourceRecord;

/**
 * Execute the chain of process plugins declared for one destination field.
 *
 * Process semantics (FR-010, spec §6.2):
 *  - A `MigrationDefinition::$process[$destinationField]` value is a single
 *    {@see ProcessPluginInterface}, a `string` source field name, or a
 *    list-shaped chain of those.
 *  - String shorthands resolve to {@see PassThroughProcessor} at runtime so
 *    `'title' => 'post_title'` is sugar for
 *    `'title' => new PassThroughProcessor('post_title')`.
 *  - Within a chain, the output of plugin N is the input of plugin N+1.
 *    Initial `$value` is `null` — the chain head (typically `PassThrough`)
 *    reads from `SourceRecord::$fields` directly.
 *
 * This class is a runtime collaborator of {@see MigrationRunner}; it is not
 * part of the stable public surface (no `@api`). Tests instantiate it
 * directly to exercise chain semantics.
 *
 * @spec FR-010 — process plugin chain semantics
 */
final class ProcessChainExecutor
{
    /**
     * Build a fresh {@see ProcessContext} and stream the {@see SourceRecord}
     * through the process chain for `$destinationField`.
     *
     * @param \Closure(string, \Waaseyaa\Migration\SourceId): ?\Waaseyaa\Migration\Plugin\WriteResult $lookup
     *
     * @throws ProcessException When any chain step raises a {@see ProcessException}, the exception is re-thrown verbatim so callers can attribute the failing source field.
     * @throws ProcessException When a chain step raises an arbitrary `\Throwable`, this method wraps it in a `ProcessException` carrying the failing source field, the migration id, and the surrounding step number.
     */
    public function executeField(
        MigrationDefinition $definition,
        string $destinationField,
        SourceRecord $record,
        \Closure $lookup,
    ): mixed {
        $chain = $definition->processForField($destinationField);

        if ($chain === []) {
            // Empty chain is rejected by MigrationDefinition's validator — guard
            // defensively so a future relaxation does not silently NULL out fields.
            throw new \LogicException(\sprintf(
                'ProcessChainExecutor: migration %s has an empty process chain for destination field %s.',
                \var_export($definition->id, true),
                \var_export($destinationField, true),
            ));
        }

        $context = new ProcessContext(
            sourceRecord: $record,
            migrationId: $definition->id,
            destinationField: $destinationField,
            lookup: $lookup,
        );

        $value = null;
        foreach ($chain as $step) {
            $plugin = $this->resolveStep($step);
            $value = $this->invokeStep($plugin, $value, $context, $definition->id, $destinationField);
        }

        return $value;
    }

    /**
     * Normalize a process-map entry (already known to be a single step) into a
     * concrete {@see ProcessPluginInterface} instance. String shorthands
     * become {@see PassThroughProcessor} reading the named source field
     * (FR-010 normalization).
     */
    private function resolveStep(ProcessPluginInterface|string $step): ProcessPluginInterface
    {
        if ($step instanceof ProcessPluginInterface) {
            return $step;
        }

        // PassThroughProcessor's constructor rejects empty strings, so
        // MigrationDefinition's per-entry validator has already filtered
        // garbage by the time we get here.
        return new PassThroughProcessor($step);
    }

    /**
     * Invoke one plugin, surfacing ProcessException verbatim and wrapping
     * arbitrary throwables so the runner gets a typed signal regardless of
     * what a third-party plugin chooses to raise.
     */
    private function invokeStep(
        ProcessPluginInterface $plugin,
        mixed $value,
        ProcessContext $context,
        string $migrationId,
        string $destinationField,
    ): mixed {
        try {
            return $plugin->transform($value, $context);
        } catch (ProcessException $e) {
            // Forward verbatim — the plugin already attributed the offending
            // source field (e.g. LookupProcessor::$sourceField).
            throw $e;
        } catch (\Throwable $e) {
            // A non-typed throwable is a plugin contract violation; wrap so
            // the runner can record a structured per-record error with a
            // useful source-field hint (the destination field is the only
            // anchor we have when the plugin did not name a source field).
            throw new ProcessException(
                processCode: 'PROCESS_PLUGIN_THREW',
                sourceField: $destinationField,
                migrationId: $migrationId,
                message: \sprintf(
                    'Process plugin %s (id=%s) threw %s while computing destination field %s: %s',
                    $plugin::class,
                    $plugin->id(),
                    $e::class,
                    \var_export($destinationField, true),
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }
    }
}
