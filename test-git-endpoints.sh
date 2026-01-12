#!/bin/bash

# Get machine token from terminus session
MACHINE_TOKEN=$(terminus auth:token)

if [ -z "$MACHINE_TOKEN" ]; then
    echo "ERROR: No terminus session found"
    exit 1
fi

# Exchange for session token
echo "Getting session token..."
SESSION_RESPONSE=$(curl -s -X POST https://api.pantheon.io/v0/authorize/machine-token \
    -H "Content-Type: application/json" \
    -d "{\"machine_token\":\"$MACHINE_TOKEN\",\"client\":\"ash-nazg\"}")

SESSION_TOKEN=$(echo "$SESSION_RESPONSE" | jq -r '.session // empty')

if [ -z "$SESSION_TOKEN" ]; then
    echo "ERROR: Failed to get session token"
    echo "$SESSION_RESPONSE" | jq '.'
    exit 1
fi

echo "✓ Got session token"
echo ""

SITE_ID="79f9c59d-a7b5-4961-ae8a-b1291065518e"
ENV="dev"

# Test 1: Code tips
echo "1. Testing GET /v0/sites/$SITE_ID/code-tips"
echo "   (Available Git branches and commits)"
curl -s "https://api.pantheon.io/v0/sites/$SITE_ID/code-tips" \
    -H "Authorization: Bearer $SESSION_TOKEN" \
    -H "Content-Type: application/json" | jq '.[0:2]'
echo ""

# Test 2: Upstream updates
echo "2. Testing GET /v0/sites/$SITE_ID/code-upstream-updates"
echo "   (Available upstream commits for merging)"
curl -s "https://api.pantheon.io/v0/sites/$SITE_ID/code-upstream-updates" \
    -H "Authorization: Bearer $SESSION_TOKEN" \
    -H "Content-Type: application/json" | jq '.'
echo ""

# Test 3: Environment commits
echo "3. Testing GET /v0/sites/$SITE_ID/environments/$ENV/commits"
echo "   (Git commit history for $ENV environment)"
curl -s "https://api.pantheon.io/v0/sites/$SITE_ID/environments/$ENV/commits" \
    -H "Authorization: Bearer $SESSION_TOKEN" \
    -H "Content-Type: application/json" | jq 'length, .[0:2], .[-2:]'
echo ""

echo "Done!"
