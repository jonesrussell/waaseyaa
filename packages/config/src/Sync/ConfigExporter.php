<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

use Waaseyaa\Config\Exception\ConfigSerializationException;

/**
 * Orchestrates `config:export`: iterates a {@see ConfigSyncFileSourceInterface}
 * and writes each entity's canonical YAML to the sync store via
 * {@see ConfigSyncRepository::put()}.
 *
 * Flags (FR-017..FR-020) are applied per-file:
 *
 *  - **diff** (FR-018) — compare new YAML bytes against the existing sync
 *    file. Identical bytes → `STATUS_UNCHANGED`, no write (preserves git's
 *    mtime-aware diff semantics so unchanged files don't show up as touched
 *    in `git status`). Differing bytes → `STATUS_UPDATED`, write.
 *  - **dryRun** (FR-019) — compute would-be outcomes; never call
 *    `ConfigSyncRepository::put()`. Existing-file probing still happens via
 *    `ConfigSyncRepository::get()` so the per-file status accurately reflects
 *    what a non-dry-run would do.
 *
 * Without `--diff` the exporter still treats byte-identical YAML as
 * `STATUS_UNCHANGED` (no spurious "updated" lines for a redundant rewrite),
 * but it rewrites the file on disk anyway. `--diff` adds the further
 * guarantee that unchanged files are never touched on disk — that is the
 * git-mtime-preserving property operators rely on.
 *
 * Failure mode (FR-021): any {@see ConfigSerializationException} raised
 * inside the loop bubbles out unchanged. The CLI command catches it and
 * maps to exit code 1; the partial filesystem effect of files written
 * before the error is intentional (matches every other long-running
 * `bin/waaseyaa` command — operators inspect output to identify which
 * entity broke).
 *
 * @api
 */
final class ConfigExporter
{
    public function __construct(
        private readonly ConfigSyncFileSourceInterface $source,
        private readonly ConfigSyncRepository $repository,
        private readonly ConfigSyncSerializer $serializer = new ConfigSyncSerializer(),
    ) {}

    /**
     * Run the export.
     *
     * @throws ConfigSerializationException on any per-entity serialization failure
     */
    public function export(bool $diff = false, bool $dryRun = false): ConfigExportResult
    {
        $files = [];
        foreach ($this->source->iterate() as $syncFile) {
            $files[] = $this->exportOne($syncFile, $diff, $dryRun);
        }

        return new ConfigExportResult(files: $files, dryRun: $dryRun);
    }

    private function exportOne(ConfigSyncFile $syncFile, bool $diff, bool $dryRun): ConfigExportFileResult
    {
        $ref = $syncFile->ref();
        $filename = $syncFile->filename();
        $existing = $this->repository->get($ref);

        if ($existing === null) {
            if (!$dryRun) {
                $this->repository->put($syncFile);
            }

            return new ConfigExportFileResult(
                ref: $ref,
                filename: $filename,
                status: ConfigExportFileResult::STATUS_CREATED,
            );
        }

        // File exists. Compare canonical bytes to classify the outcome.
        // The serializer is deterministic (WP02 snapshot test) so byte
        // equality is a safe check.
        $newYaml = $this->serializer->toYaml($syncFile);
        $existingYaml = $this->serializer->toYaml($existing);

        if ($newYaml === $existingYaml) {
            // Bytes identical. --diff preserves the on-disk file untouched
            // (mtime-aware-diff semantics, FR-018); the default rewrites it
            // anyway so a `touch`-like full-export run stamps every sync
            // file with a fresh mtime. Either way the operator-facing
            // status is `unchanged` (the content did not change).
            if (!$diff && !$dryRun) {
                $this->repository->put($syncFile);
            }

            return new ConfigExportFileResult(
                ref: $ref,
                filename: $filename,
                status: ConfigExportFileResult::STATUS_UNCHANGED,
            );
        }

        // Differs. Always rewrite (unless dry-run). --diff and the default
        // converge here — the only difference between the two modes is
        // whether unchanged files are also rewritten, handled above.
        if (!$dryRun) {
            $this->repository->put($syncFile);
        }

        return new ConfigExportFileResult(
            ref: $ref,
            filename: $filename,
            status: ConfigExportFileResult::STATUS_UPDATED,
        );
    }
}
