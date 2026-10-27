#!/usr/bin/env bash
#
# Build a deterministic release zip for the Struo plugin.
#
# Output: dist/struo-<header-version>.zip with a single top-level folder
# struo/ containing ONLY the shippable files below. Determinism: LC_ALL=C,
# sorted file list piped into `zip -X -D` (no extra fields, no dir
# entries), and every staged file's mtime fixed to the repo HEAD commit
# date — two runs on the same commit produce identical sha256. From a
# source archive without .git, set SOURCE_DATE_EPOCH (unix seconds).
#
# Usage: bash bin/build-zip.sh

set -euo pipefail
export LC_ALL=C

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$PLUGIN_DIR/struo.php" | head -1 | tr -d '[:space:]')"

if [ -z "$VERSION" ]; then
	echo "ERROR: could not read the Version from struo.php header" >&2
	exit 1
fi

FIXED_EPOCH="${SOURCE_DATE_EPOCH:-}"
if [ -z "$FIXED_EPOCH" ] && git -C "$PLUGIN_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	FIXED_EPOCH="$(git -C "$PLUGIN_DIR" log -1 --format=%ct 2>/dev/null || true)"
fi
if [ -z "$FIXED_EPOCH" ]; then
	echo "ERROR: set SOURCE_DATE_EPOCH when building without git (unix epoch seconds)" >&2
	exit 1
fi

STAGE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/struo-build.XXXXXX")"
STAGE="$STAGE_ROOT/struo"
trap 'rm -rf "$STAGE_ROOT"' EXIT

mkdir -p "$STAGE"

# Explicit allowlist: exactly the shippable artifact surface. Fail closed
# if anything required is missing.
for item in \
	struo.php \
	uninstall.php \
	block-manifest.json \
	readme.txt \
	README.md \
	CHANGELOG.md \
	LICENSE \
	includes/class-abilities.php \
	includes/class-authority.php \
	includes/class-durable-plans.php \
	includes/class-mutation-journal.php \
	includes/class-mutation-recovery.php \
	includes/class-findings.php \
	includes/class-operation-catalog.php \
	includes/plugin-entry-deactivate.php \
	provider-url-guard.php \
	src/Plugin.php \
	src/Rest/RouteRegistrar.php \
	src/Rest/PermissionPolicy.php \
	src/Planning/Planner.php \
	src/Planning/PlanRepository.php \
	src/AI/OpenAICompatibleProvider.php \
	src/Audit/PrivacyExporter.php \
	src/Security/RateLimiter.php \
	src/Security/ProviderUrlPolicy.php; do
	if [ ! -f "$PLUGIN_DIR/$item" ]; then
		echo "ERROR: required file missing: $item" >&2
		exit 1
	fi
	mkdir -p "$STAGE/$(dirname "$item")"
	cp "$PLUGIN_DIR/$item" "$STAGE/$item"
done

if [ ! -d "$PLUGIN_DIR/assets" ] || [ ! -e "$PLUGIN_DIR/assets/console.js" ] || [ ! -e "$PLUGIN_DIR/assets/console.css" ]; then
	echo "ERROR: required assets missing under assets/" >&2
	exit 1
fi
if [ ! -e "$PLUGIN_DIR/assets/work-queue.js" ] || [ ! -e "$PLUGIN_DIR/assets/work-queue.asset.php" ]; then
	echo "ERROR: required Work Queue assets missing: assets/work-queue.js and assets/work-queue.asset.php" >&2
	exit 1
fi
if [ ! -e "$PLUGIN_DIR/assets/gutenberg-host.js" ] || [ ! -e "$PLUGIN_DIR/assets/gutenberg-host.asset.php" ]; then
	echo "ERROR: required Gutenberg host assets missing: assets/gutenberg-host.js and assets/gutenberg-host.asset.php" >&2
	exit 1
fi
cp -R "$PLUGIN_DIR/assets" "$STAGE/assets"
rm -rf "$STAGE/assets/src"

# Fix every staged mtime to the HEAD commit date for byte-identical zips.
find "$STAGE" -depth -exec perl -e 'my $e = shift @ARGV; utime $e, $e, @ARGV' "$FIXED_EPOCH" {} +

DIST="$PLUGIN_DIR/dist"
mkdir -p "$DIST"
ZIP="$DIST/struo-$VERSION.zip"
rm -f "$ZIP"

( cd "$STAGE_ROOT" && find struo -type f | sort | zip -X -D -q "$ZIP" -@ )

if [ ! -s "$ZIP" ]; then
	echo "ERROR: zip was not created or is empty: $ZIP" >&2
	exit 1
fi

echo "Built $ZIP"
if command -v sha256sum >/dev/null 2>&1; then
	sha256sum "$ZIP"
else
	shasum -a 256 "$ZIP"
fi
