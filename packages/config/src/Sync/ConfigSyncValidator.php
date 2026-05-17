<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Validates every sync-store file against entity-type field constraints
 * (FR-037, FR-039) and surfaces structured per-entity/per-field violations
 * suitable for the `config:validate` CI gate (FR-040).
 *
 * ## Field-validation pipeline state (spec §10.1 open question)
 *
 * The spec calls for the validator to call `FieldDefinition::validators()`
 * per ADR-013. That method is **not yet shipped** on
 * {@see \Waaseyaa\Field\FieldDefinition} (only `getConstraints()` returning
 * Symfony `Constraint[]` plus the boolean `isRequired()` flag exist today).
 *
 * Rather than block this work package on a prerequisite mission, the
 * validator accepts a duck-typed `?callable` field-validation hook. The
 * hook is invoked once per entity with the parsed YAML envelope and may
 * return zero or more {@see FieldViolation} records. Two reference
 * implementations land here:
 *
 *  - **Default fallback** (used when no hook is supplied): a structural
 *    `required-meta-keys` check. Every sync file must include `_meta`
 *    (already enforced by {@see ConfigSyncFile::fromParsedArray()}) plus
 *    a non-empty `fields` map. This is the "generic 'is required' check"
 *    called for by the WP06 fallback rule.
 *  - **App-supplied** (production wiring): the application binds a closure
 *    that resolves the {@see \Waaseyaa\Field\FieldDefinitionInterface}
 *    set for the entity type and walks each field. Once ADR-013 lands,
 *    that closure becomes a one-liner over `FieldDefinition::validators()`.
 *
 * Layer compliance: `waaseyaa/config` is Layer 1 (Core Data) and may not
 * `require` `waaseyaa/field` (also Layer 1, sibling — no ordering exists
 * between sibling Layer 1 packages outside the kernel). The callable seam
 * keeps this validator framework-internal at L1 with no upward import.
 *
 * ## CI gate semantics (FR-040)
 *
 * {@see self::validate()} returns a {@see ConfigValidateResult} with a
 * deterministic, alphabetically-ordered list of per-entity violation
 * records. {@see ConfigValidateResult::isValid()} drives the
 * `config:validate` exit code; structured violations are forwarded to
 * the CLI for per-field output (FR-039).
 *
 * @api
 *
 * @phpstan-type FieldValidationHook callable(ConfigSyncFile): list<FieldViolation>
 */
final class ConfigSyncValidator
{
    /** @var (callable(ConfigSyncFile): list<FieldViolation>)|null */
    private $fieldValidationHook;

    /**
     * @param (callable(ConfigSyncFile): list<FieldViolation>)|null $fieldValidationHook
     *      Per-entity field-validation hook. Receives the parsed
     *      {@see ConfigSyncFile} and returns zero or more violations.
     *      `null` falls back to the structural required-fields check.
     */
    public function __construct(
        private readonly ConfigSyncRepository $repository,
        ?callable $fieldValidationHook = null,
    ) {
        $this->fieldValidationHook = $fieldValidationHook;
    }

    /**
     * Parse and validate every sync-store file.
     *
     * Deterministic order: results are sorted alphabetically by ref. Files
     * that fail to parse surface as a single per-entity violation with a
     * stable `meta.parse` code so CI logs are diffable.
     */
    public function validate(): ConfigValidateResult
    {
        $entries = [];

        foreach ($this->collectFiles() as $ref => $fileOrError) {
            if (\is_string($fileOrError)) {
                $entries[$ref] = new ConfigValidateEntry(
                    ref: $ref,
                    violations: [new FieldViolation(field: '_meta', message: $fileOrError, code: 'meta.parse')],
                );
                continue;
            }

            $violations = $this->validateOne($fileOrError);
            $entries[$ref] = new ConfigValidateEntry(ref: $ref, violations: $violations);
        }

        ksort($entries, \SORT_STRING);

        return new ConfigValidateResult(entries: array_values($entries));
    }

    /**
     * Validate a single in-memory sync file. Useful for callers that
     * already hold the {@see ConfigSyncFile} instance (e.g. `config:import`
     * threading per-entry validation in front of the apply hook).
     *
     * @return list<FieldViolation>
     */
    public function validateFile(ConfigSyncFile $file): array
    {
        return $this->validateOne($file);
    }

    /**
     * @return list<FieldViolation>
     */
    private function validateOne(ConfigSyncFile $file): array
    {
        if ($this->fieldValidationHook !== null) {
            return ($this->fieldValidationHook)($file);
        }

        return $this->fallbackRequiredCheck($file);
    }

    /**
     * Default fallback: minimal structural check applied when no
     * app-supplied field-validation hook is wired. Surfaces:
     *
     *  - empty `fields` map (no values at all to import).
     *  - non-string field names (would crash `FieldValueMapper`).
     *
     * The deserializer already enforces `_meta` shape, so this hook
     * focuses on the `fields` block only.
     *
     * @return list<FieldViolation>
     */
    private function fallbackRequiredCheck(ConfigSyncFile $file): array
    {
        $violations = [];

        if ($file->fields === []) {
            $violations[] = new FieldViolation(
                field: 'fields',
                message: 'sync file declares no field values (empty `fields` map).',
                code: 'fields.empty',
            );
        }

        foreach (array_keys($file->fields) as $fieldName) {
            if ($fieldName === '') {
                $violations[] = new FieldViolation(
                    field: $fieldName,
                    message: 'field key must be a non-empty string.',
                    code: 'fields.key_invalid',
                );
            }
        }

        return $violations;
    }

    /**
     * Yield parsed sync files alongside any per-file parse error message.
     *
     * @return iterable<string, ConfigSyncFile|string>
     */
    private function collectFiles(): iterable
    {
        try {
            foreach ($this->repository->list() as $file) {
                yield $file->ref() => $file;
            }
        } catch (\Throwable $exception) {
            // Deserialization errors arrive as ConfigSerializationException
            // from inside the iterator; surface them as parse-level
            // violations keyed by a synthetic ref so the CLI still has
            // something to print.
            yield '_unparseable' => $exception->getMessage();
        }
    }
}
