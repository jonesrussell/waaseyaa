<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Ingestion;

final class SourceAdapterNormalizer
{
    private const DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    private const NORMALIZATION_MESSAGE = 'Normalized source_uri to canonical form.';

    /**
     * @param array{raw_source_uri:string,adapter_type:string,ownership:string,synthetic_flag:bool,batch_id:string,ingested_at:string,parser_version:?string,metadata?:array<string,mixed>} $payload
     * @return array{provenance:array<string,mixed>,diagnostics:list<array<string,mixed>>,metadata:array<string,mixed>}
     */
    public function normalize(array $payload): array
    {
        $rawUri = (string) ($payload['raw_source_uri'] ?? '');
        $normalizedUri = $rawUri;
        $steps = [];
        $changed = false;

        if ($rawUri !== '') {
            $normalizedUri = $this->normalizeUri($rawUri, $steps, $changed);
        }

        $sourceId = hash('sha256', $normalizedUri);
        $provenance = [
            'source_id' => $sourceId,
            'source_uri' => $normalizedUri,
            'adapter_type' => (string) ($payload['adapter_type'] ?? ''),
            'ownership' => (string) ($payload['ownership'] ?? ''),
            'synthetic_flag' => (bool) ($payload['synthetic_flag'] ?? false),
            'batch_id' => (string) ($payload['batch_id'] ?? ''),
            'ingested_at' => (string) ($payload['ingested_at'] ?? ''),
            'parser_version' => $payload['parser_version'] ?? null,
        ];

        $diagnostics = [];
        if ($changed) {
            $diagnostics[] = [
                'code' => 'adapter.normalized_uri',
                'message' => self::NORMALIZATION_MESSAGE,
                'location' => '/source_uri',
                'item_index' => null,
                'context' => [
                    'original_uri' => $rawUri,
                    'normalized_uri' => $normalizedUri,
                    'normalization_steps' => $steps,
                ],
            ];
        }

        $metadata = $this->normalizeMetadata((array) ($payload['metadata']['adapter_extra'] ?? []));

        return [
            'provenance' => $provenance,
            'diagnostics' => $diagnostics,
            'metadata' => [
                'adapter_extra' => $metadata,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, scalar>
     */
    private function normalizeMetadata(array $metadata): array
    {
        $filtered = [];
        foreach ($metadata as $key => $value) {
            if (is_scalar($value)) {
                $filtered[$key] = $value;
            }
        }

        ksort($filtered);
        return $filtered;
    }

    private function normalizeUri(string $uri, array &$steps, bool &$changed): string
    {
        $components = parse_url($uri);
        if ($components === false) {
            return $uri;
        }

        $scheme = strtolower($components['scheme'] ?? '');
        if (($components['scheme'] ?? '') !== '') {
            if ($components['scheme'] !== $scheme) {
                $steps[] = 'scheme_lowercased';
                $changed = true;
            }
        } elseif ($scheme === '') {
            $scheme = 'http';
        }

        $host = strtolower((string) ($components['host'] ?? ''));
        if (($components['host'] ?? '') !== '' && $components['host'] !== $host) {
            $steps[] = 'host_lowercased';
            $changed = true;
        }

        $port = $components['port'] ?? null;
        if ($port !== null && isset(self::DEFAULT_PORTS[$scheme]) && self::DEFAULT_PORTS[$scheme] === (int) $port) {
            $steps[] = 'default_port_stripped';
            $changed = true;
            $port = null;
        }

        $path = $components['path'] ?? '/';
        $normalizedPath = $this->normalizePath($path, $steps, $changed);

        [$query, $querySteps, $queryChanged] = $this->normalizeQuery((string) ($components['query'] ?? ''));
        if ($queryChanged) {
            array_push($steps, ...$querySteps);
            $changed = true;
        }

        if (($components['fragment'] ?? '') !== '') {
            $steps[] = 'fragment_stripped';
            $changed = true;
        }

        $normalizedUri = $this->rebuildUri($scheme, $host, $port, $normalizedPath, $query);
        if ($normalizedUri !== $uri) {
            $changed = true;
        }

        return $normalizedUri;
    }

    private function normalizePath(string $path, array &$steps, bool &$changed): string
    {
        $collapsed = preg_replace('#/+#', '/', $path) ?? $path;
        if ($collapsed !== $path) {
            $steps[] = 'path_collapsed';
            $changed = true;
        }

        $normalized = $this->uppercasePercentEncoding($collapsed);
        if ($normalized !== $collapsed) {
            $steps[] = 'percent_encoded_upper';
            $changed = true;
        }

        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
            $steps[] = 'trailing_slash_removed';
            $changed = true;
        }

        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * @return array{string,string[],bool}
     */
    private function normalizeQuery(string $query): array
    {
        if ($query === '') {
            return ['', [], false];
        }

        $pairs = [];
        $tokens = explode('&', $query);
        $skipped = false;

        foreach ($tokens as $index => $token) {
            if ($token === '') {
                continue;
            }
            [$rawKey, $rawValue] = $this->splitQueryToken($token);
            if ($rawValue === '') {
                $skipped = true;
                continue;
            }
            $pairs[] = [
                'key' => $this->uppercasePercentEncoding($rawKey),
                'value' => $this->uppercasePercentEncoding($rawValue),
                '_index' => $index,
            ];
        }

        if ($pairs === []) {
            return ['', $skipped ? ['empty_query_param_removed'] : [], true];
        }

        $originalKeys = array_map(static fn(array $pair): string => (string) $pair['key'], $pairs);
        usort(
            $pairs,
            static fn(array $left, array $right): int => $left['key'] === $right['key']
                ? $left['_index'] <=> $right['_index']
                : strcmp($left['key'], $right['key']),
        );

        $sortedKeys = array_map(static fn(array $pair): string => (string) $pair['key'], $pairs);
        $steps = [];
        if ($originalKeys !== $sortedKeys) {
            $steps[] = 'query_sorted';
        }
        if ($skipped) {
            $steps[] = 'empty_query_param_removed';
        }

        $encoded = implode('&', array_map(
            static fn(array $pair): string => $pair['key'] . '=' . $pair['value'],
            $pairs,
        ));

        $changed = $steps !== [] || $encoded !== $query;

        return [$encoded, $steps, $changed];
    }

    private function splitQueryToken(string $token): array
    {
        $parts = explode('=', $token, 2);
        $key = $parts[0];
        $value = $parts[1] ?? '';
        return [$key, $value];
    }

    private function uppercasePercentEncoding(string $value): string
    {
        return preg_replace_callback(
            '/%[0-9a-fA-F]{2}/',
            static fn(array $match): string => strtoupper($match[0]),
            $value,
        ) ?? $value;
    }

    private function rebuildUri(string $scheme, string $host, ?int $port, string $path, string $query): string
    {
        $uri = $scheme . '://' . $host;
        if ($port !== null) {
            $uri .= ':' . $port;
        }
        $uri .= $path;
        if ($query !== '') {
            $uri .= '?' . $query;
        }
        return $uri;
    }
}
