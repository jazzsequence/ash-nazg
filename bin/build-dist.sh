#!/usr/bin/env bash
# Build a clean distributable plugin artifact.
# Output: dist/ash-nazg-{version}.zip
#
# Usage:
#   npm run build:dist
#   npm run build:dist -- --version=1.2.3   (override version)

set -euo pipefail

PLUGIN_SLUG="ash-nazg"
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST_DIR="${REPO_ROOT}/dist"
STAGING_DIR="${DIST_DIR}/${PLUGIN_SLUG}"

# Determine version: prefer --version= arg, fall back to package.json.
VERSION=""
for arg in "$@"; do
  case "$arg" in
    --version=*) VERSION="${arg#*=}" ;;
  esac
done
if [ -z "$VERSION" ]; then
  VERSION="$(node -p "require('./package.json').version")"
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean and recreate staging directory.
rm -rf "${STAGING_DIR}"
mkdir -p "${STAGING_DIR}"

# Sync files, excluding everything in .distignore.
rsync -a \
  --exclude-from="${REPO_ROOT}/.distignore" \
  "${REPO_ROOT}/" \
  "${STAGING_DIR}/"

# Create zip with the plugin in a top-level directory.
rm -f "${ZIP_PATH}"
cd "${DIST_DIR}"
zip -r "${ZIP_NAME}" "${PLUGIN_SLUG}/"

echo "Artifact: ${ZIP_PATH}"
