<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Introspection\Sovereignty;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;
use Waaseyaa\Bimaaji\Introspection\Sovereignty\SovereigntyIntrospectionProvider;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

#[CoversClass(SovereigntyIntrospectionProvider::class)]
final class SovereigntyIntrospectionProviderTest extends TestCase
{
    #[Test]
    public function it_implements_graph_section_provider_interface(): void
    {
        $provider = new SovereigntyIntrospectionProvider(SovereigntyProfile::Local);

        $this->assertInstanceOf(GraphSectionProviderInterface::class, $provider);
    }

    #[Test]
    public function get_key_returns_sovereignty(): void
    {
        $provider = new SovereigntyIntrospectionProvider(SovereigntyProfile::Local);

        $this->assertSame('sovereignty', $provider->getKey());
    }

    #[Test]
    public function provide_returns_section_with_active_profile(): void
    {
        $provider = new SovereigntyIntrospectionProvider(SovereigntyProfile::SelfHosted);
        $section = $provider->provide();

        $this->assertInstanceOf(GraphSection::class, $section);
        $this->assertSame('sovereignty', $section->key);
        $this->assertSame('1.0', $section->version);
        $this->assertSame('self_hosted', $section->data['active_profile']);
    }

    #[Test]
    public function provide_returns_all_available_profiles(): void
    {
        $provider = new SovereigntyIntrospectionProvider(SovereigntyProfile::NorthOps);
        $section = $provider->provide();

        $expected = [
            ['name' => 'Local', 'value' => 'local'],
            ['name' => 'SelfHosted', 'value' => 'self_hosted'],
            ['name' => 'NorthOps', 'value' => 'northops'],
        ];

        $this->assertSame($expected, $section->data['available_profiles']);
    }

    #[Test]
    public function provide_reflects_each_active_profile_correctly(): void
    {
        foreach (SovereigntyProfile::cases() as $profile) {
            $provider = new SovereigntyIntrospectionProvider($profile);
            $section = $provider->provide();

            $this->assertSame($profile->value, $section->data['active_profile']);
        }
    }
}
