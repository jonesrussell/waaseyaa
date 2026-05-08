<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * Output writer interface for CLI commands.
 *
 * Implementations write to a stream (stdout, stderr) or capture to a buffer.
 */
interface CliOutput
{
    /**
     * Write raw text (no newline appended).
     */
    public function write(string $text): void;

    /**
     * Write text followed by a newline.
     */
    public function writeln(string $text = ''): void;
}
