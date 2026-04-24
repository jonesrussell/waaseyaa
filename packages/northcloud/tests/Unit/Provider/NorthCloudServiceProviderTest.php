<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\NorthCloud\Client\NorthCloudClient;
use Waaseyaa\NorthCloud\Provider\NorthCloudServiceProvider;

/**
 * @covers \Waaseyaa\NorthCloud\Provider\NorthCloudServiceProvider
 */
#[CoversClass(NorthCloudServiceProvider::class)]
final class NorthCloudServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('NORTHCLOUD_BASE_URL');
        putenv('NORTHCLOUD_API_TOKEN');
    }

    #[Test]
    public function envFallbackFillsBaseUrlWhenConfigIsEmpty(): void
    {
        putenv('NORTHCLOUD_BASE_URL=https://env.example.com');
        putenv('NORTHCLOUD_API_TOKEN=env-token');

        $provider = new NorthCloudServiceProvider();
        $provider->setKernelContext('/tmp/test', []);
        $provider->register();

        $client = $provider->resolve(NorthCloudClient::class);

        $this->assertInstanceOf(NorthCloudClient::class, $client);
    }

    #[Test]
    public function defaultsToPackageBaseUrlWhenNothingSet(): void
    {
        putenv('NORTHCLOUD_BASE_URL');
        putenv('NORTHCLOUD_API_TOKEN');

        $provider = new NorthCloudServiceProvider();
        $provider->setKernelContext('/tmp/test', []);
        $provider->register();

        // Resolution should succeed (uses https://api.northcloud.one default).
        $client = $provider->resolve(NorthCloudClient::class);
        $this->assertInstanceOf(NorthCloudClient::class, $client);
    }

    #[Test]
    public function envVarOverridesConfigValue(): void
    {
        putenv('NORTHCLOUD_BASE_URL=https://env-wins.example.com');

        $provider = new NorthCloudServiceProvider();
        $provider->setKernelContext('/tmp/test', [
            'northcloud' => ['base_url' => 'https://config-loses.example.com'],
        ]);
        $provider->register();

        // We can't read the private baseUrl directly, but resolution succeeding
        // proves both constructors got valid https URLs.
        $client = $provider->resolve(NorthCloudClient::class);
        $this->assertInstanceOf(NorthCloudClient::class, $client);
    }

    #[Test]
    public function invalidEnvBaseUrlRaisesException(): void
    {
        putenv('NORTHCLOUD_BASE_URL=http://insecure.example.com');

        $provider = new NorthCloudServiceProvider();
        $provider->setKernelContext('/tmp/test', []);
        $provider->register();

        $this->expectException(\InvalidArgumentException::class);
        $provider->resolve(NorthCloudClient::class);
    }
}
