<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

/**
 * Thrown by SqlEntityQuery when a bundle-scoped field name exists in more
 * than one bundle of the entity type and the query does not constrain the
 * bundle.
 *
 * See docs/specs/bundle-scoped-fields.md §Query — the ambiguity policy is a
 * framework contract: silent resolution ("pick the first bundle" or "union
 * across bundles") is explicitly rejected.
 */
final class BundleAmbiguousFieldException extends \RuntimeException
{
    /**
     * Build the exception with the spec-mandated message shape.
     *
     * @param array<int, string> $bundles Ordered list of bundles defining the field.
     */
    public static function forField(
        string $field,
        string $entityTypeId,
        array $bundles,
        string $bundleKey,
    ): self {
        return new self(\sprintf(
            'Field "%s" is bundle-scoped and exists in bundles [%s] of entity type "%s". '
            . 'Constrain the bundle via ->condition(\'%s\', \'<bundle>\') before referencing this field.',
            $field,
            implode(', ', $bundles),
            $entityTypeId,
            $bundleKey,
        ));
    }
}
