<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * Detects whether a stream resource is an interactive TTY.
 *
 * Detection priority (per research.md §R-05):
 * 1. posix_isatty($stream) — available on most POSIX systems.
 * 2. stream_isatty($stream) — PHP 7.2+ fallback.
 * 3. false — safest non-interactive default.
 */
final class TtyDetector
{
    /**
     * Return true if $stream is an interactive TTY.
     *
     * @param resource $stream
     */
    public static function isInteractive(mixed $stream): bool
    {
        // posix_isatty() emits a PHP warning on non-file streams (e.g. php://memory).
        // Suppress it — returning false is the correct non-TTY answer.
        if (function_exists('posix_isatty') && @posix_isatty($stream)) {
            return true;
        }

        if (function_exists('stream_isatty') && stream_isatty($stream)) {
            return true;
        }

        return false;
    }
}
