<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Handler\ScheduleListHandler;
use Waaseyaa\CLI\Provider\SchedulePerfServiceProvider;
use Waaseyaa\CLI\Testing\CliTester;
use Waaseyaa\Scheduler\ScheduledTask;
use Waaseyaa\Scheduler\ScheduleInterface;

#[CoversClass(ScheduleListHandler::class)]
final class ScheduleListHandlerTest extends TestCase
{
    private function makeDefinition(): \Waaseyaa\CLI\CommandDefinition
    {
        $provider = new SchedulePerfServiceProvider();
        foreach ($provider->nativeCommands() as $cmd) {
            if ($cmd->name === 'schedule:list') {
                return $cmd;
            }
        }

        throw new \RuntimeException('schedule:list command definition not found');
    }

    /**
     * @param list<ScheduledTask> $tasks
     */
    private function makeContainer(array $tasks): \Psr\Container\ContainerInterface
    {
        $schedule = new class ($tasks) implements ScheduleInterface {
            /** @param list<ScheduledTask> $tasks */
            public function __construct(private readonly array $tasks) {}

            public function tasks(): array
            {
                return $this->tasks;
            }

            public function add(ScheduledTask $task): static
            {
                throw new \BadMethodCallException('Not implemented.');
            }
        };

        return new class ($schedule) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly ScheduleInterface $schedule) {}

            public function get(string $id): mixed
            {
                if ($id === ScheduleListHandler::class) {
                    return new ScheduleListHandler($this->schedule);
                }

                throw new \RuntimeException(sprintf('Container::get(%s) called unexpectedly', $id));
            }

            public function has(string $id): bool
            {
                return $id === ScheduleListHandler::class;
            }
        };
    }

    #[Test]
    public function lists_registered_tasks(): void
    {
        $tester = CliTester::for($this->makeDefinition(), $this->makeContainer([
            new ScheduledTask(
                name: 'cache:clear',
                expression: '0 * * * *',
                command: static fn () => null,
                description: 'Clear expired cache',
            ),
            new ScheduledTask(
                name: 'report:generate',
                expression: '0 0 * * *',
                command: static fn () => null,
                preventOverlap: true,
            ),
        ]));

        $tester->executeMap([]);

        self::assertSame(0, $tester->getExitCode());
        $output = $tester->getStdout();
        self::assertStringContainsString('scheduled task(s)', $output);
        self::assertStringContainsString('cache:clear', $output);
        self::assertStringContainsString('0 * * * *', $output);
        self::assertStringContainsString('Clear expired cache', $output);
        self::assertStringContainsString('report:generate', $output);
        self::assertStringContainsString('[no-overlap]', $output);
    }

    #[Test]
    public function shows_empty_message_when_no_tasks(): void
    {
        $tester = CliTester::for($this->makeDefinition(), $this->makeContainer([]));

        $tester->executeMap([]);

        self::assertSame(0, $tester->getExitCode());
        self::assertStringContainsString('No scheduled tasks registered.', $tester->getStdout());
    }

    #[Test]
    public function returns_success_status_code(): void
    {
        $tester = CliTester::for($this->makeDefinition(), $this->makeContainer([
            new ScheduledTask(
                name: 'heartbeat',
                expression: '* * * * *',
                command: static fn () => null,
            ),
        ]));

        $tester->executeMap([]);

        self::assertSame(0, $tester->getExitCode());
    }
}
