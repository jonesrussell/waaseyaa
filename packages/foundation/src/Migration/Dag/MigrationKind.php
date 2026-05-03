<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Migration\Dag;

/**
 * Discriminator for the two migration authoring contracts the unified
 * graph holds in a single ordering.
 *
 * - `Legacy` — {@see \Waaseyaa\Foundation\Migration\Migration} subclasses
 *   with `up(SchemaBuilder)` / `down(SchemaBuilder)` and a `$after`
 *   array of package names. Pre-WP06 contract; not deprecated.
 * - `V2` — {@see \Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2}
 *   value objects with a `MigrationPlan` (`CompositeDiff`) and explicit
 *   migration_id strings.
 *
 * The kind is informational on the node — translators dispatch on it,
 * but the graph's ordering algorithm treats both kinds as equal-weight
 * vertices.
 */
enum MigrationKind: string
{
    case Legacy = 'legacy';
    case V2 = 'v2';
}
