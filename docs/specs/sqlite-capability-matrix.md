# SQLite Capability Matrix (Schema Evolution v2)

**Status:** Implementation reference, paired with `packages/foundation/src/Schema/Compiler/Sqlite/SqliteCapabilityMatrix.php`. Companion to `docs/specs/schema-evolution-v2.md` §5 (compiler contract).

**Scope:** What each SQLite version checkpoint can and cannot do for the v1 schema-diff compiler. Where the answer depends on `PlanPolicy.allowDestructive`, both columns are shown.

---

## Why a matrix

`SqliteCapabilities` carries a small set of version-derived boolean flags. The matrix below documents how each flag is set per checkpoint, and which compiler diagnostics each version triggers for ops it cannot natively support. Drift between this table and `SqliteCapabilityMatrix` is a code smell — re-run the compiler tests after touching either side.

---

## Version checkpoints

| SQLite | Released | `supportsRenameColumn` | `supportsDropColumn` | Notes |
|--------|----------|------------------------|----------------------|-------|
| 3.0    | 2004     | ❌                     | ❌                   | Baseline. `RENAME TO` (table) supported; nothing else mutable. |
| 3.21   | 2017-10  | ❌                     | ❌                   | Last pre-rename-column line. Many legacy installs sit here. |
| 3.25   | 2018-09  | ✅                     | ❌                   | `ALTER TABLE … RENAME COLUMN` lands. |
| 3.35   | 2021-03  | ✅                     | ✅                   | `ALTER TABLE … DROP COLUMN` lands. |
| 3.40   | 2022-11  | ✅                     | ✅                   | CI default and stable mid-2022 baseline. |
| 3.50   | 2025     | ✅                     | ✅                   | Current upstream as of mission #529. |

`foreignKeysEnabled` is informational and defaults to `false`; v1 of the SQLite compiler refuses every FK op regardless (see Q6 below).

---

## Op behaviour by version + policy

| Op             | Default policy                          | `allowDestructive: true`                   | Version gate                                  |
|----------------|-----------------------------------------|--------------------------------------------|-----------------------------------------------|
| `AddColumn`    | Translates                              | Translates                                 | None.                                         |
| `AddIndex`     | Translates                              | Translates                                 | None.                                         |
| `RenameTable`  | Translates                              | Translates                                 | None.                                         |
| `RenameColumn` | Translates on ≥ 3.25                    | Translates on ≥ 3.25                       | `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25` on < 3.25. |
| `AlterColumn`  | Refused (`ALTER_COLUMN_UNSUPPORTED_SQLITE_V1`, Q5) | Refused (same)                  | Not version-gated; v1 refuses everywhere.     |
| `DropColumn`   | Refused (`DESTRUCTIVE_OP_BLOCKED`)      | Translates to `DROP COLUMN`                | None at compile time. SQLite < 3.35 will fail at apply time — operator owns version compat once they opt in. |
| `DropIndex`    | Refused (`DESTRUCTIVE_OP_BLOCKED`)      | Translates to `DROP INDEX`                 | None.                                         |
| `AddForeignKey`/`DropForeignKey` | Refused (`FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`, Q6) | Refused (same)         | Not version-gated; v1 refuses everywhere.     |

---

## Why some gates live at compile time and others don't

The compiler's job is to be a pure function. It refuses ops it cannot represent (Q5 / Q6) at compile time so dry-run / verify mode catches the problem before any DB call. Version-specific gates that the operator can sidestep with explicit consent (the destructive paths) intentionally do *not* gate at compile time: spec §11 makes "you opted in to destruction" the load-bearing promise, and adding an extra version check would create a second, redundant rejection surface.

The one version-gated *non-destructive* op is `RenameColumn`, where there is no operator-opt-in equivalent and the operation simply does not exist in older SQLite. Catching it at compile time rather than letting SQLite raise its own error yields a stable, greppable diagnostic code that operator runbooks can match on.

---

## Future expansion

When new SQLite versions add capabilities (e.g. a hypothetical 3.55 in-place `ALTER COLUMN`), update both this matrix and `SqliteCapabilityMatrix`. The gate inside `SqliteCompiler` is keyed off the boolean flags, so flipping a flag automatically flips the behaviour. Stable diagnostic codes in `SqliteDiagnosticCode` MUST NOT be repurposed — add new cases when the gate logic shifts.

Cross-dialect work (MySQL / Postgres compilers) belongs in sibling matrices alongside the eventual `MysqlCapabilityMatrix` / `PostgresCapabilityMatrix` files. Each platform's quirks stay close to its compiler.
