#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cleanup() {
    if [[ -n "${PHP_PID:-}" ]]; then
        kill "$PHP_PID" 2>/dev/null || true
    fi
    if [[ -n "${ADMIN_PID:-}" ]]; then
        kill "$ADMIN_PID" 2>/dev/null || true
    fi
}

resolve_admin_path() {
    local configured_path

    if [[ -n "${WAASEYAA_ADMIN_PATH:-}" ]]; then
        configured_path="${WAASEYAA_ADMIN_PATH}"
    else
        configured_path="$(php -r '$json = json_decode((string) file_get_contents($argv[1]), true); $path = $json["extra"]["waaseyaa"]["admin_path"] ?? ""; if (is_string($path)) { echo $path; }' "$PROJECT_ROOT/composer.json")"
    fi

    if [[ -z "$configured_path" ]]; then
        return 1
    fi

    if [[ "${configured_path:0:1}" != "/" ]]; then
        configured_path="${PROJECT_ROOT}/${configured_path}"
    fi

    if [[ ! -f "${configured_path}/package.json" ]]; then
        return 1
    fi

    echo "$configured_path"
}

backend_host="${APP_HOST:-127.0.0.1}"
backend_port="${APP_PORT:-8080}"
backend_url="http://${backend_host}:${backend_port}"

echo "Starting Waaseyaa backend at ${backend_url}"
PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}" vendor/bin/waaseyaa serve &
PHP_PID=$!

ADMIN_PID=""
if admin_path="$(resolve_admin_path)"; then
    export NUXT_BACKEND_URL="${NUXT_BACKEND_URL:-$backend_url}"
    echo "Starting admin dev server from ${admin_path} (HMR enabled)"
    vendor/bin/waaseyaa admin:dev &
    ADMIN_PID=$!
else
    echo "Admin dev server not configured; running backend only."
    echo "Set WAASEYAA_ADMIN_PATH to a Nuxt admin package to enable HMR."
fi

trap cleanup EXIT INT TERM

if [[ -n "${ADMIN_PID}" ]]; then
    wait -n "$PHP_PID" "$ADMIN_PID"
else
    wait "$PHP_PID"
fi
