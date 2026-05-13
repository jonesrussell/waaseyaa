<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\LoggerTrait;
use Waaseyaa\Foundation\Log\LogLevel;
use Waaseyaa\Migration\Discovery\HasMigrationPluginsInterface;
use Waaseyaa\Migration\Discovery\PluginRegistry;
use Waaseyaa\Migration\Exception\MigrationPluginCollisionException;
use Waaseyaa\Migration\Log\Channels;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(PluginRegistry::class)]
#[CoversClass(MigrationPluginCollisionException::class)]
final class PluginRegistryTest extends TestCase
{
    #[Test]
    public function boot_indexes_plugins_by_type_and_id(): void
    {
        $source = $this->makeSourcePlugin('csv');
        $process = $this->makeProcessPlugin('my_concat');
        $destination = $this->makeDestinationPlugin('my_writer');

        $provider = $this->providerWith([$source, $process, $destination]);
        $registry = new PluginRegistry([$provider]);
        $registry->boot();

        self::assertSame($source, $registry->getSource('csv'));
        self::assertSame($process, $registry->getProcess('my_concat'));
        self::assertSame($destination, $registry->getDestination('my_writer'));
    }

    #[Test]
    public function duplicate_plugin_id_raises_collision_with_both_fqcns(): void
    {
        $first = $this->makeProcessPlugin('dup');
        $second = $this->makeProcessPlugin('dup');

        $registry = new PluginRegistry([
            $this->providerWith([$first]),
            $this->providerWith([$second]),
        ]);

        try {
            $registry->boot();
            self::fail('Expected MigrationPluginCollisionException.');
        } catch (MigrationPluginCollisionException $exception) {
            self::assertSame('dup', $exception->pluginId);
            self::assertSame($first::class, $exception->firstFqcn);
            self::assertSame($second::class, $exception->secondFqcn);
            self::assertFalse($exception->reserved);
            self::assertStringContainsString($first::class, $exception->getMessage());
            self::assertStringContainsString($second::class, $exception->getMessage());
        }
    }

    #[Test]
    public function third_party_cannot_register_a_reserved_id(): void
    {
        $thirdParty = new class() implements ProcessPluginInterface {
            public function id(): string
            {
                return ReservedPluginIds::PASS_THROUGH;
            }

            public function stability(): string
            {
                return 'stable';
            }

            public function transform(mixed $value, ProcessContext $context): mixed
            {
                return $value;
            }
        };

        $registry = new PluginRegistry([$this->providerWith([$thirdParty])]);

        try {
            $registry->boot();
            self::fail('Expected reserved-id MigrationPluginCollisionException.');
        } catch (MigrationPluginCollisionException $exception) {
            self::assertSame(ReservedPluginIds::PASS_THROUGH, $exception->pluginId);
            self::assertTrue($exception->reserved);
            self::assertSame($thirdParty::class, $exception->secondFqcn);
            self::assertStringContainsString('reserved', $exception->getMessage());
        }
    }

    #[Test]
    public function framework_provider_may_register_a_reserved_id(): void
    {
        $frameworkPlugin = $this->makeFrameworkProcessPluginForReservedId();

        $registry = new PluginRegistry([$this->providerWith([$frameworkPlugin])]);
        $registry->boot();

        self::assertSame($frameworkPlugin, $registry->getProcess(ReservedPluginIds::PASS_THROUGH));
    }

    #[Test]
    public function experimental_plugin_emits_deprecation_warning_exactly_once_per_process(): void
    {
        $experimental = new class() implements ProcessPluginInterface {
            public function id(): string
            {
                return 'experimental_thing';
            }

            public function stability(): string
            {
                return 'experimental';
            }

            public function transform(mixed $value, ProcessContext $context): mixed
            {
                return $value;
            }
        };

        $logger = $this->makeRecordingLogger();
        $registry = new PluginRegistry([$this->providerWith([$experimental])], $logger);
        $registry->boot();

        $registry->getProcess('experimental_thing');
        $registry->getProcess('experimental_thing');
        $registry->getProcess('experimental_thing');

        self::assertCount(1, $logger->records, 'Deprecation must fire exactly once per plugin id.');
        $record = $logger->records[0];
        self::assertSame(LogLevel::WARNING, $record['level']);
        self::assertStringContainsString('experimental_thing', $record['message']);
        self::assertSame(Channels::MIGRATION_DEPRECATION, $record['context']['channel']);
        self::assertSame('experimental_thing', $record['context']['plugin_id']);
        self::assertSame('experimental', $record['context']['stability']);
    }

    #[Test]
    public function stable_plugin_use_does_not_emit_deprecation(): void
    {
        $stable = $this->makeProcessPlugin('stable_thing');

        $logger = $this->makeRecordingLogger();
        $registry = new PluginRegistry([$this->providerWith([$stable])], $logger);
        $registry->boot();

        $registry->getProcess('stable_thing');
        $registry->getProcess('stable_thing');

        self::assertSame([], $logger->records);
    }

    #[Test]
    public function unknown_plugin_id_raises_out_of_bounds(): void
    {
        $registry = new PluginRegistry([]);
        $registry->boot();

        $this->expectException(\OutOfBoundsException::class);
        $registry->getSource('not_registered');
    }

    #[Test]
    public function unknown_process_plugin_raises_out_of_bounds(): void
    {
        $registry = new PluginRegistry([]);
        $registry->boot();

        $this->expectException(\OutOfBoundsException::class);
        $registry->getProcess('not_registered');
    }

    #[Test]
    public function unknown_destination_plugin_raises_out_of_bounds(): void
    {
        $registry = new PluginRegistry([]);
        $registry->boot();

        $this->expectException(\OutOfBoundsException::class);
        $registry->getDestination('not_registered');
    }

    #[Test]
    public function resolve_before_boot_is_a_logic_error(): void
    {
        $registry = new PluginRegistry([]);

        $this->expectException(\LogicException::class);
        $registry->getProcess('whatever');
    }

    #[Test]
    public function double_boot_is_a_logic_error(): void
    {
        $registry = new PluginRegistry([]);
        $registry->boot();
        self::assertTrue($registry->isBooted());

        $this->expectException(\LogicException::class);
        $registry->boot();
    }

    private function makeSourcePlugin(string $id): SourcePluginInterface
    {
        return new class($id) implements SourcePluginInterface {
            public function __construct(private readonly string $idValue)
            {
            }

            public function id(): string
            {
                return $this->idValue;
            }

            public function stability(): string
            {
                return 'stable';
            }

            public function records(): iterable
            {
                return [];
            }

            public function sourceIdFor(SourceRecord $record): SourceId
            {
                return new SourceId($this->idValue, ['k' => 1]);
            }

            public function count(): ?int
            {
                return 0;
            }
        };
    }

    private function makeProcessPlugin(string $id): ProcessPluginInterface
    {
        return new class($id) implements ProcessPluginInterface {
            public function __construct(private readonly string $idValue)
            {
            }

            public function id(): string
            {
                return $this->idValue;
            }

            public function stability(): string
            {
                return 'stable';
            }

            public function transform(mixed $value, ProcessContext $context): mixed
            {
                return $value;
            }
        };
    }

    private function makeDestinationPlugin(string $id): DestinationPluginInterface
    {
        return new class($id) implements DestinationPluginInterface {
            public function __construct(private readonly string $idValue)
            {
            }

            public function id(): string
            {
                return $this->idValue;
            }

            public function stability(): string
            {
                return 'stable';
            }

            public function write(DestinationRecord $record): WriteResult
            {
                return new WriteResult('node', 'u', 'h', 'r', 't');
            }

            public function rollback(WriteResult $result): void
            {
            }

            public function lookup(SourceId $sourceId): ?WriteResult
            {
                return null;
            }
        };
    }

    /**
     * Returns a plugin whose FQCN sits under `Waaseyaa\Migration\` (but not
     * under `Waaseyaa\Migration\Tests\`) so the reserved-id check waves it
     * through. Mirrors the shape WP03's first-party plugins will have.
     */
    private function makeFrameworkProcessPluginForReservedId(): ProcessPluginInterface
    {
        return new \Waaseyaa\Migration\PluginFixtures\FakeReservedFrameworkPlugin();
    }

    /**
     * @param list<SourcePluginInterface|ProcessPluginInterface|DestinationPluginInterface> $plugins
     */
    private function providerWith(array $plugins): HasMigrationPluginsInterface
    {
        return new class($plugins) implements HasMigrationPluginsInterface {
            /**
             * @param list<SourcePluginInterface|ProcessPluginInterface|DestinationPluginInterface> $plugins
             */
            public function __construct(private readonly array $plugins)
            {
            }

            public function migrationPlugins(): iterable
            {
                return $this->plugins;
            }
        };
    }

    private function makeRecordingLogger(): LoggerInterface
    {
        return new class() implements LoggerInterface {
            use LoggerTrait;

            /** @var list<array{level: LogLevel, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log(LogLevel $level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
