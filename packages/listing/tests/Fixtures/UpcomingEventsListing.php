<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Fixtures;

use Waaseyaa\Listing\Filter;
use Waaseyaa\Listing\HasListingsInterface;
use Waaseyaa\Listing\ListingDefinition;
use Waaseyaa\Listing\Sort;

/**
 * Reference consumer of the listing pipeline.
 *
 * Declares a single listing — `upcoming_events` — that demonstrates the
 * full feature surface introduced by M-007:
 *  - declarative filtering with a temporal `gte` cutoff
 *  - exposed filter binding URL `?category=...` to the category field
 *  - stable sort by `starts_at ASC` with implicit `id ASC` tie-break
 *  - default `view` access op
 *  - pageSize=20 (within the validator's cap)
 *
 * Used by:
 *  - {@see \Waaseyaa\Tests\Integration\Phase14\ListingPipelineIntegrationTest}
 *  - {@see \Waaseyaa\Tests\Integration\Phase14\ListingCacheInvalidationIntegrationTest}
 *  - the cookbook page generated in WP12
 *
 * @api
 */
final class UpcomingEventsListing implements HasListingsInterface
{
    /**
     * @return list<ListingDefinition>
     */
    public function listings(): array
    {
        return [
            new ListingDefinition(
                id: 'upcoming_events',
                entityType: 'event',
                filters: [
                    // Future events only. The cutoff value is a string so it
                    // round-trips through cache-key hashing deterministically;
                    // hosts replace 'now' with a request-scoped ISO-8601
                    // timestamp when wiring their own provider.
                    Filter::gte('starts_at', 'now'),
                    // Exposed `?category=...` URL parameter. Default `null`
                    // means "no category filter" until the request supplies
                    // a concrete value.
                    Filter::exposed(Filter::eq('category', null), 'category'),
                ],
                sorts: [
                    Sort::asc('starts_at'),
                ],
                pageSize: 20,
                accessOps: ['view'],
            ),
        ];
    }
}
