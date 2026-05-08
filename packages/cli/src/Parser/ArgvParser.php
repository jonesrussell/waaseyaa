<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Parser;

use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Exception\ParseException;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

/**
 * Parses an argv token list against a CommandDefinition.
 *
 * Supported subset (R-02):
 *  - Required and optional positional arguments.
 *  - One trailing array-mode positional collecting remaining tokens.
 *  - --long-option, -s short option.
 *  - Modes: None (boolean), Required, Optional, Array_, Negatable.
 *  - --key=value and --key value equivalent for Required/Optional.
 *  - Stacked short flags ONLY for None-mode: -abc ≡ -a -b -c.
 *  - -- end-of-options sentinel.
 *  - --no-foo toggles a Negatable --foo to false.
 *  - Repeated Array_-mode option accumulates.
 *
 * NOT supported (throws ParseException):
 *  - Glued short-option values like -fbar for Required-mode flags.
 *  - Multiple short forms per option.
 */
final class ArgvParser
{
    /**
     * Parse the given argv tokens against the command definition.
     *
     * @param list<string> $argv   Tokens after the command name (no script name, no command name).
     * @throws ParseException on any parse error.
     */
    public function parse(array $argv, CommandDefinition $cmd): ParsedInput
    {
        $rawArgv = $argv;

        // Build lookup maps
        /** @var array<string, OptionDefinition> */
        $byLong = [];
        /** @var array<string, OptionDefinition> */
        $byShort = [];

        foreach ($cmd->options as $opt) {
            $byLong[$opt->name] = $opt;
            if ($opt->shortcut !== null) {
                $byShort[$opt->shortcut] = $opt;
            }
        }

        // Initialise result arrays with defaults
        $parsedOptions = $this->initOptionDefaults($cmd->options);
        $parsedArguments = [];

        $positionalTokens = [];
        $endOfOptions = false;
        $i = 0;
        $count = count($argv);

        while ($i < $count) {
            $token = $argv[$i];

            if ($endOfOptions) {
                $positionalTokens[] = $token;
                ++$i;
                continue;
            }

            if ($token === '--') {
                $endOfOptions = true;
                ++$i;
                continue;
            }

            if (str_starts_with($token, '--')) {
                $i = $this->parseLongOption(
                    substr($token, 2),
                    $argv,
                    $i,
                    $byLong,
                    $parsedOptions,
                );
                continue;
            }

            if (str_starts_with($token, '-') && strlen($token) > 1) {
                $i = $this->parseShortOption(
                    substr($token, 1),
                    $argv,
                    $i,
                    $byShort,
                    $parsedOptions,
                );
                continue;
            }

            // Positional token
            $positionalTokens[] = $token;
            ++$i;
        }

        // Map positional tokens to argument definitions
        $parsedArguments = $this->mapPositionals($positionalTokens, $cmd->arguments);

        return new ParsedInput(
            arguments: $parsedArguments,
            options: $parsedOptions,
            rawArgv: $rawArgv,
        );
    }

    /**
     * @param array<string, OptionDefinition> $byLong
     * @param array<string, scalar|array|null> $parsedOptions
     * @return int Next $i
     */
    private function parseLongOption(
        string $nameAndValue,
        array $argv,
        int $i,
        array $byLong,
        array &$parsedOptions,
    ): int {
        // Split on first '='
        $eqPos = strpos($nameAndValue, '=');

        if ($eqPos !== false) {
            $name = substr($nameAndValue, 0, $eqPos);
            $inlineValue = substr($nameAndValue, $eqPos + 1);
        } else {
            $name = $nameAndValue;
            $inlineValue = null;
        }

        // Check for --no-foo negatable toggle
        if (str_starts_with($name, 'no-')) {
            $baseName = substr($name, 3);
            if (isset($byLong[$baseName]) && $byLong[$baseName]->mode === OptionMode::Negatable) {
                $parsedOptions[$baseName] = false;
                return $i + 1;
            }
        }

        if (!isset($byLong[$name])) {
            throw new ParseException(new ParseError(
                kind: ParseErrorKind::UnknownOption,
                message: sprintf('Unknown option "--%s".', $name),
                offendingToken: '--' . $name,
            ));
        }

        $opt = $byLong[$name];

        return $this->applyOption($opt, $inlineValue, $argv, $i, $parsedOptions);
    }

    /**
     * @param array<string, OptionDefinition> $byShort
     * @param array<string, scalar|array|null> $parsedOptions
     * @return int Next $i
     */
    private function parseShortOption(
        string $chars,
        array $argv,
        int $i,
        array $byShort,
        array &$parsedOptions,
    ): int {
        // Single char — may have inline =value or next-token value
        if (strlen($chars) === 1) {
            $char = $chars;
            if (!isset($byShort[$char])) {
                throw new ParseException(new ParseError(
                    kind: ParseErrorKind::UnknownOption,
                    message: sprintf('Unknown option "-%s".', $char),
                    offendingToken: '-' . $char,
                ));
            }

            return $this->applyOption($byShort[$char], null, $argv, $i, $parsedOptions);
        }

        // Could be stacked None-mode flags OR a single Required/Optional/Array_ with value
        // Check if first char is a known Non-None option — if so, treat rest as value (NOT supported per R-02)
        $first = $chars[0];
        if (!isset($byShort[$first])) {
            throw new ParseException(new ParseError(
                kind: ParseErrorKind::UnknownOption,
                message: sprintf('Unknown option "-%s".', $first),
                offendingToken: '-' . $first,
            ));
        }

        $firstOpt = $byShort[$first];

        if ($firstOpt->mode !== OptionMode::None) {
            // Glued value like -fbar is NOT supported for non-None modes
            throw new ParseException(new ParseError(
                kind: ParseErrorKind::UnknownOption,
                message: sprintf(
                    'Glued short-option values (e.g. "-%s") are not supported for non-boolean options. '
                    . 'Use "-%s value" or "-%s=value" instead.',
                    $chars,
                    $first,
                    $first,
                ),
                offendingToken: '-' . $chars,
            ));
        }

        // Stacked None-mode flags: -abc ≡ -a -b -c
        for ($j = 0; $j < strlen($chars); ++$j) {
            $char = $chars[$j];
            if (!isset($byShort[$char])) {
                throw new ParseException(new ParseError(
                    kind: ParseErrorKind::UnknownOption,
                    message: sprintf('Unknown option "-%s" in stacked flags "-%s".', $char, $chars),
                    offendingToken: '-' . $chars,
                ));
            }

            $opt = $byShort[$char];
            if ($opt->mode !== OptionMode::None) {
                throw new ParseException(new ParseError(
                    kind: ParseErrorKind::UnknownOption,
                    message: sprintf(
                        'Option "-%s" in stacked flags "-%s" is not a boolean flag. '
                        . 'Only None-mode options may be stacked.',
                        $char,
                        $chars,
                    ),
                    offendingToken: '-' . $chars,
                ));
            }

            $parsedOptions[$opt->name] = true;
        }

        return $i + 1;
    }

    /**
     * Apply a single option (long or short), consuming next argv token if needed.
     *
     * @param array<string, scalar|array|null> $parsedOptions
     * @return int Next $i
     */
    private function applyOption(
        OptionDefinition $opt,
        ?string $inlineValue,
        array $argv,
        int $i,
        array &$parsedOptions,
    ): int {
        switch ($opt->mode) {
            case OptionMode::None:
                $parsedOptions[$opt->name] = true;
                return $i + 1;

            case OptionMode::Negatable:
                $parsedOptions[$opt->name] = true;
                return $i + 1;

            case OptionMode::Required:
                if ($inlineValue !== null) {
                    $parsedOptions[$opt->name] = $inlineValue;
                    return $i + 1;
                }

                $next = $i + 1;
                if ($next >= count($argv) || str_starts_with($argv[$next], '-')) {
                    throw new ParseException(new ParseError(
                        kind: ParseErrorKind::MissingRequiredOptionValue,
                        message: sprintf('Option "--%s" requires a value.', $opt->name),
                        offendingToken: '--' . $opt->name,
                    ));
                }

                $parsedOptions[$opt->name] = $argv[$next];
                return $next + 1;

            case OptionMode::Optional:
                if ($inlineValue !== null) {
                    $parsedOptions[$opt->name] = $inlineValue;
                    return $i + 1;
                }

                // Check if next token looks like a value (not an option flag)
                $next = $i + 1;
                if ($next < count($argv) && !str_starts_with($argv[$next], '-')) {
                    $parsedOptions[$opt->name] = $argv[$next];
                    return $next + 1;
                }

                // Bare presence yields null
                $parsedOptions[$opt->name] = null;
                return $i + 1;

            case OptionMode::Array_:
                $value = null;

                if ($inlineValue !== null) {
                    $value = $inlineValue;
                } else {
                    $next = $i + 1;
                    if ($next >= count($argv) || str_starts_with($argv[$next], '-')) {
                        throw new ParseException(new ParseError(
                            kind: ParseErrorKind::MissingRequiredOptionValue,
                            message: sprintf('Option "--%s" requires a value.', $opt->name),
                            offendingToken: '--' . $opt->name,
                        ));
                    }
                    $value = $argv[$next];
                    $i = $next;
                }

                /** @var list<scalar> $current */
                $current = is_array($parsedOptions[$opt->name]) ? $parsedOptions[$opt->name] : [];
                $current[] = $value;
                $parsedOptions[$opt->name] = $current;
                return $i + 1;
        }
    }

    /**
     * Map positional tokens to argument definitions.
     *
     * @param list<string>             $tokens
     * @param list<ArgumentDefinition> $definitions
     * @return array<string, scalar|array|null>
     */
    private function mapPositionals(array $tokens, array $definitions): array
    {
        $result = [];
        $tokenCount = count($tokens);
        $defCount = count($definitions);
        $tokenIndex = 0;

        for ($d = 0; $d < $defCount; ++$d) {
            $def = $definitions[$d];

            if ($def->isArray) {
                // Collect all remaining tokens
                $collected = array_slice($tokens, $tokenIndex);
                $result[$def->name] = $collected ?: ($def->default ?? []);
                $tokenIndex = $tokenCount;
                break;
            }

            if ($tokenIndex < $tokenCount) {
                $result[$def->name] = $tokens[$tokenIndex];
                ++$tokenIndex;
            } elseif ($def->mode === ArgumentMode::Required) {
                throw new ParseException(new ParseError(
                    kind: ParseErrorKind::MissingRequiredArgument,
                    message: sprintf('Missing required argument "%s".', $def->name),
                    offendingToken: $def->name,
                ));
            } else {
                // Optional with no value — use default
                $result[$def->name] = $def->default;
            }
        }

        // Extra positional tokens beyond defined arguments
        if ($tokenIndex < $tokenCount) {
            throw new ParseException(new ParseError(
                kind: ParseErrorKind::TooManyArguments,
                message: sprintf(
                    'Too many arguments: got %d positional token(s) but command defines %d argument(s).',
                    $tokenCount,
                    $defCount,
                ),
                offendingToken: $tokens[$tokenIndex],
            ));
        }

        return $result;
    }

    /**
     * Initialise the options map with default values.
     *
     * @param list<OptionDefinition> $options
     * @return array<string, scalar|array|null>
     */
    private function initOptionDefaults(array $options): array
    {
        $defaults = [];

        foreach ($options as $opt) {
            $defaults[$opt->name] = $opt->default;
        }

        return $defaults;
    }
}
