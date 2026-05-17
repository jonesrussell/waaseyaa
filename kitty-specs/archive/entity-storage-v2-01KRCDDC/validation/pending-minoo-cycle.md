# Pending Minoo Cycle — Deferred Operational Tasks

Mission: `entity-storage-v2-01KRCDDC` (M-001)
WP: WP11
Filed: 2026-05-12
Status: Open — awaiting live Minoo rollout cycle

---

## Why these tasks are deferred

WP11 as originally scoped included four operational tasks (T057–T060) that require
work in the `waaseyaa-minoo` repository and a 7-day production monitoring window.
Neither can be completed in the framework mission session:

1. **Separate repository** — Minoo's entity types live in `waaseyaa-minoo`, not in
   this monorepo. Touching Minoo files from a Waaseyaa worktree is out of scope for
   this mission and would create a cross-repo dependency in the PR.

2. **Real-time clock** — T060 requires 7 calendar days of production monitoring with
   no related incidents. That clock starts only after T058 (staging promotion) and
   cannot be simulated or short-circuited in a code review session.

WP11 merged with the framework test suite fully green (7693+ tests). The upgrade
guide (T062) documents the recipe that T057–T059 will follow. T061 (lessons
capture) will be completed after T060 closes.

---

## Deferred tasks

### T057 — Generate Minoo teaching migration

**What:** Run `bin/waaseyaa make:storage-migration teaching` in the Minoo working
copy (`/home/jones/dev/minoo`) and commit the emitted migration file to the
`waaseyaa-minoo` repo.

**Exit criteria (close T057 when ALL are true):**

- [ ] Migration file emitted without error (exit code 0).
- [ ] All `teaching` fields present in `up()` — including `community_id`,
  `category_id`, `published_at` and any others declared in Minoo's `teaching`
  `EntityType`.
- [ ] Indexed columns (`community_id`, `category_id`, `published_at`) have `ADD
  INDEX` statements.
- [ ] Revision table (`teaching_revision`) is emitted because `teaching` is
  revisionable.
- [ ] `down()` drops typed columns without touching `_data`.
- [ ] Row-count assertion is present and matches fixture data.

---

### T058 — Apply migration in dev + staging

**What:** Apply the T057 migration in Minoo's dev environment, verify, then promote
to staging.

**Exit criteria (close T058 when ALL are true):**

- [ ] `bin/waaseyaa migrate` completes without error in dev.
- [ ] Schema matches expected column types per spec §8.2.
- [ ] Backfill round-trip: data read via `EntityStorageCoordinator` after migration
  is byte-identical to pre-migration values for all `teaching` fields.
- [ ] Query parity: existing queries (filter by `community_id`, `category_id`,
  `published_at`) return identical result sets to pre-migration baseline.
- [ ] Revision insertion: a new `save()` after migration creates a row in
  `teaching_revision`.
- [ ] All of the above verified on staging after promotion.
- [ ] Validation log committed to
  `kitty-specs/entity-storage-v2-01KRCDDC/validation/teaching-migration-log.md`.

---

### T059 — Annotate indexed fields in Minoo teaching EntityType

**What:** Update Minoo's `teaching` `EntityType` definition to declare
`FieldDefinition::indexed()` on `community_id`, `category_id`, and `published_at`;
set `primaryStorageBackend: 'sql-column'`; confirm `revisionable: true`.

**Exit criteria (close T059 when ALL are true):**

- [ ] Minoo's `teaching` `EntityType` declares `revisionable: true`.
- [ ] `primaryStorageBackend` is set to `'sql-column'`.
- [ ] `community_id`, `category_id`, and `published_at` field definitions call
  `indexed()`.
- [ ] `entityKeys['revision']` is present and non-empty.
- [ ] `bin/check-package-layers` and `bin/check-composer-policy` pass in the Minoo
  repo after the change.

---

### T060 — Production rollout + 7-day monitoring

**What:** Deploy the migration to Minoo production and monitor for 7 calendar days
with no related incidents.

**Exit criteria (close T060 when ALL are true):**

- [ ] Migration applied to production without error.
- [ ] No P1/P2 incidents related to entity storage, `teaching` queries, or revision
  writes in the 7-day window.
- [ ] No `backfill_mismatch` log entries.
- [ ] No `outcome=partial_save` log entries on `entity.lifecycle`.
- [ ] 7-day window start date recorded here: ___________
- [ ] 7-day window end date recorded here: ___________

---

## T061 — Capture WP11 lessons (depends on T060)

After T060 closes, capture any lessons from the Minoo rollout cycle in the upgrade
guide (§9 of `docs/upgrades/waaseyaa-alpha-X-to-Y.md`) or in a dedicated
`teaching-migration-lessons.md` file under this directory.

**Exit criteria:**

- [ ] T060 is closed.
- [ ] Any production surprises, query behaviour differences, or migration tool
  shortcomings are documented.
- [ ] The §9 "Lessons from the first Minoo rollout" section in the upgrade guide
  is updated from "pending" to a concrete summary.

---

## Post-merge follow-up note

WP11 was merged with T057–T061 open by design. The scope reduction was agreed at
the start of the session: the framework test suite is the validation gate for this
PR; the Minoo live cycle is a follow-on operational gate that runs in a separate
context.

When you start the Minoo cycle:

1. Check out a branch in `waaseyaa-minoo`.
2. Follow the recipe in `docs/upgrades/waaseyaa-alpha-X-to-Y.md` §3–§4.
3. Work through the exit criteria above in order: T057 → T058 → T059 → T060 → T061.
4. Update this file with dates and close each task as exit criteria are met.
5. After T061: mark WP11 fully done in Spec Kitty (`spec-kitty agent tasks
   mark-status T057 T058 T059 T060 T061 --status done --mission
   entity-storage-v2-01KRCDDC`).
