<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit;

use Waaseyaa\Config\ConfigFactory;
use Waaseyaa\Config\EnvironmentConfigFactory;
use Waaseyaa\Config\Storage\MemoryStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(EnvironmentConfigFactory::class)]
final class EnvironmentConfigFactoryTest extends TestCase
{
    private MemoryStorage $baseStorage;
    private MemoryStorage $envStorage;

    protected function setUp(): void
    {
        $this->baseStorage = new MemoryStorage();
        $this->envStorage = new MemoryStorage();
    }

    private function createFactory(
        string $environment = 'local',
        array $envVars = [],
    ): EnvironmentConfigFactory {
        $baseFactory = new ConfigFactory(
            storage: $this->baseStorage,
            eventDispatcher: new EventDispatcher(),
        );

        return new EnvironmentConfigFactory(
            inner: $baseFactory,
            envStorage: $this->envStorage,
            environment: $environment,
            envVarPrefix: 'WAASEYAA_CONFIG_',
            envVarResolver: static fn(string $name): ?string => $envVars[$name] ?? null,
        );
    }

    #[Test]
    public function get_returns_base_config_when_no_overrides(): void
    {
        $this->baseStorage->write('system.site', [
            'site_name' => 'Waaseyaa',
            'slogan' => 'The CMS',
        ]);

        $factory = $this->createFactory();
        $config = $factory->get('system.site');

        $this->assertSame('Waaseyaa', $config->get('site_name'));
        $this->assertSame('The CMS', $config->get('slogan'));
    }

    #[Test]
    public function get_applies_environment_overlay(): void
    {
        $this->baseStorage->write('system.site', [
            'site_name' => 'Waaseyaa',
            'slogan' => 'The CMS',
        ]);
        $this->envStorage->write('system.site', [
            'site_name' => 'Waaseyaa Local',
        ]);

        $factory = $this->createFactory(environment: 'local');
        $config = $factory->get('system.site');

        $this->assertSame('Waaseyaa Local', $config->get('site_name'));
        // Non-overridden keys fall back to base
        $this->assertSame('The CMS', $config->get('slogan'));
    }

    #[Test]
    public function get_applies_env_var_overrides_last(): void
    {
        $this->baseStorage->write('system.site', [
            'site_name' => 'Waaseyaa',
            'slogan' => 'The CMS',
        ]);
        $this->envStorage->write('system.site', [
            'site_name' => 'Waaseyaa Staging',
        ]);

        $factory = $this->createFactory(
            environment: 'staging',
            envVars: ['WAASEYAA_CONFIG_SYSTEM_SITE__SITE_NAME' => 'Production Waaseyaa'],
        );

        $config = $factory->get('system.site');

        // Environment variable wins over env overlay
        $this->assertSame('Production Waaseyaa', $config->get('site_name'));
    }

    #[Test]
    public function env_var_resolution_convention(): void
    {
        $this->baseStorage->write('database.settings', [
            'host' => 'localhost',
            'port' => 3306,
        ]);

        // WAASEYAA_CONFIG_DATABASE_SETTINGS__HOST maps to database.settings -> host
        $factory = $this->createFactory(
            envVars: ['WAASEYAA_CONFIG_DATABASE_SETTINGS__HOST' => 'db.production.com'],
        );

        $config = $factory->get('database.settings');

        $this->assertSame('db.production.com', $config->get('host'));
        $this->assertSame(3306, $config->get('port'));
    }

    #[Test]
    public function get_editable_delegates_to_inner(): void
    {
        $this->baseStorage->write('system.site', ['site_name' => 'Waaseyaa']);

        $factory = $this->createFactory();
        $editable = $factory->getEditable('system.site');
        $editable->set('site_name', 'Changed')->save();

        // Verify the base storage was updated
        $data = $this->baseStorage->read('system.site');
        $this->assertSame('Changed', $data['site_name']);
    }

    #[Test]
    public function load_multiple_applies_overrides_to_all(): void
    {
        $this->baseStorage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->baseStorage->write('system.mail', ['transport' => 'sendmail']);
        $this->envStorage->write('system.mail', ['transport' => 'smtp']);

        $factory = $this->createFactory();
        $configs = $factory->loadMultiple(['system.site', 'system.mail']);

        $this->assertSame('Waaseyaa', $configs['system.site']->get('site_name'));
        $this->assertSame('smtp', $configs['system.mail']->get('transport'));
    }

    #[Test]
    public function rename_delegates_to_inner(): void
    {
        $this->baseStorage->write('system.old', ['key' => 'val']);

        $factory = $this->createFactory();
        $factory->rename('system.old', 'system.new');

        $this->assertFalse($this->baseStorage->exists('system.old'));
        $this->assertTrue($this->baseStorage->exists('system.new'));
    }

    #[Test]
    public function list_all_delegates_to_inner(): void
    {
        $this->baseStorage->write('system.site', []);
        $this->baseStorage->write('system.mail', []);
        $this->baseStorage->write('user.settings', []);

        $factory = $this->createFactory();

        $this->assertSame(['system.mail', 'system.site'], $factory->listAll('system.'));
    }

    #[Test]
    public function get_environment_returns_current_environment(): void
    {
        $factory = $this->createFactory(environment: 'staging');

        $this->assertSame('staging', $factory->getEnvironment());
    }

    #[Test]
    public function missing_base_config_returns_new_config(): void
    {
        $factory = $this->createFactory();
        $config = $factory->get('nonexistent');

        $this->assertTrue($config->isNew());
    }

    #[Test]
    public function env_overlay_only_applied_when_env_storage_has_data(): void
    {
        $this->baseStorage->write('system.site', [
            'site_name' => 'Waaseyaa',
            'slogan' => 'The CMS',
        ]);
        // envStorage has nothing for system.site

        $factory = $this->createFactory(environment: 'production');
        $config = $factory->get('system.site');

        $this->assertSame('Waaseyaa', $config->get('site_name'));
        $this->assertSame('The CMS', $config->get('slogan'));
    }

    #[Test]
    public function nested_env_var_override(): void
    {
        $this->baseStorage->write('database.settings', [
            'connection' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        // Nested key: database.settings -> connection.host
        // Convention: WAASEYAA_CONFIG_{CONFIG_NAME}__{DOT_PATH_WITH_UNDERSCORES}
        $factory = $this->createFactory(
            envVars: ['WAASEYAA_CONFIG_DATABASE_SETTINGS__CONNECTION__HOST' => 'db.prod.com'],
        );

        $config = $factory->get('database.settings');

        // The nested value should be overridden
        $data = $config->getRawData();
        $this->assertSame('db.prod.com', $data['connection']['host']);
        $this->assertSame(3306, $data['connection']['port']);
    }
}
