<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase9;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Api\Tests\Fixtures\InMemoryEntityStorage;
use Waaseyaa\Cache\CacheFactory;
use Waaseyaa\CLI\Command\CacheClearCommand;
use Waaseyaa\CLI\Command\ConfigExportCommand;
use Waaseyaa\CLI\Command\ConfigImportCommand;
use Waaseyaa\CLI\Command\InstallCommand;
use Waaseyaa\CLI\Command\UserCreateCommand;
use Waaseyaa\CLI\Command\UserRoleCommand;
use Waaseyaa\CLI\Handler\EntityCreateHandler;
use Waaseyaa\CLI\Handler\EntityListHandler;
use Waaseyaa\CLI\Provider\EntityTypeServiceProvider;
use Waaseyaa\CLI\Testing\CliTester;
use Waaseyaa\Config\ConfigManager;
use Waaseyaa\Config\Storage\MemoryStorage;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Integration tests for CLI commands with real (in-memory) Waaseyaa services.
 *
 * Exercises: waaseyaa/cli commands with waaseyaa/cache (CacheFactory, MemoryBackend),
 * waaseyaa/config (ConfigManager, MemoryStorage), and waaseyaa/entity
 * (EntityTypeManager) using in-memory storage.
 */
#[CoversNothing]
final class CliCommandIntegrationTest extends TestCase
{
    private CacheFactory $cacheFactory;
    private ConfigManager $configManager;
    private MemoryStorage $activeStorage;
    private MemoryStorage $syncStorage;
    private EntityTypeManager $entityTypeManager;
    private InMemoryEntityStorage $articleStorage;
    private InMemoryEntityStorage $userStorage;

    protected function setUp(): void
    {
        // Cache factory with default MemoryBackend.
        $this->cacheFactory = new CacheFactory();

        // Config manager with two MemoryStorage instances (active + sync).
        $this->activeStorage = new MemoryStorage();
        $this->syncStorage = new MemoryStorage();
        $this->configManager = new ConfigManager(
            $this->activeStorage,
            $this->syncStorage,
            new EventDispatcher(),
        );

        // Entity type manager with in-memory storage for articles and users.
        $this->articleStorage = new InMemoryEntityStorage('article');
        $this->userStorage = new InMemoryEntityStorage('user');

        $articleStorage = $this->articleStorage;
        $userStorage = $this->userStorage;

        $this->entityTypeManager = new EntityTypeManager(
            new EventDispatcher(),
            function ($definition) use ($articleStorage, $userStorage) {
                return match ($definition->id()) {
                    'article' => $articleStorage,
                    'user' => $userStorage,
                    default => throw new \RuntimeException("Unknown entity type: {$definition->id()}"),
                };
            },
        );

        $this->entityTypeManager->registerEntityType(new EntityType(
            id: 'article',
            label: 'Article',
            class: \Waaseyaa\Api\Tests\Fixtures\ArticleContentTestEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'title',
                'bundle' => 'type',
            ],
        ));

        $this->entityTypeManager->registerEntityType(new EntityType(
            id: 'user',
            label: 'User',
            class: \Waaseyaa\Api\Tests\Fixtures\UserNameContentTestEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'name',
            ],
        ));
    }

    #[Test]
    public function testCacheClearCommandClearsAllBins(): void
    {
        // Populate caches in multiple bins.
        $defaultBin = $this->cacheFactory->get('default');
        $renderBin = $this->cacheFactory->get('render');
        $discoveryBin = $this->cacheFactory->get('discovery');
        $configBin = $this->cacheFactory->get('config');

        $defaultBin->set('key1', 'value1');
        $renderBin->set('key2', 'value2');
        $discoveryBin->set('key3', 'value3');
        $configBin->set('key4', 'value4');

        // Verify caches are populated.
        $this->assertNotFalse($defaultBin->get('key1'));
        $this->assertNotFalse($renderBin->get('key2'));
        $this->assertNotFalse($discoveryBin->get('key3'));
        $this->assertNotFalse($configBin->get('key4'));

        // Run cache:clear command.
        $command = new CacheClearCommand($this->cacheFactory);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('All cache bins cleared', $tester->getDisplay());

        // Verify all caches are empty.
        $this->assertFalse($defaultBin->get('key1'));
        $this->assertFalse($renderBin->get('key2'));
        $this->assertFalse($discoveryBin->get('key3'));
        $this->assertFalse($configBin->get('key4'));
    }

    #[Test]
    public function testConfigExportAndImport(): void
    {
        // Write config to active storage.
        $this->activeStorage->write('system.site', [
            'name' => 'My Waaseyaa Site',
            'slogan' => 'Built with Waaseyaa',
        ]);
        $this->activeStorage->write('system.theme', [
            'default' => 'stark',
        ]);

        // Export via command.
        $exportCommand = new ConfigExportCommand($this->configManager);
        $exportTester = new CommandTester($exportCommand);
        $exportTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exportTester->getStatusCode());
        $this->assertStringContainsString('Configuration exported. Active storage contains 2 items', $exportTester->getDisplay());

        // Verify sync storage has the config.
        $this->assertSame(['name' => 'My Waaseyaa Site', 'slogan' => 'Built with Waaseyaa'], $this->syncStorage->read('system.site'));
        $this->assertSame(['default' => 'stark'], $this->syncStorage->read('system.theme'));

        // Modify active storage (simulate drift).
        $this->activeStorage->write('system.site', [
            'name' => 'Modified Site',
            'slogan' => 'Changed slogan',
        ]);

        // Verify active is now different.
        $activeData = $this->activeStorage->read('system.site');
        $this->assertSame('Modified Site', $activeData['name']);

        // Import via command (should restore from sync).
        $importCommand = new ConfigImportCommand($this->configManager);
        $importTester = new CommandTester($importCommand);
        $importTester->execute([]);

        $this->assertSame(Command::SUCCESS, $importTester->getStatusCode());
        $this->assertStringContainsString('Configuration imported successfully', $importTester->getDisplay());

        // Verify active matches sync after import.
        $restored = $this->activeStorage->read('system.site');
        $this->assertSame('My Waaseyaa Site', $restored['name']);
        $this->assertSame('Built with Waaseyaa', $restored['slogan']);
    }

    #[Test]
    public function testEntityCreateAndList(): void
    {
        $manager = $this->entityTypeManager;
        $container = new class ($manager) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly \Waaseyaa\Entity\EntityTypeManagerInterface $manager) {}

            public function get(string $id): mixed
            {
                return match ($id) {
                    EntityCreateHandler::class => new EntityCreateHandler($this->manager),
                    EntityListHandler::class   => new EntityListHandler($this->manager),
                    default => throw new \RuntimeException("Container::get({$id}) unexpected"),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [EntityCreateHandler::class, EntityListHandler::class], true);
            }
        };

        $provider = new EntityTypeServiceProvider();
        $definitions = [];
        foreach ($provider->nativeCommands() as $cmd) {
            $definitions[$cmd->name] = $cmd;
        }

        // Create entities via entity:create handler.
        $tester1 = CliTester::for($definitions['entity:create'], $container);
        $tester1->executeMap([
            'entity_type' => 'article',
            '--values' => json_encode(['title' => 'First Article', 'type' => 'blog']),
        ]);
        $this->assertSame(0, $tester1->getExitCode());
        $this->assertStringContainsString('Created article entity with ID:', $tester1->getStdout());

        $tester2 = CliTester::for($definitions['entity:create'], $container);
        $tester2->executeMap([
            'entity_type' => 'article',
            '--values' => json_encode(['title' => 'Second Article', 'type' => 'news']),
        ]);
        $this->assertSame(0, $tester2->getExitCode());

        // List entities via entity:list handler.
        $listTester = CliTester::for($definitions['entity:list'], $container);
        $listTester->executeMap(['entity_type' => 'article']);

        $this->assertSame(0, $listTester->getExitCode());

        $output = $listTester->getStdout();
        $this->assertStringContainsString('First Article', $output);
        $this->assertStringContainsString('Second Article', $output);
    }

    #[Test]
    public function testUserCreateCommand(): void
    {
        $command = new UserCreateCommand($this->entityTypeManager);
        $tester = new CommandTester($command);
        $tester->execute([
            'username' => 'testuser',
            '--email' => 'test@example.com',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Created user "testuser"', $tester->getDisplay());

        // Verify entity was created in storage.
        $user = $this->userStorage->load(1);
        $this->assertNotNull($user);
        $this->assertSame('testuser', $user->get('name'));
        $this->assertSame('test@example.com', $user->get('mail'));
    }

    #[Test]
    public function testInstallCommand(): void
    {
        $command = new InstallCommand($this->entityTypeManager, $this->configManager);
        $tester = new CommandTester($command);
        $tester->execute(['--site-name' => 'Test Waaseyaa']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Waaseyaa "Test Waaseyaa" installed successfully', $tester->getDisplay());

        // Verify initial config was written.
        $siteConfig = $this->activeStorage->read('system.site');
        $this->assertIsArray($siteConfig);
        $this->assertSame('Test Waaseyaa', $siteConfig['name']);
        $this->assertSame('admin@example.com', $siteConfig['mail']);

        // Verify admin user was created.
        $admin = $this->userStorage->load(1);
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->get('name'));
        $this->assertSame('admin@example.com', $admin->get('email'));
        $this->assertSame(['administrator'], $admin->get('roles'));
    }

    #[Test]
    public function testUserRoleAddAndRemove(): void
    {
        // First create a user.
        $createCommand = new UserCreateCommand($this->entityTypeManager);
        $createTester = new CommandTester($createCommand);
        $createTester->execute(['username' => 'editor']);
        $this->assertSame(Command::SUCCESS, $createTester->getStatusCode());

        // Add a role.
        $roleCommand = new UserRoleCommand($this->entityTypeManager);
        $addTester = new CommandTester($roleCommand);
        $addTester->execute([
            'user_id' => '1',
            'role' => 'editor',
        ]);
        $this->assertSame(Command::SUCCESS, $addTester->getStatusCode());
        $this->assertStringContainsString('Added role "editor" to user 1', $addTester->getDisplay());

        // Verify role is present.
        $user = $this->userStorage->load(1);
        $this->assertNotNull($user);
        $roles = $user->get('roles');
        $this->assertContains('editor', $roles);

        // Remove the role.
        $removeTester = new CommandTester($roleCommand);
        $removeTester->execute([
            'user_id' => '1',
            'role' => 'editor',
            '--remove' => true,
        ]);
        $this->assertSame(Command::SUCCESS, $removeTester->getStatusCode());
        $this->assertStringContainsString('Removed role "editor" from user 1', $removeTester->getDisplay());

        // Verify role is gone.
        $user = $this->userStorage->load(1);
        $this->assertNotNull($user);
        $roles = $user->get('roles');
        $this->assertNotContains('editor', $roles);
    }
}
