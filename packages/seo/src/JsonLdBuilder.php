<?php

declare(strict_types=1);

namespace Waaseyaa\Seo;

/**
 * JSON-LD array shapes compatible with schema.org (WebSite, Organization, BreadcrumbList).
 */
final class JsonLdBuilder
{
    /**
     * @param list<string> $sameAs
     *
     * @return array<string, mixed>
     */
    public function webSite(string $url, string $name, array $sameAs = []): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'url' => $url,
            'name' => $name,
        ];

        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * @param list<string> $sameAs
     *
     * @return array<string, mixed>
     */
    public function organization(string $name, ?string $url = null, array $sameAs = []): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
        ];

        if ($url !== null && $url !== '') {
            $data['url'] = $url;
        }

        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * @param list<array{name: string, url?: string}> $items ordered root → leaf
     *
     * @return array<string, mixed>
     */
    public function breadcrumb(array $items): array
    {
        $elements = [];
        $pos = 1;

        foreach ($items as $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $pos,
                'name' => $item['name'],
            ];

            if (isset($item['url']) && $item['url'] !== '') {
                $entry['item'] = $item['url'];
            }

            $elements[] = $entry;
            ++$pos;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }
}
