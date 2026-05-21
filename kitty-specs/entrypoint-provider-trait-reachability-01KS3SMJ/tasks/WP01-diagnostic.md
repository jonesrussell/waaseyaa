---
work_package_id: WP01
title: Diagnostic — Determine Trait-Member Reachability Failure Mechanism
dependencies: []
requirement_refs:
- FR-001
- FR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
- T005
<<<<<<< HEAD
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "734161"
=======
>>>>>>> kitty/mission-m006-translation-hardening-01KS3RY9-lane-a
history:
- date: '2026-05-20T23:57:25Z'
  author: tasks-materializer
  note: Initial WP file created
authoritative_surface: kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/research/
execution_mode: planning_artifact
owned_files:
- kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/research/wp01-diagnosis.md
tags: []
---

# WP01 — Diagnostic: Determine Trait-Member Reachability Failure Mechanism

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Worktree**: Allocated from `lanes.json` at runtime. Run `spec-kitty agent action implement WP01 --agent <name>` to enter the lane.

## Objective

Determine exactly which of the three hypotheses explains why 31 dead-code baseline entries persist for three traits despite those traits carrying class-level `@api` PHPDoc. **No code changes in this WP.** The deliverable is a written diagnosis at `kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/research/wp01-diagnosis.md`.

## Context

After Bucket 3 of the Phase 3 dead-code cleanup (PR #1503/#1504), the baseline still contains 31 entries across:

| Trait | Entries | Kind |
|---|---:|---|
| `packages/entity/src/RevisionableEntityTrait.php` | 17 | All `Property … is never read` |
| `packages/testing/src/Traits/InteractsWithApi.php` | 9 | 1 property + 8 method `Unused` |
| `packages/testing/src/Traits/RefreshDatabase.php` | 5 | 1 property + 4 method `Unused` |

The custom `WaaseyaaEntrypointProvider` at `tools/phpstan/WaaseyaaEntrypointProvider.php` has `hasApiPhpDoc()` (line 148) which checks for `@api` on the declaring class. All three traits carry class-level `@api`. Yet the 31 entries survive.

Three hypotheses for the failure:

- **(a) Scanner miss**: `RevisionableEntityTrait` is not populated into `entitySupportingTraits` by `loadEntitySupportingTraits` because the entity classes that use it (Node, Article) are not found by the scanner's globs.
- **(b) declaringClass is the trait itself**: shipmonk's callback passes the trait as `$property->getDeclaringClass()`. `isEntrypointClass` is called with the trait's own FQCN. The `entitySupportingTraits` lookup works on the trait FQCN, so this would pass IF the trait is in that set. But `hasApiPhpDoc` checks the trait's OWN reflection — so it should fire. The question is whether `isEntrypointClass` is actually being invoked for trait-declared members.
- **(c) shipmonk skips our provider for trait-declared members entirely**: The shipmonk callback infrastructure does not call `shouldMarkPropertyAsRead` / `shouldMarkMethodAsUsed` when the declaring class is a trait.
- **(d) Mixed**: Different root causes for entity traits vs testing traits.

## Key file references

- `tools/phpstan/WaaseyaaEntrypointProvider.php`:
  - Lines 71–81: `shouldMarkMethodAsUsed`
  - Lines 90–95: `shouldMarkPropertyAsRead`
  - Lines 97–100: `shouldMarkPropertyAsWritten`
  - Lines 102–138: `isEntrypointClass`
  - Lines 148–155: `hasApiPhpDoc`
  - Lines 220–274: `loadEntitySupportingTraits` — globs `packages/*/src/*.php` and `packages/*/src/Entity/*.php`
- `packages/entity/src/RevisionableEntityTrait.php` — has `@api`
- `packages/testing/src/Traits/InteractsWithApi.php` — has `@api`
- `packages/testing/src/Traits/RefreshDatabase.php` — has `@api`
- `phpstan-dead-code-baseline.neon` — current baseline

---

## Subtask T001 — Run PHPStan baseline grep and enumerate all 31 entries

**Purpose**: Get the exact list of findings. Confirm counts and member names before any code changes.

**Steps**:

1. Count current baseline entries for each of the three traits:
   ```bash
   grep -c "RevisionableEntityTrait" phpstan-dead-code-baseline.neon
   grep -c "InteractsWithApi" phpstan-dead-code-baseline.neon
   grep -c "RefreshDatabase" phpstan-dead-code-baseline.neon
   ```
   Expected: 17, 9, 5 respectively.

2. Extract the full finding text for each trait (member names, kind):
   ```bash
   grep "RevisionableEntityTrait" phpstan-dead-code-baseline.neon
   grep "InteractsWithApi" phpstan-dead-code-baseline.neon
   grep "RefreshDatabase" phpstan-dead-code-baseline.neon
   ```

3. Record all member names (properties, methods) in the diagnosis document. Note the exact `identifier` field format used by shipmonk (e.g., `deadCode.unusedProperty`, `deadCode.unusedMethod`).

4. Run a fresh PHPStan dead-code analysis to confirm the same entries still appear:
   ```bash
   vendor/bin/phpstan analyse -c phpstan-dead-code.neon 2>&1 | grep -E "(RevisionableEntityTrait|InteractsWithApi|RefreshDatabase)"
   ```
   If output is empty, the baseline is suppressing them (correct). If output shows entries, we have live findings to examine.

**Validation**:
- [ ] Have a complete list of all 31 member names across the three traits.
- [ ] Know the exact baseline entry format (for the regeneration step in WP03).

---

## Subtask T002 — Add temporary probe in provider to capture declaringClass + isTrait()

**Purpose**: Determine whether shipmonk invokes our provider's `shouldMarkPropertyAsRead` / `shouldMarkMethodAsUsed` with the trait as `declaringClass`, and whether `isTrait()` is true.

**Steps**:

1. Add temporary `error_log()` probes at the top of `shouldMarkPropertyAsRead` and `shouldMarkMethodAsUsed` in `tools/phpstan/WaaseyaaEntrypointProvider.php`:

   ```php
   protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
   {
       // TEMPORARY PROBE — remove before commit
       $dc = $property->getDeclaringClass();
       if (str_contains($dc->getName(), 'RevisionableEntityTrait')
           || str_contains($dc->getName(), 'InteractsWithApi')
           || str_contains($dc->getName(), 'RefreshDatabase')
       ) {
           error_log('[WP01-PROBE] shouldMarkPropertyAsRead: declaringClass=' . $dc->getName() . ' isTrait=' . ($dc->isTrait() ? 'true' : 'false') . ' prop=' . $property->getName());
       }
       // END PROBE
       return $this->isEntrypointClass($property->getDeclaringClass()->getName(), $property->getDeclaringClass())
           ? VirtualUsageData::withNote('Waaseyaa entrypoint property')
           : null;
   }
   ```

   Add equivalent probe to `shouldMarkMethodAsUsed` for method names matching the baseline entries.

2. Re-run PHPStan (or just the dead-code check):
   ```bash
   vendor/bin/phpstan analyse -c phpstan-dead-code.neon 2>&1 | grep WP01-PROBE
   ```

3. Interpret the output:
   - **If probe lines appear with `isTrait=true`**: shipmonk IS calling our provider for trait-declared members. The failure is inside `isEntrypointClass` — `hasApiPhpDoc` should be firing but isn't. Confirms hypothesis (b) with a bug in the check.
   - **If probe lines appear with `isTrait=false`**: declaringClass is the using class (not the trait), so `entitySupportingTraits` lookup should work — confirms scanner miss (a).
   - **If NO probe lines appear**: shipmonk does NOT call our provider for these members at all — confirms hypothesis (c), the most serious case.

4. Also probe `isEntrypointClass` by adding a targeted log when the FQCN matches any of the three traits:
   ```php
   // Inside isEntrypointClass, after the $fqcn parameter:
   if (str_contains($fqcn, 'RevisionableEntityTrait') || str_contains($fqcn, 'InteractsWithApi') || str_contains($fqcn, 'RefreshDatabase')) {
       error_log('[WP01-PROBE] isEntrypointClass called with fqcn=' . $fqcn . ' isTrait=' . ($reflection->isTrait() ? 'true' : 'false') . ' hasApiPhpDoc=' . (self::hasApiPhpDoc($reflection) ? 'true' : 'false') . ' inEntitySupportingTraits=' . (isset($this->entitySupportingTraits[$fqcn]) ? 'true' : 'false'));
   }
   ```

5. **IMPORTANT**: Remove all probes before committing this WP. Probes are diagnosis-only.

**Validation**:
- [ ] Know whether the provider's callbacks are called at all for trait-declared members.
- [ ] Know the exact value of `isTrait()` when called.
- [ ] Know whether `hasApiPhpDoc` returns true when called with the trait's reflection.
- [ ] Know whether the trait is in `entitySupportingTraits`.

---

## Subtask T003 — Verify hasApiPhpDoc fires correctly for the three traits

**Purpose**: Rule out a docblock parsing issue as a separate failure mode. Confirm `@api` is actually present and the `str_contains` check fires.

**Steps**:

1. Write a one-off PHP script to test the `hasApiPhpDoc` logic directly:

   ```bash
   php -r "
   require 'vendor/autoload.php';
   \$traits = [
       'Waaseyaa\\\\Entity\\\\RevisionableEntityTrait',
       'Waaseyaa\\\\Tests\\\\Traits\\\\InteractsWithApi',
       'Waaseyaa\\\\Tests\\\\Traits\\\\RefreshDatabase',
   ];
   foreach (\$traits as \$fqcn) {
       try {
           \$r = new ReflectionClass(\$fqcn);
           \$doc = \$r->getDocComment();
           \$hasApi = \$doc !== false && str_contains(\$doc, '@api');
           \$isTrait = \$r->isTrait();
           echo \$fqcn . ': isTrait=' . (\$isTrait ? 'true' : 'false') . ' hasApi=' . (\$hasApi ? 'true' : 'false') . PHP_EOL;
       } catch (Throwable \$e) {
           echo \$fqcn . ': ERROR: ' . \$e->getMessage() . PHP_EOL;
       }
   }
   "
   ```

   Adjust FQCNs if the test namespace for InteractsWithApi/RefreshDatabase differs (check the file headers).

2. Confirm the actual FQCN for the testing traits:
   ```bash
   head -5 packages/testing/src/Traits/InteractsWithApi.php
   head -5 packages/testing/src/Traits/RefreshDatabase.php
   ```

3. Record the exact FQCNs and whether `@api` is found.

**Validation**:
- [ ] `isTrait=true` confirmed for all three.
- [ ] `hasApi=true` confirmed for all three.
- [ ] Exact FQCNs documented in the diagnosis.

---

## Subtask T004 — Confirm loadEntitySupportingTraits populates RevisionableEntityTrait

**Purpose**: Test hypothesis (a) — determine whether the scanner glob finds Node/Article and adds RevisionableEntityTrait to the set.

**Steps**:

1. Locate entity classes that use RevisionableEntityTrait:
   ```bash
   grep -rl "RevisionableEntityTrait" packages/*/src/ 2>/dev/null
   ```
   Identify which files `use` it (these are the using classes, not the trait definition itself).

2. Check whether those using-class files match the scanner's globs:
   - `packages/*/src/*.php` — top-level src
   - `packages/*/src/Entity/*.php` — Entity subdirectory
   
   Example: `packages/node/src/Entity/Node.php` would match the second glob. `packages/node/src/Content/Article.php` would NOT match either glob if it's in a `Content/` subdirectory.

3. Write a one-off PHP script to run the scanner logic:
   ```bash
   php -r "
   require 'vendor/autoload.php';
   \$packagesDir = getcwd() . '/packages';
   \$traits = [];
   foreach (glob(\$packagesDir . '/*/src/*.php') ?: [] as \$file) {
       \$content = file_get_contents(\$file);
       if (preg_match('/\\\bextends\\\s+(EntityBase|ContentEntityBase)\\\b/', \$content) !== 1) continue;
       if (preg_match('/^namespace\\\s+([^;]+);/m', \$content, \$nsMatch) !== 1) continue;
       if (preg_match('/^\\\s*(?:final\\\s+|abstract\\\s+)?class\\\s+(\\\w+)/m', \$content, \$classMatch) !== 1) continue;
       \$fqcn = trim(\$nsMatch[1]) . '\\\\\' . \$classMatch[1];
       echo 'Found entity class: ' . \$fqcn . PHP_EOL;
       if (!class_exists(\$fqcn)) { echo '  class_exists=false, skipping' . PHP_EOL; continue; }
       \$r = new ReflectionClass(\$fqcn);
       foreach (\$r->getTraitNames() as \$t) { \$traits[\$t] = true; echo '  trait: ' . \$t . PHP_EOL; }
   }
   echo 'RevisionableEntityTrait in set: ' . (isset(\$traits['Waaseyaa\\\Entity\\\RevisionableEntityTrait']) ? 'YES' : 'NO') . PHP_EOL;
   "
   ```
   Also run with the `Entity/` glob pattern.

4. If the trait is NOT found: identify which entity class that uses it is missing from the globs (hypothesis a confirmed).

5. If the trait IS found in the set: the scanner works — the failure is hypothesis (b) or (c) from T002.

**Validation**:
- [ ] Know definitively whether `RevisionableEntityTrait` is in `entitySupportingTraits`.
- [ ] If missing: know exactly which glob pattern needs widening.
- [ ] Document the entity class(es) that use it and which directories they live in.

---

## Subtask T005 — Write wp01-diagnosis.md

**Purpose**: Consolidate findings into a written diagnosis that serves as the design handoff for WP02.

**Steps**:

1. Create `kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/research/wp01-diagnosis.md`.

2. Include the following sections:

   ### Section 1: Full 31-Entry Inventory
   Table of all 31 findings: trait name, member name, member kind (property/method), baseline entry text.

   ### Section 2: Probe Output
   Raw output from T002's `error_log` probes. Answer: "Is our provider called for these members? With what declaringClass FQCN and isTrait() value?"

   ### Section 3: hasApiPhpDoc Verification
   Output from T003's php -r script. Confirm `@api` is present and `str_contains` fires for each trait.

   ### Section 4: Scanner Coverage for RevisionableEntityTrait
   Output from T004. Is the trait in `entitySupportingTraits`? Which entity classes were found?

   ### Section 5: Confirmed Hypothesis
   State which of (a)/(b)/(c)/(d) is confirmed. Provide a one-paragraph plain-English explanation.

   ### Section 6: Precise Code Lines Requiring Change
   List exact file + line numbers in `tools/phpstan/WaaseyaaEntrypointProvider.php` that need modification.

   ### Section 7: WP02 Design Instruction
   One paragraph describing:
   - The method name and signature for the fix (`isTraitWithApiPhpDoc`)
   - Where it is called from (`shouldMarkPropertyAsRead`, `shouldMarkPropertyAsWritten`, `shouldMarkMethodAsUsed`)
   - Whether the `loadEntitySupportingTraits` scanner also needs widening (only if hypothesis a confirmed)
   - Whether the fix path is the same for entity traits vs testing traits, or requires two code paths

3. Remove all temporary probes from `tools/phpstan/WaaseyaaEntrypointProvider.php` before committing this WP.

4. Commit the diagnosis file. Commit message prefix: `tasks(M-E):`.

**Definition of Done for WP01**:
- `research/wp01-diagnosis.md` is committed and contains all 7 sections.
- All probe code removed from `WaaseyaaEntrypointProvider.php`.
- The diagnosis provides an unambiguous instruction for WP02's implementer.

**Validation**:
- [ ] `research/wp01-diagnosis.md` exists and has all 7 sections.
- [ ] No probe code left in `tools/phpstan/WaaseyaaEntrypointProvider.php`.
- [ ] The confirmed hypothesis is one of (a)/(b)/(c)/(d).
- [ ] WP02 design instruction is specific enough that an implementer can act without re-reading this WP.

---

## Definition of Done

- `kitty-specs/entrypoint-provider-trait-reachability-01KS3SMJ/research/wp01-diagnosis.md` committed with all 7 sections.
- All temporary probe code removed from `tools/phpstan/WaaseyaaEntrypointProvider.php`.
- Commit message uses `tasks(M-E):` prefix.
- Diagnosis confirms exactly one hypothesis variant (a/b/c/d) and provides unambiguous WP02 design instruction.

## Risks

- Probe output may not appear if PHPStan caches analysis. Run with `--no-progress --debug` or clear the cache first: `rm -rf /tmp/phpstan*`.
- Some `class_exists()` calls in the scanner require autoload — ensure `vendor/autoload.php` is available and `composer install` has run.
- The exact FQCN for testing traits may differ from assumed values; always check file headers.

## Reviewer Guidance

- Confirm diagnosis covers all 31 entries (not just a sample).
- Confirm probes were removed from source.
- Verify the WP02 design instruction is actionable without further investigation.
- Check that hypothesis (d) (mixed) was specifically tested — entity trait and testing traits may have different root causes.
<<<<<<< HEAD

## Activity Log

- 2026-05-21T00:26:09Z – claude:sonnet:implementer:implementer – shell_pid=706738 – Started implementation via action command
- 2026-05-21T00:42:51Z – claude:sonnet:implementer:implementer – shell_pid=706738 – Diagnosis at research/wp01-diagnosis.md; hypothesis (d) mixed confirmed; WP02 design instruction written. Root cause: PHPStan NodeScopeResolver short-circuits Trait_ nodes before InClassNode, so ReflectionBasedMemberUsageProvider is never invoked for trait files. Fix: override getUsages() to handle Node\Stmt\Trait_ directly for @api-tagged traits.
- 2026-05-21T00:44:47Z – claude:opus-4-7:reviewer:reviewer – shell_pid=734161 – Started review via action command
- 2026-05-21T00:45:38Z – claude:opus-4-7:reviewer:reviewer – shell_pid=734161 – Diagnosis sound: hypothesis (d) confirmed by empirical probe + vendor source read. InClassNode-only dispatch in ReflectionBasedMemberUsageProvider.getUsages (line 32-49) verified; ProvidedUsagesCollector::getNodeType returns Node::class so Stmt\Trait_ branch is reachable. Private createMethodUsage/createPropertyUsage visibility constraint correctly flagged with inline-copy resolution path. Unified Trait_+@api fix covers all 31 entries.
=======
>>>>>>> kitty/mission-m006-translation-hardening-01KS3RY9-lane-a
