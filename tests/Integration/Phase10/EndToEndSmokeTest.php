<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase10;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\AI\Schema\EntityJsonSchemaGenerator;
use Waaseyaa\AI\Schema\Mcp\McpToolDefinition;
use Waaseyaa\AI\Schema\Mcp\McpToolGenerator;
use Waaseyaa\AI\Schema\SchemaRegistry;
use Waaseyaa\Api\OpenApi\OpenApiGenerator;
use Waaseyaa\Api\Tests\Fixtures\InMemoryEntityStorage;
use Waaseyaa\Cache\CacheFactory;
use Waaseyaa\CLI\Command\CacheClearCommand;
use Waaseyaa\CLI\Command\ConfigExportCommand;
use Waaseyaa\CLI\Command\ConfigImportCommand;
use Waaseyaa\CLI\Command\InstallCommand;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\EntityCreateHandler;
use Waaseyaa\CLI\Handler\EntityListHandler;
use Waaseyaa\CLI\Handler\MigrateDefaultsHandler;
use Waaseyaa\CLI\Handler\TypeDisableHandler;
use Waaseyaa\CLI\Handler\TypeEnableHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\CLI\Provider\EntityTypeServiceProvider;
use Waaseyaa\CLI\Testing\CliTester;
use Waaseyaa\Config\ConfigManager;
use Waaseyaa\Config\Storage\MemoryStorage;
use Waaseyaa\Entity\Audit\EntityAuditLogger;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\ComponentMetadata;
use Waaseyaa\SSR\ComponentRegistry;
use Waaseyaa\SSR\ComponentRenderer;
use Waaseyaa\SSR\SsrController;

/**
 * End-to-end smoke tests exercising the complete Waaseyaa stack.
 *
 * This is the final integration test suite (Phase 10) that validates the full
 * CMS lifecycle across all architectural layers: cache, config, entity, CLI,
 * API (OpenAPI), AI (MCP tools), and SSR rendering. All services use in-memory
 * storage — no database or external dependencies required.
 */
#[CoversNothing]
final class EndToEndSmokeTest extends TestCase
{
    /**
     * The big lifecycle test: install -> configure -> create content -> query -> cache -> config export/import.
     *
     * Exercises layers 0-3 + CLI (Layer 6) in a single end-to-end flow:
     * - EntityTypeManager with in-memory storage (Layer 1)
     * - ConfigManager with MemoryStorage (Layer 1)
     * - CacheFactory with MemoryBackend (Layer 0)
     * - InstallCommand, entity:create handler, entity:list handler,
     *   CacheClearCommand, ConfigExportCommand, ConfigImportCommand (Layer 6)
     */
    #[Test]
    public function testFullCmsLifecycle(): void
    {
        // --- Set up all services ---

        $cacheFactory = new CacheFactory();

        $activeStorage = new MemoryStorage();
        $syncStorage = new MemoryStorage();
        $configManager = new ConfigManager(
            $activeStorage,
            $syncStorage,
            new EventDispatcher(),
        );

        $articleStorage = new InMemoryEntityStorage('article');
        $userStorage = new InMemoryEntityStorage('user');

        $entityTypeManager = new EntityTypeManager(
            new EventDispatcher(),
            function ($definition) use ($articleStorage, $userStorage) {
                return match ($definition->id()) {
                    'article' => $articleStorage,
                    'user' => $userStorage,
                    default => throw new \RuntimeException("Unknown entity type: {$definition->id()}"),
                };
            },
        );

        $entityTypeManager->registerEntityType(new EntityType(
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

        $entityTypeManager->registerEntityType(new EntityType(
            id: 'user',
            label: 'User',
            class: \Waaseyaa\Api\Tests\Fixtures\UserNameContentTestEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'label' => 'name',
            ],
        ));

        // --- Step 1: Install Waaseyaa ---

        $installCommand = new InstallCommand($entityTypeManager, $configManager);
        $installTester = new CommandTester($installCommand);
        $installTester->execute(['--site-name' => 'Smoke Test Site']);

        $this->assertSame(Command::SUCCESS, $installTester->getStatusCode());
        $this->assertStringContainsString('Smoke Test Site', $installTester->getDisplay());

        // Verify config was written.
        $siteConfig = $activeStorage->read('system.site');
        $this->assertIsArray($siteConfig);
        $this->assertSame('Smoke Test Site', $siteConfig['name']);

        // Verify admin user was created.
        $admin = $userStorage->load(1);
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->get('name'));

        // --- Step 2: Create content via CLI ---

        $entityProvider = new EntityTypeServiceProvider();
        $entityDefinitions = [];
        foreach ($entityProvider->nativeCommands() as $cmd) {
            $entityDefinitions[$cmd->name] = $cmd;
        }
        $entityContainer = new class ($entityTypeManager) implements \Psr\Container\ContainerInterface {
            public function __construct(
                private readonly \Waaseyaa\Entity\EntityTypeManagerInterface $manager,
            ) {}

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

        $createTester = CliTester::for($entityDefinitions['entity:create'], $entityContainer);
        $createTester->executeMap([
            'entity_type' => 'article',
            '--values' => json_encode(['title' => 'First Smoke Article', 'type' => 'blog']),
        ]);
        $this->assertSame(0, $createTester->getExitCode());
        $this->assertStringContainsString('Created article entity with ID:', $createTester->getStdout());

        // --- Step 3: List content via CLI and verify ---

        $listTester = CliTester::for($entityDefinitions['entity:list'], $entityContainer);
        $listTester->executeMap(['entity_type' => 'article']);

        $this->assertSame(0, $listTester->getExitCode());
        $this->assertStringContainsString('First Smoke Article', $listTester->getStdout());

        // --- Step 4: Cache operations ---

        // Warm cache bins.
        $defaultBin = $cacheFactory->get('default');
        $renderBin = $cacheFactory->get('render');
        $defaultBin->set('page_1', 'cached_content');
        $renderBin->set('block_1', 'rendered_block');

        $this->assertNotFalse($defaultBin->get('page_1'));
        $this->assertNotFalse($renderBin->get('block_1'));

        // Clear all caches via CLI.
        $cacheClearCommand = new CacheClearCommand($cacheFactory);
        $cacheClearTester = new CommandTester($cacheClearCommand);
        $cacheClearTester->execute([]);

        $this->assertSame(Command::SUCCESS, $cacheClearTester->getStatusCode());
        $this->assertStringContainsString('cleared', $cacheClearTester->getDisplay());

        // Verify bins are empty.
        $this->assertFalse($defaultBin->get('page_1'));
        $this->assertFalse($renderBin->get('block_1'));

        // --- Step 5: Config export, modify active, import to restore ---

        // Export current config to sync.
        $exportCommand = new ConfigExportCommand($configManager);
        $exportTester = new CommandTester($exportCommand);
        $exportTester->execute([]);
        $this->assertSame(Command::SUCCESS, $exportTester->getStatusCode());

        // Verify sync has the config.
        $syncSiteConfig = $syncStorage->read('system.site');
        $this->assertSame('Smoke Test Site', $syncSiteConfig['name']);

        // Simulate config drift by modifying active storage.
        $activeStorage->write('system.site', [
            'name' => 'Drifted Site Name',
            'mail' => 'drifted@example.com',
        ]);
        $drifted = $activeStorage->read('system.site');
        $this->assertSame('Drifted Site Name', $drifted['name']);

        // Import from sync to restore the original.
        $importCommand = new ConfigImportCommand($configManager);
        $importTester = new CommandTester($importCommand);
        $importTester->execute([]);

        $this->assertSame(Command::SUCCESS, $importTester->getStatusCode());

        // Verify active config was restored.
        $restored = $activeStorage->read('system.site');
        $this->assertSame('Smoke Test Site', $restored['name']);
    }

    /**
     * Verifies that OpenAPI spec generation produces correct paths and methods
     * for registered entity types.
     *
     * Exercises: waaseyaa/api (OpenApiGenerator, SchemaBuilder) with
     * waaseyaa/entity (EntityTypeManager, EntityType).
     */
    #[Test]
    public function testOpenApiSpecGeneration(): void
    {
        $entityTypeManager = new EntityTypeManager(
            new EventDispatcher(),
        );

        $entityTypeManager->registerEntityType(new EntityType(
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

        $generator = new OpenApiGenerator($entityTypeManager);
        $spec = $generator->generate();

        // Verify top-level structure.
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);

        // Verify paths exist for the article entity type.
        $this->assertArrayHasKey('/api/article', $spec['paths']);
        $this->assertArrayHasKey('/api/article/{id}', $spec['paths']);

        // Verify correct HTTP methods on collection path.
        $collectionPath = $spec['paths']['/api/article'];
        $this->assertArrayHasKey('get', $collectionPath);
        $this->assertArrayHasKey('post', $collectionPath);

        // Verify correct HTTP methods on resource path.
        $resourcePath = $spec['paths']['/api/article/{id}'];
        $this->assertArrayHasKey('get', $resourcePath);
        $this->assertArrayHasKey('patch', $resourcePath);
        $this->assertArrayHasKey('delete', $resourcePath);

        // Verify component schemas for article.
        $schemas = $spec['components']['schemas'];
        $this->assertArrayHasKey('ArticleResource', $schemas);
        $this->assertArrayHasKey('ArticleAttributes', $schemas);
        $this->assertArrayHasKey('ArticleCreateRequest', $schemas);
        $this->assertArrayHasKey('ArticleUpdateRequest', $schemas);

        // Verify shared JSON:API schemas.
        $this->assertArrayHasKey('JsonApiDocument', $schemas);
        $this->assertArrayHasKey('JsonApiErrorDocument', $schemas);
    }

    /**
     * Verifies that MCP tool definitions are generated for registered entity types.
     *
     * Exercises: waaseyaa/ai-schema (SchemaRegistry, EntityJsonSchemaGenerator,
     * McpToolGenerator) with waaseyaa/entity (EntityTypeManager, EntityType).
     */
    #[Test]
    public function testMcpToolGeneration(): void
    {
        $articleStorage = new InMemoryEntityStorage('article');

        $entityTypeManager = new EntityTypeManager(
            new EventDispatcher(),
            fn() => $articleStorage,
        );

        $entityTypeManager->registerEntityType(new EntityType(
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

        $schemaGenerator = new EntityJsonSchemaGenerator($entityTypeManager);
        $toolGenerator = new McpToolGenerator($entityTypeManager);
        $registry = new SchemaRegistry($schemaGenerator, $toolGenerator);

        // Register entity types and retrieve tools.
        $tools = $registry->getTools();

        // Should have 5 CRUD tools for the article entity type.
        $this->assertCount(5, $tools);

        $toolNames = array_map(fn(McpToolDefinition $t) => $t->name, $tools);
        $this->assertContains('create_article', $toolNames);
        $this->assertContains('read_article', $toolNames);
        $this->assertContains('update_article', $toolNames);
        $this->assertContains('delete_article', $toolNames);
        $this->assertContains('query_article', $toolNames);

        // Verify each tool has proper structure.
        foreach ($tools as $tool) {
            $this->assertInstanceOf(McpToolDefinition::class, $tool);
            $this->assertNotEmpty($tool->name);
            $this->assertNotEmpty($tool->description);
            $this->assertNotEmpty($tool->inputSchema);

            $array = $tool->toArray();
            $this->assertArrayHasKey('name', $array);
            $this->assertArrayHasKey('description', $array);
            $this->assertArrayHasKey('inputSchema', $array);
        }

        // Verify specific tool retrieval.
        $createTool = $registry->getTool('create_article');
        $this->assertNotNull($createTool);
        $this->assertSame('create_article', $createTool->name);
        $this->assertStringContainsString('Article', $createTool->description);

        // Verify JSON schema generation also works.
        $schema = $registry->getSchema('article');
        $this->assertSame('Article', $schema['title']);
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('title', $schema['properties']);
    }

    /**
     * Exercises the full SSR pipeline: registry -> Twig rendering -> HttpResponse.
     *
     * Exercises: waaseyaa/ssr (ComponentRegistry, ComponentRenderer,
     * SsrController, HttpResponse, ComponentMetadata) with Twig.
     */
    #[Test]
    public function testSsrComponentRenderingPipeline(): void
    {
        // Set up component registry with templates.
        $registry = new ComponentRegistry();

        $twig = new Environment(new ArrayLoader([
            'article-card.html.twig' => '<article class="card"><h2>{{ title }}</h2><p>{{ summary }}</p><span class="author">{{ author }}</span></article>',
            'page-layout.html.twig' => '<!DOCTYPE html><html><head><title>{{ page_title }}</title></head><body><main>{{ content|raw }}</main></body></html>',
        ]));

        $renderer = new ComponentRenderer($twig, $registry);
        $controller = new SsrController($renderer);

        // Register components via ComponentMetadata.
        $registry->register(new ComponentMetadata(
            name: 'article-card',
            template: 'article-card.html.twig',
            className: \stdClass::class,
        ));

        $registry->register(new ComponentMetadata(
            name: 'page-layout',
            template: 'page-layout.html.twig',
            className: \stdClass::class,
        ));

        // Verify registry state.
        $this->assertTrue($registry->has('article-card'));
        $this->assertTrue($registry->has('page-layout'));
        $this->assertCount(2, $registry->all());

        // Render article card via controller.
        $response = $controller->render('article-card', [
            'title' => 'Waaseyaa Launches',
            'summary' => 'A next-generation content management system.',
            'author' => 'Waaseyaa Team',
        ]);

        // Verify Response.
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        // Verify rendered content.
        $this->assertStringContainsString('<article class="card">', $response->getContent());
        $this->assertStringContainsString('<h2>Waaseyaa Launches</h2>', $response->getContent());
        $this->assertStringContainsString('A next-generation content management system.', $response->getContent());
        $this->assertStringContainsString('Waaseyaa Team', $response->getContent());

        // Render page layout via controller.
        $pageResponse = $controller->render('page-layout', [
            'page_title' => 'Home - Waaseyaa',
            'content' => '<p>Welcome to Waaseyaa.</p>',
        ]);

        $this->assertInstanceOf(Response::class, $pageResponse);
        $this->assertSame(200, $pageResponse->getStatusCode());
        $this->assertStringContainsString('<title>Home - Waaseyaa</title>', $pageResponse->getContent());
        $this->assertStringContainsString('<p>Welcome to Waaseyaa.</p>', $pageResponse->getContent());
    }

    /**
     * Verifies cache warming and invalidation across multiple bins.
     *
     * Exercises: waaseyaa/cache (CacheFactory, MemoryBackend) with
     * waaseyaa/cli (CacheClearCommand) for cross-layer cache management.
     */
    #[Test]
    public function testCacheIntegrationAcrossOperations(): void
    {
        $cacheFactory = new CacheFactory();

        // Get multiple cache bins.
        $defaultBin = $cacheFactory->get('default');
        $renderBin = $cacheFactory->get('render');
        $discoveryBin = $cacheFactory->get('discovery');

        // Warm caches across all bins.
        $defaultBin->set('entity:1', ['title' => 'Article One']);
        $defaultBin->set('entity:2', ['title' => 'Article Two']);
        $renderBin->set('block:sidebar', '<div>Sidebar</div>');
        $renderBin->set('block:header', '<header>Header</header>');
        $discoveryBin->set('plugins:field', ['text', 'number', 'boolean']);

        // Verify all caches are populated.
        $this->assertNotFalse($defaultBin->get('entity:1'));
        $this->assertNotFalse($defaultBin->get('entity:2'));
        $this->assertNotFalse($renderBin->get('block:sidebar'));
        $this->assertNotFalse($renderBin->get('block:header'));
        $this->assertNotFalse($discoveryBin->get('plugins:field'));

        // Clear all caches via CacheClearCommand.
        $command = new CacheClearCommand($cacheFactory);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Verify everything is cleared.
        $this->assertFalse($defaultBin->get('entity:1'));
        $this->assertFalse($defaultBin->get('entity:2'));
        $this->assertFalse($renderBin->get('block:sidebar'));
        $this->assertFalse($renderBin->get('block:header'));
        $this->assertFalse($discoveryBin->get('plugins:field'));

        // Set new values and verify they persist.
        $defaultBin->set('entity:3', ['title' => 'New Article']);
        $renderBin->set('block:footer', '<footer>Footer</footer>');

        $this->assertNotFalse($defaultBin->get('entity:3'));
        $this->assertNotFalse($renderBin->get('block:footer'));

        // Verify the old values are still gone.
        $this->assertFalse($defaultBin->get('entity:1'));
        $this->assertFalse($renderBin->get('block:sidebar'));
    }

    /**
     * Exercises the full migration lifecycle: disable types, migrate:defaults to fix,
     * rollback to revert, and verify audit log entries.
     */
    #[Test]
    public function testMigrationDefaultsLifecycle(): void
    {
        // --- Setup ---

        $tempDir = sys_get_temp_dir() . '/waaseyaa_migration_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        $lifecycleManager = new EntityTypeLifecycleManager($tempDir);
        $auditLogger = new EntityAuditLogger($tempDir);

        $entityTypeManager = new EntityTypeManager(new EventDispatcher());

        $entityTypeManager->registerEntityType(new EntityType(
            id: 'note',
            label: 'Note',
            class: \stdClass::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
        ));

        $entityTypeManager->registerEntityType(new EntityType(
            id: 'article',
            label: 'Article',
            class: \stdClass::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
        ));

        // Build native CLI definitions for type:disable and type:enable.
        $typeProvider = new EntityTypeServiceProvider();
        $typeDefinitions = [];
        foreach ($typeProvider->nativeCommands() as $cmd) {
            $typeDefinitions[$cmd->name] = $cmd;
        }
        $typeContainer = new class ($entityTypeManager, $lifecycleManager) implements \Psr\Container\ContainerInterface {
            public function __construct(
                private readonly \Waaseyaa\Entity\EntityTypeManagerInterface $manager,
                private readonly \Waaseyaa\Entity\EntityTypeLifecycleManager $lifecycle,
            ) {}

            public function get(string $id): mixed
            {
                return match ($id) {
                    TypeDisableHandler::class => new TypeDisableHandler($this->manager, $this->lifecycle),
                    TypeEnableHandler::class  => new TypeEnableHandler($this->manager, $this->lifecycle),
                    default => throw new \RuntimeException("Container::get({$id}) unexpected"),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [TypeDisableHandler::class, TypeEnableHandler::class], true);
            }
        };

        // Build a CliTester for MigrateDefaultsHandler (native CLI pattern).
        $migrateDefaultsHandler = new MigrateDefaultsHandler($entityTypeManager, $lifecycleManager, $auditLogger, $tempDir);
        $migrateDefaultsDefinition = new CommandDefinition(
            name: 'migrate:defaults',
            description: 'Migrate default content type enablement for tenants',
            options: [
                new OptionDefinition(name: 'tenant', mode: OptionMode::Array_, description: 'Tenant IDs to migrate (repeatable)'),
                new OptionDefinition(name: 'enable', mode: OptionMode::Required, description: 'Type ID to enable for all tenants (e.g. note)', default: ''),
                new OptionDefinition(name: 'actor', mode: OptionMode::Required, description: 'Actor ID for audit log entries', default: 'cli'),
                new OptionDefinition(name: 'yes', shortcut: 'y', mode: OptionMode::None, description: 'Skip confirmation prompts'),
                new OptionDefinition(name: 'dry-run', mode: OptionMode::None, description: 'Report actions without making changes'),
                new OptionDefinition(name: 'rollback', mode: OptionMode::None, description: 'Rollback previous migrate:defaults actions'),
            ],
            handler: \Closure::fromCallable([$migrateDefaultsHandler, 'execute']),
        );
        $container = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException("Not found: $id");
            }
            public function has(string $id): bool
            {
                return false;
            }
        };

        // --- Step 1: Disable all types for tenant "acme" ---

        $noteTester = CliTester::for($typeDefinitions['type:disable'], $typeContainer);
        $noteTester->executeMap(['type' => 'note', '--tenant' => 'acme', '--yes' => true]);
        $this->assertSame(0, $noteTester->getExitCode());
        $this->assertTrue($lifecycleManager->isDisabled('note', 'acme'));

        $articleTester = CliTester::for($typeDefinitions['type:disable'], $typeContainer);
        $articleTester->executeMap(['type' => 'article', '--tenant' => 'acme', '--yes' => true, '--force' => true]);
        $this->assertSame(0, $articleTester->getExitCode());
        $this->assertTrue($lifecycleManager->isDisabled('article', 'acme'));

        // --- Step 2: migrate:defaults detects and fixes ---

        $migrateTester = CliTester::for($migrateDefaultsDefinition, $container);
        $migrateTester->execute(['--tenant', 'acme', '--enable', 'note', '--yes']);

        $this->assertSame(0, $migrateTester->getExitCode());
        $this->assertFalse($lifecycleManager->isDisabled('note', 'acme'));
        $this->assertStringContainsString('Enabled "note" for tenant "acme"', $migrateTester->getStdout());

        // --- Step 3: Rollback reverses ---

        $rollbackTester = CliTester::for($migrateDefaultsDefinition, $container);
        $rollbackTester->execute(['--tenant', 'acme', '--rollback', '--yes']);

        $this->assertSame(0, $rollbackTester->getExitCode());
        $this->assertTrue($lifecycleManager->isDisabled('note', 'acme'));
        $this->assertStringContainsString('Disabled "note" for tenant "acme"', $rollbackTester->getStdout());

        // --- Step 4: Audit log contains both 'disabled' and 'enabled' actions for note/acme ---

        $auditEntries = $lifecycleManager->readAuditLog('note', 'acme');
        $actions = array_column($auditEntries, 'action');
        $this->assertContains('disabled', $actions);
        $this->assertContains('enabled', $actions);

        // --- Cleanup ---

        $storageDir = $tempDir . '/storage/framework';
        foreach (glob($storageDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($storageDir)) {
            rmdir($storageDir);
            rmdir(dirname($storageDir));
        }
        rmdir($tempDir);
    }
}
