#!/usr/bin/env bash
# Build dist/wsform-monetico-X.Y.Z.zip with a single wsform-monetico/ folder,
# and dist/wsform-monetico-X.Y.Z.json, the requirements read by the updater.
#
# Starts from the files git knows (tracked or new, never ignored), so local
# leftovers never ship, then drops every path listed in .distignore.
set -euo pipefail

slug="wsform-monetico"
root="$(cd "$(dirname "$0")/.." && pwd)"
version="$(sed -n 's/^ \* Version: *//p' "$root/$slug.php" | tr -d '[:space:]')"

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Unreadable version in $slug.php: '$version'" >&2
	exit 1
fi

# Tell whether a path is excluded by .distignore.
# "/name" excludes that root path and everything below it, "name" any file with that name.
is_excluded() {
	local path="$1" pattern
	while IFS= read -r pattern; do
		[[ -z "$pattern" || "$pattern" == \#* ]] && continue
		if [[ "$pattern" == /* ]]; then
			pattern="${pattern#/}"
			[[ "$path" == "$pattern" || "$path" == "$pattern"/* ]] && return 0
		else
			[[ "$(basename "$path")" == "$pattern" ]] && return 0
		fi
	done < "$root/.distignore"
	return 1
}

build_dir="$(mktemp -d)"
trap 'rm -rf "$build_dir"' EXIT

cd "$root"
git ls-files --cached --others --exclude-standard | while IFS= read -r path; do
	if [[ -f "$path" ]] && ! is_excluded "$path"; then
		mkdir -p "$build_dir/$slug/$(dirname "$path")"
		cp "$path" "$build_dir/$slug/$path"
	fi
done

mkdir -p "$root/dist"
archive="$root/dist/$slug-$version.zip"
rm -f "$archive"
(cd "$build_dir" && zip -qr "$archive" "$slug")

requires="$(sed -n 's/^ \* Requires at least: *//p' "$slug.php" | tr -d '[:space:]')"
requires_php="$(sed -n 's/^ \* Requires PHP: *//p' "$slug.php" | tr -d '[:space:]')"
tested="$(sed -n 's/^Tested up to: *//p' readme.txt | tr -d '[:space:]')"
printf '{"requires":"%s","requires_php":"%s","tested":"%s"}\n' "$requires" "$requires_php" "$tested" > "$root/dist/$slug-$version.json"

echo "$archive"
