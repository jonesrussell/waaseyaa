<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Attribute;

use Waaseyaa\Plugin\Attribute\WaaseyaaPlugin;

/**
 * Attribute for discovering access policy plugins.
 *
 * Place this attribute on classes that implement AccessPolicyInterface
 * to enable attribute-based plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AccessPolicy extends WaaseyaaPlugin
{
    /**
     * @param string   $id          Unique plugin ID.
     * @param string[] $entityTypes Entity type IDs this policy applies to.
     * @param string   $label       Human-readable label.
     * @param string   $description Description of the policy.
     */
    public function __construct(
        string $id,
        public readonly array $entityTypes = [],
        string $label = '',
        string $description = '',
    ) {
        parent::__construct(
            id: $id,
            label: $label,
            description: $description,
            package: 'access',
        );
    }
}
