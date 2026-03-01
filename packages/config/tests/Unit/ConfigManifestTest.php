<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Tests\Unit;

use Waaseyaa\Config\ConfigManifest;
use Waaseyaa\Config\Storage\MemoryStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigManifest::class)]
final class ConfigManifestTest extends TestCase
{
    private MemoryStorage $storage;
    private ConfigManifest $manifest;

    protected function setUp(): void
    {
        $this->storage = new MemoryStorage();
        $this->manifest = new ConfigManifest(storage: $this->storage);
    }

    #[Test]
    public function generate_creates_manifest_with_version_and_checksum(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->storage->write('user.settings', ['registration' => 'admin_only']);

        $manifest = $this->manifest->generate();

        $this->assertArrayHasKey('version', $manifest);
        $this->assertArrayHasKey('checksum', $manifest);
        $this->assertArrayHasKey('configs', $manifest);
        $this->assertArrayHasKey('generated_at', $manifest);
        $this->assertIsString($manifest['version']);
        $this->assertStringStartsWith('sha256:', $manifest['checksum']);
    }

    #[Test]
    public function generate_includes_all_config_names(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->storage->write('system.mail', ['transport' => 'smtp']);
        $this->storage->write('user.settings', ['registration' => 'open']);

        $manifest = $this->manifest->generate();

        $this->assertArrayHasKey('system.site', $manifest['configs']);
        $this->assertArrayHasKey('system.mail', $manifest['configs']);
        $this->assertArrayHasKey('user.settings', $manifest['configs']);
    }

    #[Test]
    public function generate_config_entries_have_checksums(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);

        $manifest = $this->manifest->generate();

        $this->assertArrayHasKey('checksum', $manifest['configs']['system.site']);
        $this->assertStringStartsWith('sha256:', $manifest['configs']['system.site']['checksum']);
    }

    #[Test]
    public function generate_and_save_persists_manifest_to_storage(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);

        $this->manifest->generateAndSave();

        $manifestData = $this->storage->read(ConfigManifest::MANIFEST_NAME);
        $this->assertNotFalse($manifestData);
        $this->assertArrayHasKey('version', $manifestData);
        $this->assertArrayHasKey('checksum', $manifestData);
    }

    #[Test]
    public function verify_returns_true_for_unchanged_config(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->storage->write('user.settings', ['registration' => 'open']);

        $this->manifest->generateAndSave();

        $result = $this->manifest->verify();

        $this->assertTrue($result->isValid);
        $this->assertSame([], $result->modifiedConfigs);
        $this->assertSame([], $result->addedConfigs);
        $this->assertSame([], $result->removedConfigs);
    }

    #[Test]
    public function verify_detects_modified_config(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->manifest->generateAndSave();

        // Modify the config after manifest was generated
        $this->storage->write('system.site', ['site_name' => 'Changed']);

        $result = $this->manifest->verify();

        $this->assertFalse($result->isValid);
        $this->assertContains('system.site', $result->modifiedConfigs);
    }

    #[Test]
    public function verify_detects_added_config(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->manifest->generateAndSave();

        // Add a new config after manifest was generated
        $this->storage->write('system.mail', ['transport' => 'smtp']);

        $result = $this->manifest->verify();

        $this->assertFalse($result->isValid);
        $this->assertContains('system.mail', $result->addedConfigs);
    }

    #[Test]
    public function verify_detects_removed_config(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->storage->write('user.settings', ['registration' => 'open']);
        $this->manifest->generateAndSave();

        // Remove a config after manifest was generated
        $this->storage->delete('user.settings');

        $result = $this->manifest->verify();

        $this->assertFalse($result->isValid);
        $this->assertContains('user.settings', $result->removedConfigs);
    }

    #[Test]
    public function verify_returns_invalid_when_no_manifest_exists(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);

        $result = $this->manifest->verify();

        $this->assertFalse($result->isValid);
    }

    #[Test]
    public function get_version_returns_current_manifest_version(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->manifest->generateAndSave();

        $version = $this->manifest->getVersion();

        $this->assertNotNull($version);
        $this->assertMatchesRegularExpression('/^\d{4}\.\d{2}\.\d{2}\.\d{3}$/', $version);
    }

    #[Test]
    public function get_version_returns_null_when_no_manifest(): void
    {
        $this->assertNull($this->manifest->getVersion());
    }

    #[Test]
    public function manifest_excludes_itself_from_config_list(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->manifest->generateAndSave();

        $manifestData = $this->storage->read(ConfigManifest::MANIFEST_NAME);
        $this->assertNotFalse($manifestData);
        $this->assertArrayNotHasKey(ConfigManifest::MANIFEST_NAME, $manifestData['configs']);
    }

    #[Test]
    public function checksum_changes_when_config_changes(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $manifest1 = $this->manifest->generate();

        $this->storage->write('system.site', ['site_name' => 'Changed']);
        $manifest2 = $this->manifest->generate();

        $this->assertNotSame($manifest1['checksum'], $manifest2['checksum']);
    }

    #[Test]
    public function checksum_is_deterministic(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);
        $this->storage->write('user.settings', ['registration' => 'open']);

        $manifest1 = $this->manifest->generate();
        $manifest2 = $this->manifest->generate();

        $this->assertSame($manifest1['checksum'], $manifest2['checksum']);
        $this->assertSame($manifest1['configs'], $manifest2['configs']);
    }

    #[Test]
    public function compare_versions_detects_backward_version(): void
    {
        $this->assertTrue(ConfigManifest::isBackwardVersion('2026.03.12.003', '2026.03.15.001'));
        $this->assertFalse(ConfigManifest::isBackwardVersion('2026.03.15.001', '2026.03.12.003'));
        $this->assertFalse(ConfigManifest::isBackwardVersion('2026.03.15.001', '2026.03.15.001'));
    }

    #[Test]
    public function generate_with_package_versions(): void
    {
        $this->storage->write('system.site', ['site_name' => 'Waaseyaa']);

        $manifest = $this->manifest->generate(packageVersions: [
            'waaseyaa/user' => '2026.03.15.001',
            'waaseyaa/node' => '2026.03.12.003',
        ]);

        $this->assertArrayHasKey('packages', $manifest);
        $this->assertSame('2026.03.15.001', $manifest['packages']['waaseyaa/user']);
        $this->assertSame('2026.03.12.003', $manifest['packages']['waaseyaa/node']);
    }
}
