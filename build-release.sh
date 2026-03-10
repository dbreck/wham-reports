#!/bin/bash
#
# Build a release zip for WHAM Reports.
# Creates wham-reports.zip with contents in a wham-reports/ directory
# so WordPress extracts it to the correct plugin folder.
#
# Usage: ./build-release.sh [version]
#   version: optional, reads from wham-reports.php if not provided

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# Get version from plugin header if not passed as argument.
VERSION="${1:-$(grep -m1 'Version:' wham-reports.php | sed 's/.*Version: *//' | tr -d '[:space:]')}"

echo "Building WHAM Reports v${VERSION}..."

# Clean up any previous build.
BUILD_DIR="/tmp/wham-reports-build"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/wham-reports"

# Copy plugin files (exclude dev files and full vendor).
rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='build-release.sh' \
  --exclude='pdf-debug.log' \
  --exclude='*.jsonl' \
  --exclude='.claude' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  --exclude='vendor' \
  --exclude='wham-reports.zip' \
  . "$BUILD_DIR/wham-reports/"

# Copy only the vendor packages we actually need (DomPDF + its deps).
# The Google API uses direct HTTP calls, not the apiclient-services library.
mkdir -p "$BUILD_DIR/wham-reports/vendor"
cp vendor/autoload.php "$BUILD_DIR/wham-reports/vendor/"
rsync -a vendor/composer/ "$BUILD_DIR/wham-reports/vendor/composer/"
rsync -a vendor/dompdf/ "$BUILD_DIR/wham-reports/vendor/dompdf/"
rsync -a vendor/masterminds/ "$BUILD_DIR/wham-reports/vendor/masterminds/"
rsync -a vendor/sabberworm/ "$BUILD_DIR/wham-reports/vendor/sabberworm/"

# Build the zip.
cd "$BUILD_DIR"
ZIP_FILE="${SCRIPT_DIR}/wham-reports.zip"
rm -f "$ZIP_FILE"
zip -r "$ZIP_FILE" wham-reports/ -x '*.DS_Store'

# Clean up.
rm -rf "$BUILD_DIR"

echo "Created: wham-reports.zip ($(du -h "$ZIP_FILE" | cut -f1))"
echo ""
echo "To create a GitHub release:"
echo "  git tag v${VERSION} && git push origin v${VERSION}"
echo "  gh release create v${VERSION} wham-reports.zip --title \"v${VERSION}\" --notes \"Release notes here\""
