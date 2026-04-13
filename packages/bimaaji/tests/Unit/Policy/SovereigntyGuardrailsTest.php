<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Mutation\MutationRequest;
use Waaseyaa\Bimaaji\Policy\GuardrailRule;
use Waaseyaa\Bimaaji\Policy\SovereigntyGuardrails;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

#[CoversClass(SovereigntyGuardrails::class)]
final class SovereigntyGuardrailsTest extends TestCase
{
    #[Test]
    public function allowsOperationWhenNoRuleMatches(): void
    {
        $guardrails = new SovereigntyGuardrails(
            activeProfile: SovereigntyProfile::Local,
            rules: [
                new GuardrailRule('delete_entity_type', SovereigntyProfile::NorthOps, 'Not allowed'),
            ],
        );

        $request = new MutationRequest(operation: 'delete_entity_type', entityType: 'node');
        $result = $guardrails->validate($request);

        self::assertTrue($result->isSuccess());
    }

    #[Test]
    public function blocksOperationWhenRuleMatchesActiveProfile(): void
    {
        $guardrails = new SovereigntyGuardrails(
            activeProfile: SovereigntyProfile::NorthOps,
            rules: [
                new GuardrailRule('delete_entity_type', SovereigntyProfile::NorthOps, 'Not allowed on managed hosting'),
            ],
        );

        $request = new MutationRequest(operation: 'delete_entity_type', entityType: 'node');
        $result = $guardrails->validate($request);

        self::assertFalse($result->isSuccess());
        self::assertContains('SOVEREIGNTY_VIOLATION', $result->errors);
        self::assertCount(2, $result->errors);
        self::assertSame('Not allowed on managed hosting', $result->errors[1]);
    }

    #[Test]
    public function allowsSameOperationOnDifferentProfile(): void
    {
        $guardrails = new SovereigntyGuardrails(
            activeProfile: SovereigntyProfile::SelfHosted,
            rules: [
                new GuardrailRule('delete_entity_type', SovereigntyProfile::NorthOps, 'Not allowed'),
            ],
        );

        $request = new MutationRequest(operation: 'delete_entity_type', entityType: 'node');
        $result = $guardrails->validate($request);

        self::assertTrue($result->isSuccess());
    }

    #[Test]
    public function defaultRulesBlockNorthOpsOperations(): void
    {
        $guardrails = SovereigntyGuardrails::withDefaultRules(SovereigntyProfile::NorthOps);

        $deleteEntityType = new MutationRequest(operation: 'delete_entity_type', entityType: 'node');
        self::assertFalse($guardrails->validate($deleteEntityType)->isSuccess());

        $deleteField = new MutationRequest(operation: 'delete_field', entityType: 'node', field: 'body');
        self::assertFalse($guardrails->validate($deleteField)->isSuccess());

        $modifySovereignty = new MutationRequest(operation: 'modify_sovereignty', entityType: 'system');
        self::assertFalse($guardrails->validate($modifySovereignty)->isSuccess());
    }

    /** @return iterable<string, array{SovereigntyProfile, string, bool}> */
    public static function profileOperationMatrix(): iterable
    {
        $defaultOperations = ['delete_entity_type', 'delete_field', 'modify_sovereignty'];

        foreach (SovereigntyProfile::cases() as $profile) {
            foreach ($defaultOperations as $operation) {
                $shouldBlock = $profile === SovereigntyProfile::NorthOps;
                yield "{$profile->value}:{$operation}" => [$profile, $operation, $shouldBlock];
            }
        }
    }

    #[Test]
    #[DataProvider('profileOperationMatrix')]
    public function matrixTestAllProfilesAgainstDefaultRules(
        SovereigntyProfile $profile,
        string $operation,
        bool $shouldBlock,
    ): void {
        $guardrails = SovereigntyGuardrails::withDefaultRules($profile);
        $request = new MutationRequest(operation: $operation, entityType: 'node');
        $result = $guardrails->validate($request);

        if ($shouldBlock) {
            self::assertFalse($result->isSuccess(), "Expected {$operation} to be blocked on {$profile->value}");
        } else {
            self::assertTrue($result->isSuccess(), "Expected {$operation} to be allowed on {$profile->value}");
        }
    }

    #[Test]
    public function getRulesReturnsAllRules(): void
    {
        $rules = [
            new GuardrailRule('op1', SovereigntyProfile::NorthOps, 'Reason 1'),
            new GuardrailRule('op2', SovereigntyProfile::Local, 'Reason 2'),
        ];

        $guardrails = new SovereigntyGuardrails(SovereigntyProfile::Local, $rules);

        self::assertCount(2, $guardrails->getRules());
        self::assertSame($rules, $guardrails->getRules());
    }

    #[Test]
    public function allowsUnknownOperationEvenOnNorthOps(): void
    {
        $guardrails = SovereigntyGuardrails::withDefaultRules(SovereigntyProfile::NorthOps);
        $request = new MutationRequest(operation: 'create_entity', entityType: 'node');
        $result = $guardrails->validate($request);

        self::assertTrue($result->isSuccess());
    }
}
