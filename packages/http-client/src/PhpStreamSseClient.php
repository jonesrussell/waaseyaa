<?php

declare(strict_types=1);

namespace Waaseyaa\HttpClient;

/**
 * SSE line-stream consumer backed by PHP's native stream functions.
 *
 * Opens a persistent HTTP connection with `fopen()` (Accept:
 * text/event-stream) and yields each line via a generator.  The generator
 * ends when the server closes the connection or when {@see close()} is called.
 *
 * This class lives in `packages/http-client/` (Layer 0 — Foundation) and has
 * no upward layer dependencies.
 */
final class PhpStreamSseClient implements SseLineStreamInterface
{
    /** @var resource|null */
    private mixed $stream = null;

    public function __construct(
        private readonly float $timeout = 0.0,
    ) {}

    /**
     * @param array<string, string> $headers
     * @return \Generator<int, string, null, void>
     */
    public function lines(string $url, array $headers = []): \Generator
    {
        $headers = array_merge([
            'Accept' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ], $headers);

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines),
                'timeout' => $this->timeout > 0.0 ? $this->timeout : PHP_INT_MAX,
                'ignore_errors' => true,
            ],
        ]);

        $handle = @fopen($url, 'r', false, $context);
        if ($handle === false) {
            throw new HttpRequestException(
                "SSE connection failed: GET {$url}",
                $url,
                'GET',
            );
        }

        $this->stream = $handle;

        try {
            while (\is_resource($this->stream) && !feof($this->stream)) {
                $line = fgets($this->stream);
                if ($line === false) {
                    break;
                }
                yield rtrim($line, "\r\n");
            }
        } finally {
            $this->closeStream();
        }
    }

    /**
     * Close the underlying stream early (e.g. on SIGINT).
     * Safe to call multiple times.
     */
    public function close(): void
    {
        $this->closeStream();
    }

    private function closeStream(): void
    {
        if (\is_resource($this->stream)) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }
}
