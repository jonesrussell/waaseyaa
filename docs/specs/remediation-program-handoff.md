# Remediation program handoff (M5–M8)

Authoritative summary of verification and governance surfaces for milestone chains **M5: Verification Lock-In** through **M8: Implementation Surface Alignment**. Planning issues reference this file when recording completion.

## M5 — Verification lock-in

**Verification surface**

- [`docs/public-surface-map.php`](../public-surface-map.php) — disposition map for interfaces, abstract classes, traits, and **enums** (scanner: `tests/Integration/SurfaceMap/PublicSurfaceVerificationTest.php`).
- [`PublicSurfaceVerificationTest`](../../tests/Integration/SurfaceMap/PublicSurfaceVerificationTest.php) discovers `enum` declarations in addition to interface / abstract class / trait.

**Closed engineering issues**

- **#1081** — Enum discovery in `PublicSurfaceVerificationTest` (regex includes `enum`).
- **#1082** — `SurfaceActionHandler` renamed to `SurfaceActionHandlerInterface` (`packages/admin-surface`).

**Conformance / bookkeeping (related)**

- M9 planning issues closed after M10 execution merged on `main` (batch commits D1–D5+). M11 governance specs live under `docs/specs/m11-*.md` (workflow links updated).

## M6 — Governance and discoverability

**Governance / discoverability surfaces**

- Package declaration model and compiler normalization (M10 Batch D1) in foundation discovery — see git history `exec(conformance)(M10): Batch D1`.
- Workflow governance: [`workflow.md`](workflow.md), M11 templates under `.github/ISSUE_TEMPLATE/m11-*.md`.
- Steady-state drift: [`m11-periodic-drift-scan-protocol.md`](m11-periodic-drift-scan-protocol.md), [`ops/observability/drift-detection.md`](../../ops/observability/drift-detection.md).

## M7 — Workflow and operator ergonomics

- **#1151** — Symfony `^7.3` floors audited; split packages aligned to `^7.0` with rationale in [`symfony-version-floors.md`](symfony-version-floors.md).
- **#1098** — SelfHosted vs Local defaults: roadmap documented on `SovereigntyDefaults` (queue_backend differentiation today; storage/embeddings/vector_store follow when deployments require it).

## M8 — Implementation surface alignment

- **#1100** — `AccountInterface` accessor naming (`id()` vs `get*()`): intentional breaking API; track under a dedicated v2-oriented issue before changing.
- **#1107** — Symfony facade/wrap for app code: large architectural program; not part of this handoff doc.
