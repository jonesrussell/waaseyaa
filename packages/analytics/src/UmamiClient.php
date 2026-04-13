<?php

declare(strict_types=1);

namespace Waaseyaa\Analytics;

/**
 * Fire-and-forget backend Umami event sender.
 *
 * Uses the same file_get_contents + stream_context pattern as NorthCloudClient.
 * Calls are synchronous with a short timeout — no-op when not configured.
 */
final class UmamiClient
{
    private string $hostname;

    public function __construct(
        private readonly string $trackerUrl,
        private readonly string $siteId,
        string $appUrl,
    ) {
        $this->hostname = (string) (parse_url($appUrl, PHP_URL_HOST) ?: $appUrl);
    }

    public function send(string $event, array $data = [], string $url = '/'): void
    {
        if ($this->trackerUrl === '' || $this->siteId === '') {
            return;
        }

        $payload = json_encode([
            'payload' => [
                'hostname' => $this->hostname,
                'language' => 'en',
                'referrer' => '',
                'screen'   => '',
                'title'    => '',
                'url'      => $url,
                'website'  => $this->siteId,
                'name'     => $event,
                'data'     => $data,
            ],
            'type' => 'event',
        ]);

        if ($payload === false) {
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nUser-Agent: waaseyaa-server/1.0",
                'content'       => $payload,
                'timeout'       => 2,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents(rtrim($this->trackerUrl, '/') . '/api/send', false, $context);
    }
}
