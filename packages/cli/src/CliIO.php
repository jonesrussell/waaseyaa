<?php

declare(strict_types=1);

namespace Waaseyaa\CLI;

/**
 * I/O surface passed to every command handler.
 *
 * This interface is the type contract used by CommandDefinition and ArgvParser.
 * The concrete implementation (ConsoleCliIO) is delivered in WP03.
 * The Testing\CliTester harness is delivered in WP05.
 *
 * Full behavioural contract: kitty-specs/native-cli-kernel-01KR2NR7/contracts/cli-io.md
 */
interface CliIO
{
    // --- Argument & option access ---

    /**
     * Retrieve a parsed argument value by name.
     */
    public function argument(string $name): string|int|float|bool|array|null;

    /**
     * Retrieve a parsed option value by long name.
     */
    public function option(string $name): string|int|float|bool|array|null;

    /**
     * Return all parsed arguments as an associative array.
     *
     * @return array<string, scalar|array|null>
     */
    public function arguments(): array;

    /**
     * Return all parsed options as an associative array.
     *
     * @return array<string, scalar|array|null>
     */
    public function options(): array;

    // --- Output ---

    /**
     * Write a line to stdout.
     */
    public function writeln(string $line): void;

    /**
     * Write raw text to stdout (no newline appended).
     */
    public function write(string $text): void;

    /**
     * Write a line to stderr.
     */
    public function error(string $line): void;

    // --- Prompts ---

    /**
     * Ask a question and return the answer.
     * On non-TTY stdin, returns $default and emits a stderr notice.
     */
    public function ask(string $question, ?string $default = null): ?string;

    /**
     * Ask a yes/no question and return the boolean answer.
     * On non-TTY stdin, returns $default and emits a stderr notice.
     */
    public function confirm(string $question, bool $default = false): bool;

    // --- Verbose / interactive flags ---

    /**
     * Whether --verbose was passed.
     */
    public function isVerbose(): bool;

    /**
     * Whether stdin is an interactive TTY.
     */
    public function isInteractive(): bool;
}
