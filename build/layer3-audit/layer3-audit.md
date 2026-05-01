# Layer 3 Forensic Audit (Services)

Generated: 2026-04-30T17:02:26+00:00 UTC

## 1. Canonical scope

Layer 3 (Services) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: billing, github, northcloud, notification, search, seo, workflows.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

### L3-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L3 symbols with no @covers / #[CoversClass] in this layer's test tree (count). Full list: symbol_test_map_layer3.json
```json
{
    "count": 21,
    "sample_fqcn": [
        "Waaseyaa\\Billing\\FakeStripeClient",
        "Waaseyaa\\Billing\\BillingServiceProvider",
        "Waaseyaa\\GitHub\\GitHubException",
        "Waaseyaa\\NorthCloud\\Sync\\NcSyncWorker",
        "Waaseyaa\\NorthCloud\\Sync\\NcHitSupportDiagnosticsInterface",
        "Waaseyaa\\NorthCloud\\Sync\\NcHitToEntityMapperInterface",
        "Waaseyaa\\NorthCloud\\Command\\NcSyncCommand",
        "Waaseyaa\\Notification\\NotificationServiceProvider",
        "Waaseyaa\\Notification\\Job\\SendNotificationJob",
        "Waaseyaa\\Notification\\Job\\SendNotificationHandler",
        "Waaseyaa\\Notification\\NotifiableInterface",
        "Waaseyaa\\Notification\\NotificationInterface"
    ]
}
```

## 3. PHPStan (L3 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["billing","github","northcloud","notification","search","seo","workflows"],"missing_from_phpstan_neon":[]}
- PHPStan totals (this run): 0 errors, 0 files with errors (see `layer3_static_analysis.json`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer3_layer_boundary_report.json`.
Static: Waaseyaa `use` in L3 code targeting layer >3 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **6**, *Listener* classes **1**, *Attribute* classes (heuristic) **0**. See `layer3_metadata_consistency.json` for file paths.

## 6. Test coverage (@covers + #[CoversClass])

Unique FQCNs with at least one `@covers` or `#[CoversClass]`: 50
Public symbols with no indexed coverage: 21 (see coverage finding and `symbol_test_map_layer3.json`).

## 7. Hygiene

See `layer3_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in this layer’s `src/`.

## 8. Deliverable files

- `build/layer3-audit/layer3-audit.md`
- `build/layer3-audit/layer3-audit.json`
- `build/layer3-audit/public_api_layer3.json`
- `build/layer3-audit/symbol_test_map_layer3.json`
- `build/layer3-audit/layer3_layer_boundary_report.json`
- `build/layer3-audit/layer3_metadata_consistency.json`
- `build/layer3-audit/layer3_hygiene_report.txt`
- `build/layer3-audit/layer3_static_analysis.json`
