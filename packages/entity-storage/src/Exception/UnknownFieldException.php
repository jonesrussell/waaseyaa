<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Exception;

/**
 * Thrown by SqlEntityQuery when a referenced field name does not resolve to
 * any registered FieldDefinition for the entity type.
 *
 * See docs/specs/bundle-scoped-fields.md §Query.
 */
final class UnknownFieldException extends \RuntimeException {}
