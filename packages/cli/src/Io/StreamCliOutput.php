<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * Writes output to a PHP stream resource (e.g. STDOUT, STDERR).
 */
final class StreamCliOutput implements CliOutput
{
    /** @param resource $stream */
    public function __construct(private readonly mixed $stream) {}

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function writeln(string $text = ''): void
    {
        fwrite($this->stream, $text . "\n");
    }
}
