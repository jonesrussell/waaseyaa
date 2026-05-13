<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Hydration;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Exception\InvalidConfigurationException;
use Waaseyaa\Entity\TranslatableInterface;

/**
 * Resolves a fallback chain of langcodes for translatable-field reads.
 *
 * The resolver is constructed with a `\Closure $chainFn` that, given a
 * requested langcode and the target entity, returns the ordered list of
 * langcodes the storage layer should try. The resolver itself enforces
 * the {@see NFR-002} bound on chain length and de-duplicates entries
 * lazily.
 *
 * Default chain (see {@see self::withDefaultChain()}):
 *   requested → entity default → site default → 'en'
 *
 * @api WP06 — consumed by TranslatableEntityTrait::get() and EntityRepository (WP10).
 */
final readonly class FallbackChainResolver
{
    /**
     * Hard upper bound on chain length to prevent pathological configs (NFR-002).
     *
     * Even four meaningful entries (requested, entity-default, site-default, 'en')
     * plus a custom intermediate locale or two should fit comfortably under eight.
     */
    public const int DEFAULT_MAX_CHAIN_LENGTH = 8;

    /**
     * @param \Closure(string, EntityInterface): array<int, string> $chainFn function returning the raw chain
     * @param int                                                   $maxChainLength inclusive upper bound
     */
    public function __construct(
        private \Closure $chainFn,
        private int $maxChainLength = self::DEFAULT_MAX_CHAIN_LENGTH,
    ) {}

    /**
     * Factory: build a resolver around the canonical default chain.
     *
     * The default chain yields:
     *   - the requested langcode
     *   - the entity's `defaultLangcode()` (when the entity is translatable)
     *   - the site default langcode (caller-supplied, e.g. from config['locale'])
     *   - the literal 'en' as a final terminal fallback
     *
     * Empty / null entries are filtered out before the chain is returned to
     * `resolve()`, which then de-duplicates while preserving order.
     *
     * @param string $siteDefault site-level default langcode (e.g. config['locale'] ?? 'en')
     */
    public static function withDefaultChain(string $siteDefault = 'en'): self
    {
        $chainFn = static function (string $requested, EntityInterface $entity) use ($siteDefault): array {
            $entityDefault = $entity instanceof TranslatableInterface
                ? $entity->defaultLangcode()
                : null;

            return array_values(array_filter(
                [$requested, $entityDefault, $siteDefault, 'en'],
                static fn(?string $lc): bool => $lc !== null && $lc !== '',
            ));
        };

        return new self($chainFn);
    }

    /**
     * Yield de-duplicated langcodes from the configured chain.
     *
     * The generator is lazy: callers that find a usable translation early
     * may short-circuit the iteration without materialising the rest of
     * the chain. If the raw chain exceeds {@see $maxChainLength}, throws
     * before yielding so the misconfiguration surfaces immediately.
     *
     * @return iterable<string>
     *
     * @throws InvalidConfigurationException when the configured chain length
     *                                       exceeds the bound (NFR-002).
     */
    public function resolve(string $requested, EntityInterface $entity): iterable
    {
        $chain = ($this->chainFn)($requested, $entity);
        $count = \count($chain);

        if ($count > $this->maxChainLength) {
            throw InvalidConfigurationException::fallbackChainTooLong(
                $count,
                $this->maxChainLength,
            );
        }

        $seen = [];
        foreach ($chain as $lc) {
            if (!isset($seen[$lc])) {
                $seen[$lc] = true;
                yield $lc;
            }
        }
    }
}
