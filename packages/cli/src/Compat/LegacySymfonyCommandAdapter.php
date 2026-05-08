<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Compat;

/**
 * @internal Temporary dual-boot bridge. Deleted in WP23 (mission native-cli-kernel-01KR2NR7).
 *           Do NOT depend on this from application code.
 */

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

/**
 * Adapts a Symfony Console Command into a native CommandDefinition.
 *
 * Maps InputArgument/InputOption definitions to native ArgumentDefinition/
 * OptionDefinition value objects, and wraps the Symfony command's execute()
 * in a CliIO-compatible closure handler.
 *
 * @internal Temporary dual-boot bridge. Deleted in WP23 (mission native-cli-kernel-01KR2NR7).
 *           Do NOT depend on this from application code.
 */
final class LegacySymfonyCommandAdapter
{
    /**
     * Convert a Symfony Command to a native CommandDefinition.
     *
     * @throws \InvalidArgumentException if the Symfony command has no name.
     */
    public static function adapt(SymfonyCommand $cmd): CommandDefinition
    {
        $name = $cmd->getName();
        if ($name === null || $name === '') {
            throw new \InvalidArgumentException(
                'Cannot adapt a Symfony command with no name.',
            );
        }

        $definition = $cmd->getDefinition();

        $arguments = [];
        foreach ($definition->getArguments() as $arg) {
            $arguments[] = self::adaptArgument($arg);
        }

        $options = [];
        foreach ($definition->getOptions() as $opt) {
            $options[] = self::adaptOption($opt);
        }

        $description = $cmd->getDescription();

        return new CommandDefinition(
            name: $name,
            description: $description !== '' ? $description : '(no description)',
            arguments: $arguments,
            options: $options,
            handler: self::makeHandler($cmd),
        );
    }

    /**
     * Map a Symfony InputArgument to a native ArgumentDefinition.
     */
    private static function adaptArgument(InputArgument $arg): ArgumentDefinition
    {
        $mode = match (true) {
            $arg->isArray() => ArgumentMode::Array_,
            $arg->isRequired() => ArgumentMode::Required,
            default => ArgumentMode::Optional,
        };

        return new ArgumentDefinition(
            name: $arg->getName(),
            mode: $mode,
            description: $arg->getDescription(),
            default: $arg->isRequired() ? null : $arg->getDefault(),
        );
    }

    /**
     * Map a Symfony InputOption to a native OptionDefinition.
     */
    private static function adaptOption(InputOption $opt): OptionDefinition
    {
        $mode = match (true) {
            $opt->isNegatable() => OptionMode::Negatable,
            $opt->isArray() => OptionMode::Array_,
            $opt->isValueRequired() => OptionMode::Required,
            $opt->isValueOptional() => OptionMode::Optional,
            default => OptionMode::None,
        };

        $shortcut = $opt->getShortcut();
        if ($shortcut !== null && $shortcut !== '') {
            // Symfony may return multi-char shortcuts like 'v|vv|vvv'; take only first char.
            $shortcut = $shortcut[0];
        } else {
            $shortcut = null;
        }

        return new OptionDefinition(
            name: $opt->getName(),
            mode: $mode,
            description: $opt->getDescription(),
            shortcut: $shortcut,
            default: $mode === OptionMode::None ? false : $opt->getDefault(),
        );
    }

    /**
     * Build a CliIO-compatible closure that delegates to the Symfony command.
     *
     * @return \Closure(CliIO): int
     */
    private static function makeHandler(SymfonyCommand $cmd): \Closure
    {
        return static function (CliIO $io) use ($cmd): int {
            // Collect all parsed args and options from CliIO.
            $params = array_merge(
                $io->arguments(),
                self::prefixOptions($io->options()),
            );

            $input = new ArrayInput($params, $cmd->getDefinition());

            $output = new BufferedOutput();

            $exitCode = $cmd->run($input, $output);

            // Flush buffered output to CliIO (line-by-line for progressive behaviour).
            $buffered = $output->fetch();
            foreach (explode("\n", $buffered) as $line) {
                $io->writeln($line);
            }

            return $exitCode;
        };
    }

    /**
     * Prefix option keys with '--' for ArrayInput compatibility.
     *
     * @param array<string, scalar|array|null> $options
     * @return array<string, scalar|array|null>
     */
    private static function prefixOptions(array $options): array
    {
        $prefixed = [];
        foreach ($options as $key => $value) {
            $prefixed['--' . $key] = $value;
        }

        return $prefixed;
    }
}
