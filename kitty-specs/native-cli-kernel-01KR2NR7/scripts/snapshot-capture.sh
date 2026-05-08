#!/usr/bin/env bash
# snapshot-capture.sh — Capture pre-cut CLI snapshot fixtures for byte-equality assertions.
# Usage: ./snapshot-capture.sh [<dest-dir>]
#   dest-dir defaults to packages/cli/tests/Fixtures/snapshots (relative to project root)
# Must be run from the project root (directory containing composer.json).
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

DEST="${1:-packages/cli/tests/Fixtures/snapshots}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

export WAASEYAA_SNAPSHOT=1

echo "==> Fetching command list from php packages/cli/bin/waaseyaa list..."
# Parse command names: lines that start with exactly two spaces then a command name
# (excludes group headings which have no leading spaces, and option lines which start with -)
COMMAND_LIST=$( \
  php packages/cli/bin/waaseyaa list --no-ansi 2>/dev/null \
  | grep -E '^  [a-z]' \
  | awk '{print $1}' \
  | grep -v '^$' \
  | sort -u \
)

if [[ -z "$COMMAND_LIST" ]]; then
  echo "ERROR: Could not parse any command names from bin/waaseyaa list" >&2
  exit 1
fi

cmd_count=$(echo "$COMMAND_LIST" | wc -l | tr -d ' ')
echo "==> Commands discovered: $cmd_count"
echo ""

# Always-safe no-arg commands (deterministic, read-only)
SAFE_NO_ARG_COMMANDS=(
  "health:check"
  "schema:list"
  "entity-type:list"
  "event:list"
  "permission:list"
  "route:list"
  "list"
  "about"
)

mkdir -p "$DEST"

captured=0
while IFS= read -r cmd; do
  [[ -z "$cmd" ]] && continue
  # Replace : with __ for filename safety
  safe_name="${cmd//:/__}"

  echo "  Capturing: $cmd --help"

  # Capture --help for every command; suppress stderr noise from boot warnings
  set +e
  WAASEYAA_SNAPSHOT=1 php packages/cli/bin/waaseyaa "$cmd" --help --no-ansi \
    >"$TMP_DIR/${safe_name}.help.stdout" \
    2>"$TMP_DIR/${safe_name}.help.stderr"
  echo $? >"$TMP_DIR/${safe_name}.help.exit"
  set -e

  cp "$TMP_DIR/${safe_name}.help.stdout" "$DEST/${safe_name}.help.stdout"
  cp "$TMP_DIR/${safe_name}.help.stderr" "$DEST/${safe_name}.help.stderr"
  cp "$TMP_DIR/${safe_name}.help.exit"   "$DEST/${safe_name}.help.exit"

  captured=$((captured + 1))

  # Capture no-arg run for safe read-only commands
  for safe_cmd in "${SAFE_NO_ARG_COMMANDS[@]}"; do
    if [[ "$cmd" == "$safe_cmd" ]]; then
      echo "  Capturing: $cmd (no-arg)"
      set +e
      WAASEYAA_SNAPSHOT=1 php packages/cli/bin/waaseyaa "$cmd" --no-ansi \
        >"$TMP_DIR/${safe_name}.noarg.stdout" \
        2>"$TMP_DIR/${safe_name}.noarg.stderr"
      echo $? >"$TMP_DIR/${safe_name}.noarg.exit"
      set -e
      cp "$TMP_DIR/${safe_name}.noarg.stdout" "$DEST/${safe_name}.noarg.stdout"
      cp "$TMP_DIR/${safe_name}.noarg.stderr" "$DEST/${safe_name}.noarg.stderr"
      cp "$TMP_DIR/${safe_name}.noarg.exit"   "$DEST/${safe_name}.noarg.exit"
      break
    fi
  done

done <<<"$COMMAND_LIST"

echo ""
echo "==> Captured $captured commands to $DEST"
echo ""

# Audit: verify every command has a .help.stdout fixture
echo "==> Auditing fixture completeness..."
missing=0
while IFS= read -r cmd; do
  [[ -z "$cmd" ]] && continue
  safe_name="${cmd//:/__}"
  if [[ ! -f "$DEST/${safe_name}.help.stdout" ]]; then
    echo "  MISSING: $DEST/${safe_name}.help.stdout" >&2
    missing=$((missing + 1))
  fi
done <<<"$COMMAND_LIST"

if [[ $missing -gt 0 ]]; then
  echo "ERROR: $missing commands are missing snapshot fixtures" >&2
  exit 1
fi

fixture_count=$(find "$DEST" -name '*.help.stdout' | wc -l | tr -d ' ')
echo "==> Audit complete: $fixture_count .help.stdout fixtures, 0 missing."
echo "==> Snapshot capture finished successfully."
