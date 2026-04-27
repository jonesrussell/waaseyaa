<?php

declare(strict_types=1);

namespace Waaseyaa\Attachment\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Attachment\Attachment;
use Waaseyaa\Attachment\AttachmentNotFoundException;
use Waaseyaa\Attachment\AttachmentRepository;
use Waaseyaa\Attachment\Schema\AttachmentSchema;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;

#[CoversClass(AttachmentRepository::class)]
#[CoversClass(Attachment::class)]
#[CoversClass(AttachmentSchema::class)]
#[CoversClass(AttachmentNotFoundException::class)]
final class AttachmentRepositoryTest extends TestCase
{
    private DBALDatabase $database;

    private EntityRepository $entityRepository;

    private AttachmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = DBALDatabase::createSqlite();

        // Ensure schema exists.
        $schema = new AttachmentSchema($this->database);
        $schema->ensureTable();

        $entityType = EntityType::fromClass(Attachment::class);

        $resolver = new SingleConnectionResolver($this->database);
        $driver = new SqlStorageDriver($resolver, 'id');
        $dispatcher = new EventDispatcher();

        $this->entityRepository = new EntityRepository(
            entityType: $entityType,
            driver: $driver,
            eventDispatcher: $dispatcher,
        );

        $this->repository = new AttachmentRepository(
            entityRepository: $this->entityRepository,
            database: $this->database,
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Creates and saves an Attachment, returns the saved entity loaded from storage.
     */
    private function makeAttachment(
        string $parentType,
        string $parentId,
        int $isActive = 0,
        string $filename = 'test.pdf',
    ): Attachment {
        $attachment = new Attachment([
            'parent_entity_type' => $parentType,
            'parent_entity_id' => $parentId,
            'is_active' => $isActive,
            'filename' => $filename,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        // enforceIsNew() ensures INSERT even without a pre-set ID.
        $attachment->enforceIsNew();
        $this->repository->save($attachment);

        // Reload from storage so we have the auto-assigned id.
        $id = (string) $attachment->id();
        $loaded = $this->entityRepository->find($id);
        self::assertInstanceOf(Attachment::class, $loaded);

        return $loaded;
    }

    // ── Tests ──────────────────────────────────────────────────────────────────

    #[Test]
    public function saveAndFindReturnsSameEntity(): void
    {
        $saved = $this->makeAttachment('node', '1');
        $id = (string) $saved->id();

        $found = $this->entityRepository->find($id);
        self::assertInstanceOf(Attachment::class, $found);
        self::assertSame($id, (string) $found->id());
        self::assertSame('node', $found->get('parent_entity_type'));
        self::assertSame('1', $found->get('parent_entity_id'));
    }

    #[Test]
    public function listForReturnsAttachmentsInIdAscOrder(): void
    {
        $a = $this->makeAttachment('node', '1', 0, 'a.pdf');
        $b = $this->makeAttachment('node', '1', 0, 'b.pdf');
        // Different parent — must not appear.
        $this->makeAttachment('node', '2', 0, 'c.pdf');

        $list = $this->repository->listFor('node', '1');

        self::assertCount(2, $list);
        self::assertSame((string) $a->id(), (string) $list[0]->id());
        self::assertSame((string) $b->id(), (string) $list[1]->id());
    }

    #[Test]
    public function getActiveReturnsNullWhenNoneActive(): void
    {
        $this->makeAttachment('node', '1', 0, 'inactive.pdf');

        $result = $this->repository->getActive('node', '1');
        self::assertNull($result);
    }

    #[Test]
    public function getActiveReturnsActiveAttachment(): void
    {
        $this->makeAttachment('node', '1', 0, 'inactive.pdf');
        $active = $this->makeAttachment('node', '1', 1, 'active.pdf');

        $result = $this->repository->getActive('node', '1');
        self::assertInstanceOf(Attachment::class, $result);
        self::assertSame((string) $active->id(), (string) $result->id());
    }

    #[Test]
    public function setActiveFlipsActiveFlag(): void
    {
        $a = $this->makeAttachment('node', '1', 0, 'a.pdf');
        $b = $this->makeAttachment('node', '1', 0, 'b.pdf');

        $this->repository->setActive((string) $a->id());

        $reloadedA = $this->entityRepository->find((string) $a->id());
        $reloadedB = $this->entityRepository->find((string) $b->id());
        self::assertInstanceOf(Attachment::class, $reloadedA);
        self::assertInstanceOf(Attachment::class, $reloadedB);

        self::assertSame(1, (int) $reloadedA->get('is_active'), 'Target must be active');
        self::assertSame(0, (int) $reloadedB->get('is_active'), 'Sibling must be inactive');
    }

    #[Test]
    public function setActiveTransfersActiveFlagFromPreviousToNew(): void
    {
        $a = $this->makeAttachment('node', '1', 1, 'a.pdf');
        $b = $this->makeAttachment('node', '1', 0, 'b.pdf');

        $this->repository->setActive((string) $b->id());

        $reloadedA = $this->entityRepository->find((string) $a->id());
        $reloadedB = $this->entityRepository->find((string) $b->id());
        self::assertInstanceOf(Attachment::class, $reloadedA);
        self::assertInstanceOf(Attachment::class, $reloadedB);

        self::assertSame(0, (int) $reloadedA->get('is_active'), 'Previously active must now be inactive');
        self::assertSame(1, (int) $reloadedB->get('is_active'), 'Target must now be active');
    }

    #[Test]
    public function setActiveOnNonExistentIdThrowsNotFoundException(): void
    {
        $this->expectException(AttachmentNotFoundException::class);
        $this->repository->setActive('99999');
    }

    #[Test]
    public function setActiveDoesNotAffectOtherParents(): void
    {
        $parent1 = $this->makeAttachment('node', '1', 0, 'p1.pdf');
        $parent2 = $this->makeAttachment('node', '2', 1, 'p2.pdf');

        $this->repository->setActive((string) $parent1->id());

        // parent2's attachment must remain active.
        $reloadedP2 = $this->entityRepository->find((string) $parent2->id());
        self::assertInstanceOf(Attachment::class, $reloadedP2);
        self::assertSame(1, (int) $reloadedP2->get('is_active'), 'Other parent attachment must remain active');
    }

    #[Test]
    public function deleteRemovesAttachment(): void
    {
        $attachment = $this->makeAttachment('node', '1');
        $id = (string) $attachment->id();

        $this->repository->delete($id);

        $found = $this->entityRepository->find($id);
        self::assertNull($found);
    }

    #[Test]
    public function deleteOnNonExistentIdIsNoOp(): void
    {
        // Should not throw.
        $this->repository->delete('99999');
        self::assertTrue(true); // Reached without exception.
    }
}
