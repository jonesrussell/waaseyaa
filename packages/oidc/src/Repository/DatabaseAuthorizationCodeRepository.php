<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Repository;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Database\DBALDatabase;

/**
 * DBAL-backed authorization code repository.
 *
 * Single-use is guaranteed by a single-statement atomic UPDATE with affected-rows
 * check: `UPDATE ... SET consumed_at = ? WHERE code = ? AND consumed_at IS NULL
 * AND expires_at > ?`. Concurrent consume() calls race on this one statement;
 * exactly one observes a non-zero affected-rows count.
 */
final class DatabaseAuthorizationCodeRepository implements AuthorizationCodeRepositoryInterface
{
    private const TABLE = 'oidc_authorization_codes';

    public const TTL_SECONDS = 60;

    /** @var \Closure():int */
    private readonly \Closure $clock;

    private bool $tableEnsured = false;

    /**
     * @param (\Closure():int)|null $clock Clock returning Unix timestamp; defaults to time().
     */
    public function __construct(
        private readonly DBALDatabase $database,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    public function issue(
        string $clientId,
        AccountInterface $account,
        string $redirectUri,
        array $scopes,
        string $codeChallenge,
        string $codeChallengeMethod,
    ): AuthorizationCode {
        $this->ensureTable();

        $now = ($this->clock)();
        $expiresAt = $now + self::TTL_SECONDS;

        $code = bin2hex(random_bytes(32));
        $accountId = (string) $account->id();
        $scopesJson = json_encode(array_values($scopes), JSON_THROW_ON_ERROR);

        $this->database->insert(self::TABLE)
            ->values([
                'code' => $code,
                'client_id' => $clientId,
                'account_id' => $accountId,
                'redirect_uri' => $redirectUri,
                'scopes' => $scopesJson,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'issued_at' => $now,
                'expires_at' => $expiresAt,
                'consumed_at' => null,
            ])
            ->execute();

        return new AuthorizationCode(
            code: $code,
            clientId: $clientId,
            accountId: $accountId,
            redirectUri: $redirectUri,
            scopes: array_values($scopes),
            codeChallenge: $codeChallenge,
            codeChallengeMethod: $codeChallengeMethod,
            issuedAt: $now,
            expiresAt: $expiresAt,
            consumedAt: null,
        );
    }

    public function consume(string $code): ?AuthorizationCode
    {
        $this->ensureTable();

        $now = ($this->clock)();

        $affected = $this->database->getConnection()->executeStatement(
            'UPDATE ' . self::TABLE . ' SET consumed_at = ? WHERE code = ? AND consumed_at IS NULL AND expires_at > ?',
            [$now, $code, $now],
        );

        if ($affected === 0) {
            return null;
        }

        $row = $this->fetchByCode($code);
        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function purgeExpired(): int
    {
        $this->ensureTable();

        return $this->database->delete(self::TABLE)
            ->condition('expires_at', ($this->clock)(), '<=')
            ->execute();
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $this->database->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS oidc_authorization_codes (
                code VARCHAR(128) PRIMARY KEY,
                client_id VARCHAR(255) NOT NULL,
                account_id VARCHAR(255) NOT NULL,
                redirect_uri TEXT NOT NULL,
                scopes TEXT NOT NULL,
                code_challenge VARCHAR(128) NOT NULL,
                code_challenge_method VARCHAR(16) NOT NULL,
                issued_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                consumed_at INTEGER
            )
        SQL);

        $this->database->query(
            'CREATE INDEX IF NOT EXISTS idx_oidc_auth_codes_expires_at ON oidc_authorization_codes (expires_at)',
        );
        $this->database->query(
            'CREATE INDEX IF NOT EXISTS idx_oidc_auth_codes_client_id ON oidc_authorization_codes (client_id)',
        );

        $this->tableEnsured = true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchByCode(string $code): ?array
    {
        foreach ($this->database->select(self::TABLE)->condition('code', $code)->execute() as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AuthorizationCode
    {
        $scopes = json_decode((string) $row['scopes'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($scopes)) {
            $scopes = [];
        }
        /** @var list<string> $scopes */
        $scopes = array_values(array_map('strval', $scopes));

        return new AuthorizationCode(
            code: (string) $row['code'],
            clientId: (string) $row['client_id'],
            accountId: (string) $row['account_id'],
            redirectUri: (string) $row['redirect_uri'],
            scopes: $scopes,
            codeChallenge: (string) $row['code_challenge'],
            codeChallengeMethod: (string) $row['code_challenge_method'],
            issuedAt: (int) $row['issued_at'],
            expiresAt: (int) $row['expires_at'],
            consumedAt: isset($row['consumed_at']) ? (int) $row['consumed_at'] : null,
        );
    }
}
