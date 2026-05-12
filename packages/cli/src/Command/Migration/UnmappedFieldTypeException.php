<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migration;

/**
 * Thrown when a field type has no §8.2 sql-column mapping.
 *
 * @api
 */
final class UnmappedFieldTypeException extends \RuntimeException
{
    /**
     * @api
     */
    public function __construct(
        public readonly string $fieldId,
        public readonly string $fieldType,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Field %s has type %s which has no sql-column mapping. '
                . 'Route it to an alternate backend via FieldDefinition::storedIn(<backend>).',
                $fieldId,
                $fieldType,
            ),
            0,
            $previous,
        );
    }
}
