#!/usr/bin/env bash
# Bumps the plugin version locally: updates amrf-admin.php/readme.txt/
# changelog.txt, commits, tags, and pushes both — no CI round-trip needed.
# The pushed tag (v*.*.*) triggers .github/workflows/badges.yml on its own,
# since it's a normal push from your own credentials, not the
# GITHUB_TOKEN a workflow-triggered push would use (which GitHub
# deliberately never re-triggers other workflows from).
#
# Usage:
#   .github/scripts/bump-version.sh patch|minor|major
#   .github/scripts/bump-version.sh manual 0.3.0
set -euo pipefail

BUMP_TYPE="${1:-}"
MANUAL_VERSION="${2:-}"

if [[ -z "$BUMP_TYPE" ]]; then
  echo "Usage: $0 patch|minor|major|manual [version]" >&2
  exit 1
fi

if [[ "$BUMP_TYPE" == "manual" && -z "$MANUAL_VERSION" ]]; then
  echo "Usage: $0 manual <version>  (e.g. $0 manual 0.3.0)" >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: working tree has uncommitted changes. Commit or stash them first." >&2
  exit 1
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

echo "Fetching latest state from origin..."
git fetch origin

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$(git rev-parse HEAD)" != "$(git rev-parse "origin/$BRANCH")" ]]; then
  echo "Error: local branch '$BRANCH' is not up to date with origin/$BRANCH. Pull or push first." >&2
  exit 1
fi

CURRENT_VERSION=$(grep -oP 'Version:\s*\K[0-9]+\.[0-9]+\.[0-9]+' amrf-admin.php)

if [[ "$BUMP_TYPE" == "manual" ]]; then
  NEW_VERSION="$MANUAL_VERSION"
else
  IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
  case "$BUMP_TYPE" in
    major) NEW_VERSION="$((MAJOR + 1)).0.0" ;;
    minor) NEW_VERSION="$MAJOR.$((MINOR + 1)).0" ;;
    patch) NEW_VERSION="$MAJOR.$MINOR.$((PATCH + 1))" ;;
    *)
      echo "Error: unknown bump type '$BUMP_TYPE' (expected patch|minor|major|manual)" >&2
      exit 1
      ;;
  esac
fi

echo "Bumping version: $CURRENT_VERSION -> $NEW_VERSION"

# Collect commit messages since the last tag for the changelog entry.
LAST_TAG="$(git describe --tags --abbrev=0 2>/dev/null || git rev-list --max-parents=0 HEAD)"
echo "Collecting commits since: $LAST_TAG"
CHANGES="$(git log "$LAST_TAG"..HEAD --pretty=format:"- %s" --no-merges)"
if [[ -z "$CHANGES" ]]; then
  CHANGES="- Maintenance release."
fi

# Update amrf-admin.php (matches "Version: X.X.X" and preserves spacing).
sed -i "s/Version:[[:space:]]*[0-9.]*/Version:         $NEW_VERSION/" amrf-admin.php

# Update readme.txt.
sed -i "s/Stable tag:[[:space:]]*[0-9.]*/Stable tag: $NEW_VERSION/" readme.txt

# Update changelog.txt — insert the new entry after the header block (line 5).
new_entry_file="$(mktemp)"
{
  echo ""
  echo "$NEW_VERSION"
  echo "------"
  echo "$CHANGES"
} > "$new_entry_file"
sed -i "5r $new_entry_file" changelog.txt
rm -f "$new_entry_file"

git add amrf-admin.php readme.txt changelog.txt
git commit -m "Bump version to $NEW_VERSION"
git tag "v$NEW_VERSION"

echo "Pushing commit and tag..."
git push
git push origin "v$NEW_VERSION"

echo ""
echo "Done. Pushed commit + tag v$NEW_VERSION — the badges workflow will run automatically."
echo "Current version files:"
grep -m1 'Version:' amrf-admin.php
grep -m1 'Stable tag:' readme.txt
