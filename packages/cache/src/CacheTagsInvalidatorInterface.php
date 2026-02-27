<?php

declare(strict_types=1);

namespace Aurora\Cache;

interface CacheTagsInvalidatorInterface
{
    /** @param string[] $tags */
    public function invalidateTags(array $tags): void;
}
