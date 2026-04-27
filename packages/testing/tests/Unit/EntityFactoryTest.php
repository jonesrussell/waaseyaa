<?php

declare(strict_types=1);

namespace Waaseyaa\Testing\Tests\Unit;

use Waaseyaa\Entity\Tests\Helper\TestEntityType;
use Waaseyaa\Testing\Factory\EntityFactory;
use Waaseyaa\Testing\Tests\Fixture\StubFieldableEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityFactory::class)]
final class EntityFactoryTest extends TestCase
{
    private EntityFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new EntityFactory();
    }

    #[Test]
    public function defineRegistersEntityType(): void
    {
        $result = $this->factory->define('article', ['title' => 'Default']);

        $this->assertSame($this->factory, $result, 'define() should return self for fluent chaining.');
        $this->assertTrue($this->factory->hasDefinition('article'));
    }

    #[Test]
    public function hasDefinitionReturnsFalseForUndefined(): void
    {
        $this->assertFalse($this->factory->hasDefinition('nonexistent'));
    }

    #[Test]
    public function createReturnsDefaultValues(): void
    {
        $this->factory->define('article', [
            'title' => 'Default title',
            'status' => 1,
            'type' => 'article',
        ]);

        $values = $this->factory->create('article');

        $this->assertSame('Default title', $values['title']);
        $this->assertSame(1, $values['status']);
        $this->assertSame('article', $values['type']);
    }

    #[Test]
    public function createMergesOverrides(): void
    {
        $this->factory->define('article', [
            'title' => 'Default title',
            'status' => 1,
        ]);

        $values = $this->factory->create('article', ['title' => 'Custom title']);

        $this->assertSame('Custom title', $values['title']);
        $this->assertSame(1, $values['status']);
    }

    #[Test]
    public function createAddsNewKeysFromOverrides(): void
    {
        $this->factory->define('article', [
            'title' => 'Default title',
        ]);

        $values = $this->factory->create('article', ['author_id' => 42]);

        $this->assertSame('Default title', $values['title']);
        $this->assertSame(42, $values['author_id']);
    }

    #[Test]
    public function createThrowsForUndefinedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity type "unknown" has not been defined');

        $this->factory->create('unknown');
    }

    #[Test]
    public function createManyReturnsCorrectCount(): void
    {
        $this->factory->define('article', ['title' => 'Test']);

        $results = $this->factory->createMany('article', 5);

        $this->assertCount(5, $results);
    }

    #[Test]
    public function createManyAppliesOverridesToAll(): void
    {
        $this->factory->define('article', [
            'title' => 'Default',
            'status' => 1,
        ]);

        $results = $this->factory->createMany('article', 3, ['status' => 0]);

        foreach ($results as $values) {
            $this->assertSame(0, $values['status']);
            $this->assertSame('Default', $values['title']);
        }
    }

    #[Test]
    public function createManyThrowsForZeroCount(): void
    {
        $this->factory->define('article', ['title' => 'Test']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Count must be at least 1');

        $this->factory->createMany('article', 0);
    }

    #[Test]
    public function createManyThrowsForNegativeCount(): void
    {
        $this->factory->define('article', ['title' => 'Test']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Count must be at least 1');

        $this->factory->createMany('article', -1);
    }

    #[Test]
    public function sequenceGeneratesIncrementingValues(): void
    {
        $this->factory->define('article', ['title' => 'Default', 'status' => 1]);
        $this->factory->sequence('article', 'title', fn (int $n): string => "Article #{$n}");

        $first = $this->factory->create('article');
        $second = $this->factory->create('article');
        $third = $this->factory->create('article');

        $this->assertSame('Article #1', $first['title']);
        $this->assertSame('Article #2', $second['title']);
        $this->assertSame('Article #3', $third['title']);
    }

    #[Test]
    public function sequenceIsSkippedWhenOverridden(): void
    {
        $this->factory->define('article', ['title' => 'Default']);
        $this->factory->sequence('article', 'title', fn (int $n): string => "Article #{$n}");

        $values = $this->factory->create('article', ['title' => 'Manual title']);

        $this->assertSame('Manual title', $values['title']);
    }

    #[Test]
    public function sequenceCounterIncrementsWithCreateMany(): void
    {
        $this->factory->define('article', ['title' => 'Default']);
        $this->factory->sequence('article', 'title', fn (int $n): string => "Article #{$n}");

        $results = $this->factory->createMany('article', 3);

        $this->assertSame('Article #1', $results[0]['title']);
        $this->assertSame('Article #2', $results[1]['title']);
        $this->assertSame('Article #3', $results[2]['title']);
    }

    #[Test]
    public function resetClearsAllState(): void
    {
        $this->factory->define('article', ['title' => 'Test']);
        $this->factory->sequence('article', 'title', fn (int $n): string => "#{$n}");

        $this->factory->reset();

        $this->assertFalse($this->factory->hasDefinition('article'));
    }

    #[Test]
    public function resetAllowsRedefining(): void
    {
        $this->factory->define('article', ['title' => 'Old']);
        $this->factory->sequence('article', 'title', fn (int $n): string => "Old #{$n}");
        $this->factory->create('article'); // counter = 1

        $this->factory->reset();
        $this->factory->define('article', ['title' => 'New']);
        $this->factory->sequence('article', 'title', fn (int $n): string => "New #{$n}");

        $values = $this->factory->create('article');

        // Counter should have been reset, so this should be #1 again.
        $this->assertSame('New #1', $values['title']);
    }

    #[Test]
    public function multipleEntityTypesAreIndependent(): void
    {
        $this->factory->define('article', ['title' => 'Article default']);
        $this->factory->define('page', ['title' => 'Page default']);

        $article = $this->factory->create('article');
        $page = $this->factory->create('page');

        $this->assertSame('Article default', $article['title']);
        $this->assertSame('Page default', $page['title']);
    }

    #[Test]
    public function multipleSequencesPerEntityType(): void
    {
        $this->factory->define('user', ['name' => '', 'mail' => '']);
        $this->factory->sequence('user', 'name', fn (int $n): string => "user{$n}");
        $this->factory->sequence('user', 'mail', fn (int $n): string => "user{$n}@example.com");

        $user = $this->factory->create('user');

        $this->assertSame('user1', $user['name']);
        $this->assertSame('user1@example.com', $user['mail']);
    }

    #[Test]
    public function defineFromEntityTypeRegistersDefaults(): void
    {
        $type = TestEntityType::stub(
            id: 'article',
            class: StubFieldableEntity::class,
            label: 'Article',
            fieldDefinitions: [
                'title' => ['type' => 'string', 'required' => true],
                'status' => [
                    'type' => 'string',
                    'required' => true,
                    'allowed_values' => ['draft', 'published'],
                ],
            ],
        );

        $this->factory->defineFromEntityType($type, ['title' => 'Seeded title']);

        $this->assertTrue($this->factory->hasDefinition('article'));

        $values = $this->factory->create('article', []);

        $this->assertSame('Seeded title', $values['title']);
        $this->assertContains($values['status'], ['draft', 'published']);
    }
}
