<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin\Process;

use Waaseyaa\Migration\Exception\ProcessException;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;

/**
 * Cast the chained value to a target PHP scalar (or array) type.
 *
 * Source data is frequently string-typed (CSV columns, XML attributes) even
 * when the destination entity field is an `int`, `bool`, or `float`. This
 * processor closes the gap with explicit, fail-loud coercion:
 *
 *   - `'string'` — `(string) $value`. `null` passes through unchanged.
 *   - `'int'` — `filter_var(FILTER_VALIDATE_INT)`. A non-integer-looking value
 *     raises {@see ProcessException} with code
 *     {@see ProcessException::CODE_TYPE_COERCE_FAIL}.
 *   - `'float'` — `filter_var(FILTER_VALIDATE_FLOAT)`. Same failure handling.
 *   - `'bool'` — `filter_var(FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`.
 *     A non-boolean-looking value raises `ProcessException`. Recognised
 *     truthy/falsy strings include `'true'`/`'false'`, `'1'`/`'0'`, `'on'`/`'off'`,
 *     `'yes'`/`'no'`.
 *   - `'array'` — already-array values pass through; scalars are wrapped in a
 *     single-element list.
 *
 * `null` always passes through unchanged — `DefaultValue` (a different
 * processor) owns the null-substitution policy.
 *
 * @api
 *
 * @spec FR-010 — framework-reserved process plugin (`type_coerce`)
 */
final readonly class TypeCoerceProcessor implements ProcessPluginInterface
{
    /** @var list<string> */
    public const array ALLOWED_TYPES = ['string', 'int', 'float', 'bool', 'array'];

    /**
     * @param string $targetType One of {@see self::ALLOWED_TYPES}.
     *
     * @throws \InvalidArgumentException If $targetType is not one of the allowed values.
     */
    public function __construct(public string $targetType)
    {
        if (!in_array($targetType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'TypeCoerceProcessor::$targetType must be one of %s, got %s.',
                implode(', ', array_map(static fn(string $t): string => var_export($t, true), self::ALLOWED_TYPES)),
                var_export($targetType, true),
            ));
        }
    }

    public function id(): string
    {
        return ReservedPluginIds::TYPE_COERCE;
    }

    public function stability(): string
    {
        return 'stable';
    }

    /**
     * @throws ProcessException When $value cannot be coerced to the target scalar type.
     */
    public function transform(mixed $value, ProcessContext $context): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->targetType) {
            'string' => $this->coerceString($value),
            'int'    => $this->coerceInt($value, $context),
            'float'  => $this->coerceFloat($value, $context),
            'bool'   => $this->coerceBool($value, $context),
            'array'  => $this->coerceArray($value),
            // Unreachable: constructor guards.
            default  => $value,
        };
    }

    private function coerceString(mixed $value): string
    {
        if (is_array($value)) {
            // Stringifying an array yields the warning-prone literal "Array".
            // Encode as JSON so the destination gets a deterministic value.
            return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function coerceInt(mixed $value, ProcessContext $context): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_float($value) && (float) (int) $value === $value) {
            return (int) $value;
        }

        if (is_string($value)) {
            $filtered = filter_var($value, \FILTER_VALIDATE_INT);
            if ($filtered !== false) {
                return $filtered;
            }
        }

        throw $this->fail($value, 'int', $context);
    }

    private function coerceFloat(mixed $value, ProcessContext $context): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        // After the narrowing branches above, only string remains in the
        // scalar family — float/int/bool have all been handled. Resources,
        // arrays, and objects flow through to the failure path.
        if (is_string($value)) {
            $filtered = filter_var($value, \FILTER_VALIDATE_FLOAT);
            if ($filtered !== false) {
                return $filtered;
            }
        }

        throw $this->fail($value, 'float', $context);
    }

    private function coerceBool(mixed $value, ProcessContext $context): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if ($filtered === null) {
            throw $this->fail($value, 'bool', $context);
        }

        return $filtered;
    }

    /**
     * @return list<mixed>|array<array-key, mixed>
     */
    private function coerceArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [$value];
    }

    private function fail(mixed $value, string $target, ProcessContext $context): ProcessException
    {
        return new ProcessException(
            processCode: ProcessException::CODE_TYPE_COERCE_FAIL,
            sourceField: $context->destinationField === '' ? '(unknown)' : $context->destinationField,
            migrationId: $context->migrationId,
            message: \sprintf(
                'TypeCoerceProcessor: cannot coerce %s to %s.',
                $this->describe($value),
                $target,
            ),
        );
    }

    private function describe(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }
        if (is_object($value)) {
            return 'object(' . $value::class . ')';
        }

        return get_debug_type($value);
    }
}
