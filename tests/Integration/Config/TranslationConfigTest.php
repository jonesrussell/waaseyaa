<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the skeleton-default translation configuration block in
 * `skeleton/config/waaseyaa.php`. (M-006 / FR-037, FR-041, C-004)
 *
 * The skeleton config is a plain PHP `return [...]` file; loading it via
 * `require` lets us assert the runtime shape without booting a kernel.
 */
#[CoversNothing]
final class TranslationConfigTest extends TestCase
{
    private const string ENV_VAR = 'WAASEYAA_TRANSLATION_READ_ACTIVE_LANGUAGE';

    private const string SKELETON_CONFIG = __DIR__ . '/../../../skeleton/config/waaseyaa.php';

    /** @var false|string */
    private $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = getenv(self::ENV_VAR);
        putenv(self::ENV_VAR);
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === false) {
            putenv(self::ENV_VAR);
        } else {
            putenv(self::ENV_VAR . '=' . $this->originalEnv);
        }
        parent::tearDown();
    }

    #[Test]
    public function defaultConfigDisablesActiveLanguageReads(): void
    {
        $config = $this->loadSkeletonConfig();

        self::assertArrayHasKey('translation', $config, 'skeleton config must expose a translation block');
        self::assertIsArray($config['translation']);
        self::assertArrayHasKey('read_active_language', $config['translation']);
        self::assertFalse(
            $config['translation']['read_active_language'],
            'read_active_language must default to false (opt-in)',
        );
    }

    #[Test]
    public function envVarTrueEnablesActiveLanguageReads(): void
    {
        putenv(self::ENV_VAR . '=true');

        $config = $this->loadSkeletonConfig();

        self::assertTrue(
            $config['translation']['read_active_language'],
            'WAASEYAA_TRANSLATION_READ_ACTIVE_LANGUAGE=true must flip the flag on',
        );
    }

    #[Test]
    public function fallbackChainIsNullByDefault(): void
    {
        $config = $this->loadSkeletonConfig();

        self::assertArrayHasKey('fallback_chain', $config['translation']);
        self::assertNull(
            $config['translation']['fallback_chain'],
            'fallback_chain must default to null (defer to i18n language list)',
        );
    }

    /** @return array<string, mixed> */
    private function loadSkeletonConfig(): array
    {
        $config_path = realpath(self::SKELETON_CONFIG);
        self::assertNotFalse($config_path, 'skeleton/config/waaseyaa.php must exist');

        /** @var array<string, mixed> $config */
        $config = require $config_path;

        return $config;
    }
}
