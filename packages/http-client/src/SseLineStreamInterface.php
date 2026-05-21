<?php

declare(strict_types=1);

namespace Waaseyaa\HttpClient;

/**
 * Thin abstraction over a server-sent event (SSE) byte stream.
 *
 * Implementations yield raw text lines from the HTTP response body one at a
 * time.  Callers (e.g. {@see \Waaseyaa\CLI\Command\Ai\AiRunCommand}) drive the
 * SSE protocol parsing on top of these lines.
 *
 * @api
 */
interface SseLineStreamInterface
{
    /**
     * Open a streaming GET connection to $url and return a generator that
     * yields each line of the response body as a string (without the trailing
     * newline).
     *
     * The generator MUST terminate naturally when the server closes the
     * connection.  Implementations SHOULD also honour a "close" signal so
     * that callers can abort mid-stream (e.g. on SIGINT) without waiting for
     * the server to close first.
     *
     * @param array<string, string> $headers Additional request headers.
     * @return \Generator<int, string, null, void>
     *
     * @throws HttpRequestException If the connection cannot be established.
     */
    public function lines(string $url, array $headers = []): \Generator;
}
