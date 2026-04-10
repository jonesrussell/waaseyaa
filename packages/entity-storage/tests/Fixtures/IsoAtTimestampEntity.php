<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Fixtures;

/**
 * Entity with only default timestamp fields (no casts) — *_at uses ISO storage (#1183).
 */
final class IsoAtTimestampEntity extends TestStorageEntity {}
