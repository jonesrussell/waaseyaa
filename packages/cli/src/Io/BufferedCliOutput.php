<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * Captures output into an internal string buffer.
 *
 * Used by CliTester to capture stdout and stderr independently.
 */
final class BufferedCliOutput implements CliOutput
{
    private string $buffer = '';

    public function write(string $text): void
    {
        $this->buffer .= $text;
    }

    public function writeln(string $text = ''): void
    {
        $this->buffer .= $text . "\n";
    }

    /**
     * Return all captured bytes.
     */
    public function getContents(): string
    {
        return $this->buffer;
    }

    /**
     * Reset the buffer. Called by CliTester between execute() calls.
     */
    public function reset(): void
    {
        $this->buffer = '';
    }
}
