<?php

declare(strict_types=1);

namespace Aurora\TypedData;

interface PrimitiveInterface extends TypedDataInterface
{
    public function getCastedValue(): string|int|float|bool|null;
}
