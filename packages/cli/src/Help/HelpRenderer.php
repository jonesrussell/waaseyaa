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
 * Output format matches Symfony Console help output (WP01 snapshot contract):
 *
 *   Description:
 *     <description wrapped at 80 cols>
 *
 *   Usage:
 *     <name> [options] [--] <required_arg> [<optional_arg>] [<array_arg>...]
 *
 *   Arguments:
 *     <name>      <description>
 *
 *   Options:
 *     -s, --long[=VALUE]   <description> [default: <val>]
 *
 * User-defined options are rendered in declaration order (matching Symfony
 * Console's DescriptorHelper behaviour). Kernel-level flags
 * (--help, --silent, -q/--quiet, -V/--version, --ansi|--no-ansi,
 * -n/--no-interaction, -v|vv|vvv/--verbose) are auto-injected after
 * user-defined options, matching Symfony Console's exact wording.
 */
final class HelpRenderer
{
    private const WRAP_WIDTH = 80;

    /**
     * Kernel-level flags in the order Symfony Console renders them.
     * Labels with pipe separators (like -v|vv|vvv) are pre-formatted.
     */
    private const KERNEL_OPTIONS = [
        [
            'label' => '-h, --help',
            'desc'  => 'Display help for the given command. When no command is given display help for the list command',
        ],
        [
            'label' => '    --silent',
            'desc'  => 'Do not output any message',
        ],
        [
            'label' => '-q, --quiet',
            'desc'  => 'Only errors are displayed. All other output is suppressed',
        ],
        [
            'label' => '-V, --version',
            'desc'  => 'Display this application version',
        ],
        [
            'label' => '    --ansi|--no-ansi',
            'desc'  => 'Force (or disable --no-ansi) ANSI output',
        ],
        [
            'label' => '-n, --no-interaction',
            'desc'  => 'Do not ask any interactive question',
        ],
        [
            'label' => '-v|vv|vvv, --verbose',
            'desc'  => 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug',
        ],
    ];

    public function render(CommandDefinition $command): string
    {
        $lines = [];

        if ($command->description !== '') {
            $lines[] = 'Description:';
            foreach ($this->wordWrap($command->description, self::WRAP_WIDTH - 2) as $wrapped) {
                $lines[] = '  ' . $wrapped;
            }
            $lines[] = '';
        }

        $lines[] = 'Usage:';
        $lines[] = '  ' . $this->buildUsageLine($command);
        $lines[] = '';

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
        $parts = [$command->name];

        // Only add [options] when the command actually declares user options.
        if ($command->options !== []) {
            $parts[] = '[options]';
        }

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

        // User-defined options in declaration order (matches Symfony Console behaviour).
        foreach ($userOptions as $opt) {
            $desc = $opt->description;
            // Symfony Console appends "(multiple values allowed)" for VALUE_IS_ARRAY options.
            if ($opt->mode === OptionMode::Array_) {
                $desc .= ' (multiple values allowed)';
            }

            $result[] = [
                'label'   => $this->buildOptionLabel($opt),
                'desc'    => $desc,
                'default' => $this->formatDefault($opt),
            ];
        }

        // Kernel-level flags in Symfony Console order with exact wording.
        foreach (self::KERNEL_OPTIONS as $k) {
            $result[] = [
                'label'   => $k['label'],
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

        $metavar = strtoupper($opt->name);
        $longPart .= match ($opt->mode) {
            OptionMode::Required  => '=' . $metavar,
            OptionMode::Optional  => '[=' . $metavar . ']',
            OptionMode::Negatable => '',
            OptionMode::Array_    => '=' . $metavar,
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

        // Symfony Console wraps string defaults in double-quotes.
        return '"' . $default . '"';
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
