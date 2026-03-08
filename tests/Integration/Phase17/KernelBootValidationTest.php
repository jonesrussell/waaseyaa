<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase17;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Note\Note;

#[CoversClass(AbstractKernel::class)]
final class KernelBootValidationTest extends TestCase
{
    #[Test]
    public function bootHaltsWithDefaultTypeMissingWhenNoTypesRegistered(): void
    {
        $kernel = new MinimalTestKernel(types: []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/DEFAULT_TYPE_MISSING/');

        $kernel->bootForTest();
    }

    #[Test]
    public function exceptionIncludesRemediationMessage(): void
    {
        $kernel = new MinimalTestKernel(types: []);

        try {
            $kernel->bootForTest();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('DEFAULT_TYPE_MISSING', $e->getMessage());
            $this->assertStringContainsString('core.note', $e->getMessage());
        }
    }

    #[Test]
    public function bootSucceedsWithOneRegisteredContentType(): void
    {
        $kernel = new MinimalTestKernel(types: [
            new EntityType(
                id: 'note',
                label: 'Note',
                class: Note::class,
                keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
            ),
        ]);

        // Should not throw.
        $kernel->bootForTest();

        $this->assertTrue($kernel->getEntityTypeManager()->hasDefinition('note'));
    }

    #[Test]
    public function bootSucceedsWithMultipleContentTypes(): void
    {
        $kernel = new MinimalTestKernel(types: [
            new EntityType(id: 'note', label: 'Note', class: Note::class, keys: ['id' => 'id']),
            new EntityType(id: 'article', label: 'Article', class: Note::class, keys: ['id' => 'id']),
        ]);

        $kernel->bootForTest();

        $this->assertCount(2, $kernel->getEntityTypeManager()->getDefinitions());
    }
}

/**
 * Minimal AbstractKernel subclass for testing boot validation in isolation.
 *
 * Skips database, manifest, providers, access policies, and extensions.
 * Only exercises the entity type registration + content type validation path.
 */
class MinimalTestKernel extends AbstractKernel
{
    /** @param \Waaseyaa\Entity\EntityTypeInterface[] $types */
    public function __construct(
        private readonly array $types,
    ) {
        parent::__construct(projectRoot: sys_get_temp_dir());
    }

    /**
     * Runs only the entity type registration + validation portion of boot.
     */
    public function bootForTest(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->entityTypeManager = new EntityTypeManager($this->dispatcher);

        foreach ($this->types as $type) {
            $this->entityTypeManager->registerEntityType($type);
        }

        $this->validateContentTypes();
    }
}
