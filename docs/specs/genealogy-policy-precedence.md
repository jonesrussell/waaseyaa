# Genealogy policy precedence (B2 identity vs C1 grants)

When both an **identity link** (`genealogy_identity_of_user` from `user` → `genealogy_person`) and a **`genealogy_share`** grant could apply, evaluation order must be **deterministic**:

1. **Admin / emergency deny** (if the product introduces such a layer).
2. **Identity link** — the account’s own person row; governs “this is me” edit semantics.
3. **`genealogy_share` grant** — collaborator visibility within the owner’s tree.
4. **Tree defaults** — owner workspace visibility and published / workflow flags.
5. **Deny** when no rule allows the operation.

Adjust numbering as implementations evolve, but **publish one matrix** before expanding C1 logic beyond dyadic grants.
