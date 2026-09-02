#!/usr/bin/env bash
#
# Verify GitHub Action pins in a workflow file.
#
# Pins must be 40-char commit SHAs (not tags). A denylist catches the
# known-invalid setup-php SHA that 422s on GitHub's commits API.
#
# Usage:
#   bash tests/action-pins.sh --format [workflow]
#   bash tests/action-pins.sh --resolve [workflow]   # needs network
#   bash tests/action-pins.sh --selftest
#
set -euo pipefail

DENYLIST='9e72090525849c5e82e596468b86eb55e9cc5331'

extract_pins() {
	local file="$1"
	# uses: owner/repo@40hex  (step dash or nested under - name:)
	grep -E '^[[:space:]]+-?[[:space:]]*uses: [^@]+@[0-9a-f]{40}[[:space:]]*$' "$file" \
		| sed -E 's/^[[:space:]]+-?[[:space:]]*uses:[[:space:]]*//'
}

pin_ok_format() {
	local pin="$1"
	[[ "$pin" =~ ^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+@[0-9a-f]{40}$ ]]
}

pin_denied() {
	local sha="${1##*@}"
	[[ "$sha" == "$DENYLIST" ]]
}

fail() {
	echo "FAIL: $1" >&2
	exit 1
}

check_format() {
	local file="$1"
	local pin sha count
	count=0
	while IFS= read -r pin; do
		[ -n "$pin" ] || continue
		count=$((count + 1))
		pin_ok_format "$pin" || fail "pin is not owner/repo@40-hex: $pin"
		pin_denied "$pin" && fail "denylisted (unresolvable) Action SHA: $pin"
	done < <(extract_pins "$file")
	[ "$count" -ge 1 ] || fail "no SHA-pinned uses: lines in $file"
	if grep -E '^[[:space:]]+-?[[:space:]]*uses: ' "$file" | grep -vE '@[0-9a-f]{40}[[:space:]]*$' >/dev/null; then
		fail "workflow still has a tag-pinned uses: line in $file"
	fi
	echo "PASS: $count SHA pin(s) in $file (format + denylist)"
}

check_resolve() {
	local file="$1"
	local pin repo sha code
	local -a curl_opts
	check_format "$file"
	# GHA runner IPs often 403 unauthenticated /rate_limit. Use the job token.
	curl_opts=(
		-sS
		-o /dev/null
		-w '%{http_code}'
		-H 'Accept: application/vnd.github+json'
	)
	if [ -n "${GITHUB_TOKEN:-}" ]; then
		curl_opts+=( -H "Authorization: Bearer ${GITHUB_TOKEN}" )
	fi
	while IFS= read -r pin; do
		[ -n "$pin" ] || continue
		repo="${pin%@*}"
		sha="${pin#*@}"
		code="$(curl "${curl_opts[@]}" \
			"https://api.github.com/repos/${repo}/commits/${sha}")"
		[ "$code" = "200" ] || fail "GitHub commits API ${code} for ${pin}"
	done < <(extract_pins "$file")
	echo "PASS: every Action pin resolves to a commit"
}

selftest() {
	local tmp
	tmp="$(mktemp)"
	trap 'rm -f "$tmp"' RETURN
	cat > "$tmp" <<EOF
jobs:
  x:
    steps:
      - uses: shivammathur/setup-php@${DENYLIST}
EOF
	if ( check_format "$tmp" >/dev/null 2>&1 ); then
		fail "selftest: denylisted SHA must be rejected"
	fi
	echo "PASS: denylisted invalid Action SHA is rejected"
}

MODE="${1:-}"
FILE="${2:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_WF="$(cd "$SCRIPT_DIR/.." && pwd)/.github/workflows/ci.yml"

case "$MODE" in
	--format)
		check_format "${FILE:-$DEFAULT_WF}"
		;;
	--resolve)
		check_resolve "${FILE:-$DEFAULT_WF}"
		;;
	--selftest)
		selftest
		;;
	*)
		echo "Usage: $0 --format|--resolve|--selftest [workflow.yml]" >&2
		exit 2
		;;
esac
