#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: ./scripts/release.sh <new-version> <short-changelog-text>"
  echo "Example: ./scripts/release.sh 0.1.1 \"Fix ESP32 WiFi reconnect\""
  exit 1
fi

NEW_VERSION="$1"
shift
CHANGE_TEXT="$*"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION_FILE="$PROJECT_DIR/VERSION"
CHANGELOG_FILE="$PROJECT_DIR/CHANGELOG.md"
TIMESTAMP="$(date '+%Y-%m-%d %H:%M')"

cd "$PROJECT_DIR"

echo "$NEW_VERSION" > "$VERSION_FILE"

TMP_FILE="$(mktemp)"
{
  echo "# Changelog"
  echo
  echo "## [$NEW_VERSION] - $TIMESTAMP"
  echo
  echo "### Changed"
  echo "- $CHANGE_TEXT"
  echo
  tail -n +2 "$CHANGELOG_FILE"
} > "$TMP_FILE"
mv "$TMP_FILE" "$CHANGELOG_FILE"

git add VERSION CHANGELOG.md

git commit -m "Release v$NEW_VERSION"

git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION"

git push origin main

git push origin "v$NEW_VERSION"

echo "Release completed: v$NEW_VERSION"
