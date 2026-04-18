<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Keys;

interface OidcKeyLoaderInterface
{
    /**
     * @return list<SigningKey>
     */
    public function loadSigningKeys(): array;
}
