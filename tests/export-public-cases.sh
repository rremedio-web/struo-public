#!/usr/bin/env bash
#
# Dest-safety and leak-scan cases for bin/export-public.sh. Does not create
# a public clone. Proves an existing directory is refused, a sentinel file
# is not deleted, and the private-name leak scan fails closed.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT="$PLUGIN_DIR/bin/export-public.sh"
failed=0

pass() {
	echo "PASS: $1"
}

fail() {
	echo "FAIL: $1" >&2
	failed=1
}

if [ ! -f "$SCRIPT" ]; then
	echo "FAIL: bin/export-public.sh missing" >&2
	exit 1
fi

if grep -vE '^[[:space:]]*#' "$SCRIPT" | grep -q -- '--delete'; then
	fail "export-public.sh does not rsync --delete"
else
	pass "export-public.sh does not rsync --delete"
fi

if grep -q 'destination must not already exist' "$SCRIPT"; then
	pass "export-public.sh names the existing-dest refusal"
else
	fail "export-public.sh names the existing-dest refusal"
fi

if grep -q 'mktemp' "$SCRIPT"; then
	pass "export-public.sh stages in mktemp"
else
	fail "export-public.sh stages in mktemp"
fi

err="$(bash "$SCRIPT" 2>&1 >/dev/null || true)"
if printf '%s\n' "$err" | grep -q 'Usage:'; then
	pass "export-public.sh requires a destination"
else
	fail "export-public.sh requires a destination"
fi

EXISTING="$(mktemp -d "${TMPDIR:-/tmp}/struo-export-sentinel.XXXXXX")"
SENTINEL="$EXISTING/sentinel-must-survive"
printf 'stay\n' > "$SENTINEL"
set +e
out="$(bash "$SCRIPT" "$EXISTING" 2>&1)"
status=$?
set -e
if [ "$status" -eq 0 ]; then
	fail "export-public.sh refuses an existing destination"
else
	pass "export-public.sh refuses an existing destination"
fi
if printf '%s\n' "$out" | grep -q 'destination must not already exist'; then
	pass "existing dest error names the refusal"
else
	fail "existing dest error names the refusal"
fi
if [ -f "$SENTINEL" ] && [ "$(cat "$SENTINEL")" = "stay" ]; then
	pass "sentinel in an existing dest is not deleted"
else
	fail "sentinel in an existing dest is not deleted"
fi
rm -rf "$EXISTING"

set +e
out="$(bash "$SCRIPT" / 2>&1)"
status=$?
set -e
if [ "$status" -ne 0 ] && printf '%s\n' "$out" | grep -q 'destination must not be /'; then
	pass "export-public.sh refuses /"
else
	fail "export-public.sh refuses /"
fi

set +e
out="$(bash "$SCRIPT" "$HOME" 2>&1)"
status=$?
set -e
if [ "$status" -ne 0 ] && printf '%s\n' "$out" | grep -q 'home directory'; then
	pass "export-public.sh refuses \$HOME"
else
	fail "export-public.sh refuses \$HOME"
fi

set +e
out="$(bash "$SCRIPT" "$PLUGIN_DIR" 2>&1)"
status=$?
set -e
if [ "$status" -ne 0 ]; then
	pass "export-public.sh refuses the source plugin directory"
else
	fail "export-public.sh refuses the source plugin directory"
fi

PLUGIN_TOP="$(git -C "$PLUGIN_DIR" rev-parse --show-toplevel)"
PARENT="$(dirname "$PLUGIN_TOP")"
HOME_ABS="$(cd "$HOME" && pwd)"
if [ "$PARENT" = "/" ] || [ "$PARENT" = "$HOME_ABS" ]; then
	pass "export-public.sh parent refusal covered by / or \$HOME"
else
	set +e
	out="$(bash "$SCRIPT" "$PARENT" 2>&1)"
	status=$?
	set -e
	if [ "$status" -ne 0 ] && printf '%s\n' "$out" | grep -q 'parent of this repository'; then
		pass "export-public.sh refuses a parent of the source repository"
	else
		fail "export-public.sh refuses a parent of the source repository"
	fi
fi

# Leak scan: every case below must stop before a clone exists at the
# destination and must not leave a staging directory behind.
LEAK_BASE="$(mktemp -d "${TMPDIR:-/tmp}/struo-export-leak.XXXXXX")"
LEAK_DEST="$LEAK_BASE/out"
leak_dest_clean() {
	[ ! -e "$LEAK_DEST" ] && [ -z "$(find "$LEAK_BASE" -maxdepth 1 -name '.struo-export.*' -print -quit)" ]
}

set +e
out="$(env -u STRUO_LEAK_PATTERNS_FILE -u STRUO_SKIP_LEAK_SCAN bash "$SCRIPT" "$LEAK_DEST" 2>&1)"
status=$?
set -e
if [ "$status" -ne 0 ] && printf '%s\n' "$out" | grep -q 'STRUO_LEAK_PATTERNS_FILE' && leak_dest_clean; then
	pass "export-public.sh refuses to run without a leak patterns file"
else
	fail "export-public.sh refuses to run without a leak patterns file"
fi

set +e
out="$(env -u STRUO_SKIP_LEAK_SCAN STRUO_LEAK_PATTERNS_FILE="$LEAK_BASE/missing-patterns" bash "$SCRIPT" "$LEAK_DEST" 2>&1)"
status=$?
set -e
if [ "$status" -ne 0 ] && printf '%s\n' "$out" | grep -q 'not a readable file' && leak_dest_clean; then
	pass "export-public.sh refuses an unreadable leak patterns file"
else
	fail "export-public.sh refuses an unreadable leak patterns file"
fi

# A pattern that is certainly in the tree stands in for a private name.
printf '# fixture\n\nplugin name: struo\n' > "$LEAK_BASE/patterns"
set +e
out="$(env -u STRUO_SKIP_LEAK_SCAN STRUO_LEAK_PATTERNS_FILE="$LEAK_BASE/patterns" bash "$SCRIPT" "$LEAK_DEST" 2>&1)"
status=$?
set -e
if [ "$status" -ne 0 ] && printf '%s\n' "$out" | grep -q 'leak scan matched' && printf '%s\n' "$out" | grep -q 'struo.php' && leak_dest_clean; then
	pass "export-public.sh fails on a case-insensitive leak pattern hit and names the file"
else
	fail "export-public.sh fails on a case-insensitive leak pattern hit and names the file"
fi
rm -rf "$LEAK_BASE"

if [ "$failed" -ne 0 ]; then
	echo "EXPORT-PUBLIC CASES RESULT: CHECK(S) FAILED" >&2
	exit 1
fi
echo "EXPORT-PUBLIC CASES RESULT: ALL CHECKS PASSED"
