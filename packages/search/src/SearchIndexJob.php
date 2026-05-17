<?php

declare(strict_types=1);

namespace Waaseyaa\Search;

/**
 * Queue message for async search indexing.
 *
 * Carries only the document ID and entity type — the job handler
 * reloads the entity from storage to get fresh data.
 * @api
 */
final readonly class SearchIndexJob
{
    public function __construct(
        public string $documentId,
        public string $entityTypeId,
        public string $operation = 'index',
    ) {}
}
