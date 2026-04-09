<?php

declare(strict_types=1);

namespace Waaseyaa\Seo;

final class MetaTagBuilder
{
    /**
     * Minimal HTML fragment for document head (title, meta description, canonical).
     */
    public function buildHeadSnippet(
        string $title,
        ?string $description = null,
        ?string $canonicalUrl = null,
    ): string {
        $parts = [
            '<title>' . htmlspecialchars($title, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '</title>',
        ];

        if ($description !== null && $description !== '') {
            $parts[] = '<meta name="description" content="'
                . htmlspecialchars($description, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '">';
        }

        if ($canonicalUrl !== null && $canonicalUrl !== '') {
            $parts[] = '<link rel="canonical" href="'
                . htmlspecialchars($canonicalUrl, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '">';
        }

        return implode("\n", $parts) . "\n";
    }
}
