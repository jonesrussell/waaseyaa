<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\Parser\ParsedInput;

/**
 * Concrete CliIO implementation backed by ParsedInput, CliOutput writers,
 * and a StdinSource.
 *
 * This class fulfils the contract defined in contracts/cli-io.md and the
 * CliIO interface declared in WP02.
 * @api
 */
final class ConsoleCliIO implements CliIO
{
    public function __construct(
        private readonly ParsedInput $input,
        private readonly CliOutput $stdout,
        private readonly CliOutput $stderr,
        private readonly StdinSource $stdin,
        private readonly bool $verbose,
    ) {}

    // --- Argument & option access ---

    public function argument(string $name): string|int|float|bool|array|null
    {
        return $this->input->arguments[$name] ?? null;
    }

    public function option(string $name): string|int|float|bool|array|null
    {
        return $this->input->options[$name] ?? null;
    }

    /**
     * @return array<string, scalar|array|null>
     */
    public function arguments(): array
    {
        return $this->input->arguments;
    }

    /**
     * @return array<string, scalar|array|null>
     */
    public function options(): array
    {
        return $this->input->options;
    }

    // --- Output ---

    public function writeln(string $line = ''): void
    {
        $this->stdout->writeln($line);
    }

    public function write(string $text): void
    {
        $this->stdout->write($text);
    }

    public function error(string $line): void
    {
        $this->stderr->writeln($line);
    }

    // --- Prompts ---

    public function ask(string $question, ?string $default = null): ?string
    {
        if (!$this->stdin->isInteractive()) {
            $this->stderr->writeln(
                sprintf('waaseyaa-cli: stdin is not a tty; using default for prompt "%s"', $question),
            );

            return $default;
        }

        $this->stderr->write($question . ' ');
        $answer = $this->stdin->readLine();

        if ($answer === null || $answer === '') {
            return $default;
        }

        return $answer;
    }

    public function confirm(string $question, bool $default = false): bool
    {
        if (!$this->stdin->isInteractive()) {
            $this->stderr->writeln(
                sprintf('waaseyaa-cli: stdin is not a tty; using default for prompt "%s"', $question),
            );

            return $default;
        }

        $this->stderr->write($question . ' ');
        $answer = $this->stdin->readLine();

        if ($answer === null || $answer === '') {
            return $default;
        }

        return match (strtolower(trim($answer))) {
            'y', 'yes' => true,
            'n', 'no' => false,
            default => $default,
        };
    }

    // --- Flags ---

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function isInteractive(): bool
    {
        return $this->stdin->isInteractive();
    }
}
