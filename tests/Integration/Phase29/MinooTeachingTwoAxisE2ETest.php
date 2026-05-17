<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase29;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\ContextAwareAccessPolicyInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;

/**
 * M-004 / WP08 — Minoo `teaching` E2E (FR-043 + FR-044).
 *
 * Walks the canonical two-axis lifecycle end-to-end against the driver
 * surface. The driver-level signals exercised here are the public contract
 * consumed by the (separately tested) coordinator layer.
 *
 * FR-043 — round-trip with 5 revisions across English + Anishinaabemowin
 *   with independent sequencing; non-translatable field change propagates
 *   via fallback. Timeline:
 *
 *     | Step | Action                | en revisions | oj revisions |
 *     |------|-----------------------|--------------|--------------|
 *     | 1    | create (en)           | 1            | 0            |
 *     | 2    | add `oj` translation  | 1            | 1            |
 *     | 3    | edit `en` ×3          | 4            | 1            |
 *     | 4    | edit `oj` ×2          | 4            | 3            |
 *
 *   (Spec §5; cookbook §"Independent per-language sequencing".)
 *
 * FR-044 — per-language access fixture (Coordinator vs Knowledge-Keeper):
 *   Coordinator role sees English-only history; Knowledge-Keeper sees both.
 *   Composes `ContextAwareAccessPolicyInterface` with `$context['langcode']`.
 */
#[CoversNothing]
final class MinooTeachingTwoAxisE2ETest extends TestCase
{
    private function makeSqlite(): DBALDatabase
    {
        return new DBALDatabase(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
        );
    }

    private function twoAxisType(): EntityType
    {
        return new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: ContentEntityBase::class,
            keys: [
                'id'               => 'id',
                'uuid'             => 'uuid',
                'revision'         => 'revision_id',
                'langcode'         => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: true,
            translatable: true,
        );
    }

    private function createSchema(DBALDatabase $db): void
    {
        // translation-revision table (translatable fields)
        $db->schema()->createTable('teaching__translation__revision', [
            'fields' => [
                'entity_id'        => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                'langcode'         => ['type' => 'varchar', 'length' => 12,  'not null' => true],
                'revision_id'      => ['type' => 'int',     'not null' => true],
                'revision_created' => ['type' => 'varchar', 'length' => 32,  'not null' => false],
                'revision_log'     => ['type' => 'text', 'not null' => false],
                'title'            => ['type' => 'varchar', 'length' => 255, 'not null' => false],
                'body'             => ['type' => 'text', 'not null' => false],
            ],
            'primary key' => ['entity_id', 'langcode', 'revision_id'],
        ]);

        // non-translatable revision archive
        $db->schema()->createTable('teaching__revision', [
            'fields' => [
                'entity_id' => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                'vid'       => ['type' => 'int',     'not null' => true],
                'featured'  => ['type' => 'int',     'not null' => false],
                'category'  => ['type' => 'varchar', 'length' => 64, 'not null' => false],
            ],
            'primary key' => ['entity_id', 'vid'],
        ]);
    }

    #[Test]
    public function fr043_teaching_round_trip_five_revisions_with_independent_sequencing(): void
    {
        $db = $this->makeSqlite();
        $this->createSchema($db);

        $driver = new RevisionableStorageDriver(
            new SingleConnectionResolver($db),
            $this->twoAxisType(),
        );

        // Step 1: create teaching in English.
        $driver->writeRevision('t1', ['title' => 'Teaching about turtles', 'body' => 'EN body v1'], null, 'en');
        self::assertSame(1, $driver->currentLangcodeRevision('t1', 'en'));
        self::assertFalse($driver->hasCurrentLangcodeRevision('t1', 'oj'));

        // Step 2: add Anishinaabemowin translation.
        $driver->writeRevision('t1', ['title' => "Mikinaak-gikinoo'amaadiwin", 'body' => 'OJ body v1'], null, 'oj');
        self::assertSame(1, $driver->currentLangcodeRevision('t1', 'en'));
        self::assertSame(1, $driver->currentLangcodeRevision('t1', 'oj'));

        // Step 3: edit English 3 times — English bumps to revision 4, oj stays at 1.
        $driver->writeRevision('t1', ['title' => 'Teaching about turtles', 'body' => 'EN body v2'], null, 'en');
        $driver->writeRevision('t1', ['title' => 'Teaching about turtles', 'body' => 'EN body v3'], null, 'en');
        $driver->writeRevision('t1', ['title' => 'Teaching about turtles', 'body' => 'EN body v4'], null, 'en');
        self::assertSame(4, $driver->currentLangcodeRevision('t1', 'en'), 'English revision pointer must advance to 4');
        self::assertSame(1, $driver->currentLangcodeRevision('t1', 'oj'), 'oj revision pointer must NOT advance when only en is edited');

        // Step 4: edit Anishinaabemowin 2 times — oj bumps to 3, English stays at 4.
        $driver->writeRevision('t1', ['title' => "Mikinaak-gikinoo'amaadiwin", 'body' => 'OJ body v2'], null, 'oj');
        $driver->writeRevision('t1', ['title' => "Mikinaak-gikinoo'amaadiwin", 'body' => 'OJ body v3'], null, 'oj');
        self::assertSame(4, $driver->currentLangcodeRevision('t1', 'en'), 'English must not bump when only oj is edited');
        self::assertSame(3, $driver->currentLangcodeRevision('t1', 'oj'), 'oj must advance to 3');

        // Verify total revision-list output: 4 en + 3 oj = 7 rows in translation__revision.
        $totalRows = 0;
        foreach ($db->query(
            'SELECT COUNT(*) AS c FROM teaching__translation__revision WHERE entity_id = ?',
            ['t1'],
        ) as $row) {
            $totalRows = (int) ((array) $row)['c'];
            break;
        }
        self::assertSame(7, $totalRows, 'Total per-langcode revisions across both languages');

        // Per-langcode counts.
        $enRows = $this->countRevisions($db, 't1', 'en');
        $ojRows = $this->countRevisions($db, 't1', 'oj');
        self::assertSame(4, $enRows);
        self::assertSame(3, $ojRows);
    }

    #[Test]
    public function fr043_non_translatable_field_change_propagates_via_fallback(): void
    {
        $db = $this->makeSqlite();
        $this->createSchema($db);

        // Initialise translation rows for two languages so fallback is meaningful.
        $driver = new RevisionableStorageDriver(
            new SingleConnectionResolver($db),
            $this->twoAxisType(),
        );
        $driver->writeRevision('t1', ['title' => 'EN'], null, 'en');
        $driver->writeRevision('t1', ['title' => 'OJ'], null, 'oj');

        // A non-translatable field change writes one row to <entity>__revision and
        // is observed by reads in *every* langcode (the row is langcode-agnostic).
        $db->query(
            'INSERT INTO teaching__revision (entity_id, vid, featured, category) VALUES (?, ?, ?, ?)',
            ['t1', 1, 0, 'pedagogy'],
        );
        $db->query(
            'INSERT INTO teaching__revision (entity_id, vid, featured, category) VALUES (?, ?, ?, ?)',
            ['t1', 2, 1, 'pedagogy'],
        );

        // Confirm: 2 rows in the non-translatable archive, langcode-agnostic.
        $count = 0;
        foreach ($db->query(
            'SELECT COUNT(*) AS c FROM teaching__revision WHERE entity_id = ?',
            ['t1'],
        ) as $row) {
            $count = (int) ((array) $row)['c'];
            break;
        }
        self::assertSame(2, $count, 'Non-translatable archive has exactly 2 rows after two writes');

        // Fallback semantics: the latest non-translatable revision is shared across
        // both languages. Read latest (vid=2) and verify featured=1.
        $latestFeatured = null;
        $latestCategory = null;
        foreach ($db->query(
            'SELECT featured, category FROM teaching__revision WHERE entity_id = ? AND vid = ?',
            ['t1', 2],
        ) as $row) {
            $arr = (array) $row;
            $latestFeatured = (int) $arr['featured'];
            $latestCategory = (string) $arr['category'];
            break;
        }
        self::assertSame(1, $latestFeatured, 'Latest non-translatable revision carries featured=1');
        self::assertSame('pedagogy', $latestCategory);
    }

    #[Test]
    public function fr044_coordinator_sees_english_only_knowledge_keeper_sees_both(): void
    {
        $db = $this->makeSqlite();
        $this->createSchema($db);

        $driver = new RevisionableStorageDriver(
            new SingleConnectionResolver($db),
            $this->twoAxisType(),
        );
        $driver->writeRevision('t1', ['title' => 'EN'], null, 'en');
        $driver->writeRevision('t1', ['title' => 'OJ'], null, 'oj');

        $policy = $this->makeLangcodeAwarePolicy();

        // Stub entity for the policy's `accessWithContext()` call. Two-axis policy
        // composition cares only about (operation, account, context['langcode']).
        $entity = $this->makeStubEntity('teaching', 't1');

        $coordinator     = $this->makeAccount(roles: ['coordinator']);
        $knowledgeKeeper = $this->makeAccount(roles: ['knowledge_keeper']);

        // Coordinator: allowed for en, forbidden for oj.
        self::assertTrue(
            $policy->accessWithContext($entity, 'view_revision', $coordinator, ['langcode' => 'en'])->isAllowed(),
            'coordinator must see English revisions',
        );
        self::assertTrue(
            $policy->accessWithContext($entity, 'view_revision', $coordinator, ['langcode' => 'oj'])->isForbidden(),
            'coordinator must NOT see Anishinaabemowin revisions',
        );

        // Knowledge-Keeper: allowed for both.
        self::assertTrue(
            $policy->accessWithContext($entity, 'view_revision', $knowledgeKeeper, ['langcode' => 'en'])->isAllowed(),
            'knowledge-keeper must see English revisions',
        );
        self::assertTrue(
            $policy->accessWithContext($entity, 'view_revision', $knowledgeKeeper, ['langcode' => 'oj'])->isAllowed(),
            'knowledge-keeper must see Anishinaabemowin revisions',
        );
    }

    private function countRevisions(DBALDatabase $db, string $entityId, string $langcode): int
    {
        $count = 0;
        foreach ($db->query(
            'SELECT COUNT(*) AS c FROM teaching__translation__revision WHERE entity_id = ? AND langcode = ?',
            [$entityId, $langcode],
        ) as $row) {
            $count = (int) ((array) $row)['c'];
            break;
        }
        return $count;
    }

    /**
     * Per-language access policy fixture for the Coordinator vs Knowledge-Keeper
     * test. Implements both `AccessPolicyInterface` and
     * `ContextAwareAccessPolicyInterface` so it can be wired into the entity-type
     * surface unchanged (charter §5.3 — composition seam).
     */
    private function makeLangcodeAwarePolicy(): AccessPolicyInterface&ContextAwareAccessPolicyInterface
    {
        return new class implements AccessPolicyInterface, ContextAwareAccessPolicyInterface {
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                // No langcode context — neutral. Real decision lives in accessWithContext().
                return AccessResult::neutral();
            }

            public function accessWithContext(
                EntityInterface $entity,
                string $operation,
                AccountInterface $account,
                array $context,
            ): AccessResult {
                if ($operation !== 'view_revision') {
                    return AccessResult::neutral();
                }
                $lc    = $context['langcode'] ?? null;
                $roles = $account->getRoles();

                if (in_array('knowledge_keeper', $roles, true)) {
                    return AccessResult::allowed();
                }
                if (in_array('coordinator', $roles, true) && $lc === 'en') {
                    return AccessResult::allowed();
                }
                if (in_array('coordinator', $roles, true)) {
                    return AccessResult::forbidden();
                }
                return AccessResult::neutral();
            }

            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }

            public function appliesTo(string $entityTypeId): bool
            {
                return $entityTypeId === 'teaching';
            }
        };
    }

    /**
     * @param list<string> $roles
     */
    private function makeAccount(array $roles): AccountInterface
    {
        return new class ($roles) implements AccountInterface {
            /** @param list<string> $roles */
            public function __construct(private readonly array $roles) {}
            public function id(): int|string
            {
                return 42;
            }
            public function getRoles(): array
            {
                return $this->roles;
            }
            public function isAuthenticated(): bool
            {
                return true;
            }
            public function hasPermission(string $permission): bool
            {
                return false;
            }
        };
    }

    private function makeStubEntity(string $entityTypeId, string $id): EntityInterface
    {
        return new class ($entityTypeId, $id) implements EntityInterface {
            public function __construct(
                private readonly string $entityTypeId,
                private readonly string $entityId,
            ) {}
            public function id(): int|string|null
            {
                return $this->entityId;
            }
            public function uuid(): string
            {
                return $this->entityId;
            }
            public function getEntityTypeId(): string
            {
                return $this->entityTypeId;
            }
            public function bundle(): string
            {
                return $this->entityTypeId;
            }
            public function label(): string
            {
                return $this->entityId;
            }
            public function isNew(): bool
            {
                return false;
            }
            public function get(string $name): mixed
            {
                return null;
            }
            public function set(string $name, mixed $value): static
            {
                return $this;
            }
            public function language(): string
            {
                return 'en';
            }
            public function toArray(): array
            {
                return ['id' => $this->entityId];
            }
        };
    }
}
