#!/usr/bin/env bash
# Manage the E2E multidev environment via the Pantheon Public API.
# Creates the multidev on PR open/sync; deletes it on PR close.
#
# Reads credentials from the environment — never from argv — so they
# do not appear in the process table (ps aux / /proc/<pid>/cmdline).
#
# Required env vars: PANTHEON_MACHINE_TOKEN, PANTHEON_SITE_UUID
# Usage:
#   PANTHEON_MACHINE_TOKEN=... PANTHEON_SITE_UUID=... \
#     bash bin/manage-e2e-multidev.sh create|delete ENV_NAME

set -euo pipefail

ACTION="${1:-}"
ENV_NAME="${2:-}"

if [ -z "$ACTION" ] || [ -z "$ENV_NAME" ]; then
  echo "Usage: $0 create|delete ENV_NAME" >&2
  echo "Required env vars: PANTHEON_MACHINE_TOKEN, PANTHEON_SITE_UUID" >&2
  exit 1
fi

if [ -z "${PANTHEON_MACHINE_TOKEN:-}" ]; then
  echo "Error: PANTHEON_MACHINE_TOKEN is not set." >&2
  exit 1
fi

if [ -z "${PANTHEON_SITE_UUID:-}" ]; then
  echo "Error: PANTHEON_SITE_UUID is not set." >&2
  exit 1
fi

API="https://api.pantheon.io/v0"
SITE_ID="$PANTHEON_SITE_UUID"

# ── Auth ─────────────────────────────────────────────────────────────────────

echo "Authenticating with Pantheon API..."
SESSION_TOKEN=$(curl -sf -X POST "${API}/authorize/machine-token" \
  -H "Content-Type: application/json" \
  -d "{\"machine_token\": \"${PANTHEON_MACHINE_TOKEN}\", \"client\": \"ash-nazg-ci\"}" \
  | jq -r '.session')

if [ -z "$SESSION_TOKEN" ] || [ "$SESSION_TOKEN" = "null" ]; then
  echo "Error: Failed to obtain session token." >&2
  exit 1
fi

AUTH=(-H "Authorization: Bearer ${SESSION_TOKEN}")

# ── Poll workflow to completion ───────────────────────────────────────────────

poll_workflow() {
  local workflow_id="$1"
  local max_attempts=60   # 10 minutes
  local attempt=0

  echo "Polling workflow ${workflow_id}..."
  while [ "$attempt" -lt "$max_attempts" ]; do
    attempt=$((attempt + 1))
    local result
    result=$(curl -sf "${API}/sites/${SITE_ID}/workflows/${workflow_id}" \
      "${AUTH[@]}" | jq -r '.result // "running"')

    echo "  Attempt ${attempt}: ${result}"

    case "$result" in
      succeeded) echo "Workflow succeeded."; return 0 ;;
      failed)    echo "Error: Workflow failed." >&2; return 1 ;;
    esac

    sleep 10
  done

  echo "Error: Workflow timed out after $((max_attempts * 10))s." >&2
  return 1
}

# ── Create ────────────────────────────────────────────────────────────────────

if [ "$ACTION" = "create" ]; then
  HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" \
    "${API}/sites/${SITE_ID}/environments/${ENV_NAME}" \
    "${AUTH[@]}" 2>/dev/null || echo "000")

  if [ "$HTTP_CODE" = "200" ]; then
    echo "Multidev '${ENV_NAME}' already exists — skipping creation."
    exit 0
  fi

  echo "Creating multidev '${ENV_NAME}' cloned from dev..."
  WORKFLOW_ID=$(curl -sf -X POST "${API}/sites/${SITE_ID}/environments" \
    "${AUTH[@]}" \
    -H "Content-Type: application/json" \
    -d "{\"name\": \"${ENV_NAME}\", \"clone_env\": \"dev\"}" \
    | jq -r '.id')

  if [ -z "$WORKFLOW_ID" ] || [ "$WORKFLOW_ID" = "null" ]; then
    echo "Error: Multidev creation returned no workflow ID." >&2
    exit 1
  fi

  poll_workflow "$WORKFLOW_ID"
  echo "Multidev '${ENV_NAME}' is ready."
  exit 0
fi

# ── Delete ────────────────────────────────────────────────────────────────────

if [ "$ACTION" = "delete" ]; then
  HTTP_CODE=$(curl -sf -o /dev/null -w "%{http_code}" \
    "${API}/sites/${SITE_ID}/environments/${ENV_NAME}" \
    "${AUTH[@]}" 2>/dev/null || echo "000")

  if [ "$HTTP_CODE" != "200" ]; then
    echo "Multidev '${ENV_NAME}' does not exist — nothing to delete."
    exit 0
  fi

  echo "Deleting multidev '${ENV_NAME}'..."
  WORKFLOW_ID=$(curl -sf -X DELETE \
    "${API}/sites/${SITE_ID}/environments/${ENV_NAME}" \
    "${AUTH[@]}" | jq -r '.id')

  if [ -z "$WORKFLOW_ID" ] || [ "$WORKFLOW_ID" = "null" ]; then
    echo "Error: Multidev deletion returned no workflow ID." >&2
    exit 1
  fi

  poll_workflow "$WORKFLOW_ID"
  echo "Multidev '${ENV_NAME}' deleted."
  exit 0
fi

echo "Error: Unknown action '${ACTION}'. Use create or delete." >&2
exit 1
