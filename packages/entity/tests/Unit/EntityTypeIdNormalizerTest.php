<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeIdNormalizer;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(EntityTypeIdNormalizer::class)]
final class EntityTypeIdNormalizerTest extends TestCase
{
    private EntityTypeIdNormalizer $normalizer;

    protected function setUp(): void
    {
        $manager = new EntityTypeManager(new EventDispatcher());
        $manager->registerEntityType(new EntityType(
            id: 'note',
            label: 'Note',
            class: \stdClass::class,
            keys: ['id' => 'id'],
        ));

        $this->normalizer = new EntityTypeIdNormalizer($manager);
    }

    #[Test]
    public function stripsCorePrefix(): void
    {
        $this->assertSame('note', $this->normalizer->normalize('core.note'));
    }

    #[Test]
    public function trimsWhitespace(): void
    {
        $this->assertSame('note', $this->normalizer->normalize('  note  '));
    }

    #[Test]
    public function returnsOriginalForUnknownCorePrefix(): void
    {
        $this->assertSame('core.unknown', $this->normalizer->normalize('core.unknown'));
    }

    #[Test]
    public function returnsOriginalForNonPrefixedId(): void
    {
        $this->assertSame('note', $this->normalizer->normalize('note'));
    }

    #[Test]
    public function returnsCoreAloneUnchanged(): void
    {
        $this->assertSame('core.', $this->normalizer->normalize('core.'));
    }
}
