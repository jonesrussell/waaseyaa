<?php

declare(strict_types=1);

namespace Waaseyaa\TypedData;

/**
 * @api
 */
interface PrimitiveInterface extends TypedDataInterface
{
    public function getCastedValue(): string|int|float|bool|null;
}
