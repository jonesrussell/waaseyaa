<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

use Waaseyaa\Entity\EntityTypeManager;

/**
 * Capability marker for service providers that contribute GraphQL mutation
 * argument or resolver overrides.
 *
 * Providers opt in by declaring `implements HasGraphqlMutationOverridesInterface`;
 * the GraphQL bootstrap (`Waaseyaa\GraphQL\GraphQlServiceProvider`) checks
 * `instanceof` before invoking `graphqlMutationOverrides()`, so the abstract
 * `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` no longer carries an
 * unused no-op default. Surfaces this capability the same way
 * `LanguagePathStripperInterface` surfaces optional HTTP path stripping.
 *
 * Locked in step with kernel/dispatcher call sites by
 * `packages/foundation/tests/Contract/ServiceProviderContractTest.php`
 * (mission #824 WP03 surface D).
 */
interface HasGraphqlMutationOverridesInterface
{
    /**
     * Return GraphQL mutation overrides keyed by mutation name.
     *
     * Each value is an array with optional `args` (merged with defaults) and
     * optional `resolve` (replaces the default resolver).
     *
     * @return array<string, array{args?: array<string, mixed>, resolve?: callable}>
     */
    public function graphqlMutationOverrides(EntityTypeManager $entityTypeManager): array;
}
