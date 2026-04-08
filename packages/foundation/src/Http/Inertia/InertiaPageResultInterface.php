<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Inertia;

/**
 * Inertia.js page payload returned from invokable / closure controllers.
 *
 * Implemented by the inertia package; foundation only type-checks against this contract.
 */
interface InertiaPageResultInterface
{
    /** @return array<string, mixed> */
    public function toPageObject(): array;
}
