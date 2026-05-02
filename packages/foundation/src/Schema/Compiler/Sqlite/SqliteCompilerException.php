<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Sqlite;

/**
 * Compile-time error from the SQLite compiler.
 *
 * Carries a stable {@see SqliteDiagnosticCode} alongside the human
 * message. WP05's validation gates wrap and re-frame these into the
 * operator-diagnostics surface; until then, callers (CLI, executor)
 * read the structured `diagnosticCode()` and present the message.
 *
 * **Production safety:** message strings MUST NOT include filesystem
 * paths or other host-specific data (see spec §7.3). Pass only the op
 * kind, table/column names from the diff, and version strings. The
 * compiler is platform-pure — it never sees a filesystem.
 */
final class SqliteCompilerException extends \RuntimeException
{
    public function __construct(
        private readonly SqliteDiagnosticCode $diagnosticCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function diagnosticCode(): SqliteDiagnosticCode
    {
        return $this->diagnosticCode;
    }
}
