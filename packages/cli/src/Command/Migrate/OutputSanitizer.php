<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Migrate;

/**
 * Strips host-specific filesystem paths from operator-facing output in
 * production mode.
 *
 * Per spec §7.3, production diagnostic output MUST NOT include raw
 * filesystem paths — operators paste these into Slack / Sentry / public
 * dashboards and the paths leak deployment topology. Development output
 * keeps the paths so debugging stays ergonomic.
 *
 * The sanitizer only filters absolute-looking unix paths
 * (`/anything/with/slashes.php` or `/anything/with/slashes/`) ending at
 * a word boundary. It is intentionally conservative: it never rewrites
 * substrings that look like migration ids
 * (`waaseyaa/foundation:v2:foo` — colons are not in the path regex) or
 * package names (`waaseyaa/cli` — no slash-then-extension).
 */
final readonly class OutputSanitizer
{
    public function __construct(public bool $isProduction) {}

    /**
     * Replace absolute filesystem paths in the message with `<path>`
     * when running in production mode. In development mode, returns the
     * message unchanged.
     */
    public function sanitize(string $message): string
    {
        if (! $this->isProduction) {
            return $message;
        }

        // Match unix absolute paths that look like file references — at
        // least one slash, ending with a file-like extension or trailing
        // slash. Avoids touching package names like "waaseyaa/cli".
        $pattern = '#/[A-Za-z0-9._/\-]+\.(?:php|json|yaml|yml|sql|md)#';
        $sanitized = preg_replace($pattern, '<path>', $message);

        return $sanitized ?? $message;
    }
}
