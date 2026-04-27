#!/usr/bin/env bash
# Generate E2E test blueprint by substituting CI secrets into the template.
# The machine token is base64-encoded before substitution so any special
# characters (quotes, backslashes, etc.) cannot break the JSON string.
#
# Required env vars: PANTHEON_MACHINE_TOKEN, PANTHEON_SITE_UUID, PANTHEON_ENV_NAME
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

# Default to dev if no specific env is provided (e.g. local runs).
export PANTHEON_ENV_NAME="${PANTHEON_ENV_NAME:-dev}"

# Base64-encode the token so any special characters cannot break the JSON string.
# PHP decodes it at runtime via base64_decode().
export PANTHEON_MACHINE_TOKEN_B64
PANTHEON_MACHINE_TOKEN_B64="$(printf '%s' "$PANTHEON_MACHINE_TOKEN" | base64 | tr -d '\n')"

# Single quotes are intentional: envsubst receives literal variable name strings,
# not shell-expanded values. SC2016 suppressed for this reason.
# shellcheck disable=SC2016
envsubst '${PANTHEON_MACHINE_TOKEN_B64} ${PANTHEON_SITE_UUID} ${PANTHEON_ENV_NAME}' \
  < "$TEMPLATE" > "$OUTPUT"

echo "Blueprint generated: ${OUTPUT} (env: ${PANTHEON_ENV_NAME})"
