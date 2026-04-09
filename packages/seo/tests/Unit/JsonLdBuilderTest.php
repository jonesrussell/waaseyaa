<?php

declare(strict_types=1);

namespace Waaseyaa\Seo\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Seo\JsonLdBuilder;

#[CoversClass(JsonLdBuilder::class)]
final class JsonLdBuilderTest extends TestCase
{
    #[Test]
    public function web_site_shape(): void
    {
        $b = new JsonLdBuilder();
        $data = $b->webSite('https://example.com', 'Example', ['https://social.example/e']);

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('WebSite', $data['@type']);
        $this->assertSame(['https://social.example/e'], $data['sameAs']);
    }

    #[Test]
    public function breadcrumb_positions(): void
    {
        $b = new JsonLdBuilder();
        $data = $b->breadcrumb([
            ['name' => 'Home', 'url' => 'https://example.com/'],
            ['name' => 'Topic'],
        ]);

        $items = $data['itemListElement'];
        $this->assertSame(1, $items[0]['position']);
        $this->assertSame('Home', $items[0]['name']);
        $this->assertSame(2, $items[1]['position']);
        $this->assertArrayNotHasKey('item', $items[1]);
    }
}
