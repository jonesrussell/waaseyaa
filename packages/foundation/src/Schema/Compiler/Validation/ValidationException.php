<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Schema\Compiler\Validation;

/**
 * Base for compile-time validation failures across all platform compilers.
 *
 * Carries a stable string `diagnosticCode` (sourced from
 * {@see ValidationDiagnosticCode} or a platform enum like
 * {@see \Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteDiagnosticCode})
 * alongside the human-readable message. Callers that need the typed
 * code can match on the concrete subclass (e.g.
 * `instanceof DestructiveOpBlockedException`); cross-cutting tools that
 * just need the string (logs, dashboards, CI greps) read
 * {@see diagnosticCode} directly.
 *
 * **Production safety:** subclasses MUST NOT include filesystem paths,
 * connection strings, or other host-specific data in the message (spec
 * §7.3). The compiler is platform-pure — it never sees a filesystem.
 */
abstract class ValidationException extends \RuntimeException
{
    public function __construct(
        public readonly string $diagnosticCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
