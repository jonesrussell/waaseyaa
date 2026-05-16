<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Listing\ListingResult;
use Waaseyaa\Listing\Pagination;

#[CoversClass(ListingResult::class)]
final class ListingResultTest extends TestCase
{
    #[Test]
    public function exposesAllFourPropertiesFromConstructor(): void
    {
        $pagination = new Pagination(1, 10, 0, 1, false, false);
        $rows = [];
        $tags = ['entity:node', 'entity:node:1'];
        $contexts = ['url.query.page', 'language.content'];

        $result = new ListingResult($rows, $pagination, $tags, $contexts);

        self::assertSame($rows, $result->rows);
        self::assertSame($pagination, $result->pagination);
        self::assertSame($tags, $result->cacheTags);
        self::assertSame($contexts, $result->cacheContexts);
    }

    #[Test]
    public function acceptsGeneratorRows(): void
    {
        $pagination = new Pagination(1, 10, 0, 1, false, false);
        $gen = (static function (): \Generator {
            yield 'row-1';
            yield 'row-2';
        })();

        $result = new ListingResult($gen, $pagination, [], []);

        $materialized = iterator_to_array($result->rows, preserve_keys: false);
        self::assertSame(['row-1', 'row-2'], $materialized);
    }
}
