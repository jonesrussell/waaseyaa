# Quickstart: Register and Use a Listing

**Phase:** 1 (design)
**Mission:** M-007 / `listing-pipeline-v1-01KRMN0B`
**Date:** 2026-05-15

This walkthrough demonstrates a Waaseyaa app registering a listing of upcoming events, displaying it on a page with exposed filters, and observing automatic cache invalidation on entity saves. The flow mirrors what the final cookbook recipe (`docs/cookbook/listing-first-cut.md`, authored in WP12) will document.

## Scenario

A community-events application has an `event` entity type. The home page shows the next 20 upcoming events sorted by start date. Users can filter by category via the URL (`?category=teaching`).

## 1. Declare the listing on your service provider

```php
namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider;
use Waaseyaa\Listing\Filter;
use Waaseyaa\Listing\HasListingsInterface;
use Waaseyaa\Listing\ListingDefinition;
use Waaseyaa\Listing\Sort;

final class EventsServiceProvider extends ServiceProvider implements HasListingsInterface
{
    public function listings(): array
    {
        return [
            new ListingDefinition(
                id: 'upcoming_events',
                entityType: 'event',
                filters: [
                    Filter::gte('starts_at', 'now'),
                    Filter::exposed(Filter::eq('category', null), 'category'),
                ],
                sorts: [Sort::asc('starts_at')],
                pageSize: 20,
                accessOps: ['view'],
            ),
        ];
    }
}
```

**What happens at boot:**
- `PackageManifestCompiler::warm()` discovers `EventsServiceProvider` implements `HasListingsInterface`.
- `listings()` returns the array; framework stores each `ListingDefinition` in `ListingDefinitionRegistry` keyed by `id`.
- `ListingDefinitionValidator` runs against the registry:
  - Checks `event` entity type exists.
  - Checks `starts_at` and `category` fields exist on `event`.
  - Checks both fields' storage backends report `supportsQuery() === true`.
  - Checks `gte` is compatible with `starts_at`'s typed-data type.
  - Checks `pageSize = 20` is within the cap (not opt-out needed).
- In dev: this happens on every request (~1ms). In prod: only when `var/manifest.php` is rebuilt.

## 2. Resolve the listing in your controller

```php
namespace App\Controller;

use Waaseyaa\Listing\ListingDefinitionRegistry;
use Waaseyaa\Listing\ListingResolver;
use Waaseyaa\Listing\ExposedFilterParser;
use Waaseyaa\Foundation\Http\Request;
use Waaseyaa\Foundation\Http\Response;

final class HomeController
{
    public function __construct(
        private readonly ListingDefinitionRegistry $registry,
        private readonly ListingResolver $resolver,
        private readonly ExposedFilterParser $parser,
        private readonly TwigEnvironment $twig,
    ) {}

    public function __invoke(Request $request): Response
    {
        $def = $this->registry->get('upcoming_events');
        $values = $this->parser->parse($request->getQueryParams(), $def);
        $result = $this->resolver->resolve($def, $values);

        return new Response($this->twig->render('home.html.twig', [
            'rows' => $result->rows,
            'pagination' => $result->pagination,
        ]));
    }
}
```

Cache integration is transparent: the resolver checks `TaggedCacheInterface` first; on miss it builds the EntityQuery, applies access policies, paginates, stores tagged.

## 3. Render the listing in Twig (app concern, ADR 013)

```twig
{# resources/views/home.html.twig #}
{% extends 'layout.html.twig' %}

{% block content %}
  <h1>Upcoming events</h1>

  <form method="get" action="">
    <select name="category">
      <option value="">All categories</option>
      <option value="teaching" {{ app.request.query.get('category') == 'teaching' ? 'selected' : '' }}>Teaching</option>
      <option value="ceremony" {{ app.request.query.get('category') == 'ceremony' ? 'selected' : '' }}>Ceremony</option>
    </select>
    <button type="submit">Filter</button>
  </form>

  {% for event in rows %}
    <article>
      <h2>{{ event.title }}</h2>
      <time>{{ event.starts_at|date('Y-m-d H:i') }}</time>
    </article>
  {% endfor %}

  {% if pagination.totalPages > 1 %}
    <nav>
      {% if pagination.hasPrev %}
        <a href="?page={{ pagination.page - 1 }}">Previous</a>
      {% endif %}
      Page {{ pagination.page }} of {{ pagination.totalPages }}
      {% if pagination.hasNext %}
        <a href="?page={{ pagination.page + 1 }}">Next</a>
      {% endif %}
    </nav>
  {% endif %}
{% endblock %}
```

The framework provides no Twig components or render plugins — `ListingResult::$rows` is just iterable entities; render them however you want (ADR 013).

## 4. Cache invalidation on entity save (automatic)

```php
// Somewhere in the admin flow:
$event = $eventRepository->find(42);
$event->setTitle('Updated title');
$eventRepository->save($event);
```

What happens behind the scenes:
1. `EntityRepository::save()` writes the entity.
2. Dispatches `AfterSaveEvent($event, $original, affectedLangcodes: ['en'])` (for translatable types) or `(...)` for non-translatable types.
3. `ListingCacheInvalidator` (priority 100) receives the event.
4. Computes affected tags: `entity:event`, `entity:event:42`.
5. Calls `$cache->invalidateByTag('entity:event:42')` — every cached listing that touched this row is evicted.
6. Next user request that resolves a listing including this event misses cache, re-builds with fresh data.

No app-side code needed. The cache stays correct because every save fires the event.

## 5. Test pattern

```php
namespace App\Tests\Integration;

use Waaseyaa\Foundation\Testing\IntegrationTestCase;
use Waaseyaa\Listing\ExposedFilterValues;
use Waaseyaa\Listing\ListingResolver;
use PHPUnit\Framework\Attributes\Test;

final class UpcomingEventsListingTest extends IntegrationTestCase
{
    #[Test]
    public function listing_returns_future_events_only(): void
    {
        $past = $this->createEvent(starts_at: '-1 day');
        $future = $this->createEvent(starts_at: '+1 day');

        $def = $this->registry->get('upcoming_events');
        $result = $this->container->get(ListingResolver::class)->resolve($def);

        $this->assertCount(1, iterator_to_array($result->rows));
        $this->assertSame($future->id(), $result->rows[0]->id());
    }

    #[Test]
    public function exposed_filter_narrows_by_category(): void
    {
        $teaching = $this->createEvent(starts_at: '+1 day', category: 'teaching');
        $ceremony = $this->createEvent(starts_at: '+1 day', category: 'ceremony');

        $def = $this->registry->get('upcoming_events');
        $values = new ExposedFilterValues(['category' => 'teaching']);
        $result = $this->container->get(ListingResolver::class)->resolve($def, $values);

        $rows = iterator_to_array($result->rows);
        $this->assertCount(1, $rows);
        $this->assertSame($teaching->id(), $rows[0]->id());
    }
}
```

For unit-level testing of the exposed-filter parser, use `ExposedFilterParser::create()->strict()` to surface coercion failures instead of silently dropping them.

## Common variations

### Translatable listing

A listing of an entity type that implements `TranslatableInterface` (e.g., the M-006 `teaching` entity in Minoo) needs no extra work — `ListingResolver` automatically:
- Adds an implicit `language.content` filter for the active request langcode (FR-047)
- Adds `language.content` to `cacheContexts` (FR-048)
- Emits `entity:teaching:<id>:<langcode>` cache tags per row (FR-023 translatable case)

To list across all langcodes (admin views): declare the listing without a langcode filter AND add `Filter::langcode('*')` (special "any" sentinel — to be confirmed in tasks phase).

### Unbounded listing for CSV export

```php
new ListingDefinition(
    id: 'all_events_for_export',
    entityType: 'event',
    pageSize: null,                  // would normally throw at boot
    approximateTotal: false,          // also conflicts with allowUnbounded+null
)->allowUnbounded();                  // explicit opt-out
```

Use with care; the resolver will scan every row through access policy + return them all in one page. Reserved for fixture / admin / CLI scenarios.

### Approximate counts on a huge listing

```php
new ListingDefinition(
    id: 'all_articles_paginated',
    entityType: 'article',
    pageSize: 50,
    approximateTotal: true,           // resolver skips full-scan; totalRows = null
);
```

`Pagination::$totalRows === null` and `$totalPages === null` for the consumer. UI shows "Next page" buttons without total counts.

## Reference for downstream missions

M-004 (entity-storage-translatable-revisions) WP07 uses this surface directly:
- Per-langcode listing filters via `Filter::langcode($code)`.
- Langcode cache tags via the automatic translatable-row tag contribution.
- Per-language access policy via the `'translate'` op M-006 shipped.

The cookbook example for that use case will live in M-004's own quickstart at planning time (revalidate per the M-004 BLOCKED stamp's "Unblocker" caveat).
