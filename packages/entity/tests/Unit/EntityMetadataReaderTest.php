<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Attribute\EntityMetadataReader;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * @covers \Waaseyaa\Entity\Attribute\EntityMetadataReader
 */
#[CoversClass(EntityMetadataReader::class)]
final class EntityMetadataReaderTest extends TestCase
{
    protected function tearDown(): void
    {
        EntityMetadataReader::clearCache();
    }

    #[Test]
    public function it_resolves_type_id_and_identity_defaults_without_content_entity_keys(): void
    {
        $class = $this->evalContentEntity(<<<'PHP'
            #[\Waaseyaa\Entity\Attribute\ContentEntityType(id: 'metadata_defaults')]
            class %s extends \Waaseyaa\Entity\ContentEntityBase {}
            PHP,
        );

        $meta = EntityMetadataReader::forClass($class);

        $this->assertSame('metadata_defaults', $meta->typeId);
        $this->assertSame(
            [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'label',
            ],
            $meta->keys,
        );
    }

    #[Test]
    public function it_merges_content_entity_keys_along_inheritance_with_child_last(): void
    {
        $parent = $this->evalContentEntity(<<<'PHP'
            #[\Waaseyaa\Entity\Attribute\ContentEntityType(id: 'merge_parent_type')]
            #[\Waaseyaa\Entity\Attribute\ContentEntityKeys(label: 'title')]
            class %s extends \Waaseyaa\Entity\ContentEntityBase {}
            PHP,
        );

        $child = $this->evalChildOfParent(
            $parent,
            <<<'PHP'
            #[\Waaseyaa\Entity\Attribute\ContentEntityType(id: 'merge_child_type')]
            #[\Waaseyaa\Entity\Attribute\ContentEntityKeys(uuid: 'public_id', bundle: 'kind')]
            class %s extends %s {}
            PHP,
        );

        $parentMeta = EntityMetadataReader::forClass($parent);
        $this->assertSame('merge_parent_type', $parentMeta->typeId);
        $this->assertSame(
            [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'title',
            ],
            $parentMeta->keys,
        );

        $childMeta = EntityMetadataReader::forClass($child);
        $this->assertSame('merge_child_type', $childMeta->typeId);
        $this->assertSame(
            [
                'id' => 'id',
                'uuid' => 'public_id',
                'label' => 'title',
                'bundle' => 'kind',
            ],
            $childMeta->keys,
        );
    }

    #[Test]
    public function it_caches_until_cleared_for_class(): void
    {
        $class = $this->evalContentEntity(<<<'PHP'
            #[\Waaseyaa\Entity\Attribute\ContentEntityType(id: 'cache_type')]
            class %s extends \Waaseyaa\Entity\ContentEntityBase {}
            PHP,
        );

        $a = EntityMetadataReader::forClass($class);
        $b = EntityMetadataReader::forClass($class);
        $this->assertSame($a, $b);

        EntityMetadataReader::clearCacheForClass($class);
        $c = EntityMetadataReader::forClass($class);
        $this->assertNotSame($a, $c);
        $this->assertSame('cache_type', $c->typeId);
    }

    /**
     * @param class-string $parent
     * @param non-empty-string $classBody sprintf with %s child name, %s parent name
     *
     * @return class-string<ContentEntityBase>
     */
    private function evalChildOfParent(string $parent, string $classBody): string
    {
        $base = 'EntityMetadataTest_' . str_replace(['.', '-'], '_', uniqid('c_', true));
        if (class_exists($base, false)) {
            return $this->evalChildOfParent($parent, $classBody);
        }

        $code = \sprintf($classBody, $base, $parent);
        eval($code);

        if (!is_subclass_of($base, ContentEntityBase::class)) {
            throw new \RuntimeException("Invalid fixture: {$base}");
        }

        return $base;
    }

    /**
     * @param non-empty-string $classBody uses sprintf placeholder %s for the generated short class name
     *
     * @return class-string<ContentEntityBase>
     */
    private function evalContentEntity(string $classBody): string
    {
        $base = 'EntityMetadataTest_' . str_replace(['.', '-'], '_', uniqid('', true));
        if (class_exists($base, false)) {
            return $this->evalContentEntity($classBody);
        }

        $code = \sprintf($classBody, $base);
        eval($code);

        if (!is_subclass_of($base, ContentEntityBase::class)) {
            throw new \RuntimeException("Invalid fixture: {$base}");
        }

        return $base;
    }
}
