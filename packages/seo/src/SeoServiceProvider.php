<?php

declare(strict_types=1);

namespace Waaseyaa\Seo;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Seo\Twig\SeoTwigExtension;

final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(SitemapGenerator::class, static fn(): SitemapGenerator => new SitemapGenerator());
        $this->singleton(RobotsTxtGenerator::class, static fn(): RobotsTxtGenerator => new RobotsTxtGenerator());
        $this->singleton(MetaTagBuilder::class, static fn(): MetaTagBuilder => new MetaTagBuilder());
        $this->singleton(JsonLdBuilder::class, static fn(): JsonLdBuilder => new JsonLdBuilder());
        $this->singleton(SeoTwigExtension::class, fn(): SeoTwigExtension => new SeoTwigExtension(
            metaTagBuilder: $this->resolve(MetaTagBuilder::class),
        ));
    }
}
