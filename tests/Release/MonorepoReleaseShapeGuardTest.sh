#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT="${ROOT_DIR}/bin/check-monorepo-release-shape"
cd "${ROOT_DIR}"

bad_revision="18d84b30c8ff7a061fc1b3df80986f6f861be344"
good_revision="HEAD"

if "${SCRIPT}" "${bad_revision}" >/tmp/waaseyaa-release-shape-bad.out 2>&1; then
  echo "FAIL: expected ${bad_revision} to fail the monorepo release-shape guard"
  exit 1
fi

if ! "${SCRIPT}" "${good_revision}" >/tmp/waaseyaa-release-shape-good.out 2>&1; then
  echo "FAIL: expected ${good_revision} to pass the monorepo release-shape guard"
  cat /tmp/waaseyaa-release-shape-good.out
  exit 1
fi

echo "PASS: monorepo release-shape guard rejects rewrite commit and accepts restored monorepo HEAD"
