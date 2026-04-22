# C1 prerequisite — `waaseyaa/groups` evaluation gate

**Verdict (current checkout):** `waaseyaa/groups` registers **`group`** and **`group_type`** only. There is **no** membership entity, membership API, or package-owned access policy surface that competes with genealogy authorization.

## Re-evaluation threshold

Revisit the whole C1 approach **only if** `waaseyaa/groups` later ships **(a)** a first-class membership entity or API **and/or** **(b)** package-owned access policies that would conflict with genealogy’s policy story.

## v1 recommendation

Ship **dyadic `genealogy_share`** grants (`tree` + `user`) **without** a `waaseyaa/groups` dependency. Optional named recipient lists backed by `group` bundles remain a **later** product choice after the threshold check passes.
