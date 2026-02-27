<?php

declare(strict_types=1);

namespace Aurora\Cache;

interface CacheBackendInterface
{
    public const PERMANENT = -1;

    public function get(string $cid): CacheItem|false;

    /** @return array<string, CacheItem> */
    public function getMultiple(array &$cids): array;

    public function set(string $cid, mixed $data, int $expire = self::PERMANENT, array $tags = []): void;

    public function delete(string $cid): void;

    public function deleteMultiple(array $cids): void;

    public function deleteAll(): void;

    public function invalidate(string $cid): void;

    public function invalidateMultiple(array $cids): void;

    public function invalidateAll(): void;

    public function removeBin(): void;
}
