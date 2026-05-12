<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;

/**
 * Unit tests for the WP07 EntityType revisionable + primaryStorageBackend slots.
 *
 * Covers:
 * - Default values keep existing call sites unchanged (T035).
 * - Revisionable=true with revision key → constructs cleanly (T035/T036).
 * - Revisionable=true without revision key → InvalidArgumentException (T036).
 * - primaryStorageBackend accessor returns configured value or null (T035).
 */
#[CoversClass(EntityType::class)]
final class EntityTypeRevisionableTest extends TestCase
{
    // -------------------------------------------------------------------------
    // T035: backward-compatible defaults
    // -------------------------------------------------------------------------

    #[Test]
    public function default_revisionable_is_false(): void
    {
        $type = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        self::assertFalse($type->isRevisionable());
    }

    #[Test]
    public function default_primary_storage_backend_is_null(): void
    {
        $type = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        self::assertNull($type->getPrimaryStorageBackend());
    }

    #[Test]
    public function existing_entity_type_construction_unchanged(): void
    {
        // Simulate a typical pre-WP07 call site — no new params.
        $type = new EntityType(
            id: 'node',
            label: 'Content',
            class: TestEntity::class,
            keys: ['id' => 'nid', 'uuid' => 'uuid'],
        );

        self::assertSame('node', $type->id());
        self::assertFalse($type->isRevisionable());
        self::assertNull($type->getPrimaryStorageBackend());
    }

    // -------------------------------------------------------------------------
    // T035/T036: revisionable=true with revision key
    // -------------------------------------------------------------------------

    #[Test]
    public function revisionable_true_with_revision_key_constructs_cleanly(): void
    {
        $type = new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: TestEntity::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid', 'revision' => 'vid'],
            revisionable: true,
        );

        self::assertTrue($type->isRevisionable());
        self::assertSame('vid', $type->getKeys()['revision']);
    }

    #[Test]
    public function primary_storage_backend_accessor_returns_configured_value(): void
    {
        $type = new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: TestEntity::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid', 'revision' => 'vid'],
            revisionable: true,
            primaryStorageBackend: 'sql-column',
        );

        self::assertSame('sql-column', $type->getPrimaryStorageBackend());
    }

    #[Test]
    public function primary_storage_backend_null_when_not_set(): void
    {
        $type = new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: TestEntity::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid', 'revision' => 'vid'],
            revisionable: true,
        );

        self::assertNull($type->getPrimaryStorageBackend());
    }

    // -------------------------------------------------------------------------
    // T036: revisionable=true without revision key → exception
    // -------------------------------------------------------------------------

    #[Test]
    public function revisionable_true_without_revision_key_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('teaching');
        $this->expectExceptionMessage("entityKeys['revision']");

        new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: TestEntity::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid'],
            revisionable: true,
        );
    }

    #[Test]
    public function revisionable_true_with_empty_revision_key_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no_revision_entity');

        new EntityType(
            id: 'no_revision_entity',
            label: 'No Revision Entity',
            class: TestEntity::class,
            keys: ['id' => 'id', 'revision' => ''],
            revisionable: true,
        );
    }

    #[Test]
    public function revisionable_false_without_revision_key_does_not_throw(): void
    {
        // Existing entity types have no revision key — must not throw.
        $type = new EntityType(
            id: 'user',
            label: 'User',
            class: TestEntity::class,
            keys: ['id' => 'uid', 'uuid' => 'uuid'],
            revisionable: false,
        );

        self::assertFalse($type->isRevisionable());
    }
}
