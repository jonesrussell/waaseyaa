<?php

declare(strict_types=1);

namespace Waaseyaa\Listing\Tests\Contract\Fixtures;

use Waaseyaa\Access\Gate\PolicyAttribute;

/**
 * Test access policy that denies `view` for entities with an even-numbered id.
 *
 * The {@see \Waaseyaa\Access\Gate\Gate} resolves this policy via the
 * `#[PolicyAttribute]` binding to the `article` entity type and calls
 * `view($user, $subject)` returning bool.
 */
#[PolicyAttribute(entityType: 'article')]
final class DenyEvenIdsArticlePolicy
{
    public function view(?object $user, mixed $subject): bool
    {
        if (!is_object($subject) || !method_exists($subject, 'id')) {
            return true;
        }
        $id = $subject->id();
        if ($id === null) {
            return true;
        }

        return ((int) $id) % 2 !== 0;
    }
}
