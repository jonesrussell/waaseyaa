<?php

declare(strict_types=1);

namespace Waaseyaa\Seo;

use InvalidArgumentException;

/**
 * One URL entry for an XML sitemap (sitemap.xml semantics).
 */
final readonly class SitemapUrl
{
    private const VALID_CHANGEFREQ = [
        'always',
        'hourly',
        'daily',
        'weekly',
        'monthly',
        'yearly',
        'never',
    ];

    public function __construct(
        public string $loc,
        public ?string $lastmod = null,
        public ?string $changefreq = null,
        public ?float $priority = null,
    ) {
        if ($this->loc === '') {
            throw new InvalidArgumentException('SitemapUrl loc must not be empty.');
        }

        if ($this->changefreq !== null && !\in_array($this->changefreq, self::VALID_CHANGEFREQ, true)) {
            throw new InvalidArgumentException(
                'Invalid changefreq; expected one of: ' . implode(', ', self::VALID_CHANGEFREQ),
            );
        }

        if ($this->priority !== null && ($this->priority < 0.0 || $this->priority > 1.0)) {
            throw new InvalidArgumentException('SitemapUrl priority must be between 0.0 and 1.0.');
        }
    }
}
