<?php

declare(strict_types=1);

namespace Waaseyaa\Testing\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SkeletonLayoutTest extends TestCase
{
    #[Test]
    public function skeletonPhpUnitDirectoriesExist(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $config = simplexml_load_file($repoRoot . '/skeleton/phpunit.xml.dist');

        self::assertInstanceOf(\SimpleXMLElement::class, $config);

        $directories = $config->xpath('//testsuite/directory');

        self::assertIsArray($directories);
        self::assertNotEmpty($directories);

        foreach ($directories as $directory) {
            $path = $repoRoot . '/skeleton/' . trim((string) $directory);
            self::assertDirectoryExists($path, sprintf('Skeleton PHPUnit path missing: %s', $path));
        }
    }

    #[Test]
    public function skeletonDevScriptIsWiredAndValid(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $devScript = $repoRoot . '/skeleton/bin/dev.sh';
        $composerJson = $repoRoot . '/skeleton/composer.json';

        self::assertFileExists($devScript);
        self::assertFileExists($composerJson);

        $composer = json_decode((string) file_get_contents($composerJson), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);

        $scripts = $composer['scripts'] ?? null;
        self::assertIsArray($scripts);

        $dev = $scripts['dev'] ?? null;
        self::assertIsArray($dev);
        self::assertContains('Composer\\Config::disableProcessTimeout', $dev);
        self::assertContains('bash bin/dev.sh', $dev);

        exec(sprintf('bash -n %s 2>&1', escapeshellarg($devScript)), $output, $exitCode);
        self::assertSame(0, $exitCode, implode("\n", $output));
    }

    #[Test]
    public function skeletonIncludesEssentialFirstBootFiles(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $requiredFiles = [
            '/skeleton/.env.example',
            '/skeleton/bin/waaseyaa',
            '/skeleton/bin/post-create-setup.php',
            '/skeleton/bin/dev.sh',
            '/skeleton/public/index.php',
            '/skeleton/config/waaseyaa.php',
            '/skeleton/composer.json',
        ];

        foreach ($requiredFiles as $relativePath) {
            self::assertFileExists(
                $repoRoot . $relativePath,
                sprintf('Missing first-boot skeleton artifact: %s', $relativePath),
            );
        }
    }
}
