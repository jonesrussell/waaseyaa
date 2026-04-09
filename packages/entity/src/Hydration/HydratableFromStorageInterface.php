<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Hydration;

use Waaseyaa\Entity\EntityInterface;

/**
 * Opt-in contract for entities that cannot be constructed with only
 * `new EntityClass(values: $row)` (e.g. domain-shaped constructors).
 *
 * Extends {@see EntityInterface} so every implementation is a persisted entity type.
 *
 * {@see \Waaseyaa\EntityStorage\Hydration\EntityInstantiator} invokes this after
 * the storage layer has normalized the row (numeric id cast, `_data` merged,
 * json-typed columns decoded where applicable).
 *
 * Application code may expose `public static function make(array $values): self`
 * as a thin wrapper that builds a {@see HydrationContext} for tests, while
 * production loading always goes through storage + this interface.
 */
interface HydratableFromStorageInterface extends EntityInterface
{
    /**
     * @param array<string, mixed> $values Storage-normalized values keyed by field/column name.
     */
    public static function fromStorage(array $values, HydrationContext $context): static;
}
