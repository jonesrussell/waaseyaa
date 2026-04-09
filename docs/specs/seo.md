# SEO package (`waaseyaa/seo`)

**Audience:** framework contributors and app authors wiring sitemaps, robots, and head metadata.

## Scope

The SEO package provides **small, framework-agnostic building blocks**:

- **Sitemap:** `SitemapUrl`, `SitemapGenerator::toXml()`, and `collectFromEntityTypes()` (entity ID listing + application-provided URL callable).
- **Robots:** `RobotsTxtGenerator::toText()` (user-agent, allow/disallow lines, optional sitemap URL).
- **Meta tags:** `MetaTagBuilder::buildHeadSnippet()` (title, description, canonical).
- **JSON-LD:** `JsonLdBuilder` helpers for `WebSite`, `Organization`, and `BreadcrumbList` shapes.
- **Twig:** `SeoTwigExtension` with `seo_meta_head` and `seo_json_ld_script` (HTML-safe).

There is **no HTTP response wrapper** and **no routing** dependency: applications choose routes and return `Response` bodies themselves.

## Service provider

`SeoServiceProvider` registers singletons for the generators/builders and `SeoTwigExtension`. Apps using Twig must still call `Environment::addExtension()` (or equivalent) with the resolved extension.

## Sitemap collection

`collectFromEntityTypes()` uses `EntityTypeManagerInterface::getStorage($type)->getQuery()->range(0, $limit)->execute()` to obtain IDs. URL shape, hostname, and optional `lastmod` come from the **`$buildLoc` callable**; returning `null` or `''` skips an ID.

Per-type options support optional `changefreq`, `priority` (numeric string or float), and `max` (override default cap per type).

## Related specs

- `docs/specs/entity-system.md` — entity storage/query contracts used by sitemap collection.
- `docs/specs/package-discovery.md` — `extra.waaseyaa.providers` registration.

## Change log

- **2026-04-08** — Initial package and spec (#613).
