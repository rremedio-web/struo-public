#!/usr/bin/env bash
#
# Runtime REST probe (no credentials, curl -k).
#
# Verifies on a live site that:
#   1. struo/v1 lists every registered route plus the namespace root.
#   2. Anonymous POST console/kill-switch and insert return 401
#      (401 = auth-first; 403 would mean allowlist-before-auth).
#
# Usage: bash tests/rest-probe.sh <site-url>
# Example: bash tests/rest-probe.sh https://struo.local/

set -u

SITE="${1:-}"
if [ -z "$SITE" ]; then
	echo "Usage: bash tests/rest-probe.sh <site-url>" >&2
	exit 1
fi
SITE="${SITE%/}"

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d "${TMPDIR:-/tmp}/struo-probe.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT

FAILURES=0
pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1"; FAILURES=$((FAILURES + 1)); }

count_namespace_routes() { # count_namespace_routes <namespace>
	curl -k -s "$SITE/wp-json/" | python3 -c '
import json, sys
data = json.load(sys.stdin)
ns = sys.argv[1]
routes = [r for r in data.get("routes", {}) if r == "/" + ns or r.startswith("/" + ns + "/")]
print(len(routes))
' "$1"
}

probe_post() { # probe_post <path> -> prints HTTP code; headers in $TMP/headers
	curl -k -s -o "$TMP/body" -D "$TMP/headers" -X POST \
		-H 'Content-Type: application/json' --data '{}' \
		-w '%{http_code}' "$SITE/wp-json$1"
}

ROUTES_FILE="$PLUGIN_DIR/src/Rest/RouteRegistrar.php"
EXPECTED_ROUTES=$(( $(grep -c $'^\t\tregister_rest_route($' "$ROUTES_FILE") + 1 ))

echo "== Route index =="
STRUO_COUNT="$(count_namespace_routes struo/v1)" || STRUO_COUNT=""
echo "struo/v1 routes: ${STRUO_COUNT:-unreachable}"
if [ "$STRUO_COUNT" = "$EXPECTED_ROUTES" ]; then
	pass "struo/v1 lists $EXPECTED_ROUTES routes ($((EXPECTED_ROUTES - 1)) registered + namespace root)"
else
	fail "struo/v1 route count is '${STRUO_COUNT:-none}', want $EXPECTED_ROUTES"
fi

check_anon_401() { # check_anon_401 <label> <path>
	local code
	: > "$TMP/headers"
	code="$(probe_post "$2")" || code="curl-error"
	if [ "$code" = "401" ]; then
		pass "$1 -> 401 (auth-first)"
	else
		fail "$1 -> '$code', want 401 (403 = allowlist-before-auth leak, 404 = delegate methods bug)"
	fi
}

echo "== Anonymous permission ordering (auth before allowlist) =="
check_anon_401 "POST /wp-json/struo/v1/console/kill-switch"   "/struo/v1/console/kill-switch"
check_anon_401 "POST /wp-json/struo/v1/posts/1/blocks/insert" "/struo/v1/posts/1/blocks/insert"

echo ""
if [ -n "${STRUO_PROBE_AUTH:-}" ]; then
	echo "== Authenticated fresh-install check (STRUO_PROBE_AUTH set) =="
	STATUS_CODE="$(curl -k -s -o "$TMP/status" -u "$STRUO_PROBE_AUTH" \
		-w '%{http_code}' "$SITE/wp-json/struo/v1/console/status")" || STATUS_CODE="curl-error"
	if [ "$STATUS_CODE" = "200" ]; then
		ALLOWED_COUNT="$(python3 -c '
import json, sys
print(json.load(open(sys.argv[1]))["allowlist"]["allowed_post_count"])
' "$TMP/status")"
		if [ "$ALLOWED_COUNT" = "0" ]; then
			pass "fresh site allowlist.allowed_post_count == 0 (no dynamic discovery)"
		else
			fail "fresh site allowlist.allowed_post_count == '$ALLOWED_COUNT', want 0"
		fi
	else
		fail "GET /wp-json/struo/v1/console/status -> '$STATUS_CODE', want 200"
	fi
else
	echo "SKIP: STRUO_PROBE_AUTH not set — authenticated allowed_post_count check skipped"
fi

if [ -n "${STRUO_PROBE_AUTH:-}" ] && [ -n "${STRUO_PROBE_POST_ID:-}" ]; then
	echo "== Authenticated REST dry-run is plan-only (no redeemable token) =="
	MINT_CODE="$(curl -k -s -o "$TMP/mint" -u "$STRUO_PROBE_AUTH" -X POST \
		-H 'Content-Type: application/json' \
		--data '{"block_name":"core/paragraph","fields":{"content":"probe"},"dry_run":true}' \
		-w '%{http_code}' "$SITE/wp-json/struo/v1/posts/$STRUO_PROBE_POST_ID/blocks/insert")" || MINT_CODE="curl-error"
	if [ "$MINT_CODE" = "200" ]; then
		MINT_STATE="$(python3 -c '
import json, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    print("PARSE_ERROR")
    sys.exit(0)
conf = data.get("confirmation") or {}
token = conf.get("token") or ""
redeemable = conf.get("redeemable")
plan_id = data.get("plan_id") or ""
print("OK" if (plan_id and redeemable is not True and not token) else "TOKEN_OR_NO_PLAN")
' "$TMP/mint")"
		if [ "$MINT_STATE" = "OK" ]; then
			pass "REST-origin dry-run returns plan_id and is non-redeemable"
		elif [ "$MINT_STATE" = "PARSE_ERROR" ]; then
			fail "insert dry-run response malformed/non-JSON (fail closed)"
		else
			fail "REST-origin dry-run must return plan_id without a redeemable token"
		fi
	else
		fail "POST insert dry_run=true -> '$MINT_CODE', want 200 (is post $STRUO_PROBE_POST_ID allowlisted for this user?)"
	fi

	echo "== Authenticated REST persist with confirmation_token is rejected =="
	APPLY_CODE="$(curl -k -s -o "$TMP/apply" -u "$STRUO_PROBE_AUTH" -X POST \
		-H 'Content-Type: application/json' \
		--data '{"block_name":"core/paragraph","fields":{"content":"probe"},"dry_run":false,"confirmation_token":"legacy-token"}' \
		-w '%{http_code}' "$SITE/wp-json/struo/v1/posts/$STRUO_PROBE_POST_ID/blocks/insert")" || APPLY_CODE="curl-error"
	APPLY_STATE="$(python3 -c '
import json, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    print("PARSE_ERROR")
    sys.exit(0)
print(data.get("code") or "")
' "$TMP/apply")"
	if [ "$APPLY_CODE" = "403" ] && [ "$APPLY_STATE" = "sae_plan_apply_required" ]; then
		pass "REST persist with confirmation_token returns sae_plan_apply_required"
	else
		fail "REST persist with confirmation_token -> '$APPLY_CODE'/'$APPLY_STATE', want 403/sae_plan_apply_required"
	fi
else
	echo "SKIP: STRUO_PROBE_AUTH/STRUO_PROBE_POST_ID not both set — REST plan-only checks skipped"
fi

if [ "$FAILURES" -eq 0 ]; then
	echo "REST PROBE RESULT: ALL CHECKS PASSED"
	exit 0
fi
echo "REST PROBE RESULT: $FAILURES CHECK(S) FAILED"
exit 1
