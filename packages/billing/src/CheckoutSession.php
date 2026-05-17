<?php

declare(strict_types=1);

namespace Waaseyaa\Billing;

/**
 * @api
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
