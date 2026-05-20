<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Waaseyaa\Foundation\Http\JsonApiResponseTrait;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

final class BroadcastRouter implements DomainRouterInterface
{
    use JsonApiResponseTrait;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_controller', '') === 'broadcast';
    }

    public function handle(Request $request): Response
    {
        $ctx = WaaseyaaContext::fromRequest($request);
        $broadcastStorage = $ctx->broadcastStorage;
        $channels = self::parseChannels($ctx->query['channels'] ?? 'admin');
        if ($channels === []) {
            $channels = ['admin'];
        }
        $logger = $this->logger ?? new NullLogger();

        $initialCursor = self::resolveInitialCursor($request, $broadcastStorage->maxId($channels));

        return new StreamedResponse(function () use ($broadcastStorage, $channels, $logger, $initialCursor): void {
            echo "event: connected\ndata: " . json_encode(['channels' => $channels], JSON_THROW_ON_ERROR) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            $cursor = $initialCursor;
            $lastKeepalive = time();

            while (connection_aborted() === 0) {
                try {
                    $messages = $broadcastStorage->poll($cursor, $channels);
                } catch (\Throwable $e) {
                    $logger->error(sprintf('SSE poll error: %s', $e->getMessage()));
                    echo "event: error\ndata: " . json_encode(['message' => 'Broadcast poll failed'], JSON_THROW_ON_ERROR) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    usleep(5_000_000);
                    continue;
                }

                foreach ($messages as $msg) {
                    $cursor = $msg['id'];
                    try {
                        // Emit `id:` so EventSource sends Last-Event-ID on reconnect,
                        // letting the resume path above pick up from this exact point.
                        $frame = sprintf(
                            "id: %d\nevent: %s\ndata: %s\n\n",
                            $msg['id'],
                            $msg['event'],
                            json_encode($msg, JSON_THROW_ON_ERROR),
                        );
                        echo $frame;
                    } catch (\JsonException $e) {
                        $logger->error(sprintf('SSE json_encode error for event %s: %s', $msg['event'], $e->getMessage()));
                    }
                }

                if ($messages !== []) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                if ((time() - $lastKeepalive) >= 15) {
                    echo ": keepalive\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    $lastKeepalive = time();
                }

                usleep(500_000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Resolve the starting cursor for a new SSE connection.
     *
     * Resume from the EventSource `Last-Event-ID` header when the client sent
     * one (auto-reconnect path) so no events are missed. Otherwise begin at
     * the supplied high-water mark — new connections do NOT receive history.
     */
    public static function resolveInitialCursor(Request $request, int $highWaterMark): int
    {
        $lastEventId = $request->headers->get('Last-Event-ID');
        if ($lastEventId !== null && ctype_digit($lastEventId)) {
            return (int) $lastEventId;
        }

        return $highWaterMark;
    }

    /**
     * @return list<string>
     */
    private static function parseChannels(string $channelsParam): array
    {
        if ($channelsParam === '') {
            return [];
        }

        $channels = array_map('trim', explode(',', $channelsParam));

        return array_values(array_filter($channels, static fn(string $ch): bool => $ch !== ''));
    }
}
