#!/usr/bin/env bash
#
# Copy the sanitized working tree into a new orphan git repo and tag v0.3.0.
# Does not rewrite this repository or push origin.
#
# The destination must not already exist. The tree is staged in a temporary
# sibling directory, leak-checked there, then renamed into place only after
# success. This script never wipes a caller-supplied directory.
#
# Usage: bash bin/export-public.sh /path/to/struo-public

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST_ARG="${1:-}"
if [ -z "$DEST_ARG" ]; then
	echo "Usage: bash bin/export-public.sh /path/to/struo-public" >&2
	exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
	echo "ERROR: python3 is required to resolve the destination path" >&2
	exit 1
fi

# Resolve without creating. Trailing slashes are stripped except for root.
DEST="$(python3 -c 'import os, sys
raw = sys.argv[1]
if raw != "/":
    raw = raw.rstrip("/")
print(os.path.normpath(os.path.abspath(os.path.expanduser(raw))))' "$DEST_ARG")"

PLUGIN_TOP="$(git -C "$PLUGIN_DIR" rev-parse --show-toplevel)"
PLUGIN_GITDIR="$(git -C "$PLUGIN_DIR" rev-parse --absolute-git-dir)"
HOME_ABS="$(cd "$HOME" && pwd)"

path_is_self_or_child() {
	local parent="$1"
	local child="$2"
	[ "$child" = "$parent" ] || [ "${child#"$parent"/}" != "$child" ]
}

if [ "$DEST" = "/" ]; then
	echo "ERROR: destination must not be /" >&2
	exit 1
fi
if [ "$DEST" = "$HOME_ABS" ]; then
	echo "ERROR: destination must not be your home directory" >&2
	exit 1
fi
if path_is_self_or_child "$PLUGIN_DIR" "$DEST" || path_is_self_or_child "$PLUGIN_TOP" "$DEST"; then
	echo "ERROR: destination must not be this repository or a path inside it ($PLUGIN_TOP)" >&2
	exit 1
fi
if path_is_self_or_child "$DEST" "$PLUGIN_DIR" || path_is_self_or_child "$DEST" "$PLUGIN_TOP"; then
	echo "ERROR: destination must not be a parent of this repository ($PLUGIN_TOP)" >&2
	exit 1
fi

if [ -e "$DEST" ]; then
	echo "ERROR: destination must not already exist" >&2
	exit 1
fi

PARENT="$(dirname "$DEST")"
if [ ! -d "$PARENT" ]; then
	mkdir -p "$PARENT"
fi
PARENT="$(cd "$PARENT" && pwd)"
DEST="$PARENT/$(basename "$DEST")"

if [ -e "$DEST" ]; then
	echo "ERROR: destination must not already exist" >&2
	exit 1
fi

STAGE="$(mktemp -d "$PARENT/.struo-export.XXXXXX")"
STAGE_LIVE=1
cleanup_stage() {
	if [ "${STAGE_LIVE:-0}" = "1" ] && [ -n "${STAGE:-}" ] && [ -d "$STAGE" ]; then
		rm -rf "$STAGE"
	fi
}
trap cleanup_stage EXIT

# Empty staging dir: copy only. Never wipe files at the caller path.
# A worktree's .git is a file. Never copy it, or git -C dest talks to this repo.
rsync -a \
	--exclude '.git' \
	--exclude '.git/' \
	--exclude 'node_modules/' \
	--exclude 'vendor/' \
	--exclude 'dist/' \
	--exclude '.wp-env/' \
	--exclude '.serena/' \
	--exclude '.cursor/' \
	--exclude 'docs/plans/' \
	--exclude 'docs/AMBITIOUS-ROADMAP.md' \
	--exclude 'reviews/' \
	"$PLUGIN_DIR/" "$STAGE/"

rm -rf "$STAGE/.git"
if [ -e "$STAGE/.git" ]; then
	echo "ERROR: staging directory still has git metadata after copy" >&2
	exit 1
fi

if [ -d "$STAGE/docs/plans" ] || [ -d "$STAGE/reviews" ] || [ -f "$STAGE/docs/AMBITIOUS-ROADMAP.md" ]; then
	echo "ERROR: private paths leaked into staging directory" >&2
	exit 1
fi

git -C "$STAGE" init -b main
STAGE_GITDIR="$(git -C "$STAGE" rev-parse --absolute-git-dir)"
if [ "$STAGE_GITDIR" = "$PLUGIN_GITDIR" ]; then
	echo "ERROR: public clone resolved to this repository's git dir" >&2
	exit 1
fi

git -C "$STAGE" add -A
git -C "$STAGE" commit -m "$(cat <<'EOF'
chore: public Struo 0.3.0 (clean history)

Orphan snapshot of the sanitized tree. Former private paths are not in this history.
EOF
)"
git -C "$STAGE" tag -a v0.3.0 -m "Struo 0.3.0"

if [ -e "$DEST" ]; then
	echo "ERROR: destination must not already exist" >&2
	exit 1
fi

mv "$STAGE" "$DEST"
STAGE_LIVE=0

echo "Public clone at $DEST (tag v0.3.0). Do not push this repository's origin from here."
echo "orphan_commits=$(git -C "$DEST" rev-list --count HEAD) tag=$(git -C "$DEST" describe --tags)"
