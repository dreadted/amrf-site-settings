#!/usr/bin/env bash
# Bumps the plugin version via the "Bump Version" GitHub Actions workflow
# and syncs the resulting commit back to the local branch.
#
# Usage:
#   bin/bump-version.sh patch|minor|major
#   bin/bump-version.sh manual 0.3.0
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

if ! command -v gh >/dev/null 2>&1; then
  echo "Error: gh CLI is not installed. See https://cli.github.com" >&2
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

if [[ "$BUMP_TYPE" == "manual" ]]; then
  echo "Triggering workflow: bump_type=manual, version=$MANUAL_VERSION"
  gh workflow run bump_version.yml -f bump_type=manual -f version="$MANUAL_VERSION"
else
  echo "Triggering workflow: bump_type=$BUMP_TYPE"
  gh workflow run bump_version.yml -f bump_type="$BUMP_TYPE"
fi

# gh workflow run is fire-and-forget; give the run a moment to be created
# before we look for it.
sleep 3

RUN_ID="$(gh run list --workflow=bump_version.yml --limit 1 --json databaseId --jq '.[0].databaseId')"
echo "Watching run $RUN_ID..."
gh run watch "$RUN_ID" --exit-status

echo "Syncing local branch..."
git pull --ff-only

echo "Done. Current version files:"
grep -m1 'Version:' amrf-admin.php
grep -m1 'Stable tag:' readme.txt
