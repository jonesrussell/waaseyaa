<?php

declare(strict_types=1);

namespace Waaseyaa\Api\CodifiedContext;

/**
 * Read-only query surface for codified-context sessions consumed by {@see \Waaseyaa\Api\Controller\CodifiedContextController}.
 */
interface CodifiedContextSessionStoreInterface
{
    /**
     * @return list<CodifiedContextSessionRow>
     */
    public function queryBySession(string $sessionId, int $limit = 100, int $offset = 0): array;

    /**
     * @return list<CodifiedContextSessionRow>
     */
    public function queryByEventType(string $eventType, int $limit = 50, int $offset = 0): array;
}
