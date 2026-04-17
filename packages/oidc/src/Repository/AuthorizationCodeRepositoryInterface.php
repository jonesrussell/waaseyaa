<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Repository;

use Waaseyaa\Access\AccountInterface;

/**
 * Persistence port for OIDC authorization codes.
 *
 * Codes are short-lived (60 s per OAuth 2.1) and single-use. Atomicity of consume()
 * is part of the contract: a second consume() for the same code MUST return null,
 * even under concurrent callers.
 *
 * Alternate backends (Redis, in-memory) are allowed; the /token endpoint depends
 * only on this interface.
 */
interface AuthorizationCodeRepositoryInterface
{
    /**
     * Issue a fresh code bound to the given client, account, redirect URI, scopes,
     * and PKCE challenge. Returns the persisted AuthorizationCode with its generated
     * code, issuedAt, and expiresAt fields.
     *
     * @param list<string> $scopes
     */
    public function issue(
        string $clientId,
        AccountInterface $account,
        string $redirectUri,
        array $scopes,
        string $codeChallenge,
        string $codeChallengeMethod,
    ): AuthorizationCode;

    /**
     * Atomically consume a code. Returns the stored code on the first successful
     * consume, or null if the code does not exist, is already consumed, or has
     * expired. Callers still validate client binding, redirect URI, and PKCE
     * verifier against the returned object.
     */
    public function consume(string $code): ?AuthorizationCode;

    /**
     * Remove codes whose expiresAt is in the past. Returns the number of rows
     * removed. Safe to call from a scheduled job.
     */
    public function purgeExpired(): int;
}
