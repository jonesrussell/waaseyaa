<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Command\Make;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\Make\MakePublicCommand;

#[CoversClass(MakePublicCommand::class)]
final class MakePublicCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/waaseyaa-make-public-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function it_creates_public_index_php(): void
    {
        $command = new MakePublicCommand($this->tempDir);
        $tester = new CommandTester($command);

        $exit = $tester->execute([]);

        self::assertSame(0, $exit);
        $target = $this->tempDir . '/public/index.php';
        self::assertFileExists($target);
        $contents = (string) file_get_contents($target);
        self::assertNotSame('', $contents);
        self::assertStringContainsString('HttpKernel', $contents);
        self::assertStringContainsString('handle()', $contents);
        self::assertStringContainsString("->loadEnv(\$projectRoot . '/.env', 'APP_ENV', 'production');", $contents);
    }

    #[Test]
    public function it_refuses_to_overwrite_existing_file(): void
    {
        mkdir($this->tempDir . '/public', 0755, true);
        $target = $this->tempDir . '/public/index.php';
        file_put_contents($target, '<?php // existing');

        $command = new MakePublicCommand($this->tempDir);
        $tester = new CommandTester($command);

        $exit = $tester->execute([]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('already exists', $tester->getDisplay());
        self::assertSame('<?php // existing', file_get_contents($target));
    }

    #[Test]
    public function it_overwrites_with_force_flag(): void
    {
        mkdir($this->tempDir . '/public', 0755, true);
        $target = $this->tempDir . '/public/index.php';
        file_put_contents($target, '<?php // old');

        $command = new MakePublicCommand($this->tempDir);
        $tester = new CommandTester($command);

        $exit = $tester->execute(['--force' => true]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('HttpKernel', (string) file_get_contents($target));
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
