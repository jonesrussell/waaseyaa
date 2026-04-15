<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Client;

use PDO;

/**
 * SQLite-backed response cache for North Cloud API calls.
 *
 * Keyed by URL. Cache table is created lazily on first use.
 */
final class NorthCloudCache
{
    private bool $tableEnsured = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $ttl = 3600,
    ) {}

    public function get(string $key): ?string
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare(
            'SELECT response_body FROM nc_api_cache WHERE cache_key = :key AND expires_at > :now',
        );
        $stmt->execute(['key' => $key, 'now' => time()]);
        $result = $stmt->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    public function set(string $key, string $value): void
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO nc_api_cache (cache_key, response_body, expires_at) VALUES (:key, :body, :expires)',
        );
        $stmt->execute([
            'key' => $key,
            'body' => $value,
            'expires' => time() + $this->ttl,
        ]);
    }

    public function clear(): void
    {
        $this->ensureTable();
        $this->pdo->exec('DELETE FROM nc_api_cache');
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS nc_api_cache ('
            . 'cache_key TEXT PRIMARY KEY, '
            . 'response_body TEXT NOT NULL, '
            . 'expires_at INTEGER NOT NULL'
            . ')',
        );
        $this->tableEnsured = true;
    }
}
