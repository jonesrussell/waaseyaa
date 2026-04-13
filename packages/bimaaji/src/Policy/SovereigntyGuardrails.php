<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Policy;

use Waaseyaa\Bimaaji\Mutation\MutationRequest;
use Waaseyaa\Bimaaji\Mutation\MutationResult;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

final class SovereigntyGuardrails
{
    /** @param list<GuardrailRule> $rules */
    public function __construct(
        private readonly SovereigntyProfile $activeProfile,
        private readonly array $rules,
    ) {}

    public static function withDefaultRules(SovereigntyProfile $profile): self
    {
        return new self($profile, [
            new GuardrailRule(
                operation: 'delete_entity_type',
                deniedProfile: SovereigntyProfile::NorthOps,
                reason: 'Managed hosting does not allow entity type deletion',
            ),
            new GuardrailRule(
                operation: 'delete_field',
                deniedProfile: SovereigntyProfile::NorthOps,
                reason: 'Managed hosting does not allow field deletion',
            ),
            new GuardrailRule(
                operation: 'modify_sovereignty',
                deniedProfile: SovereigntyProfile::NorthOps,
                reason: 'Sovereignty profile cannot be changed on managed hosting',
            ),
        ]);
    }

    public function validate(MutationRequest $request): MutationResult
    {
        foreach ($this->rules as $rule) {
            if ($rule->operation === $request->operation && $rule->deniedProfile === $this->activeProfile) {
                return MutationResult::failure($request, ['SOVEREIGNTY_VIOLATION', $rule->reason]);
            }
        }

        return MutationResult::success($request);
    }

    /** @return list<GuardrailRule> */
    public function getRules(): array
    {
        return $this->rules;
    }
}
