<?php

declare(strict_types=1);

namespace Waaseyaa\Seo;

final class RobotsTxtGenerator
{
    /**
     * @param list<string> $allowPaths
     * @param list<string> $disallowPaths
     */
    public function toText(
        ?string $sitemapUrl = null,
        string $userAgent = '*',
        array $allowPaths = [],
        array $disallowPaths = ['/'],
    ): string {
        $lines = ['User-agent: ' . $userAgent];

        foreach ($allowPaths as $path) {
            $lines[] = 'Allow: ' . $path;
        }

        foreach ($disallowPaths as $path) {
            $lines[] = 'Disallow: ' . $path;
        }

        if ($sitemapUrl !== null && $sitemapUrl !== '') {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $sitemapUrl;
        }

        return implode("\n", $lines) . "\n";
    }
}
