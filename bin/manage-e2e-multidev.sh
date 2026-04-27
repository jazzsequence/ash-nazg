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
TMPFILE=$(mktemp)
trap 'rm -f "$TMPFILE"' EXIT

# ── Helper: curl with status + body capture ───────────────────────────────────
# Usage: api_call STATUS_VAR BODY_VAR METHOD URL [extra curl args...]
api_call() {
  local -n _status="$1"
  local -n _body="$2"
  local method="$3"
  local url="$4"
  shift 4

  _status=$(curl -s -o "$TMPFILE" -w "%{http_code}" \
    -X "$method" "$url" "$@")
  _body=$(cat "$TMPFILE")
}

# ── Auth ─────────────────────────────────────────────────────────────────────

echo "Authenticating with Pantheon API..."
STATUS="" BODY=""
api_call STATUS BODY POST "${API}/authorize/machine-token" \
  -H "Content-Type: application/json" \
  -d "{\"machine_token\": \"${PANTHEON_MACHINE_TOKEN}\", \"client\": \"ash-nazg-ci\"}"

if [ "$STATUS" != "200" ]; then
  echo "Error: Auth failed (HTTP ${STATUS}): ${BODY}" >&2
  exit 1
fi

SESSION_TOKEN=$(echo "$BODY" | jq -r '.session')
if [ -z "$SESSION_TOKEN" ] || [ "$SESSION_TOKEN" = "null" ]; then
  echo "Error: No session token in auth response: ${BODY}" >&2
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
    STATUS="" BODY=""
    api_call STATUS BODY GET "${API}/sites/${SITE_ID}/workflows/${workflow_id}" \
      "${AUTH[@]}"

    local result
    result=$(echo "$BODY" | jq -r '.result // "running"')
    echo "  Attempt ${attempt}: ${result}"

    case "$result" in
      succeeded) echo "Workflow succeeded."; return 0 ;;
      failed)
        echo "Error: Workflow failed." >&2
        echo "  Response: ${BODY}" >&2
        return 1
        ;;
    esac

    sleep 10
  done

  echo "Error: Workflow timed out after $((max_attempts * 10))s." >&2
  return 1
}

# ── Check if multidev exists ──────────────────────────────────────────────────

multidev_exists() {
  STATUS="" BODY=""
  api_call STATUS BODY GET \
    "${API}/sites/${SITE_ID}/environments/${ENV_NAME}" \
    "${AUTH[@]}"
  [ "$STATUS" = "200" ]
}

# ── Create ────────────────────────────────────────────────────────────────────

if [ "$ACTION" = "create" ]; then
  if multidev_exists; then
    echo "Multidev '${ENV_NAME}' already exists — skipping creation."
    exit 0
  fi

  echo "Creating multidev '${ENV_NAME}' cloned from dev..."
  STATUS="" BODY=""
  api_call STATUS BODY POST "${API}/sites/${SITE_ID}/environments" \
    "${AUTH[@]}" \
    -H "Content-Type: application/json" \
    -d "{\"name\": \"${ENV_NAME}\", \"clone_env\": \"dev\"}"

  if [ "$STATUS" != "200" ] && [ "$STATUS" != "201" ] && [ "$STATUS" != "202" ]; then
    echo "Error: Multidev creation failed (HTTP ${STATUS})." >&2
    echo "  Response: ${BODY}" >&2
    exit 1
  fi

  WORKFLOW_ID=$(echo "$BODY" | jq -r '.id // .workflow_id // empty')
  if [ -z "$WORKFLOW_ID" ]; then
    echo "Error: No workflow ID in creation response." >&2
    echo "  Response: ${BODY}" >&2
    exit 1
  fi

  poll_workflow "$WORKFLOW_ID"
  echo "Multidev '${ENV_NAME}' is ready."
  exit 0
fi

# ── Delete ────────────────────────────────────────────────────────────────────

if [ "$ACTION" = "delete" ]; then
  if ! multidev_exists; then
    echo "Multidev '${ENV_NAME}' does not exist — nothing to delete."
    exit 0
  fi

  echo "Deleting multidev '${ENV_NAME}'..."
  STATUS="" BODY=""
  api_call STATUS BODY DELETE \
    "${API}/sites/${SITE_ID}/environments/${ENV_NAME}" \
    "${AUTH[@]}"

  if [ "$STATUS" != "200" ] && [ "$STATUS" != "201" ] && [ "$STATUS" != "202" ]; then
    echo "Error: Multidev deletion failed (HTTP ${STATUS})." >&2
    echo "  Response: ${BODY}" >&2
    exit 1
  fi

  WORKFLOW_ID=$(echo "$BODY" | jq -r '.id // .workflow_id // empty')
  if [ -z "$WORKFLOW_ID" ]; then
    echo "Error: No workflow ID in deletion response." >&2
    echo "  Response: ${BODY}" >&2
    exit 1
  fi

  poll_workflow "$WORKFLOW_ID"
  echo "Multidev '${ENV_NAME}' deleted."
  exit 0
fi

echo "Error: Unknown action '${ACTION}'. Use create or delete." >&2
exit 1
