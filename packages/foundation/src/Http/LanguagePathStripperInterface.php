<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http;

/**
 * Optional HTTP hook for altering the request path before Symfony route matching.
 *
 * Used by SSR language-prefix routing; default no-op when no provider implements.
 */
interface LanguagePathStripperInterface
{
    /**
     * Strip URL language prefixes so `/oj/foo` matches the `/foo` route.
     */
    public function stripLanguagePrefixForRouting(string $path): string;
}
