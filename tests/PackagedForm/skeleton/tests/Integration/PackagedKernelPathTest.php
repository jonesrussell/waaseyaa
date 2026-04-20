<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;

final class PackagedKernelPathTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->resetStorageDirectory();
    }

    protected function tearDown(): void
    {
        $registryProperty = new \ReflectionProperty(ContentEntityBase::class, 'fieldRegistry');
        $registryProperty->setValue(null, null);

        $this->removeDir($this->projectRoot . '/storage');
    }

    #[Test]
    public function publishedPackagesMaterializeAndUseBundleSubtableViaKernelPath(): void
    {
        $kernel = new class ($this->projectRoot) extends AbstractKernel {
            public function publicBoot(): void
            {
                $this->boot();
            }
        };
        $kernel->publicBoot();

        $entityTypeManager = $kernel->getEntityTypeManager();

        $groupTypeStorage = $entityTypeManager->getStorage('group_type');
        $groupType = $groupTypeStorage->create([
            'id' => 'packaged_fixture',
            'label' => 'Packaged fixture',
            'description' => 'Bundle for packaged-form CI.',
        ]);
        $groupTypeStorage->save($groupType);

        $groupStorage = $entityTypeManager->getStorage('group');

        $database = $kernel->getDatabase();
        self::assertInstanceOf(DBALDatabase::class, $database);
        $connection = $database->getConnection();

        $subtableExists = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :name",
            ['name' => 'group__packaged_fixture'],
        );
        self::assertSame(
            1,
            $subtableExists,
            'Packaged-form kernel path must materialize group__packaged_fixture when the consumer provider registers bundle fields.',
        );

        $group = $groupStorage->create([
            'uuid' => 'packaged-form-group-uuid',
            'type' => 'packaged_fixture',
            'name' => 'Packaged fixture group',
            'langcode' => 'en',
            'fixture_code' => 'PKG-001',
        ]);
        $groupStorage->save($group);

        $bundleRow = $connection->fetchAssociative(
            'SELECT fixture_code FROM group__packaged_fixture WHERE gid = :gid',
            ['gid' => $group->id()],
        );
        self::assertNotFalse($bundleRow);
        self::assertSame('PKG-001', $bundleRow['fixture_code']);

        $loaded = $groupStorage->load($group->id());
        self::assertNotNull($loaded);
        self::assertSame('Packaged fixture group', $loaded->label());
        self::assertSame('PKG-001', $loaded->get('fixture_code'));
    }

    private function resetStorageDirectory(): void
    {
        $storageRoot = $this->projectRoot . '/storage';
        $frameworkRoot = $storageRoot . '/framework';

        $this->removeDir($storageRoot);

        mkdir($frameworkRoot, 0755, true);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
