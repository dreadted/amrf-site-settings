#!/usr/bin/env bash
# Cuts a release from main: bumps the plugin header and readme.txt, updates changelog.txt and the
# README badges, commits, tags and builds the zip, then pushes and publishes once confirmed.
#
# Usage: bin/release.sh [patch|minor|major|X.Y.Z]   (default: patch)
set -Eeuo pipefail

SLUG="amrf-site-settings"
MAIN_FILE="$SLUG.php"

die() {
	echo "Error: $*" >&2
	exit 1
}

plugin_header() {
	sed -nE "/^[ *]*$1:/{s/^[ *]*$1:[[:space:]]*//;s/[[:space:]]+$//;p;q}" "$MAIN_FILE"
}

readme_field() {
	sed -nE "/^$1:/{s/^$1:[[:space:]]*//;s/[[:space:]]+$//;p;q}" readme.txt
}

BUMP="${1:-patch}"
[[ "$BUMP" =~ ^(patch|minor|major|[0-9]+\.[0-9]+\.[0-9]+)$ ]] || die "usage: $0 [patch|minor|major|X.Y.Z]"

cd "$(git rev-parse --show-toplevel)"

command -v gh >/dev/null || die "the GitHub CLI (gh) is required: https://cli.github.com/"
gh auth status >/dev/null 2>&1 || die "gh is not logged in; run: gh auth login"
[[ "$(git rev-parse --abbrev-ref HEAD)" == "main" ]] || die "check out main first."
git diff --quiet && git diff --cached --quiet || die "commit or stash your changes first."
[[ -f changelog.txt ]] || die "changelog.txt is missing."
for field in 'Requires at least' 'Tested up to' 'Requires PHP' 'Stable tag'; do
	grep -qE "^$field:" readme.txt || die "readme.txt has no '$field:' line."
done
grep -q '^<!-- badges:start -->$' README.md && grep -q '^<!-- badges:end -->$' README.md ||
	die "README.md needs <!-- badges:start --> and <!-- badges:end --> lines."

git fetch --quiet --tags origin main
[[ "$(git rev-parse HEAD)" == "$(git rev-parse origin/main)" ]] || die "main and origin/main differ; push or pull first."

CURRENT="$(plugin_header 'Version')"
REQUIRES_WP="$(plugin_header 'Requires at least')"
REQUIRES_PHP="$(plugin_header 'Requires PHP')"
TESTED_WP="$(readme_field 'Tested up to')"
[[ "$CURRENT" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "$MAIN_FILE Version '$CURRENT' is not X.Y.Z."
for v in "$REQUIRES_WP" "$REQUIRES_PHP" "$TESTED_WP"; do
	[[ "$v" =~ ^[0-9]+(\.[0-9]+)*$ ]] || die "Requires at least, Requires PHP and Tested up to must be plain version numbers."
done

IFS=. read -r MAJOR MINOR PATCH <<< "$CURRENT"
case "$BUMP" in
	major) NEW="$((MAJOR + 1)).0.0" ;;
	minor) NEW="$MAJOR.$((MINOR + 1)).0" ;;
	patch) NEW="$MAJOR.$MINOR.$((PATCH + 1))" ;;
	*) NEW="$BUMP" ;;
esac
TAG="v$NEW"
[[ "$NEW" != "$CURRENT" && "$(printf '%s\n' "$CURRENT" "$NEW" | sort -V | tail -n1)" == "$NEW" ]] ||
	die "$NEW is not higher than the current version $CURRENT."
if git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
	die "tag $TAG already exists."
fi

LAST_TAG="$(git describe --tags --abbrev=0 --match 'v[0-9]*' 2>/dev/null || true)"
if [[ -z "$LAST_TAG" ]]; then
	CHANGES="- Initial release."
else
	CHANGES="$(git log "$LAST_TAG"..HEAD --no-merges --pretty=format:'- %s')"
	[[ -n "$CHANGES" ]] || CHANGES="- Maintenance release."
fi

echo "Releasing $CURRENT -> $NEW."

REPO_URL="$(gh repo view --json url --jq .url)"
START="$(git rev-parse HEAD)"
WORK="$(mktemp -d)"

rollback() {
	git tag -d "$TAG" >/dev/null 2>&1 || true
	git reset --hard -q "$START"
}
trap 'rm -f -- "$WORK"/*; rmdir -- "$WORK"' EXIT
trap rollback ERR
trap 'rollback; exit 130' INT

sed -E -i "0,/^[ *]*Version:/s/^([ *]*Version:[[:space:]]*)[^[:space:]]+/\1$NEW/" "$MAIN_FILE"

# The plugin header is the source for the requirements; readme.txt mirrors them.
sed -E -i \
	-e "s/^(Requires at least:[[:space:]]*).*/\1$REQUIRES_WP/" \
	-e "s/^(Requires PHP:[[:space:]]*).*/\1$REQUIRES_PHP/" \
	-e "s/^(Stable tag:[[:space:]]*).*/\1$NEW/" \
	readme.txt

# New entry above the newest one, keeping the file's own line endings.
ENTRY="$(printf '%s\n------\n%s' "$NEW" "$CHANGES")" awk '
	function emit(   n, i, lines) { n = split(ENVIRON["ENTRY"], lines, "\n"); for (i = 1; i <= n; i++) print lines[i] eol }
	NR == 1 { eol = /\r$/ ? "\r" : "" }
	{ line = $0; sub(/\r$/, "", line) }
	!done && line ~ /^[0-9]+\.[0-9]+\.[0-9]+$/ { emit(); print eol; done = 1 }
	{ print; last = line }
	END { if (!done) { if (last != "") print eol; emit() } }
' changelog.txt > "$WORK/changelog.txt"
cat "$WORK/changelog.txt" > changelog.txt

SHIELDS="https://img.shields.io/badge"
STYLE="style=for-the-badge&labelColor=1f2328"
WP_LOGO="logo=wordpress&logoColor=white"
BADGES="[![Plugin Version]($SHIELDS/Plugin_Version-$NEW-3d444d?$STYLE)]($REPO_URL/releases/latest)
[![Requires WP]($SHIELDS/Requires_WP-$REQUIRES_WP-3d444d?$STYLE&$WP_LOGO)](https://wordpress.org/)
[![Tested WP]($SHIELDS/Tested_WP-$TESTED_WP-3d444d?$STYLE&$WP_LOGO)](https://wordpress.org/)
[![License]($SHIELDS/License-GPLv2%2B-3d444d?$STYLE&logo=gnu&logoColor=white)](https://www.gnu.org/licenses/gpl-2.0.html)"
BADGES="$BADGES" awk '
	/^<!-- badges:start -->$/ { print; print ENVIRON["BADGES"]; skip = 1; next }
	/^<!-- badges:end -->$/ { skip = 0 }
	!skip { print }
' README.md > "$WORK/README.md"
cat "$WORK/README.md" > README.md

git add "$MAIN_FILE" readme.txt changelog.txt README.md
git commit -q -m "release: bump version to $NEW"
git tag -a "$TAG" -m "$TAG"
# The prefix makes WordPress install it as amrf-site-settings, not as GitHub's "<repo>-<ref>" folder name.
git archive --format=zip --prefix="$SLUG/" --output="$WORK/$SLUG.zip" HEAD
printf '%s\n' "$CHANGES" > "$WORK/notes.md"

echo
git --no-pager show --stat --format='%h %s' HEAD
echo
echo "Changelog entry for $NEW:"
echo "$CHANGES"
echo
read -r -p "Push $TAG to origin and publish the GitHub Release? [y/N] " answer
if [[ ! "$answer" =~ ^[yY]$ ]]; then
	rollback
	echo "Cancelled: the release commit and tag were removed."
	exit 0
fi

if ! git push --quiet --atomic origin main "$TAG"; then
	rollback
	die "the push failed; the release commit and tag were removed locally."
fi
trap - ERR INT

gh release create "$TAG" "$WORK/$SLUG.zip" --title "$TAG" --notes-file "$WORK/notes.md" --verify-tag ||
	die "$TAG is pushed but the GitHub Release failed; create it with: git archive --format=zip --prefix=$SLUG/ -o $SLUG.zip $TAG && gh release create $TAG $SLUG.zip --title $TAG"
