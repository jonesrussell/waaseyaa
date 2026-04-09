<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityValues;

#[CoversClass(EntityValues::class)]
final class EntityValuesTest extends TestCase
{
    #[Test]
    public function toCastAwareMapUsesGetPerKey(): void
    {
        $entity = new class (['title' => 'hello', 'n' => '3']) extends ContentEntityBase {
            protected array $casts = ['n' => 'int'];

            public function __construct(array $values = [])
            {
                parent::__construct($values, 'article', ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'bundle']);
            }
        };

        $map = EntityValues::toCastAwareMap($entity);

        self::assertSame('hello', $map['title']);
        self::assertSame(3, $map['n']);
        self::assertSame('hello', $entity->toArray()['title']);
        self::assertSame('3', $entity->toArray()['n']);
    }

    #[Test]
    public function statusToIntNormalizesBooleansAndStrings(): void
    {
        self::assertSame(1, EntityValues::statusToInt(true));
        self::assertSame(0, EntityValues::statusToInt(false));
        self::assertSame(1, EntityValues::statusToInt('published'));
        self::assertSame(0, EntityValues::statusToInt('0'));
        self::assertSame(1, EntityValues::statusToInt(1));
        self::assertSame(0, EntityValues::statusToInt(2));
    }
}
