# Phase 0 Research — Native CLI Kernel

**Mission**: `native-cli-kernel-01KR2NR7`
**Date**: 2026-05-08

This document records the decisions reached during planning interrogation
and the supporting reasoning. Every `[NEEDS CLARIFICATION]` from `spec.md`
is resolved below.

---

## R-01 · Handler shape

**Decision**: `CommandDefinition::$handler` is typed as `\Closure(CliIO):int`. Providers MAY pass a closure directly, OR a `[ClassFqn::class, 'methodName']` pair which `CommandDefinition` normalises via `\Closure::fromCallable(...)` after asking the container to resolve the class. The kernel never calls `new` — class instantiation always goes through the container so constructor injection works.

**Rationale**:
- Mirrors the existing Waaseyaa pattern: ServiceProvider classes register everything via the container; commands shouldn't be the exception.
- Keeps `CommandDefinition` immutable (the closure is a `readonly` field after normalisation).
- Permits inline closure handlers for tests and one-off commands without ceremony.
- DI symmetry: handlers can declare service dependencies via constructor and read them in the method body — same shape every other dispatched callable in the framework uses.

**Alternatives considered**:
- *Closures only.* Rejected — providers would have to wire dependencies into the closure manually, breaking constructor-injection symmetry.
- *Invokable-class-only (`__invoke`).* Rejected — forces 1:1 class-per-command; Make/Optimize/Queue groupings benefit from one class with several methods.

## R-02 · Argv parser semantics (the supported subset)

**Decision**: The parser supports the subset documented in spec §5/FR-002 and below. Anything outside this list is unsupported and produces a parse error.

**Supported**:
- Required and optional positional arguments.
- One trailing `array`-mode positional that collects remaining tokens.
- `--long-option`, `-s` short option.
- Modes: `NONE` (boolean true if present), `REQUIRED` (must have a value), `OPTIONAL` (value optional; bare presence yields `null`), `ARRAY` (accumulates list), `NEGATABLE` (toggleable via `--no-foo`).
- `--key=value` and `--key value` are equivalent for `REQUIRED`-mode and `OPTIONAL`-mode options.
- Stacked short flags **only for `NONE`-mode**: `-abc` ≡ `-a -b -c`. `REQUIRED`/`OPTIONAL`/`ARRAY` short flags must stand alone (`-f bar` or `-f=bar`).
- `--` end-of-options sentinel: every token after `--` is treated as positional.
- `--no-foo` toggles a `NEGATABLE` `--foo` option to `false`.
- Repeated `ARRAY`-mode option (`--tag=a --tag=b`) accumulates.
- Default values applied to absent options.

**Explicitly NOT supported** (parse error or static-analysis-time rejection):
- Glued short-option values like `-fbar` for `REQUIRED`-mode flags. This Symfony Console quirk is rare in shipped commands and ambiguous with stacked NONE flags.
- Symfony's `InputDefinition` aliases beyond a single short form per option.
- Auto-completion descriptors (out of scope; deferred to a possible `waaseyaa/cli-completion` package).

**Rationale**: The shipped command surface has been audited (145 first-party files); none rely on the unsupported features. Keeping the parser strict makes the test matrix bounded and the error messages clear.

## R-03 · Exit-code contract

**Decision**:
| Code | Meaning |
|---|---|
| `0` | Success. |
| `1` | Handler-reported failure (the handler returned `1`, threw a domain exception caught by the kernel, or exited with `1`). |
| `2` | Parse error: unknown command, unknown option, missing required argument, type-coercion failure (e.g. `--limit=abc` for an int-typed option). |
| `64`–`78` | Reserved for future use (sysexits.h-style); kernel never emits in this range today. |
| `130` | Process interrupted via SIGINT (Ctrl-C). Set by PHP's signal handling, propagated by the kernel. |

**Rationale**: Aligns with POSIX/sysexits.h conventions and with the existing exit-code expectations in operator scripts that call Symfony Console commands today (which already emit `0`/`1`/`2`). No regression for downstream automation.

**Verification**: Snapshot tests in `packages/cli/tests/Integration/Snapshot/` assert exit code byte-equality against pre-cut Symfony output for every public command.

## R-04 · `CommandTester` replacement

**Decision**: A new `Waaseyaa\Cli\Testing\CliTester` exposes:

```php
final class CliTester
{
    public static function for(
        CommandDefinition $definition,
        ContainerInterface $container,
        ?StdinSource $stdin = null,
    ): self;

    public function execute(array $argv): self;     // argv == ['arg1', '--opt=val', ...]
    public function executeMap(array $inputs): self; // {arg_name|opt_name => value}
    public function getExitCode(): int;
    public function getStdout(): string;
    public function getStderr(): string;
    public function getOutput(): string;             // stdout + stderr interleaved
}
```

Stdin can be injected via `StdinSource` (interface with `readLine(): ?string`); default is `EmptyStdinSource` which returns `null` immediately and triggers the non-TTY fallback for `ask`/`confirm`.

**Rationale**:
- Symfony's `CommandTester` is concrete and final-ish (since 6.x). We can't subclass it without dragging the whole runtime back in.
- A typed Waaseyaa harness lets tests inject a container — important because R-01 makes handlers DI-resolved.
- The `executeMap()` helper keeps existing tests' ergonomics (most current tests pass associative arrays).

## R-05 · TTY detection & non-TTY prompts

**Decision**: Detection priority:
1. `function_exists('posix_isatty') && posix_isatty(STDIN)`
2. `function_exists('stream_isatty') && stream_isatty(STDIN)`
3. fall back to `false` (treat as non-interactive).

When non-interactive, `CliIO::ask($question, $default)` and `CliIO::confirm($question, $default)` return the supplied default and write the line `waaseyaa-cli: stdin is not a tty; using default for prompt "<question>"` to stderr exactly once per call. They do not throw and never block.

**Rationale**: Aligns with widespread CLI conventions (npm, gh, kubectl). Strict throw-on-non-TTY would force every existing prompt-using command to declare a `--yes`/`--no-interaction` flag; out of scope. Tests can override via `CliTester::executeMap($inputs)` setting prompt answers explicitly.

## R-06 · `--help` rendering format

**Decision**: Three deterministic sections:
```
Usage:
  <command-name> [options] [--] <required_arg> [<optional_arg>] [<array_arg>...]

Description:
  <description, wrapped at 80 cols>

Arguments:
  <name>      <description>

Options:
  -s, --long[=VALUE]   <description> (default: <default_or_omitted>)
```

Render order is: arguments in declaration order, options sorted alphabetically by long name. `--help` and `--verbose` are auto-added to every command (kernel-level flags, not declared per command). `list` (the bin/waaseyaa list command) groups by `domain:` prefix.

**Rationale**: Deterministic output → snapshot-testable. Alphabetical option order eliminates a class of test flakiness tied to declaration order.

## R-07 · Performance baseline harness

**Decision**: WP-01 commits `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh`. Contents:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

CMD="${1:-list}"
RUNS="${2:-10}"

timings=()
mems=()
for _ in $(seq 1 "$RUNS"); do
  out=$(/usr/bin/env time -f "TIME=%e MEM=%M" \
        php -d opcache.enable_cli=0 bin/waaseyaa "$CMD" 2>&1 1>/dev/null \
        | tail -n1)
  timings+=("$(awk '{print $1}' <<<"$out" | cut -d= -f2)")
  mems+=("$(awk '{print $2}' <<<"$out" | cut -d= -f2)")
done

# Sort numerically and emit median + max
median() { printf '%s\n' "$@" | sort -n | awk 'BEGIN{c=0}{a[c++]=$1}END{print a[int(c/2)]}'; }
max()    { printf '%s\n' "$@" | sort -n | tail -n1; }

echo "wall_median_s=$(median "${timings[@]}")"
echo "mem_max_kb=$(max "${mems[@]}")"
```

Run as: `kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh list 10` and `… health:check 10`.

**Rationale**: Median across 10 runs filters JIT/cache noise; opcache disabled to avoid second-run distortion; both wall-time and peak memory captured. Numbers go straight into `plan.md` § Performance Baseline.

## R-08 · Per-command port grouping

**Decision**: Port WPs (06–11) grouped by domain prefix as listed in `plan.md` § WP sequencing. Each WP is independently mergeable once WP-05 lands. Each WP contains: handler files, migrated tests, and updated snapshot fixtures.

**Rationale**: Coherent review surface; bounded blast radius per merge; parallelisable across reviewer lanes; per-domain failures isolated.

## R-09 · Discovery of `HasNativeCommandsInterface` providers

**Decision**: `PackageManifestCompiler` already iterates registered `ServiceProvider` instances looking for capability interfaces; the new interface is added to the recognised list with no new manifest field. The CLI bootstrap calls a new `CliKernelServiceProvider::buildRegistry()` which iterates providers via the manifest and accumulates `CommandDefinition`s into a single `CommandRegistry` instance, cached per request.

**Rationale**: Reuses existing discovery machinery; no manifest schema migration; matches how Health checks, middleware, and policies are discovered today.

## R-10 · Removed code

**Decision**: WP-12 deletes:
- `packages/foundation/src/ServiceProvider/Capability/HasCommandsInterface.php`
- `packages/cli/src/WaaseyaaApplication.php`
- `packages/cli/src/CliCommandRegistry.php`
- The temporary dual-boot adapter branch inside `bin/waaseyaa`.
- Any `use Symfony\Component\Console\…` import in first-party non-test code.
- `symfony/console` from `packages/cli/composer.json` runtime `require`.

A `composer why symfony/console` script step in WP-14 asserts no first-party (waaseyaa/*) chain depends on it at runtime.

**Rationale**: Hard-cut per spec C-004/C-006. No permanent shim.

---

## Open questions

None. All `[NEEDS CLARIFICATION]` markers from the spec are resolved (the spec has none — it was complete on first pass).
