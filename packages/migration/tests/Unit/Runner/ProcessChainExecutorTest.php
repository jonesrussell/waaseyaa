<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Runner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\ProcessException;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\Process\PassThroughProcessor;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\Runner\ProcessChainExecutor;
use Waaseyaa\Migration\SourceId;

#[CoversClass(ProcessChainExecutor::class)]
final class ProcessChainExecutorTest extends TestCase
{
    #[Test]
    public function string_shorthand_resolves_to_pass_through(): void
    {
        $executor = new ProcessChainExecutor();
        $record = new SourceRecord('demo', ['post_title' => 'Hello world']);
        $definition = $this->demoDefinition(['title' => 'post_title']);

        $result = $executor->executeField($definition, 'title', $record, $this->stubLookup());

        self::assertSame('Hello world', $result);
    }

    #[Test]
    public function chain_threads_value_through_each_processor(): void
    {
        $executor = new ProcessChainExecutor();
        $record = new SourceRecord('demo', ['raw' => 'lowercase']);
        $upper = $this->makeProcessor(static fn(mixed $value): mixed => \is_string($value) ? \strtoupper($value) : $value);
        $exclaim = $this->makeProcessor(static fn(mixed $value): mixed => \is_string($value) ? $value . '!' : $value);

        $definition = $this->demoDefinition(['body' => ['raw', $upper, $exclaim]]);

        $result = $executor->executeField($definition, 'body', $record, $this->stubLookup());

        self::assertSame('LOWERCASE!', $result);
    }

    #[Test]
    public function process_exception_passes_through_verbatim(): void
    {
        $executor = new ProcessChainExecutor();
        $record = new SourceRecord('demo', ['payload' => 'whatever']);
        $thrower = $this->makeProcessor(static function (): mixed {
            throw new ProcessException(
                processCode: 'LOOKUP_MISS',
                sourceField: 'payload',
                migrationId: 'demo',
                message: 'verbatim error',
            );
        });
        $definition = $this->demoDefinition(['body' => [$thrower]]);

        $this->expectException(ProcessException::class);
        $this->expectExceptionMessage('verbatim error');
        $executor->executeField($definition, 'body', $record, $this->stubLookup());
    }

    #[Test]
    public function arbitrary_throwable_wraps_as_process_exception(): void
    {
        $executor = new ProcessChainExecutor();
        $record = new SourceRecord('demo', ['payload' => 'whatever']);
        $thrower = $this->makeProcessor(static function (): mixed {
            throw new \RuntimeException('plugin contract violation');
        });
        $definition = $this->demoDefinition(['body' => [$thrower]]);

        try {
            $executor->executeField($definition, 'body', $record, $this->stubLookup());
            self::fail('expected ProcessException');
        } catch (ProcessException $e) {
            self::assertSame('PROCESS_PLUGIN_THREW', $e->processCode);
            self::assertSame('body', $e->sourceField);
            self::assertSame('demo', $e->migrationId);
            self::assertStringContainsString('plugin contract violation', $e->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
    }

    /**
     * @param array<string, \Waaseyaa\Migration\Plugin\ProcessPluginInterface|string|array<\Waaseyaa\Migration\Plugin\ProcessPluginInterface|string>> $process
     */
    private function demoDefinition(array $process): MigrationDefinition
    {
        return new MigrationDefinition(
            id: 'demo',
            source: $this->stubSource(),
            process: $process,
            destination: $this->stubDestination(),
        );
    }

    private function stubLookup(): \Closure
    {
        return static fn(string $migrationId, SourceId $sourceId): ?WriteResult => null;
    }

    private function makeProcessor(\Closure $body): ProcessPluginInterface
    {
        return new class ($body) implements ProcessPluginInterface {
            public function __construct(private readonly \Closure $body) {}
            public function id(): string { return 'inline_test'; }
            public function stability(): string { return 'experimental'; }
            public function transform(mixed $value, ProcessContext $context): mixed
            {
                return ($this->body)($value, $context);
            }
        };
    }

    private function stubSource(): SourcePluginInterface
    {
        return new class implements SourcePluginInterface {
            public function id(): string { return 'stub'; }
            public function stability(): string { return 'stable'; }
            public function records(): iterable { return []; }
            public function sourceIdFor(SourceRecord $record): SourceId { return new SourceId('stub', ['id' => 0]); }
            public function count(): ?int { return 0; }
        };
    }

    private function stubDestination(): DestinationPluginInterface
    {
        return new class implements DestinationPluginInterface {
            public function id(): string { return 'stub_dest'; }
            public function stability(): string { return 'experimental'; }
            public function write(DestinationRecord $record): WriteResult
            {
                return new WriteResult('stub', 'uuid', 'hash', 'run', '2026-05-13T00:00:00Z');
            }
            public function rollback(WriteResult $result): void {}
            public function lookup(SourceId $sourceId): ?WriteResult { return null; }
        };
    }
}
