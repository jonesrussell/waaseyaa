<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Help;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

/**
 * Renders deterministic --help output for a CommandDefinition.
 *
 * Output format (per research.md §R-06):
 *
 *   Usage:
 *     <name> [options] [--] <required_arg> [<optional_arg>] [<array_arg>...]
 *
 *   Description:
 *     <description wrapped at 80 cols>
 *
 *   Arguments:
 *     <name>      <description>
 *
 *   Options:
 *     -s, --long[=VALUE]   <description> [default: <val>]
 *
 * Options are sorted alphabetically by long name. Kernel-level flags
 * (--help, -v/--verbose, -q/--quiet, --no-interaction, --version) are
 * auto-injected after user-defined options.
 */
final class HelpRenderer
{
    private const WRAP_WIDTH = 80;

    private const KERNEL_OPTIONS = [
        ['long' => 'help',           'short' => 'h', 'desc' => 'Display help for the given command'],
        ['long' => 'no-interaction', 'short' => null, 'desc' => 'Do not ask any interactive question'],
        ['long' => 'quiet',          'short' => 'q', 'desc' => 'Do not output any message'],
        ['long' => 'verbose',        'short' => 'v', 'desc' => 'Increase verbosity of messages'],
        ['long' => 'version',        'short' => null, 'desc' => 'Display the application version'],
    ];

    public function render(CommandDefinition $command): string
    {
        $lines = [];

        $lines[] = 'Usage:';
        $lines[] = '  ' . $this->buildUsageLine($command);
        $lines[] = '';

        if ($command->description !== '') {
            $lines[] = 'Description:';
            foreach ($this->wordWrap($command->description, self::WRAP_WIDTH - 2) as $wrapped) {
                $lines[] = '  ' . $wrapped;
            }
            $lines[] = '';
        }

        if ($command->arguments !== []) {
            $lines[] = 'Arguments:';
            $nameWidth = $this->maxNameWidth(
                array_map(static fn(ArgumentDefinition $a) => $a->name, $command->arguments),
            );
            foreach ($command->arguments as $arg) {
                $lines[] = '  ' . str_pad($arg->name, $nameWidth) . '  ' . $arg->description;
            }
            $lines[] = '';
        }

        $lines[] = 'Options:';
        $allOptions = $this->collectOptions($command->options);
        $labelWidth = $this->maxNameWidth(array_column($allOptions, 'label'));
        foreach ($allOptions as $opt) {
            $suffix = $opt['default'] !== '' ? ' [default: ' . $opt['default'] . ']' : '';
            $lines[] = '  ' . str_pad($opt['label'], $labelWidth) . '  ' . $opt['desc'] . $suffix;
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    // -------------------------------------------------------------------------

    private function buildUsageLine(CommandDefinition $command): string
    {
        $parts = [$command->name, '[options]'];

        $hasRequired = false;
        foreach ($command->arguments as $arg) {
            if ($arg->mode === ArgumentMode::Required && !$arg->isArray) {
                $hasRequired = true;
                break;
            }
        }

        if ($hasRequired) {
            $parts[] = '[--]';
        }

        foreach ($command->arguments as $arg) {
            if ($arg->isArray) {
                $parts[] = '[<' . $arg->name . '>...]';
            } elseif ($arg->mode === ArgumentMode::Required) {
                $parts[] = '<' . $arg->name . '>';
            } else {
                $parts[] = '[<' . $arg->name . '>]';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<OptionDefinition> $userOptions
     * @return list<array{label: string, desc: string, default: string}>
     */
    private function collectOptions(array $userOptions): array
    {
        $result = [];

        // User-defined options, sorted alphabetically by long name.
        $sorted = $userOptions;
        usort($sorted, static fn(OptionDefinition $a, OptionDefinition $b) => strcmp($a->name, $b->name));

        foreach ($sorted as $opt) {
            $result[] = [
                'label'   => $this->buildOptionLabel($opt),
                'desc'    => $opt->description,
                'default' => $this->formatDefault($opt),
            ];
        }

        // Kernel-level flags, sorted alphabetically by long name (already sorted in const).
        foreach (self::KERNEL_OPTIONS as $k) {
            $shortPart = $k['short'] !== null ? '-' . $k['short'] . ', ' : '    ';
            $result[] = [
                'label'   => $shortPart . '--' . $k['long'],
                'desc'    => $k['desc'],
                'default' => '',
            ];
        }

        return $result;
    }

    private function buildOptionLabel(OptionDefinition $opt): string
    {
        $shortPart = $opt->shortcut !== null ? '-' . $opt->shortcut . ', ' : '    ';
        $longPart  = '--' . $opt->name;

        $longPart .= match ($opt->mode) {
            OptionMode::Required  => '=VALUE',
            OptionMode::Optional  => '[=VALUE]',
            OptionMode::Negatable => '',
            OptionMode::Array_    => '=VALUE',
            OptionMode::None      => '',
        };

        return $shortPart . $longPart;
    }

    private function formatDefault(OptionDefinition $opt): string
    {
        $default = $opt->default;

        if ($default === null || $default === false || $default === []) {
            return '';
        }

        if ($default === true) {
            return 'true';
        }

        if (is_array($default)) {
            return implode(', ', $default);
        }

        return (string) $default;
    }

    /** @return list<string> */
    private function wordWrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($text, $width, "\n", false));
    }

    /** @param list<string> $names */
    private function maxNameWidth(array $names): int
    {
        if ($names === []) {
            return 0;
        }

        return max(array_map('strlen', $names));
    }
}
