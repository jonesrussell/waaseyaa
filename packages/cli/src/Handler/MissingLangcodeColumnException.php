<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

/**
 * Thrown when the primary table for an entity type targeted by
 * `make:migration --add-translations` lacks the required `langcode` column.
 *
 * For sql-blob entity types the `langcode` column is part of the canonical
 * primary table shape (WP04); for sql-column types every row implicitly has
 * a single default langcode, but the column is still expected before the
 * promotion migration can backfill.
 *
 * @api
 */
final class MissingLangcodeColumnException extends \RuntimeException
{
    public function __construct(string $table)
    {
        parent::__construct(sprintf(
            'Table "%s" is missing the required `langcode` column. Run schema sync (or the storage WPs WP04/WP05) before generating an add-translations migration.',
            $table,
        ));
    }
}
