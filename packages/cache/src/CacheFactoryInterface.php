<?php

declare(strict_types=1);

namespace Aurora\Cache;

interface CacheFactoryInterface
{
    public function get(string $bin): CacheBackendInterface;
}
