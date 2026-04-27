#!/usr/bin/env bash
# Generate E2E test blueprint by substituting CI secrets into the template.
# Uses envsubst so secrets are never exposed in the process list (ps aux).
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

# envsubst reads secrets from the environment — they never appear in argv.
# The quoted list restricts substitution to only these two variables,
# preventing accidental expansion of other ${...} expressions in the JSON.
envsubst '${PANTHEON_MACHINE_TOKEN} ${PANTHEON_SITE_UUID}' \
  < "$TEMPLATE" > "$OUTPUT"

echo "Blueprint generated: ${OUTPUT}"
