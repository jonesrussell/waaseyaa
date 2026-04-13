<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Introspection\Sovereignty;

use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

final class SovereigntyIntrospectionProvider implements GraphSectionProviderInterface
{
    public function __construct(
        private readonly SovereigntyProfile $activeProfile,
    ) {}

    public function getKey(): string
    {
        return 'sovereignty';
    }

    public function provide(): GraphSection
    {
        $availableProfiles = array_map(
            static fn(SovereigntyProfile $profile): array => [
                'name' => $profile->name,
                'value' => $profile->value,
            ],
            SovereigntyProfile::cases(),
        );

        return new GraphSection(
            key: 'sovereignty',
            version: '1.0',
            data: [
                'active_profile' => $this->activeProfile->value,
                'available_profiles' => $availableProfiles,
            ],
        );
    }
}
