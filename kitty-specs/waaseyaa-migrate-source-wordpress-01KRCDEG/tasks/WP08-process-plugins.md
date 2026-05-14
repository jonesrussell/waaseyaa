---
work_package_id: WP08
title: 'Process plugins: shortcode strip, oEmbed expand, media URL rewrite'
dependencies:
- WP05
- WP06
requirement_refs:
- FR-013
- FR-014
- FR-015
- FR-016
- FR-017
- FR-034
- FR-035
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T057
- T058
- T059
- T060
- T061
- T062
- T063
- T064
history: []
authoritative_surface: src/Process/
execution_mode: code_change
owned_files:
- src/Process/WordPressShortcodeStrip.php
- src/Process/WordPressOembedExpand.php
- src/Process/WordPressMediaRewriteUrl.php
- src/Exception/WordPressOembedResolutionException.php
- tests/Unit/Process/WordPressShortcodeStripTest.php
- tests/Unit/Process/WordPressOembedExpandTest.php
- tests/Unit/Process/WordPressMediaRewriteUrlTest.php
tags: []
agent: "claude"
shell_pid: "146569"
---

# WP08 — Process plugins: shortcode strip, oEmbed expand, media URL rewrite

## Objective

Ship the three WP-specific process plugins that transform post content during migration. Each implements M-002's `ProcessPluginInterface` and composes in chains.

## Context

- Spec: [`spec.md`](../spec.md) §3.3 (FR-013..FR-017).
- Research: [`research.md`](../research.md) §1.6 (oEmbed opt-in remote resolution).
- Substrate: M-002 `ProcessPluginInterface`, `ProcessContext`.
- Depends on WP05 (Media records) + WP06 (Post records).

## Implementation command

```
spec-kitty agent action implement WP08 --agent sonnet
```

## Subtask guidance

### T057 — `WordPressShortcodeStrip`

```php
final class WordPressShortcodeStrip implements ProcessPluginInterface
{
    public const ID = 'wordpress_shortcode_strip';

    /** @param array<string, callable(array): string> $rewriters */
    public function __construct(private readonly array $rewriters = []) {}

    public function process(mixed $value, ProcessContext $context): mixed
    {
        if (!is_string($value)) return $value;
        // Find [shortcode attr="val"]content[/shortcode] patterns
        // If $rewriters[$tagName] exists: call it with parsed args
        // Otherwise: strip silently
        return $this->stripOrRewrite($value);
    }
}
```

Use a regex pattern like `/\[([\w-]+)([^\]]*)\](?:(.*?)\[\/\1\])?/s`. Edge cases: nested shortcodes (limit recursion depth), unmatched closing tags (strip the opening tag silently).

### T058 — `WordPressOembedExpand`

```php
final class WordPressOembedExpand implements ProcessPluginInterface
{
    public const ID = 'wordpress_oembed_expand';

    public function __construct(
        private readonly bool $resolveRemote = false,
        private readonly ?HttpClientInterface $http = null,
    ) {}

    public function process(mixed $value, ProcessContext $context): mixed
    {
        // Find URLs matching YouTube/Vimeo/Twitter/Instagram patterns
        // For each: emit a record (URL + provider name)
        // If $resolveRemote: HTTP-fetch oEmbed metadata, attach
        return $value; // Returns content with annotations or unchanged
    }
}
```

Default `$resolveRemote = false` per research §1.6.

### T059 — `resolve_remote: true` opt-in

When enabled, fetch from the provider's oEmbed endpoint:
- YouTube: `https://www.youtube.com/oembed?url=...&format=json`
- Vimeo: `https://vimeo.com/api/oembed.json?url=...`
- Twitter: `https://publish.twitter.com/oembed?url=...`
- Instagram: deprecated their public oEmbed; document as best-effort

Cache results within a single migration run (idempotent re-fetch).

Throw `WordPressOembedResolutionException` on resolution failure (which the runner converts to a per-record warning unless `--halt-on-error`).

### T060 — `WordPressMediaRewriteUrl`

```php
final class WordPressMediaRewriteUrl implements ProcessPluginInterface
{
    public const ID = 'wordpress_media_rewrite_url';

    public function __construct(
        private readonly LookupProcessor $mediaLookup,  // M-002 substrate
        private readonly array $cdnHosts = [],          // T062 — host allowlist
    ) {}

    public function process(mixed $value, ProcessContext $context): mixed
    {
        // Find wp-content/uploads/<path> in $value
        // For each: derive WP attachment id, lookup destination media UUID via id-map
        // Replace URL
        return $value;
    }
}
```

### T061 — `WordPressOembedResolutionException`

Per FR-034, on stable surface. Stable codes:
- `wp_oembed.provider_unsupported`
- `wp_oembed.http_failure`
- `wp_oembed.invalid_response`

### T062 — CDN-prefixed URL handling

Resolves research Q2. URLs like `https://cdn.example.com/wp-content/uploads/...` are common. The plugin's `cdnHosts: ['cdn.example.com']` config tells it to treat those hosts equivalently to the canonical WP hostname for rewriting.

### T063 — Composability tests

```php
test('all three process plugins chain together cleanly', function () {
    $context = /* M-002 ProcessContext with media lookup populated */;
    $input = '<p>[gallery ids="1,2"]</p><p>https://youtube.com/watch?v=abc</p><p><img src="https://example.com/wp-content/uploads/2024/01/photo.jpg"></p>';

    $stripped = (new WordPressShortcodeStrip())->process($input, $context);
    $expanded = (new WordPressOembedExpand(resolveRemote: false))->process($stripped, $context);
    $rewritten = (new WordPressMediaRewriteUrl($mediaLookup))->process($expanded, $context);

    expect($rewritten)->not->toContain('[gallery');
    expect($rewritten)->not->toContain('youtube.com');
    expect($rewritten)->not->toContain('wp-content/uploads');
});
```

### T064 — Unit tests per plugin

- ShortcodeStrip: nested shortcodes, unknown shortcode silently stripped, registered shortcode invokes callback
- OembedExpand: each provider URL pattern detected; resolve_remote: false records URL only; resolve_remote: true with mock HTTP client returns metadata
- MediaRewriteUrl: rewrites canonical URL; rewrites CDN-prefixed URL when host allowlisted; leaves non-allowlisted CDN URL unchanged; missing media in id-map → warning, leaves URL unchanged

## Definition of Done

- [ ] All 3 plugins implement `ProcessPluginInterface` and compose in chains
- [ ] Plugin ids follow `wordpress_*` naming convention (FR-017)
- [ ] OembedExpand's resolve_remote default is false
- [ ] CDN host allowlist works for MediaRewriteUrl
- [ ] All 3 plugins listed on public-surface-map.md, plus `WordPressOembedResolutionException`

## Risks

- **Shortcode regex edge cases.** WP's shortcode parser is gnarly. Don't try to outdo it — handle the 95% case (well-formed shortcodes), document limitations for the 5% (malformed escaping, deeply nested).
- **oEmbed provider drift.** YouTube/Vimeo APIs change. Failing-open (record URL, skip resolution) is the right default.
- **Media URL rewrites for content that references missing media.** `LookupProcessor` returns null for missing — don't crash, leave URL as-is and log a warning.

## Reviewer guidance

- The composability test (T063) is the integration check — verify it actually runs all three in sequence with realistic content.
- Watch for case-sensitivity issues in URL host matching (CDN allowlist).
- Verify oEmbed remote resolution caches per-run — re-fetching the same URL N times across N posts would explode network usage.

## Activity Log

- 2026-05-14T22:26:44Z – claude – shell_pid=146569 – Started implementation via action command
