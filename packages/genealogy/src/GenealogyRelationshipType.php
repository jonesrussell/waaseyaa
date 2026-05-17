<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy;

/**
 * `relationship_type` values for genealogy edges.
 *
 * @see docs/specs/genealogy.md
 * @api
 */
final class GenealogyRelationshipType
{
    public const string PARENT_OF = 'genealogy_parent_of';

    public const string SPOUSE_OF = 'genealogy_spouse_of';

    public const string MEMBER_OF_FAMILY = 'genealogy_member_of_family';

    /**
     * Canonical “this user account is this person” identity edge (B2).
     *
     * Direction: `user` (from) → `genealogy_person` (to). Policy precedence
     * vs `genealogy_share` grants is documented in `docs/specs/genealogy-policy-precedence.md`.
     */
    public const string IDENTITY_OF_USER = 'genealogy_identity_of_user';

    public static function isGenealogyEdge(string $relationshipType): bool
    {
        return str_starts_with($relationshipType, 'genealogy_');
    }
}
