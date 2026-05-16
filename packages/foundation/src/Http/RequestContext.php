<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http;

/**
 * Immutable per-request state consumed by the cache package's `ContextResolver`
 * to compute deterministic canonical strings for cache-key contributions.
 *
 * Per `docs/specs/listing-pipeline-v1.md` C-005, the foundation package is the
 * canonical home for the per-request state surface — Layer 0 siblings (cache,
 * listing, …) read it without an upward dependency. The class intentionally
 * has no behaviour beyond accessors so resolvers in higher-stability packages
 * can pin to a value-object shape.
 *
 * The accessor names are pinned by `contracts/context-architecture.md`:
 * - `roles()` — list of role IDs the current account holds. ORDER IS NOT
 *   GUARANTEED at this layer; the consuming resolver sorts before joining.
 * - `accountId()` — integer account ID, or `null` for anonymous.
 * - `activeLangcode()` — active **content** langcode (BCP-47 shape) or `null`.
 * - `interfaceLangcode()` — active **interface** langcode or `null`.
 * - `getQueryParams()` — URL query parameters as an associative array, with
 *   values URL-decoded once at request-construction time.
 *
 * Determinism (FR-037): the same state in two PHP workers MUST produce the
 * same resolver output. This class is `final` and `readonly` to make that
 * guarantee structural.
 *
 * @api
 */
final readonly class RequestContext
{
    /**
     * @param  list<string>          $roles            role IDs held by the current account
     * @param  int|null              $accountId        integer account ID; `null` for anonymous
     * @param  string|null           $activeLangcode   active content langcode (BCP-47), or `null`
     * @param  string|null           $interfaceLangcode active interface langcode, or `null`
     * @param  array<string, string> $queryParams      URL-decoded query parameters
     */
    public function __construct(
        private array $roles = [],
        private ?int $accountId = null,
        private ?string $activeLangcode = null,
        private ?string $interfaceLangcode = null,
        private array $queryParams = [],
    ) {}

    /**
     * Role IDs held by the current account.
     *
     * Order is not guaranteed — the resolver sorts before joining.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * Integer account ID, or `null` for anonymous.
     */
    public function accountId(): ?int
    {
        return $this->accountId;
    }

    /**
     * Active content langcode (BCP-47 shape) for the current request.
     */
    public function activeLangcode(): ?string
    {
        return $this->activeLangcode;
    }

    /**
     * Active interface langcode for the current request.
     */
    public function interfaceLangcode(): ?string
    {
        return $this->interfaceLangcode;
    }

    /**
     * URL query parameters, URL-decoded.
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }
}
