<?php

declare(strict_types=1);

namespace Aurora\Taxonomy\Tests\Unit;

use Aurora\Access\AccessPolicyInterface;
use Aurora\Access\AccessResult;
use Aurora\Access\AccountInterface;
use Aurora\Entity\EntityInterface;
use Aurora\Taxonomy\TermAccessPolicy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\Taxonomy\TermAccessPolicy
 */
class TermAccessPolicyTest extends TestCase
{
    private TermAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new TermAccessPolicy();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createAccount(array $permissions = []): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')
            ->willReturnCallback(fn(string $permission) => \in_array($permission, $permissions, true));

        return $account;
    }

    private function createTermEntity(string $vocabularyId = 'tags'): EntityInterface
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->method('getEntityTypeId')->willReturn('taxonomy_term');
        $entity->method('bundle')->willReturn($vocabularyId);

        return $entity;
    }

    // ---------------------------------------------------------------
    // appliesTo
    // ---------------------------------------------------------------

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(AccessPolicyInterface::class, $this->policy);
    }

    public function testAppliesToTaxonomyTerm(): void
    {
        $this->assertTrue($this->policy->appliesTo('taxonomy_term'));
    }

    public function testDoesNotApplyToOtherEntityTypes(): void
    {
        $this->assertFalse($this->policy->appliesTo('node'));
        $this->assertFalse($this->policy->appliesTo('user'));
        $this->assertFalse($this->policy->appliesTo('taxonomy_vocabulary'));
    }

    // ---------------------------------------------------------------
    // View access
    // ---------------------------------------------------------------

    public function testViewAccessAllowedWithAccessContentPermission(): void
    {
        $entity = $this->createTermEntity();
        $account = $this->createAccount(['access content']);

        $result = $this->policy->access($entity, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    public function testViewAccessNeutralWithoutPermission(): void
    {
        $entity = $this->createTermEntity();
        $account = $this->createAccount([]);

        $result = $this->policy->access($entity, 'view', $account);

        $this->assertTrue($result->isNeutral());
    }

    public function testViewAccessAllowedWithAdministerTaxonomyPermission(): void
    {
        $entity = $this->createTermEntity();
        $account = $this->createAccount(['administer taxonomy']);

        $result = $this->policy->access($entity, 'view', $account);

        $this->assertTrue($result->isAllowed());
    }

    // ---------------------------------------------------------------
    // Update access
    // ---------------------------------------------------------------

    public function testUpdateAccessAllowedWithEditTermsPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['edit terms in tags']);

        $result = $this->policy->access($entity, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    public function testUpdateAccessNeutralWithoutPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount([]);

        $result = $this->policy->access($entity, 'update', $account);

        $this->assertTrue($result->isNeutral());
    }

    public function testUpdateAccessAllowedWithAdministerTaxonomyPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['administer taxonomy']);

        $result = $this->policy->access($entity, 'update', $account);

        $this->assertTrue($result->isAllowed());
    }

    public function testUpdateAccessDeniedWithWrongVocabularyPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['edit terms in categories']);

        $result = $this->policy->access($entity, 'update', $account);

        $this->assertTrue($result->isNeutral());
    }

    // ---------------------------------------------------------------
    // Delete access
    // ---------------------------------------------------------------

    public function testDeleteAccessAllowedWithDeleteTermsPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['delete terms in tags']);

        $result = $this->policy->access($entity, 'delete', $account);

        $this->assertTrue($result->isAllowed());
    }

    public function testDeleteAccessNeutralWithoutPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount([]);

        $result = $this->policy->access($entity, 'delete', $account);

        $this->assertTrue($result->isNeutral());
    }

    public function testDeleteAccessAllowedWithAdministerTaxonomyPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['administer taxonomy']);

        $result = $this->policy->access($entity, 'delete', $account);

        $this->assertTrue($result->isAllowed());
    }

    public function testDeleteAccessDeniedWithWrongVocabularyPermission(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['delete terms in categories']);

        $result = $this->policy->access($entity, 'delete', $account);

        $this->assertTrue($result->isNeutral());
    }

    // ---------------------------------------------------------------
    // Unknown operation
    // ---------------------------------------------------------------

    public function testUnknownOperationReturnsNeutral(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['access content']);

        $result = $this->policy->access($entity, 'archive', $account);

        $this->assertTrue($result->isNeutral());
    }

    public function testUnknownOperationAllowedWithAdministerTaxonomy(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['administer taxonomy']);

        // 'administer taxonomy' grants access for ANY operation.
        $result = $this->policy->access($entity, 'archive', $account);

        $this->assertTrue($result->isAllowed());
    }

    // ---------------------------------------------------------------
    // Create access
    // ---------------------------------------------------------------

    public function testCreateAccessAllowedWithCreateTermsPermission(): void
    {
        $account = $this->createAccount(['create terms in tags']);

        $result = $this->policy->createAccess('taxonomy_term', 'tags', $account);

        $this->assertTrue($result->isAllowed());
    }

    public function testCreateAccessNeutralWithoutPermission(): void
    {
        $account = $this->createAccount([]);

        $result = $this->policy->createAccess('taxonomy_term', 'tags', $account);

        $this->assertTrue($result->isNeutral());
    }

    public function testCreateAccessAllowedWithAdministerTaxonomyPermission(): void
    {
        $account = $this->createAccount(['administer taxonomy']);

        $result = $this->policy->createAccess('taxonomy_term', 'tags', $account);

        $this->assertTrue($result->isAllowed());
    }

    public function testCreateAccessDeniedWithWrongVocabularyPermission(): void
    {
        $account = $this->createAccount(['create terms in categories']);

        $result = $this->policy->createAccess('taxonomy_term', 'tags', $account);

        $this->assertTrue($result->isNeutral());
    }

    // ---------------------------------------------------------------
    // Vocabulary-specific permissions
    // ---------------------------------------------------------------

    public function testPermissionsAreVocabularySpecific(): void
    {
        $tagsEntity = $this->createTermEntity('tags');
        $categoriesEntity = $this->createTermEntity('categories');

        // Account has edit permission only for 'tags' vocabulary.
        $account = $this->createAccount(['edit terms in tags']);

        $tagsResult = $this->policy->access($tagsEntity, 'update', $account);
        $categoriesResult = $this->policy->access($categoriesEntity, 'update', $account);

        $this->assertTrue($tagsResult->isAllowed());
        $this->assertTrue($categoriesResult->isNeutral());
    }

    public function testAdministerTaxonomyGrantsAccessToAllVocabularies(): void
    {
        $tagsEntity = $this->createTermEntity('tags');
        $categoriesEntity = $this->createTermEntity('categories');
        $account = $this->createAccount(['administer taxonomy']);

        $this->assertTrue($this->policy->access($tagsEntity, 'update', $account)->isAllowed());
        $this->assertTrue($this->policy->access($categoriesEntity, 'update', $account)->isAllowed());
        $this->assertTrue($this->policy->access($tagsEntity, 'delete', $account)->isAllowed());
        $this->assertTrue($this->policy->access($categoriesEntity, 'delete', $account)->isAllowed());
    }

    // ---------------------------------------------------------------
    // Access result reasons
    // ---------------------------------------------------------------

    public function testAccessResultContainsReason(): void
    {
        $entity = $this->createTermEntity('tags');
        $account = $this->createAccount(['access content']);

        $result = $this->policy->access($entity, 'view', $account);

        $this->assertNotEmpty($result->reason);
    }

    public function testCreateAccessResultContainsReason(): void
    {
        $account = $this->createAccount(['create terms in tags']);

        $result = $this->policy->createAccess('taxonomy_term', 'tags', $account);

        $this->assertNotEmpty($result->reason);
    }
}
