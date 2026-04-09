<?php

declare(strict_types=1);

namespace Waaseyaa\Seo;

use Waaseyaa\Entity\EntityTypeManagerInterface;

final class SitemapGenerator
{
    /**
     * @param list<SitemapUrl> $urls
     */
    public function toXml(array $urls): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . $this->escapeXml($url->loc) . '</loc>';

            if ($url->lastmod !== null && $url->lastmod !== '') {
                $lines[] = '    <lastmod>' . $this->escapeXml($url->lastmod) . '</lastmod>';
            }

            if ($url->changefreq !== null) {
                $lines[] = '    <changefreq>' . $this->escapeXml($url->changefreq) . '</changefreq>';
            }

            if ($url->priority !== null) {
                $lines[] = '    <priority>' . htmlspecialchars(
                    (string) round($url->priority, 1),
                    \ENT_XML1 | \ENT_QUOTES,
                    'UTF-8',
                ) . '</priority>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Collect sitemap rows by paging entity IDs per type. URL generation stays in application code.
     *
     * @param callable(string,int|string): ?string $buildLoc Returning null or '' skips the ID.
     * @param list<string>|null $includeEntityTypes When null, all registered types (from getDefinitions) are considered.
     * @param list<string> $excludeEntityTypes
     * @param array<string, array<string, mixed>> $perTypeOptions Optional keys: `changefreq`, `priority`, `max`.
     *
     * @return list<SitemapUrl>
     */
    public function collectFromEntityTypes(
        EntityTypeManagerInterface $entityTypeManager,
        callable $buildLoc,
        ?array $includeEntityTypes = null,
        array $excludeEntityTypes = [],
        int $maxUrlsPerType = 50000,
        array $perTypeOptions = [],
    ): array {
        $exclude = array_flip($excludeEntityTypes);
        $include = $includeEntityTypes !== null ? array_flip($includeEntityTypes) : null;
        $out = [];

        foreach (array_keys($entityTypeManager->getDefinitions()) as $entityTypeId) {
            if (isset($exclude[$entityTypeId])) {
                continue;
            }

            if ($include !== null && !isset($include[$entityTypeId])) {
                continue;
            }

            if (!$entityTypeManager->hasDefinition($entityTypeId)) {
                continue;
            }

            $typeOpts = $perTypeOptions[$entityTypeId] ?? [];
            $rawMax = $typeOpts['max'] ?? null;
            $limit = is_numeric($rawMax) ? max(0, (int) $rawMax) : $maxUrlsPerType;

            if ($limit === 0) {
                continue;
            }

            $ids = $entityTypeManager->getStorage($entityTypeId)->getQuery()->range(0, $limit)->execute();
            $changefreqRaw = $typeOpts['changefreq'] ?? null;
            $changefreq = is_string($changefreqRaw) && $changefreqRaw !== '' ? $changefreqRaw : null;
            $priority = null;
            $priorityRaw = $typeOpts['priority'] ?? null;

            if (is_numeric($priorityRaw)) {
                $priority = (float) $priorityRaw;
            }

            foreach ($ids as $id) {
                $loc = $buildLoc($entityTypeId, $id);

                if ($loc === null || $loc === '') {
                    continue;
                }

                $out[] = new SitemapUrl(
                    loc: $loc,
                    changefreq: $changefreq,
                    priority: $priority,
                );
            }
        }

        return $out;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }
}
