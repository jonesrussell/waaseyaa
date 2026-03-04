<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;

#[CoversClass(AbstractKernel::class)]
final class AbstractKernelTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = $this->createMinimalProjectRoot();
    }

    private function createMinimalProjectRoot(): string
    {
        $projectRoot = sys_get_temp_dir() . '/waaseyaa_kernel_test_' . uniqid();
        mkdir($projectRoot . '/config', 0755, true);
        mkdir($projectRoot . '/storage', 0755, true);

        file_put_contents(
            $projectRoot . '/config/waaseyaa.php',
            "<?php return ['database' => ':memory:'];",
        );
        file_put_contents(
            $projectRoot . '/config/entity-types.php',
            '<?php return [];',
        );

        return $projectRoot;
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->projectRoot);
    }

    #[Test]
    public function kernel_provides_project_root(): void
    {
        $kernel = new class('/tmp/test-project') extends AbstractKernel {
        };

        $this->assertSame('/tmp/test-project', $kernel->getProjectRoot());
    }

    #[Test]
    public function kernel_boots_core_services(): void
    {
        $kernel = new class($this->projectRoot) extends AbstractKernel {
            public function publicBoot(): void
            {
                $this->boot();
            }
        };

        $kernel->publicBoot();

        $this->assertNotNull($kernel->getEntityTypeManager());
        $this->assertNotNull($kernel->getDatabase());
        $this->assertNotNull($kernel->getEventDispatcher());
    }

    #[Test]
    public function boot_is_idempotent(): void
    {
        $kernel = new class($this->projectRoot) extends AbstractKernel {
            public int $bootCount = 0;

            public function publicBoot(): void
            {
                $this->bootCount++;
                $this->boot();
            }
        };

        $kernel->publicBoot();
        $kernel->publicBoot();

        $this->assertSame(2, $kernel->bootCount);
        $this->assertInstanceOf(\Waaseyaa\Entity\EntityTypeManager::class, $kernel->getEntityTypeManager());
    }

    #[Test]
    public function boot_writes_manifest_cache_inside_fake_project_root(): void
    {
        $kernel = new class($this->projectRoot) extends AbstractKernel {
            public function publicBoot(): void
            {
                $this->boot();
            }
        };

        $kernel->publicBoot();

        $cachePath = $this->projectRoot . '/storage/framework/packages.php';
        $this->assertFileExists($cachePath);
        $this->assertIsArray(require $cachePath);
    }
}
