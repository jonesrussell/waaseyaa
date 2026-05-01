# waaseyaa/seo

**Layer 3 — Services**

SEO building blocks for Waaseyaa: sitemap, robots.txt, meta tags, JSON-LD, Twig helpers.

`SitemapGenerator` produces `sitemap.xml` from `SitemapUrl` value objects supplied by entity-type-specific contributors. `MetaTagBuilder` and `JsonLdBuilder` emit head metadata from canonical entity data; `RobotsTxtGenerator` reads access-policy state to decide whether a path should be `Disallow`-listed for unauthenticated crawlers. Twig helpers expose all of the above to template authors.

Key classes: `SitemapGenerator`, `SitemapUrl`, `MetaTagBuilder`, `JsonLdBuilder`, `RobotsTxtGenerator`, `SeoServiceProvider`.
