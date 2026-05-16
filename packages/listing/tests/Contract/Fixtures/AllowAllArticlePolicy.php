<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Contract\Fixtures;

use Waaseyaa\Access\Gate\PolicyAttribute;

/**
 * Default test policy: allows every op on every `article` entity.
 *
 * The resolver contract suite registers this when a test doesn't supply its
 * own restrictive policy — without it, `Gate::allows()` returns false for
 * unrecognised abilities and every row gets filtered out.
 */
#[PolicyAttribute(entityType: 'article')]
final class AllowAllArticlePolicy
{
    public function view(?object $user, mixed $subject): bool
    {
        return true;
    }

    public function update(?object $user, mixed $subject): bool
    {
        return true;
    }

    public function delete(?object $user, mixed $subject): bool
    {
        return true;
    }

    public function translate(?object $user, mixed $subject): bool
    {
        return true;
    }
}
