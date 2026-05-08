<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Provider;

use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Handler\PerformanceBaselineHandler;
use Waaseyaa\CLI\Handler\PerformanceCompareHandler;
use Waaseyaa\CLI\Handler\ScheduleListHandler;
use Waaseyaa\CLI\Handler\ScheduleRunHandler;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class SchedulePerfServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'schedule:list',
            description: 'List all registered scheduled tasks',
            handler: [ScheduleListHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'schedule:run',
            description: 'Run due scheduled tasks',
            handler: [ScheduleRunHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'perf:baseline',
            description: 'Generate a versioned performance baseline artifact',
            options: [
                new OptionDefinition(
                    name: 'contract-version',
                    mode: OptionMode::Required,
                    description: 'Baseline contract version',
                    default: 'v1.0',
                ),
                new OptionDefinition(
                    name: 'surface',
                    mode: OptionMode::Required,
                    description: 'Baseline surface ID',
                    default: 'performance_regression_gate',
                ),
                new OptionDefinition(
                    name: 'snapshot-hash',
                    mode: OptionMode::Required,
                    description: 'Snapshot hash to lock',
                ),
                new OptionDefinition(
                    name: 'threshold',
                    mode: OptionMode::Array_,
                    description: 'Latency threshold in surface:ms form (repeatable)',
                ),
                new OptionDefinition(
                    name: 'output',
                    shortcut: 'o',
                    mode: OptionMode::Required,
                    description: 'Optional output file path',
                ),
            ],
            handler: [PerformanceBaselineHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'perf:compare',
            description: 'Compare current performance measurements against a baseline artifact',
            options: [
                new OptionDefinition(
                    name: 'baseline',
                    mode: OptionMode::Required,
                    description: 'Baseline artifact JSON path',
                ),
                new OptionDefinition(
                    name: 'current',
                    mode: OptionMode::Required,
                    description: 'Current measurements JSON path',
                ),
                new OptionDefinition(
                    name: 'json',
                    mode: OptionMode::None,
                    description: 'Emit JSON result payload',
                ),
            ],
            handler: [PerformanceCompareHandler::class, 'execute'],
        );
    }
}
