# Track 4 — Schema evolution sprint sequence

**Anchor:** [#1305](https://github.com/waaseyaa/framework/issues/1305) — explicit `deriveColumnSpec()` mappings for `text_long` / `uri` / `entity_reference`, tests, optional logging on unknown types, spec table, vendor/FK nuance and `FieldStorage` context (folded from closed duplicate [#1314](https://github.com/waaseyaa/framework/issues/1314)).

**Goal for #1305:** A deterministic, explicit mapping layer that removes silent fallthroughs for those types (see issue body).

## Dependency order (execute in this order)

| Order | Issue | Role |
|------:|-------|------|
| 1 | **#1305** | Anchor — column derivation contract; other Track 4 work assumes correct, explicit specs. |
| 2 | **#1286** | Package-level migrations via `extra.waaseyaa.migrations` (structural convention). |
| 3 | **#529** | Schema evolution v2.0 / diffing baseline (epic-scale; follows concrete migration + column behavior). |
| 4 | **#1310** | Deploy / RP003 verify-tag-parity noise (after baseline schema work is clearer). |
| — | *(optional)* | New follow-ups filed after #1305 lands if scope splits. |

**Closed (not in queue):** #1314 — duplicate of #1305.

**Open issues in this milestone (snapshot):** #529, #1286, #1305, #1310 — all carry or should carry Track 4 milestone; use label `sprint-candidate` on the slice you are actively pulling (see GitHub).

## After #1305 merges — enrich pipeline

Re-run so snapshots and clusters stay aligned with reality:

```bash
gh issue list --repo waaseyaa/framework --state open --limit 500 \
  --json number,title,body,url,milestone,labels,assignees,updatedAt \
  > docs/audits/github-issues-open-snapshot.json

python3 tools/github-issue-backlog-enrich.py
```

That refreshes [github-issues-triage-enriched.csv](github-issues-triage-enriched.csv) / [.md](github-issues-triage-enriched.md) and the GraphQL “merged PR” column before heavy work on Tracks 1–3.

## Next consolidation wave (do not start until Track 4 sprint is underway)

Defer mixing with schema evolution:

- Remediation **M5–M7** clusters  
- **PHPDoc** / @covers families  
- **Hydration** edge-case clusters  

## Hygiene (keep as routine)

```bash
cd /path/to/waaseyaa && ./bin/check-milestones
gh issue list --repo waaseyaa/framework --state open --search "no:milestone" --limit 200
```

Expect: no `WARNING` lines from `check-milestones`; zero `no:milestone` hits.
