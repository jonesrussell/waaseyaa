<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

/**
 * Storage hint for a FieldDefinition.
 *
 * Tells the schema handler whether to materialize a dedicated column for the
 * field, and tells query routing whether to read it from a column or from the
 * `_data` JSON blob. Defaults to {@see self::Column} so existing call sites
 * keep their pre-hint semantics.
 *
 * Some entity types declare core fields whose canonical persistence is the
 * `_data` blob (e.g. low-traffic universals like `created_at` on a small
 * config entity). Marking those as {@see self::Data} keeps the registry
 * aware of the field — so `getQuery()->condition($field, ...)` resolves
 * cleanly via `json_extract` instead of throwing UnknownFieldException —
 * without forcing a column to exist on disk.
 */
enum FieldStorage: string
{
    case Column = 'column';
    case Data = 'data';
}
