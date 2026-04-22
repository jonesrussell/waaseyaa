<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Genealogy\Access\GenealogyContentAccessPolicy;
use Waaseyaa\Genealogy\Entity\GenealogyPerson;
use Waaseyaa\Genealogy\Entity\GenealogyTree;
use Waaseyaa\Genealogy\GenealogyBootstrap;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\User\AnonymousUser;

final class GenealogyContentAccessPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        GenealogyBootstrap::reset();
    }

    /**
     * Resolves tree_id to a published tree so {@see GenealogyContentAccessPolicy}
     * exercises anonymous published rules, not "tree could not be resolved".
     */
    private function bindPublishedTreeStorage(int $treeId, GenealogyTree $tree): void
    {
        $treeStorage = $this->createMock(EntityStorageInterface::class);
        $treeStorage->method('load')->with((string) $treeId)->willReturn($tree);

        $etm = $this->createMock(EntityTypeManagerInterface::class);
        $etm->method('getStorage')->with('genealogy_tree')->willReturn($treeStorage);

        GenealogyBootstrap::bind($etm, null);
    }

    #[Test]
    public function anonymous_cannot_view_published_person(): void
    {
        $this->bindPublishedTreeStorage(1, new GenealogyTree([
            'id' => 1,
            'display_name' => 'Fixture tree',
            'status' => 1,
            'owner_uid' => 99,
        ]));

        $policy = new GenealogyContentAccessPolicy();
        $account = new AnonymousUser();
        $person = new GenealogyPerson([
            'display_name' => 'X',
            'status' => 1,
            'tree_id' => 1,
            'is_living' => true,
        ]);

        $result = $policy->access($person, 'view', $account);

        self::assertTrue($result->isForbidden());
        self::assertStringContainsString('Living persons', $result->reason);
    }

    #[Test]
    public function anonymous_cannot_view_unpublished_person(): void
    {
        $this->bindPublishedTreeStorage(1, new GenealogyTree([
            'id' => 1,
            'display_name' => 'Fixture tree',
            'status' => 1,
            'owner_uid' => 99,
        ]));

        $policy = new GenealogyContentAccessPolicy();
        $account = new AnonymousUser();
        $person = new GenealogyPerson([
            'display_name' => 'X',
            'status' => 0,
            'tree_id' => 1,
            'is_living' => false,
        ]);

        $result = $policy->access($person, 'view', $account);

        self::assertTrue($result->isForbidden());
        self::assertStringContainsString('not published for anonymous viewing', $result->reason);
    }

    #[Test]
    public function anonymous_can_view_published_deceased_person_when_tree_is_public(): void
    {
        $this->bindPublishedTreeStorage(1, new GenealogyTree([
            'id' => 1,
            'display_name' => 'Fixture tree',
            'status' => 1,
            'owner_uid' => 99,
        ]));

        $policy = new GenealogyContentAccessPolicy();
        $account = new AnonymousUser();
        $person = new GenealogyPerson([
            'display_name' => 'X',
            'status' => 1,
            'tree_id' => 1,
            'is_living' => false,
        ]);

        $result = $policy->access($person, 'view', $account);

        self::assertTrue($result->isAllowed());
        self::assertStringContainsString('Published genealogy resource is viewable anonymously', $result->reason);
    }
}
