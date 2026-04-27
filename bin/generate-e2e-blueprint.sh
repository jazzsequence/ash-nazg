#!/usr/bin/env bash
# Generate E2E test blueprint by substituting CI secrets into the template.
# The machine token is base64-encoded before substitution so any special
# characters (quotes, backslashes, etc.) cannot break the JSON string.
#
# Required env vars: PANTHEON_MACHINE_TOKEN, PANTHEON_SITE_UUID
#
# Usage (CI): bash bin/generate-e2e-blueprint.sh
# Output: tests/e2e/blueprint.generated.json (gitignored)

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TEMPLATE="${REPO_ROOT}/tests/e2e/blueprint.json"
OUTPUT="${REPO_ROOT}/tests/e2e/blueprint.generated.json"

if [ -z "${PANTHEON_MACHINE_TOKEN:-}" ]; then
  echo "Error: PANTHEON_MACHINE_TOKEN is not set." >&2
  exit 1
fi

if [ -z "${PANTHEON_SITE_UUID:-}" ]; then
  echo "Error: PANTHEON_SITE_UUID is not set." >&2
  exit 1
fi

# Base64-encode the token so any special characters cannot break the JSON string.
# PHP decodes it at runtime via base64_decode().
export PANTHEON_MACHINE_TOKEN_B64
PANTHEON_MACHINE_TOKEN_B64="$(printf '%s' "$PANTHEON_MACHINE_TOKEN" | base64 | tr -d '\n')"

# PANTHEON_SITE_UUID is a UUID (hex + hyphens) — safe to substitute directly.
# The restricted variable list prevents substitution of other ${...} in the JSON.
envsubst '${PANTHEON_MACHINE_TOKEN_B64} ${PANTHEON_SITE_UUID}' \
  < "$TEMPLATE" > "$OUTPUT"

echo "Blueprint generated: ${OUTPUT}"
