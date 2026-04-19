<?php

declare(strict_types=1);

namespace Waaseyaa\Field\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionRegistry;

#[CoversClass(FieldDefinitionRegistry::class)]
final class FieldDefinitionRegistryTest extends TestCase
{
    #[Test]
    public function registersAndRetrievesBundleFields(): void
    {
        $registry = new FieldDefinitionRegistry();
        $email = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'business',
        );

        $registry->registerBundleFields('group', 'business', ['email' => $email]);

        $fields = $registry->bundleFieldsFor('group', 'business');
        self::assertArrayHasKey('email', $fields);
        self::assertSame($email, $fields['email']);
    }

    #[Test]
    public function coreFieldsReturnedInOriginalShape(): void
    {
        $registry = new FieldDefinitionRegistry();
        $meta = ['name' => ['type' => 'string', 'required' => true]];

        $registry->registerCoreFields('group', $meta);

        self::assertSame($meta, $registry->coreFieldsFor('group'));
    }

    #[Test]
    public function bundleFieldsEmptyForUnregisteredEntityType(): void
    {
        $registry = new FieldDefinitionRegistry();

        self::assertSame([], $registry->bundleFieldsFor('group', 'business'));
    }

    #[Test]
    public function bundleFieldsEmptyForUnknownBundleOnRegisteredEntityType(): void
    {
        $registry = new FieldDefinitionRegistry();
        $registry->registerCoreFields('group', []);

        self::assertSame([], $registry->bundleFieldsFor('group', 'unknown'));
    }

    #[Test]
    public function mismatchedTargetEntityTypeIdThrows(): void
    {
        $registry = new FieldDefinitionRegistry();
        $field = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'node',
            targetBundle: 'business',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('targetEntityTypeId "node"');

        $registry->registerBundleFields('group', 'business', [$field]);
    }

    #[Test]
    public function mismatchedTargetBundleThrows(): void
    {
        $registry = new FieldDefinitionRegistry();
        $field = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'organization',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('targetBundle "organization"');

        $registry->registerBundleFields('group', 'business', [$field]);
    }

    #[Test]
    public function nullTargetBundleOnBundleRegistrationThrows(): void
    {
        $registry = new FieldDefinitionRegistry();
        $field = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: null,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('targetBundle "(null)"');

        $registry->registerBundleFields('group', 'business', [$field]);
    }

    #[Test]
    public function nonFieldDefinitionEntryThrows(): void
    {
        $registry = new FieldDefinitionRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FieldDefinitionInterface');

        $registry->registerBundleFields('group', 'business', [new \stdClass()]);
    }

    #[Test]
    public function coreBundleCollisionThrowsWithSpecifiedMessage(): void
    {
        $registry = new FieldDefinitionRegistry();
        $registry->registerCoreFields('group', ['status' => ['type' => 'boolean']]);

        $bundleStatus = new FieldDefinition(
            name: 'status',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'business',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Field "status" on entity type "group" bundle "business" collides with core field "status" on entity type "group".'
        );

        $registry->registerBundleFields('group', 'business', ['status' => $bundleStatus]);
    }

    #[Test]
    public function duplicateNameWithinSameRegistrationThrows(): void
    {
        $registry = new FieldDefinitionRegistry();
        $first = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'business',
        );
        $second = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'business',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate bundle field "email"');

        $registry->registerBundleFields('group', 'business', [$first, $second]);
    }

    #[Test]
    public function duplicateAcrossTwoRegistrationsSameBundleThrows(): void
    {
        $registry = new FieldDefinitionRegistry();
        $email = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'business',
        );
        $registry->registerBundleFields('group', 'business', [$email]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        $registry->registerBundleFields('group', 'business', [
            new FieldDefinition(
                name: 'email',
                type: 'string',
                targetEntityTypeId: 'group',
                targetBundle: 'business',
            ),
        ]);
    }

    #[Test]
    public function sameNameAcrossDifferentBundlesIsAllowed(): void
    {
        $registry = new FieldDefinitionRegistry();
        $businessEmail = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'business',
        );
        $orgEmail = new FieldDefinition(
            name: 'email',
            type: 'string',
            targetEntityTypeId: 'group',
            targetBundle: 'organization',
        );

        $registry->registerBundleFields('group', 'business', [$businessEmail]);
        $registry->registerBundleFields('group', 'organization', [$orgEmail]);

        self::assertSame($businessEmail, $registry->bundleFieldsFor('group', 'business')['email']);
        self::assertSame($orgEmail, $registry->bundleFieldsFor('group', 'organization')['email']);
    }
}
