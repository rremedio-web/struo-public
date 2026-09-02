#!/usr/bin/env bash
#
# Struo static smoke harness.
# No WordPress required: lint + structural assertions over the committed
# source. Exits non-zero on the first failing group; prints PASS/FAIL per
# check. Run from anywhere: the script resolves its own directory.
#
# Usage: bash tests/smoke.sh

set -u

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN="$PLUGIN_DIR/struo.php"
UNINSTALL="$PLUGIN_DIR/uninstall.php"
ROUTES="$PLUGIN_DIR/src/Rest/RouteRegistrar.php"
PROVIDER_HTTP="$PLUGIN_DIR/src/AI/OpenAICompatibleProvider.php"

FAILURES=0

pass() { echo "PASS: $1"; }
fail() { echo "FAIL: $1"; FAILURES=$((FAILURES + 1)); }

check_grep() { # check_grep <label> <file> <pattern>
	if grep -q "$3" "$2" 2>/dev/null; then pass "$1"; else fail "$1"; fi
}

check_count() { # check_count <label> <file> <pattern> <expected-count>
	local count
	count=$(grep -c "$3" "$2" 2>/dev/null || true)
	if [ "$count" -eq "$4" ]; then pass "$1"; else fail "$1 (found $count, want $4)"; fi
}

check_gte() { # check_gte <label> <file> <pattern> <minimum-count>
	local count
	count=$(grep -c "$3" "$2" 2>/dev/null || true)
	if [ "$count" -ge "$4" ]; then pass "$1 ($count >= $4)"; else fail "$1 (found $count, want >= $4)"; fi
}

fn_body() { # fn_body <file> <function-name> — prints the function's source
	awk "/function[[:space:]]+$2[[:space:]]*\(/,/^	}/" "$1"
}

check_body_absent() { # check_body_absent <label> <file> <function-name> <pattern>
	if fn_body "$2" "$3" | grep -q "$4"; then fail "$1"; else pass "$1"; fi
}

echo "== 0. Lint =="
while IFS= read -r php_file; do
	[ -f "$php_file" ] || continue
	rel="${php_file#$PLUGIN_DIR/}"
	if php -l "$php_file" >/dev/null 2>&1; then
		pass "php -l $rel"
	else
		fail "php -l $rel"
		php -l "$php_file"
	fi
done < <(find "$PLUGIN_DIR" \( -path "$PLUGIN_DIR/vendor" -o -path "$PLUGIN_DIR/node_modules" -o -path "$PLUGIN_DIR/tests" -o -path "$PLUGIN_DIR/src/work-queue" -o -path "$PLUGIN_DIR/src/gutenberg-host" \) -prune -o -name '*.php' -print | sort)

echo "== 0b. Atomic rate limiter unit cases (pure PHP, no WP) =="
if php "$PLUGIN_DIR/tests/rate-limit-cases.php"; then
	pass "rate-limit-cases.php all cases pass"
else
	fail "rate-limit-cases.php has failing cases"
fi
CAS_BODY="$(awk '/BEGIN Struo_Rate_Limit_Cas/,/END Struo_Rate_Limit_Cas/' "$MAIN")"
if [ -n "$CAS_BODY" ]; then pass "atomic limiter core inlined in struo.php"; else fail "atomic limiter core inlined in struo.php"; fi
if printf '%s' "$CAS_BODY" | grep -q "SET option_value = option_value + 1 WHERE option_name = %s AND option_value < %d"; then
	pass "CAS conditional increment (hard cap under concurrency)"
else
	fail "CAS conditional increment (hard cap under concurrency)"
fi
if printf '%s' "$CAS_BODY" | grep -q "add_option( \$key, 0, '', 'no' )"; then
	pass "options-table creator is atomic add_option"
else
	fail "options-table creator is atomic add_option"
fi
for primitive in wp_cache_add wp_cache_incr wp_using_ext_object_cache; do
	if printf '%s' "$CAS_BODY" | grep -q "$primitive"; then
		pass "object-cache path uses atomic $primitive"
	else
		fail "object-cache path uses atomic $primitive"
	fi
done
if printf '%s' "$CAS_BODY" | grep -q "DELETE FROM {\$wpdb->options}"; then
	pass "expired window rows pruned opportunistically"
else
	fail "expired window rows pruned opportunistically"
fi
for limiter in check_plan_rate_limit check_rate_limit; do
	LIMITER_BODY="$(fn_body "$MAIN" "$limiter")"
	if printf '%s' "$LIMITER_BODY" | grep -q "Struo_Rate_Limit_Cas::consume("; then
		pass "$limiter routes through the shared atomic counter"
	else
		fail "$limiter routes through the shared atomic counter"
	fi
	if printf '%s' "$LIMITER_BODY" | grep -q "get_transient"; then
		fail "$limiter has no legacy get_transient path"
	else
		pass "$limiter has no legacy get_transient path"
	fi
done
check_count "stray root rate-limit-cas.php removed" "$MAIN" "require_once __DIR__ . '/rate-limit-cas.php';" 0

echo "== 0c. Provider URL guard unit cases (pure PHP, no WP) =="
if php "$PLUGIN_DIR/tests/provider-url-cases.php"; then
	pass "provider-url-cases.php all cases pass"
else
	fail "provider-url-cases.php has failing cases"
fi

echo "== 0d. Load-time plugin-entry deactivation cases (pure PHP, no WP) =="
if php "$PLUGIN_DIR/tests/plugin-entry-deactivate-cases.php"; then
	pass "plugin-entry-deactivate-cases.php all cases pass"
else
	fail "plugin-entry-deactivate-cases.php has failing cases"
fi

echo "== 1. Rename hygiene: no interim identifiers in code =="
if grep -rn --include='*.php' --include='*.json' -e 'AIBE_' -e 'aibe_' -e 'AI Assistant' "$PLUGIN_DIR"; then
	fail "interim identifier residue"
else
	pass "interim identifier residue"
fi
check_grep "text domain constant is struo" "$MAIN" "const TEXT_DOMAIN = 'struo';"
check_grep "header Text Domain: struo" "$MAIN" "* Text Domain: struo"

echo "== 1b. Public edition has a single entry file =="
check_grep "header Plugin Name: Struo" "$MAIN" "* Plugin Name: Struo"
if [ -f "$PLUGIN_DIR/subsurface-ai-block-editor.php" ]; then
	fail "legacy entry stub is removed"
else
	pass "legacy entry stub is removed"
fi
if [ -f "$PLUGIN_DIR/struo-compat.php" ]; then
	fail "struo-compat.php is deleted (no config-managed alias)"
else
	pass "struo-compat.php is deleted (no config-managed alias)"
fi
check_count "struo.php class defined exactly once" "$MAIN" "^final class Struo_Block_Editor {" 1
check_count "no class_alias to a former name" "$MAIN" "class_alias( 'Struo_Block_Editor'" 0

echo "== 1c. REST namespace struo/v1 only =="
if [ -f "$PLUGIN_DIR/includes/class-legacy-rest.php" ]; then
	fail "includes/class-legacy-rest.php is removed"
else
	pass "includes/class-legacy-rest.php is removed"
fi
check_grep "REST namespace constant is struo/v1" "$MAIN" "const REST_NAMESPACE = 'struo/v1';"
check_count "no former REST namespace constant" "$MAIN" "LEGACY_REST_NAMESPACE" 0
COUNT_NS=$(grep -c "register_rest_route(" "$ROUTES" || true)
if [ "$COUNT_NS" -ge 20 ]; then pass "route registrations live in RouteRegistrar ($COUNT_NS >= 20)"; else fail "route registrations live in RouteRegistrar (found $COUNT_NS, want >= 20)"; fi
check_grep "routes register through RouteRegistrar" "$MAIN" "RouteRegistrar::register( self::REST_NAMESPACE )"
check_count "no hardcoded former namespace as primary route arg" "$MAIN" "subsurface-ai/v1" 0
check_count "console config no longer targets former namespace" "$MAIN" "rest_url( 'subsurface-ai/v1/" 0
if grep -A40 "'/pages/create'" "$ROUTES" | grep -q "confirmation_token"; then
	fail "pages/create schema has no confirmation_token arg"
else
	pass "pages/create schema has no confirmation_token arg"
fi
check_grep "capability schema version is stored" "$MAIN" "const CAPS_SCHEMA_VERSION = 1;"
check_grep "capability migration compares schema version" "$MAIN" "\$stored >= self::CAPS_SCHEMA_VERSION"

echo "== 1d. Menu slugs + console shortcut =="
check_grep "console menu slug is struo-console" "$MAIN" "const CONSOLE_MENU_SLUG = 'struo-console';"
check_grep "console page hook follows new slug" "$MAIN" "const CONSOLE_PAGE_HOOK = 'toplevel_page_struo-console';"
check_grep "settings page slug is struo" "$MAIN" "const SETTINGS_PAGE_SLUG = 'struo';"
check_count "no former console slug constant" "$MAIN" "LEGACY_CONSOLE_MENU_SLUG" 0
check_count "no former settings slug constant" "$MAIN" "LEGACY_SETTINGS_PAGE_SLUG" 0
check_grep "settings page registers with struo slug" "$MAIN" "self::SETTINGS_PAGE_SLUG,"
check_count "no /ai-console shortcut alias" "$MAIN" "'ai-console'" 0
check_grep "/console primary shortcut path (filterable)" "$MAIN" "struo_console_shortcut_path', 'console'"
check_count "no former admin slug redirect" "$MAIN" "maybe_redirect_legacy_admin_pages" 0
check_grep "console CSS keyed to new page hook" "$PLUGIN_DIR/assets/console.css" "toplevel_page_struo-console"
check_count "console CSS has no old page hook" "$PLUGIN_DIR/assets/console.css" "toplevel_page_subsurface-ai-console" 0

echo "== 1e. Option keys are struo_* =="
check_grep "options key is struo_options" "$MAIN" "const OPTION_KEY = 'struo_options';"
check_grep "legacy seeds flag is struo_legacy_seeds" "$MAIN" "const LEGACY_SEEDS_FLAG_OPTION = 'struo_legacy_seeds';"
check_grep "audit option is struo_audit" "$MAIN" "const AUDIT_KEY = 'struo_audit';"
check_grep "audit db version option is struo_*" "$MAIN" "const AUDIT_DB_VERSION_OPTION = 'struo_audit_db_version';"
check_grep "audit migrated option is struo_*" "$MAIN" "const AUDIT_MIGRATED_OPTION = 'struo_audit_migrated';"
check_grep "api key option is struo_*" "$MAIN" "const OPENAI_API_KEY_OPTION = 'struo_openai_api_key';"
check_grep "user templates option is struo_*" "$MAIN" "const USER_TEMPLATES_OPTION = 'struo_user_templates';"
check_grep "template registry option is struo_*" "$MAIN" "const TEMPLATE_REGISTRY_OPTION = 'struo_template_registry_v2';"
check_grep "pattern registry option is struo_*" "$MAIN" "const PATTERN_REGISTRY_OPTION = 'struo_pattern_registry_v1';"
check_grep "migration flag constant" "$MAIN" "const OPTIONS_MIGRATION_FLAG = 'struo_options_migrated_v1';"
check_count "no former option key map" "$MAIN" "LEGACY_OPTION_KEY_MAP" 0
check_grep "options current-flag still written" "$MAIN" "private static function maybe_migrate_legacy_option_keys"
check_grep "current-flag runs first in maybe_upgrade_storage" "$MAIN" "self::maybe_migrate_legacy_option_keys();"
MIGRATION_BODY="$(fn_body "$MAIN" "maybe_migrate_legacy_option_keys")"
if printf '%s' "$MIGRATION_BODY" | grep -q "update_option( self::OPTIONS_MIGRATION_FLAG, 1, false )"; then
	pass "upgrade writes the current-options flag"
else
	fail "upgrade writes the current-options flag"
fi
if printf '%s' "$MIGRATION_BODY" | grep -q "subsurface_ai_"; then
	fail "upgrade does not copy former option keys"
else
	pass "upgrade does not copy former option keys"
fi
check_count "migration flag written exactly once (sole writer)" \
	"$MAIN" "update_option( self::OPTIONS_MIGRATION_FLAG, 1, false )" 1
check_count "migration flag reads use the null !== guard" \
	"$MAIN" "null !== get_option( self::OPTIONS_MIGRATION_FLAG, null )" 1
check_grep "settings group is struo" "$MAIN" "register_setting(
			'struo',"
check_grep "settings_fields group is struo" "$MAIN" "settings_fields( 'struo' )"

echo "== 2. Legacy seeds: pure read + one-time explicit migration =="
check_count "flag written exactly once (migration only)" \
	"$MAIN" "update_option( self::LEGACY_SEEDS_FLAG_OPTION" 1
check_grep "migration lives in maybe_record_legacy_seeds_flag" \
	"$MAIN" "private static function maybe_record_legacy_seeds_flag"
check_grep "marker: audit DB-version option" \
	"$MAIN" "null !== get_option( self::AUDIT_DB_VERSION_OPTION, null )"
check_grep "marker: audit table existence" \
	"$MAIN" "self::audit_table_exists()"
check_grep "marker: plugin options row" \
	"$MAIN" "null !== get_option( self::OPTION_KEY, null )"
if awk '/private static function legacy_seeds_enabled/,/^	}/' "$MAIN" | grep -q "update_option"; then
	fail "legacy_seeds_enabled performs no writes"
else
	pass "legacy_seeds_enabled performs no writes"
fi
check_grep "unmarked installs fail closed to generic + notice" \
	"$MAIN" "maybe_queue_legacy_seeds_notice()"
if fn_body "$MAIN" "legacy_seeds_enabled" | grep -q "maybe_queue_legacy_seeds_notice"; then
	pass "notice queued behaviourally inside legacy_seeds_enabled"
else
	fail "notice queued behaviourally inside legacy_seeds_enabled"
fi
NOTICE_BODY="$(fn_body "$MAIN" "render_legacy_seeds_notice")"
if printf '%s' "$NOTICE_BODY" | grep -q "current_user_can( 'manage_options' )"; then
	pass "legacy-seeds notice is admin-only"
else
	fail "legacy-seeds notice is admin-only"
fi

echo "== 3. Rate limiter floors =="
check_grep "min window constant 60" "$MAIN" "const PLAN_RATE_LIMIT_MIN_WINDOW = 60;"
check_grep "min max constant 5" "$MAIN" "const PLAN_RATE_LIMIT_MIN_MAX = 5;"
check_grep "window floored" "$MAIN" "max( self::PLAN_RATE_LIMIT_MIN_WINDOW, \$window )"
check_grep "max floored" "$MAIN" "max( self::PLAN_RATE_LIMIT_MIN_MAX, \$limit )"
check_count "no zero-disable branch on window" "$MAIN" "if ( \$window <= 0 )" 0
check_count "no zero-disable branch on max" "$MAIN" "if ( \$max <= 0 )" 0

echo "== 4. MCP dry-run enforcement + permission parity =="
DISPATCH_BODY="$(awk '/public static function dispatch_internal/,/^	}/' "$MAIN")"
if printf '%s' "$DISPATCH_BODY" | grep -q "\$dispatch_args\['dry_run'\] = true;"; then
	pass "dispatch_internal forces dry_run=true for non-REST origins"
else
	fail "dispatch_internal forces dry_run=true for non-REST origins"
fi
if printf '%s' "$DISPATCH_BODY" | grep -q "unset( \$dispatch_args\['confirmation_token'\] );"; then
	pass "dispatch_internal strips confirmation tokens for non-REST origins"
else
	fail "dispatch_internal strips confirmation tokens for non-REST origins"
fi
for needed in Struo_Authority::authorize; do
	if printf '%s' "$DISPATCH_BODY" | grep -q "$needed"; then
		pass "dispatch_internal runs $needed permission check"
	else
		fail "dispatch_internal runs $needed permission check"
	fi
done
if printf '%s' "$DISPATCH_BODY" | grep -q "can_read_catalog\|can_read_post\|can_write_post\|can_struo_plan"; then
	fail "dispatch_internal does not call leftover can_* permission methods"
else
	pass "dispatch_internal does not call leftover can_* permission methods"
fi
MCP_BODY="$(awk '/public static function handle_mcp_call/,/^	}/' "$MAIN")"
if printf '%s' "$MCP_BODY" | grep -q "self::dispatch_internal("; then
	pass "handle_mcp_call delegates to dispatch_internal"
else
	fail "handle_mcp_call delegates to dispatch_internal"
fi
if printf '%s' "$MCP_BODY" | grep -q "'mcp'"; then
	pass "handle_mcp_call stamps origin mcp"
else
	fail "handle_mcp_call stamps origin mcp"
fi
check_grep "MCP invocations audited" "$MAIN" "'mcp_tool_call'"
if grep -A 2 "function run_plan_dry_run" "$MAIN" | grep -q '\$origin'; then
	pass "run_plan_dry_run requires origin (nested plan dry-run cannot default to rest)"
else
	fail "run_plan_dry_run requires origin (nested plan dry-run cannot default to rest)"
fi
if awk '/function run_plan_dry_run/,/function prepare_create_page_outline/' "$MAIN" | grep -q '\$origin,'; then
	pass "run_plan_dry_run forwards origin into apply_*"
else
	fail "run_plan_dry_run forwards origin into apply_*"
fi
check_grep "REST/MCP post id has one reader" "$MAIN" "function request_post_id"
if printf '%s' "$DISPATCH_BODY" | grep -q "set_url_params"; then
	pass "dispatch_internal binds post id as REST URL param"
else
	fail "dispatch_internal binds post id as REST URL param"
fi
INSERT_BODY="$(fn_body "$MAIN" "insert_block")"
if printf '%s' "$INSERT_BODY" | grep -q "request_post_id"; then
	pass "insert_block uses request_post_id (MCP post_id survives body params)"
else
	fail "insert_block uses request_post_id (MCP post_id survives body params)"
fi

echo "== 4b. Write endpoints default dry_run true =="
FALSE_DEFAULTS="$(grep -cF "\$payload['dry_run'] : false" "$MAIN" || true)"
if [ "$FALSE_DEFAULTS" -eq 0 ]; then
	pass "no write handler defaults dry_run to false"
else
	fail "no write handler defaults dry_run to false (found $FALSE_DEFAULTS)"
fi
TRUE_DEFAULTS="$(grep -cF "\$payload['dry_run'] : true" "$MAIN" || true)"
if [ "$TRUE_DEFAULTS" -eq 5 ]; then
	pass "five write handlers default dry_run to true"
else
	fail "five write handlers default dry_run to true (found $TRUE_DEFAULTS, want 5)"
fi
FIELD_BODY="$(fn_body "$MAIN" "update_post_field")"
if printf '%s' "$FIELD_BODY" | grep -qF "\$payload['dry_run'] : true"; then
	pass "fields/update defaults dry_run true (same contract as insert/update/remove/batch)"
else
	fail "fields/update defaults dry_run true (same contract as insert/update/remove/batch)"
fi
if grep -F -A30 "/fields/update'," "$ROUTES" | grep -q "'default' => true"; then
	pass "fields/update REST arg defaults dry_run true"
else
	fail "fields/update REST arg defaults dry_run true"
fi

echo "== 5. Registry capability gates =="
check_gte "/templates+/patterns methods gated by registry cap (>=4)" \
	"$ROUTES" "'permission_callback' => \[ 'Struo_Block_Editor', 'can_manage_template_registry' \]" 4
SAVE_CAP_BODY="$(fn_body "$MAIN" "can_save_created_template")"
if printf '%s' "$SAVE_CAP_BODY" | grep -q "save-created-template"; then
	pass "save-created requires struo_manage_registry"
else
	fail "save-created requires struo_manage_registry"
fi

echo "== 6. Kill-switch coverage =="
# Helper definition + call sites; tolerant floor so new handlers can be
# added without touching the harness (per-handler assertions below pin
# the five known state-changing handlers).
check_gte "ensure_kill_switch definition + call sites (>=8)" "$MAIN" "ensure_kill_switch()" 8
for handler in save_created_template save_template_record save_pattern_record \
	promote_page_template_preview promote_section_pattern_preview; do
	BODY="$(awk "/public static function ${handler}/,/^	}/" "$MAIN")"
	if printf '%s' "$BODY" | grep -q "ensure_kill_switch"; then
		pass "kill switch enforced in $handler"
	else
		fail "kill switch enforced in $handler"
	fi
done

echo "== 6b. MCP dry-run token suppression (explicit origin, no redeemable mint) =="
MINT_BODY="$(fn_body "$MAIN" "issue_confirmation_token")"
if fn_body "$MAIN" "normalize_token_origin" | grep -qF "'mcp' === \$origin ? 'mcp' : 'rest'"; then
	pass "token origins are rest|mcp (no console enum)"
else
	fail "token origins are rest|mcp (no console enum)"
fi
if printf '%s' "$MINT_BODY" | grep -q 'mcp_dry_run_token_suppressed'; then
	pass "MCP origin still audits token suppression"
else
	fail "MCP origin still audits token suppression"
fi
if printf '%s' "$MINT_BODY" | grep -q 'set_transient('; then
	fail "issue_confirmation_token never stores a confirmation transient"
else
	pass "issue_confirmation_token never stores a confirmation transient"
fi
if printf '%s' "$MINT_BODY" | grep -q "'redeemable' => true"; then
	fail "issue_confirmation_token never returns redeemable true"
else
	pass "issue_confirmation_token never returns redeemable true"
fi
CREATE_MINT_BODY="$(fn_body "$MAIN" "issue_create_confirmation_token")"
if printf '%s' "$CREATE_MINT_BODY" | grep -q 'mcp_dry_run_token_suppressed'; then
	pass "create-page minting suppresses tokens for MCP origin too"
else
	fail "create-page minting suppresses tokens for MCP origin too"
fi
if printf '%s' "$CREATE_MINT_BODY" | grep -q 'set_transient('; then
	fail "issue_create_confirmation_token never stores a confirmation transient"
else
	pass "issue_create_confirmation_token never stores a confirmation transient"
fi
check_count "no public/static origin switch" "$MAIN" "public static function set_token_origin" 0
check_count "no static origin property" "$MAIN" 'static $token_origin' 0
if grep -qF "set_attributes(" "$MAIN" && awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q "struo_origin"; then
	pass "dispatch_internal stamps struo_origin via set_attributes"
else
	fail "dispatch_internal stamps struo_origin via set_attributes"
fi
check_grep "handlers read origin from the request attribute" "$MAIN" "private static function request_token_origin( WP_REST_Request \$request )"
for handler in insert_block update_block remove_block batch_blocks update_post_field plan_block_change plan_create_page; do
	HANDLER_BODY="$(fn_body "$MAIN" "$handler")"
	if printf '%s' "$HANDLER_BODY" | grep -qF 'self::request_token_origin( $request )'; then
		pass "$handler reads origin from the request and forwards it"
	else
		fail "$handler reads origin from the request and forwards it"
	fi
done
check_grep "token record stores origin" "$MAIN" "'origin' => \$origin,"
if grep -qE 'function[[:space:]]+ensure_confirmation_token[[:space:]]*\(' "$MAIN"; then
	fail "ensure_confirmation_token is gone (no token redemption)"
else
	pass "ensure_confirmation_token is gone (no token redemption)"
fi
if grep -qE 'function[[:space:]]+ensure_create_confirmation_token[[:space:]]*\(' "$MAIN"; then
	fail "ensure_create_confirmation_token is gone (no token redemption)"
else
	pass "ensure_create_confirmation_token is gone (no token redemption)"
fi
WRITE_CONF_BODY="$(fn_body "$MAIN" "ensure_write_confirmation")"
if printf '%s' "$WRITE_CONF_BODY" | grep -q "sae_plan_apply_required"; then
	pass "ensure_write_confirmation requires a durable plan claim"
else
	fail "ensure_write_confirmation requires a durable plan claim"
fi
if printf '%s' "$WRITE_CONF_BODY" | grep -q "ensure_confirmation_token"; then
	fail "ensure_write_confirmation must not fall back to token redemption"
else
	pass "ensure_write_confirmation must not fall back to token redemption"
fi
CREATE_CONF_BODY="$(fn_body "$MAIN" "ensure_create_write_confirmation")"
if printf '%s' "$CREATE_CONF_BODY" | grep -q "sae_plan_apply_required"; then
	pass "ensure_create_write_confirmation requires a durable plan claim"
else
	fail "ensure_create_write_confirmation requires a durable plan claim"
fi
if printf '%s' "$CREATE_CONF_BODY" | grep -q "ensure_create_confirmation_token"; then
	fail "ensure_create_write_confirmation must not fall back to token redemption"
else
	pass "ensure_create_write_confirmation must not fall back to token redemption"
fi
APPLY_CREATE_BODY="$(fn_body "$MAIN" "apply_create_page")"
if printf '%s' "$APPLY_CREATE_BODY" | grep -q "sae_plan_apply_required"; then
	pass "legacy REST pages/create persist is rejected"
else
	fail "legacy REST pages/create persist is rejected"
fi
if grep -qF "'tool' => sanitize_key( (string) ( \$context['tool'] ?? '' ) )," "$MAIN"; then
	pass "suppression audited with tool name"
else
	fail "suppression audited with tool name"
fi
if grep -qF "'request_id' => sanitize_text_field( (string) ( \$context['request_id'] ?? '' ) )," "$MAIN"; then
	pass "suppression audited with request id"
else
	fail "suppression audited with request id"
fi
check_grep "non-redeemable marker for MCP dry-runs" "$MAIN" "'redeemable' => false,"

echo "== 6c. Privacy policy disclosure (lane 3b) =="
if grep -qF "add_action( 'admin_init', [ __CLASS__, 'add_privacy_policy_content' ] );" "$MAIN"; then
	pass "suggested privacy-policy content hooked"
else
	fail "suggested privacy-policy content hooked"
fi
POLICY_BODY="$(fn_body "$MAIN" "add_privacy_policy_content")"
if printf '%s' "$POLICY_BODY" | grep -qF 'wp_add_privacy_policy_content'; then
	pass "policy content registered with wp_add_privacy_policy_content"
else
	fail "policy content registered with wp_add_privacy_policy_content"
fi
for topic in "page titles" "block excerpts" "RAG" "120 characters" "5,000 rows"; do
	if printf '%s' "$POLICY_BODY" | grep -qF "$topic"; then
		pass "policy text covers: $topic"
	else
		fail "policy text covers: $topic"
	fi
done
if printf '%s' "$POLICY_BODY" | grep -qF 'get_openai_base_url'; then
	pass "policy text names the configured provider host"
else
	fail "policy text names the configured provider host"
fi

echo "== 6d. Audit retention by time (lane 3b) =="
check_grep "retention cron hook constant" "$MAIN" "const AUDIT_RETENTION_CRON_HOOK = 'struo_audit_retention_prune';"
check_grep "retention default is 90 days" "$MAIN" "'audit_retention_days' => 90,"
if grep -qF "min( self::AUDIT_RETENTION_MAX_DAYS, absint(" "$MAIN"; then
	pass "retention clamped to a maximum"
else
	fail "retention clamped to a maximum"
fi
RETENTION_BODY="$(fn_body "$MAIN" "prune_audit_rows_by_age")"
if printf '%s' "$RETENTION_BODY" | grep -qF 'DELETE FROM'; then
	pass "age-based prune deletes table rows by created_at"
else
	fail "age-based prune deletes table rows by created_at"
fi
if grep -qF "0 disables time-based" "$MAIN"; then
	pass "retention 0 keeps volume-based cap only"
else
	fail "retention 0 keeps volume-based cap only"
fi
check_grep "age prune piggybacks the insert cadence" "$MAIN" "self::prune_audit_rows_by_age();"
if grep -qF "add_action( self::AUDIT_RETENTION_CRON_HOOK, [ __CLASS__, 'run_audit_retention_prune' ] );" "$MAIN"; then
	pass "daily cron event registered"
else
	fail "daily cron event registered"
fi
check_grep "cron schedule self-heals on init" "$MAIN" "maybe_schedule_audit_retention_prune"
check_grep "deactivation clears the retention cron" "$MAIN" "wp_clear_scheduled_hook( self::AUDIT_RETENTION_CRON_HOOK )"
check_grep "volume cap kept (5,000 rows)" "$MAIN" "const AUDIT_TABLE_MAX_ROWS = 5000;"
check_grep "settings row for audit retention" "$MAIN" "Audit retention (days)"

echo "== 6e. Audit excerpt redaction (lane 3b) =="
check_grep "excerpt setting defaults to legacy seeds" "$MAIN" "'audit_store_excerpts' => self::legacy_seeds_enabled(),"
EXCERPT_BODY="$(fn_body "$MAIN" "audit_excerpt")"
if printf '%s' "$EXCERPT_BODY" | grep -qF 'mb_substr( $stripped, 0, 120 )'; then
	pass "raw excerpts capped at 120 characters"
else
	fail "raw excerpts capped at 120 characters"
fi
if printf '%s' "$EXCERPT_BODY" | grep -qF "hash( 'sha256', \$text )"; then
	pass "redacted mode stores sha256 prefix + length, never raw text"
else
	fail "redacted mode stores sha256 prefix + length, never raw text"
fi
check_grep "apply audits carry before/after excerpts (redacted per setting)" "$MAIN" "'before_excerpt' => self::audit_excerpt( \$before_content ),"
check_grep "settings row for excerpt storage" "$MAIN" "Store audit excerpts"

echo "== 6f. Personal-data exporter + eraser (lane 3b) =="
if grep -qF "add_filter( 'wp_privacy_personal_data_exporters', [ \\Struo\\Audit\\PrivacyExporter::class, 'register_audit_exporter' ] );" "$MAIN"; then
	pass "exporter filter registered"
else
	fail "exporter filter registered"
fi
if grep -qF "add_filter( 'wp_privacy_personal_data_erasers', [ \\Struo\\Audit\\PrivacyExporter::class, 'register_audit_eraser' ] );" "$MAIN"; then
	pass "eraser filter registered"
else
	fail "eraser filter registered"
fi
ERASER_BODY="$(fn_body "$MAIN" "anonymise_audit_rows_for_user")"
if printf '%s' "$ERASER_BODY" | grep -qF "SET user_id = 0 WHERE user_id = %d"; then
	pass "eraser anonymises user id to 0 (keeps action/post for integrity)"
else
	fail "eraser anonymises user id to 0 (keeps action/post for integrity)"
fi
EXPORTER_BODY="$(fn_body "$MAIN" "export_audit_rows_for_user")"
for field in created_at action post_id details; do
	if printf '%s' "$EXPORTER_BODY" | grep -qF "$field"; then
		pass "exporter includes $field"
	else
		fail "exporter includes $field"
	fi
done

echo "== 6g. Admin privacy disclosure (lane 3b) =="
DISCLOSURE_BODY="$(awk '/struo-privacy-disclosure/,/<\/form>/' "$MAIN")"
for needle in "Privacy notice" "sent off-site to" "excerpt-storage setting"; do
	if printf '%s' "$DISCLOSURE_BODY" | grep -qF "$needle"; then
		pass "settings disclosure covers: $needle"
	else
		fail "settings disclosure covers: $needle"
	fi
done
if grep -qF 'wp_parse_url( self::get_openai_base_url(), PHP_URL_HOST )' "$MAIN"; then
	pass "settings disclosure names the configured provider host"
else
	fail "settings disclosure names the configured provider host"
fi

echo "== 6h. Transient prefix rename (lane 6 / step 2.4) =="
for pair in "PLAN_RATE_LIMIT_KEY_PREFIX = 'struo_plan_rate_'" "CONFIRM_KEY_PREFIX = 'struo_confirm_'" "CREATE_PLAN_KEY_PREFIX = 'struo_create_plan_'" "IDEMPOTENCY_KEY_PREFIX = 'struo_idempotency_'" "RATE_LIMIT_KEY_PREFIX = 'struo_rate_'" "SESSION_CONTEXT_KEY_PREFIX = 'struo_session_'"; do
	check_grep "transient prefix renamed: $pair" "$MAIN" "const $pair;"
done
check_grep "one-time legacy transient rename migration exists" "$MAIN" "migrate_legacy_transient_prefixes"
check_grep "migration flag constant" "$MAIN" "const TRANSIENTS_MIGRATED_FLAG = 'struo_transients_migrated_v1';"
MIGRATE_BODY="$(fn_body "$MAIN" "migrate_legacy_transient_prefixes")"
BATCH_BODY="$(fn_body "$MAIN" "migrate_legacy_transient_batch")"
if printf '%s' "$BATCH_BODY" | grep -qF 'LIMIT 200'; then
	pass "migration batched (LIMIT 200)"
else
	fail "migration batched (LIMIT 200)"
fi
if printf '%s' "$BATCH_BODY" | grep -qF 'delete_transient( $legacy_key )'; then
	pass "legacy key deleted in every case (convergent + cache-aware)"
else
	fail "legacy key deleted in every case (convergent + cache-aware)"
fi
if printf '%s' "$BATCH_BODY" | grep -qF 'get_transient'; then
	pass "migration uses the transient API (not raw option rows)"
else
	fail "migration uses the transient API (not raw option rows)"
fi
check_grep "migration runs in maybe_upgrade_storage" "$MAIN" "self::migrate_legacy_transient_prefixes();"
MIGRATE_BODY="$(fn_body "$MAIN" "migrate_legacy_transient_prefixes")"
BATCH_BODY="$(fn_body "$MAIN" "migrate_legacy_transient_batch")"
if printf '%s' "$BATCH_BODY" | grep -qF "_transient_timeout_' . \$legacy_key"; then
	pass "migration carries the remaining TTL from the timeout row"
else
	fail "migration carries the remaining TTL from the timeout row"
fi
# Expired timeout row => skip and delete (never set_transient(..., 0) = permanent).
# No timeout row => ttl 0 keeps a legacy-permanent transient permanent.
if printf '%s' "$BATCH_BODY" | grep -qF 'false === $timeout ? 0'; then
	pass "no timeout row => permanent (ttl 0)"
else
	fail "no timeout row => permanent (ttl 0)"
fi
if printf '%s' "$BATCH_BODY" | grep -qF 'false !== $timeout' && printf '%s' "$BATCH_BODY" | grep -q '<= time()'; then
	pass "expired timeout row is skipped (not rewritten as permanent)"
else
	fail "expired timeout row is skipped (not rewritten as permanent)"
fi
if printf '%s' "$BATCH_BODY" | grep -qF 'max( 0,'; then
	fail "expired timeout must not collapse to ttl 0 via max(0, remaining)"
else
	pass "expired timeout does not collapse to ttl 0 via max(0, remaining)"
fi
if printf '%s' "$BATCH_BODY" | grep -qF 'AND option_name NOT IN'; then
	pass "unparseable names are excluded from the next SELECT (no batch spin)"
else
	fail "unparseable names are excluded from the next SELECT (no batch spin)"
fi
if printf '%s' "$BATCH_BODY" | grep -qF 'delete_site_transient( $legacy_key )' \
	&& printf '%s' "$BATCH_BODY" | grep -qF 'set_site_transient( $new_key, $value, $ttl )' \
	&& printf '%s' "$BATCH_BODY" | grep -qF "_site_transient_timeout_' . \$legacy_key"; then
	pass "site-transient branch carries TTL, deletes, and does not truncate at 200"
else
	fail "site-transient branch carries TTL, deletes, and does not truncate at 200"
fi
if printf '%s' "$MIGRATE_BODY" | grep -qF '$regular_empty && $site_empty'; then
	pass "migration flag set only after both branches report an empty pass"
else
	fail "migration flag set only after both branches report an empty pass"
fi
if printf '%s' "$BATCH_BODY" | grep -qF "wp_cache_delete( \$legacy_key, 'transient' )" \
	&& printf '%s' "$BATCH_BODY" | grep -qF "wp_cache_delete( \$legacy_key, 'transient_timeout' )" \
	&& printf '%s' "$BATCH_BODY" | grep -qF "wp_cache_delete( \$legacy_key, 'site-transient' )" \
	&& printf '%s' "$BATCH_BODY" | grep -qF "wp_cache_delete( \$legacy_key, 'site-transient_timeout' )"; then
	pass "object-cache deletes use bare keys and core groups"
else
	fail "object-cache deletes use bare keys and core groups"
fi
if printf '%s' "$BATCH_BODY" | grep -qF "wp_cache_delete( '_transient_'"; then
	fail "object-cache deletes must not prefix the transient key"
else
	pass "object-cache deletes do not prefix the transient key"
fi
check_grep "deactivation sweeps both generations" "$MAIN" "_transient_struo_"
check_grep "uninstall sweeps struo_ transient families" "$UNINSTALL" "'struo_confirm_'"

echo "== 7. Provider selection default =="
check_grep "Auto is the default provider" "$MAIN" "const AI_PROVIDER_DEFAULT = 'auto';"
check_grep "provider whitelist includes auto/ai_engine/openai" \
	"$MAIN" "in_array( \$provider, \[ 'auto', 'ai_engine', 'openai' \], true ) ? \$provider : self::AI_PROVIDER_DEFAULT"
check_grep "planner backend prefers wp_ai_client_prompt" "$MAIN" "function_exists( 'wp_ai_client_prompt' )"
BACKEND_BODY="$(fn_body "$MAIN" "get_ai_planner_backend")"
if printf '%s' "$BACKEND_BODY" | grep -q "wp_ai_client_has_text_generation"; then
	pass "planner backend requires a text_generation model before Client"
else
	fail "planner backend requires a text_generation model before Client"
fi
HAS_TG_BODY="$(fn_body "$MAIN" "wp_ai_client_has_text_generation")"
if printf '%s' "$HAS_TG_BODY" | grep -q "function_exists( 'wp_ai_client_prompt' )"; then
	pass "text_generation probe feature-detects the WP AI Client"
else
	fail "text_generation probe feature-detects the WP AI Client"
fi
if printf '%s' "$HAS_TG_BODY" | grep -q "is_supported_for_text_generation"; then
	pass "text_generation probe uses the Client support check"
else
	fail "text_generation probe uses the Client support check"
fi
if printf '%s' "$BACKEND_BODY" | grep -A2 "if ( 'ai_engine' === \$provider )" | grep -q "return 'ai_engine'"; then
	fail "ai_engine pin does not skip Client on WordPress 7"
else
	pass "ai_engine pin does not skip Client on WordPress 7"
fi
check_grep "outbound prompt redaction filter" "$MAIN" "struo_ai_outbound_prompt"
check_grep "console platform hint markup" "$MAIN" "sae-platform-hint"
check_grep "console renders platform hint" "$PLUGIN_DIR/assets/console.js" "function renderPlatformHint"
check_grep "AI Client JSON adapter exists" "$MAIN" "private static function call_ai_client_json_query"
check_grep "OpenAI adapter kept as last resort" "$MAIN" "private static function call_openai_json_query"
check_grep "API key never returned by status (name only)" "$MAIN" "'ai_provider' => \["
if fn_body "$MAIN" "generate_page_copy" | grep -q "call_ai_json_query"; then
	pass "generate_page_copy routes through call_ai_json_query"
else
	fail "generate_page_copy routes through call_ai_json_query"
fi
if fn_body "$MAIN" "generate_page_copy" | grep -q 'simpleJsonQuery'; then
	fail "generate_page_copy no longer calls AI Engine directly"
else
	pass "generate_page_copy no longer calls AI Engine directly"
fi
check_grep "prevent_prompt filter hooked" "$MAIN" "add_filter( 'wp_ai_client_prevent_prompt'"

echo "== 7a. Provider destination hardening =="
GUARD="$PLUGIN_DIR/provider-url-guard.php"
if [ -f "$GUARD" ]; then pass "provider-url-guard.php exists"; else fail "provider-url-guard.php exists"; fi
for denied in "169.254.169.254" "fd00:ec2::254" "100.64.0.0" "172.16.0.0" "192.168.0.0" "fe80::" "fc00::"; do
	check_grep "deny-list covers $denied" "$GUARD" "$denied"
done
check_grep "guard refuses loopback ::1" "$GUARD" "::1 loopback"
check_grep "guard unwraps packed IPv4-mapped IPv6" "$GUARD" "unwrap_packed_ipv4_mapped_ipv6"
check_grep "provider cases cover packed mapped loopback" "$PLUGIN_DIR/tests/provider-url-cases.php" "::ffff:7f00:1"
check_grep "provider cases cover packed mapped private" "$PLUGIN_DIR/tests/provider-url-cases.php" "::ffff:a00:1"
check_grep "provider cases cover packed mapped metadata" "$PLUGIN_DIR/tests/provider-url-cases.php" "::ffff:a9fe:a9fe"
check_grep "default approved hosts include api.openai.com" "$MAIN" "'api.openai.com', 'openrouter.ai'"
if fn_body "$MAIN" "provider_guard_config" | grep -q "struo_allowed_provider_hosts"; then
	fail "provider_guard_config does not widen hosts via filter"
else
	pass "provider_guard_config does not widen hosts via filter"
fi
check_grep "insecure-URL constant honored" "$MAIN" "STRUO_ALLOW_INSECURE_PROVIDER_URL"
check_grep "private-URL constant honored" "$MAIN" "STRUO_ALLOW_PRIVATE_PROVIDER_URL"
OPENAI_BODY="$(fn_body "$MAIN" "call_openai_json_query")"
VALIDATE_AT="$(printf '%s' "$OPENAI_BODY" | grep -n 'validate_provider_destination' | head -1 | cut -d: -f1)"
POST_AT="$(printf '%s' "$OPENAI_BODY" | grep -n 'OpenAICompatibleProvider::post_json' | head -1 | cut -d: -f1)"
if [ -n "$VALIDATE_AT" ] && [ -n "$POST_AT" ] && [ "$VALIDATE_AT" -lt "$POST_AT" ]; then
	pass "provider HTTP hop passes through the destination validator (validate first)"
else
	fail "provider HTTP hop passes through the destination validator (validate first)"
fi
check_grep "redirects disabled for provider requests" "$PROVIDER_HTTP" "'redirection' => 0,"
check_grep "provider HTTP timeout is 45s" "$PROVIDER_HTTP" "REQUEST_TIMEOUT_SECONDS = 45"
check_grep "key host binding option declared" "$MAIN" "const OPENAI_KEY_HOST_OPTION = 'struo_openai_key_host';"
KEYCHECK_AT="$(printf '%s' "$OPENAI_BODY" | grep -n 'check_provider_key_host' | head -1 | cut -d: -f1)"
if [ -n "$KEYCHECK_AT" ] && [ -n "$POST_AT" ] && [ "$KEYCHECK_AT" -lt "$POST_AT" ]; then
	pass "key-host check runs before the provider HTTP hop"
else
	fail "key-host check runs before the provider HTTP hop"
fi
check_grep "empty API key submit keeps the saved value" "$MAIN" "if ( '' === \$value ) {"
check_grep "explicit remove API key action" "$MAIN" "struo_remove_openai_api_key"
check_grep "settings never echo the API key" "$MAIN" 'type="password"'
check_grep "wp-config API key is preferred" "$MAIN" "STRUO_OPENAI_API_KEY"
check_grep "redirect Location revalidated" "$PLUGIN_DIR/provider-url-guard.php" "function validate_redirect_location"
if printf '%s' "$OPENAI_BODY" | grep -q "/chat/completions" \
	&& printf '%s' "$OPENAI_BODY" | grep -q "/responses"; then
	pass "HTTP hop can use chat completions or responses"
else
	fail "HTTP hop can use chat completions or responses"
fi
check_grep "DB key refused on host mismatch" "$MAIN" "'sae_provider_key_host_mismatch'"
check_grep "constant key host mismatch audited" "$MAIN" "'provider_key_constant_host_mismatch'"
check_grep "refusals audit logged without secrets" "$MAIN" "'provider_url_refused'"
check_grep "provider health surfaces in console/status" "$MAIN" "'provider' => self::build_provider_health(),"
check_grep "uninstall removes key-host option" "$UNINSTALL" "'struo_openai_key_host'"

echo "== 7c. Planner hop recording + daily spend cap =="
check_grep "daily token cap option default" "$MAIN" "'ai_plan_daily_token_cap' => 0"
check_grep "spend cap constant names" "$MAIN" "STRUO_PLAN_DAILY_TOKEN_CAP"
check_grep "typed over-cap error" "$MAIN" "'sae_plan_spend_cap'"
JSON_BODY="$(fn_body "$MAIN" "call_ai_json_query")"
if printf '%s' "$JSON_BODY" | grep -q "struo_ai_outbound_prompt"; then
	pass "outbound redaction runs in call_ai_json_query"
else
	fail "outbound redaction runs in call_ai_json_query"
fi
if printf '%s' "$JSON_BODY" | grep -q "PLAN_MAX_PROMPT_LENGTH"; then
	pass "size cap runs in call_ai_json_query"
else
	fail "size cap runs in call_ai_json_query"
fi
if printf '%s' "$JSON_BODY" | grep -q "AI prompt was empty"; then
	pass "empty prompt refused in call_ai_json_query"
else
	fail "empty prompt refused in call_ai_json_query"
fi
EMPTY_AT="$(printf '%s' "$JSON_BODY" | grep -n 'AI prompt was empty' | head -1 | cut -d: -f1)"
REDACT_AT="$(printf '%s' "$JSON_BODY" | grep -n 'struo_ai_outbound_prompt' | head -1 | cut -d: -f1)"
SIZE_AT="$(printf '%s' "$JSON_BODY" | grep -n 'PLAN_MAX_PROMPT_LENGTH' | head -1 | cut -d: -f1)"
SPEND_AT="$(printf '%s' "$JSON_BODY" | grep -n 'check_plan_spend_cap' | head -1 | cut -d: -f1)"
DISPATCH_AT="$(printf '%s' "$JSON_BODY" | grep -n 'dispatch_ai_json_query' | head -1 | cut -d: -f1)"
if [ -n "$EMPTY_AT" ] && [ -n "$REDACT_AT" ] && [ -n "$SIZE_AT" ] && [ -n "$SPEND_AT" ] && [ -n "$DISPATCH_AT" ] \
	&& [ "$EMPTY_AT" -lt "$REDACT_AT" ] && [ "$REDACT_AT" -lt "$SIZE_AT" ] && [ "$SIZE_AT" -lt "$SPEND_AT" ] && [ "$SPEND_AT" -lt "$DISPATCH_AT" ]; then
	pass "empty, redact, size, spend cap run before the provider hop"
else
	fail "empty, redact, size, spend cap run before the provider hop"
fi
check_body_absent "Client adapter is not the sole redaction site" "$MAIN" "call_ai_client_json_query" "struo_ai_outbound_prompt"
check_grep "planner exceptions are audited not returned" "$MAIN" "planner_provider_exception"
check_grep "last hop stored without prompt text" "$MAIN" "const PLANNER_LAST_HOP_OPTION = 'struo_planner_last_hop';"
check_grep "status reports last hop" "$MAIN" "'last' => self::get_planner_last_hop(),"
check_grep "status reports plan spend" "$MAIN" "'plan_spend' => self::get_plan_spend_state(),"
check_grep "status reports text_generation flag" "$MAIN" "'text_generation' => self::wp_ai_client_has_text_generation(),"
check_grep "uninstall removes last-hop option" "$UNINSTALL" "'struo_planner_last_hop'"
check_grep "uninstall removes daily spend rows" "$UNINSTALL" "'struo_plan_spend_'"
check_body_absent "no prompt text in last-hop writer" "$MAIN" "record_planner_hop" "'prompt' =>"
check_grep "console mentions missing text_generation" "$PLUGIN_DIR/assets/console.js" "no text_generation model"

echo "== 7d. Agent plan inbox (lane 3) =="
check_grep "agent plan store option" "$MAIN" "const AGENT_PLANS_OPTION = 'struo_agent_plans'"
check_grep "MCP plans persist through persist_mcp_agent_plan" "$MAIN" "persist_mcp_agent_plan"
check_grep "console status lists agent_plans" "$MAIN" "'agent_plans' => self::list_agent_plan_summaries()"
check_grep "Mission Brief has Multi-page and new pages" "$MAIN" "Multi-page and new pages"
check_grep "console wires agent plan inbox" "$PLUGIN_DIR/assets/console.js" "function renderAgentPlansInbox"
check_grep "console reviews via REST preview" "$PLUGIN_DIR/assets/console.js" "agentPlansEndpoint(planId, 'preview')"
check_grep "uninstall deletes agent plan store" "$UNINSTALL" "'struo_agent_plans'"
PERSIST_BODY="$(fn_body "$MAIN" "create_durable_mutation_plan")"
if printf '%s' "$PERSIST_BODY" | grep -q "mcp' !== self::normalize_token_origin" || printf '%s' "$(fn_body "$MAIN" "persist_mcp_agent_plan")" | grep -q "mcp' !== self::normalize_token_origin"; then
	pass "persist refuses non-mcp origins"
else
	fail "persist refuses non-mcp origins"
fi
if printf '%s' "$PERSIST_BODY" | grep -q "strip_redeemable_fields"; then
	pass "persist strips confirmation before storage"
else
	fail "persist strips confirmation before storage"
fi
if printf '%s' "$PERSIST_BODY" | grep -q "'preview' =>"; then
	pass "persist stores compiled preview on the durable envelope"
else
	fail "persist stores compiled preview on the durable envelope"
fi
if printf '%s' "$PERSIST_BODY" | grep -q "'payload_type' => 'mutation_v1'"; then
	pass "persist uses mutation_v1 payload type"
else
	fail "persist uses mutation_v1 payload type"
fi
check_grep "page_spec_v1 compile helper" "$MAIN" "compile_page_spec_from_create_snapshot"
check_grep "page_spec_v1 apply writes frozen content" "$MAIN" "execute_page_spec_apply"
check_grep "page_spec_v1 durable persist" "$MAIN" "create_durable_page_spec_plan"
PAGE_SPEC_APPLY="$(fn_body "$MAIN" "execute_page_spec_apply")"
if printf '%s' "$PAGE_SPEC_APPLY" | grep -q "generate_page_copy"; then
	fail "page_spec apply must not regenerate copy"
else
	pass "page_spec apply must not regenerate copy"
fi
if printf '%s' "$PAGE_SPEC_APPLY" | grep -q "serialized_content"; then
	pass "page_spec apply writes stored serialized_content"
else
	fail "page_spec apply writes stored serialized_content"
fi
CREATE_PLAN_BODY="$(fn_body "$MAIN" "plan_create_page")"
if printf '%s' "$CREATE_PLAN_BODY" | grep -q "compile_page_spec_from_create_snapshot"; then
	pass "plan_create_page compiles page_spec at plan time"
else
	fail "plan_create_page compiles page_spec at plan time"
fi
if printf '%s' "$CREATE_PLAN_BODY" | grep -q "attach_durable_page_spec_envelope"; then
	pass "plan_create_page attaches page_spec durable envelope"
else
	fail "plan_create_page attaches page_spec durable envelope"
fi
CREATE_INTENT_BODY="$(fn_body "$MAIN" "is_create_intent_request")"
if printf '%s' "$CREATE_INTENT_BODY" | grep -q '\[\\s\\S\]{0,40}?'; then
	pass "create intent allows adjectives before page/post"
else
	fail "create intent allows adjectives before page/post"
fi
PREPARE_CREATE_BODY="$(fn_body "$MAIN" "prepare_create_page_outline")"
if printf '%s' "$PREPARE_CREATE_BODY" | grep -q 'normalize_create_post_type( \$payload\['\''post_type'\'''; then
	pass "create outline honors payload post_type"
else
	fail "create outline honors payload post_type"
fi
if grep -q "function shouldForceCreateIntent" "$PLUGIN_DIR/assets/console.js" \
	&& grep -q "isCreateIntentRailMode" "$PLUGIN_DIR/assets/console.js"; then
	pass "console forces create intent from create rail"
else
	fail "console forces create intent from create rail"
fi
REVIEW_PLAN_BODY="$(fn_body "$MAIN" "user_can_review_agent_plan")"
if printf '%s' "$REVIEW_PLAN_BODY" | grep -q "create_posts" \
	&& printf '%s' "$REVIEW_PLAN_BODY" | grep -q "page_spec_v1"; then
	pass "create page_spec plans reviewable without existing post_id"
else
	fail "create page_spec plans reviewable without existing post_id"
fi
PAGE_SPEC_COMPILE="$(fn_body "$MAIN" "compile_page_spec_from_create_snapshot")"
if printf '%s' "$PAGE_SPEC_COMPILE" | grep -q "sae_copy_generation_unavailable" \
	&& printf '%s' "$PAGE_SPEC_COMPILE" | grep -q "sae_planner_unavailable"; then
	pass "page_spec compile falls back on planner unavailable"
else
	fail "page_spec compile falls back on planner unavailable"
fi
if printf '%s' "$PAGE_SPEC_COMPILE" | grep -q "sae_copy_generation_failed"; then
	fail "page_spec compile must not fallback on copy-generation failed"
else
	pass "page_spec compile must not fallback on copy-generation failed"
fi
OPENAI_QUERY_BODY="$(fn_body "$MAIN" "call_openai_json_query")"
if printf '%s' "$OPENAI_QUERY_BODY" | grep -q "429 === \$status_code" \
	&& printf '%s' "$OPENAI_QUERY_BODY" | grep -q "sae_planner_unavailable"; then
	pass "provider HTTP 429 maps to planner unavailable"
else
	fail "provider HTTP 429 maps to planner unavailable"
fi
check_grep "durable plans table suffix" "$MAIN" "const PLANS_TABLE_SUFFIX = 'struo_plans'"
check_grep "agent plan approve route" "$MAIN" "approve_agent_plan"
check_grep "agent plan apply route" "$MAIN" "apply_agent_plan"
check_grep "struo_plan capability constant" "$MAIN" "const CAP_STRUO_PLAN = 'struo_plan'"
check_grep "struo_approve capability constant" "$MAIN" "const CAP_STRUO_APPROVE = 'struo_approve'"
check_grep "struo_apply capability constant" "$MAIN" "const CAP_STRUO_APPLY = 'struo_apply'"
PREVIEW_BODY="$(fn_body "$MAIN" "preview_agent_plan")"
if printf '%s' "$PREVIEW_BODY" | grep -q "ensure_kill_switch"; then
	pass "operator preview respects kill switch"
else
	fail "operator preview respects kill switch"
fi
APPLY_PLAN_BODY="$(fn_body "$MAIN" "apply_agent_plan")"
if printf '%s' "$APPLY_PLAN_BODY" | grep -q "ensure_kill_switch"; then
	pass "operator apply respects kill switch"
else
	fail "operator apply respects kill switch"
fi
MCP_MAP_BODY="$(fn_body "$MAIN" "mcp_operation_map")"
if printf '%s' "$MCP_MAP_BODY" | grep -q "legacy_prefix = 'subsurface_'"; then
	fail "MCP has no former plugin prefix aliases"
else
	pass "MCP has no former plugin prefix aliases"
fi
MCP_TOOLS_BODY="$(fn_body "$MAIN" "register_mcp_tools")"
if printf '%s' "$MCP_TOOLS_BODY" | grep -q "apply-agent-plan"; then
	fail "MCP catalog has no apply-agent-plan tool"
else
	pass "MCP catalog has no apply-agent-plan tool"
fi
if printf '%s' "$PREVIEW_BODY" | grep -q "'plan_only' => true"; then
	pass "operator preview uses plan-only dry-run"
else
	fail "operator preview uses plan-only dry-run"
fi
if printf '%s' "$PREVIEW_BODY" | grep -q "'plan_id' =>"; then
	pass "operator preview returns plan_id"
else
	fail "operator preview returns plan_id"
fi
PLAN_BODY="$(fn_body "$MAIN" "plan_block_change")"
if printf '%s' "$PLAN_BODY" | grep -q "attach_durable_plan_envelope"; then
	pass "console plan attaches durable envelope"
else
	fail "console plan attaches durable envelope"
fi
if printf '%s' "$PLAN_BODY" | grep -q "'plan_only' =>"; then
	pass "console plan uses plan-only dry-run context"
else
	fail "console plan uses plan-only dry-run context"
fi
if printf '%s' "$PREVIEW_BODY" | grep -q "run_plan_dry_run" && printf '%s' "$PREVIEW_BODY" | grep -q "'rest'" && ! printf '%s' "$PREVIEW_BODY" | grep -q "'plan_only' => true"; then
	fail "operator preview no longer mints via bare REST dry-run"
else
	pass "operator preview no longer mints via bare REST dry-run"
fi
if grep -q "struo_approve" "$PLUGIN_DIR/assets/console.js" && grep -q "approveDurablePlan" "$PLUGIN_DIR/assets/console.js"; then
	pass "console approves via durable plan envelope"
else
	fail "console approves via durable plan envelope"
fi
if grep -q "applyDurablePlan" "$PLUGIN_DIR/assets/console.js" && grep -q "struo_apply" "$PLUGIN_DIR/assets/console.js"; then
	pass "console applies via durable plan envelope"
else
	fail "console applies via durable plan envelope"
fi
if grep -q "'struo_approve'" "$MAIN" && grep -q "'struo_apply'" "$MAIN"; then
	pass "console status exposes Struo approve/apply caps"
else
	fail "console status exposes Struo approve/apply caps"
fi
if ! printf '%s' "$PREVIEW_BODY" | grep -q "run_plan_dry_run( \$prepared, \$response_mode, 'rest', \[\] )"; then
	pass "preview_agent_plan does not mint with empty REST context"
else
	fail "preview_agent_plan does not mint with empty REST context"
fi
PLAN_BODY="$(fn_body "$MAIN" "plan_block_change")"
if printf '%s' "$PLAN_BODY" | grep -q "'origin' => self::normalize_token_origin"; then
	pass "plan audit stamps origin mcp vs rest"
else
	fail "plan audit stamps origin mcp vs rest"
fi

echo "== 7b. Key-exposure negatives (scoped to read/export/error surfaces) =="
for surface in get_console_status get_console_audit get_console_audit_export build_console_audit_csv; do
	check_body_absent "no api_key in $surface" "$MAIN" "$surface" "api_key"
	check_body_absent "no Authorization header in $surface" "$MAIN" "$surface" "Authorization"
done
check_body_absent "no api_key in plan rate-limit error builder" "$MAIN" "build_plan_rate_limit_response" "api_key"
check_body_absent "no Authorization in client adapter" "$MAIN" "call_ai_client_json_query" "Authorization"
check_body_absent "no api_key in client adapter" "$MAIN" "call_ai_client_json_query" "api_key"
# The OpenAI bearer key may exist ONLY inside the provider hop. MAIN must not
# carry Authorization after the extract; Google findings tokens live in includes/.
check_count "no Authorization in plugin bootstrap" "$MAIN" "Authorization" 0
check_count "'Authorization' appears only in the OpenAI adapter" "$PROVIDER_HTTP" "Authorization" 1
check_body_absent "no raw key option reads outside its getter" "$MAIN" "get_ai_provider" "OPENAI_API_KEY_OPTION"

echo "== 8. Uninstall scope =="
check_grep "guarded by WP_UNINSTALL_PLUGIN" "$UNINSTALL" "defined( 'WP_UNINSTALL_PLUGIN' )"
for option in \
	subsurface_ai_block_editor_options \
	subsurface_ai_block_editor_audit \
	subsurface_ai_block_editor_audit_db_version \
	subsurface_ai_block_editor_audit_migrated \
	subsurface_ai_block_editor_user_templates \
	subsurface_ai_block_editor_template_registry_v2 \
	subsurface_ai_block_editor_pattern_registry_v1 \
	subsurface_ai_block_editor_legacy_seeds \
	subsurface_ai_block_editor_openai_api_key; do
	check_grep "uninstall deletes legacy $option" "$UNINSTALL" "'$option'"
done
for option in \
	struo_options \
	struo_legacy_seeds \
	struo_audit \
	struo_audit_db_version \
	struo_audit_migrated \
	struo_openai_api_key \
	struo_google_service_account \
	struo_ga4_property_id \
	struo_gsc_site_url \
	struo_user_templates \
	struo_template_registry_v2 \
	struo_pattern_registry_v1 \
	struo_options_migrated_v1 \
	struo_activation_handoff_notice \
	struo_transients_migrated_v1 \
	struo_openai_key_host \
	struo_planner_last_hop \
	struo_agent_plans; do
	check_grep "uninstall deletes $option" "$UNINSTALL" "'$option'"
done
for family in struo_confirm_ struo_create_plan_ struo_idempotency_ struo_plan_rate_ struo_rate_ struo_session_ struo_findings_ \
	sae_confirm_ sae_create_plan_ sae_idempotency_ sae_plan_rate_ sae_rate_ sae_session_; do
	check_grep "uninstall clears $family transients" "$UNINSTALL" "'$family'"
done
check_grep "audit table drop is opt-in (generic const)" "$UNINSTALL" "STRUO_DROP_AUDIT_TABLE_ON_UNINSTALL"
check_grep "audit table drop is opt-in (legacy const)" "$UNINSTALL" "SAE_DROP_AUDIT_TABLE_ON_UNINSTALL"
check_grep "plans table drop is opt-in (generic const)" "$UNINSTALL" "STRUO_DROP_PLANS_TABLE_ON_UNINSTALL"
check_grep "plans table drop is opt-in (legacy const)" "$UNINSTALL" "SAE_DROP_PLANS_TABLE_ON_UNINSTALL"
if awk '/struo_drop_audit/,/struo_drop_plans/' "$UNINSTALL" | grep -q "struo_plans"; then
	fail "audit-drop constant does not drop plans"
else
	pass "audit-drop constant does not drop plans"
fi
check_grep "drop uses idempotent DROP TABLE IF EXISTS" "$UNINSTALL" "DROP TABLE IF EXISTS"
check_grep "uninstall purges site-transient rows (_site_transient_sae_)" "$UNINSTALL" "_site_transient_sae_"
check_grep "uninstall purges site-transient timeout rows" "$UNINSTALL" "_site_transient_timeout_sae_"

echo "== 9. Fail-closed allowlist default =="
DEFAULTS_BODY="$(awk '/public static function defaults/,/^	}/' "$MAIN")"
if printf '%s' "$DEFAULTS_BODY" | grep -qE "7089|10711|18901|18302"; then
	fail "post-ID defaults do not ship hardcoded site IDs"
else
	pass "post-ID defaults do not ship hardcoded site IDs"
fi
if grep -n "Dremio" "$MAIN" "$PLUGIN_DIR/assets/console.js" >/dev/null 2>&1; then
	fail "runtime PHP/JS contains no Dremio branding"
else
	pass "runtime PHP/JS contains no Dremio branding"
fi
check_grep "plugin display name default is Struo" "$MAIN" "return 'Struo';"
check_grep "canonical MCP tools use struo_ prefix" "$MAIN" "'name' => 'struo_insert_block'"
check_count "no former class_alias" "$MAIN" "Subsurface_AI_Block_Editor" 0

echo "== 10. §2.5 audit table + identity text =="
check_grep "audit table keeps sae_audit_log suffix" "$MAIN" "const AUDIT_TABLE_SUFFIX = 'sae_audit_log';"
check_grep "status payload identifies plugin as struo" "$MAIN" "'name' => 'struo',"

echo "== 10b. Fresh-install discovery fail-closed =="
REGISTRY_BODY="$(fn_body "$MAIN" "get_default_content_type_registry")"
if printf '%s' "$REGISTRY_BODY" | grep -q "legacy_seeds_enabled()"; then
	pass "default content-type registry branches on legacy_seeds_enabled()"
else
	fail "default content-type registry branches on legacy_seeds_enabled()"
fi
if printf '%s' "$REGISTRY_BODY" | grep -q "'enabled' => false"; then
	pass "non-legacy default has post.enabled=false (no dynamic discovery)"
else
	fail "non-legacy default has post.enabled=false (no dynamic discovery)"
fi
if printf '%s' "$REGISTRY_BODY" | grep -q "'enabled' => true"; then
	pass "legacy default keeps today's post discovery defaults"
else
	fail "legacy default keeps today's post discovery defaults"
fi

echo "== 11. PHP floor 8.2 =="
HEADER_PHP="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Requires PHP:[[:space:]]*//p' "$MAIN" | head -1 | tr -d '[:space:]')"
README_PHP="$(sed -n 's/^Requires PHP:[[:space:]]*//p' "$PLUGIN_DIR/readme.txt" | head -1 | tr -d '[:space:]')"
COMPOSER_PHP="$(sed -n 's/.*"php":[[:space:]]*">=\([^"]*\)".*/\1/p' "$PLUGIN_DIR/composer.json" | head -1 | tr -d '[:space:]')"
if [ "$HEADER_PHP" = "8.2" ] && [ "$README_PHP" = "$HEADER_PHP" ] && [ "$COMPOSER_PHP" = "$HEADER_PHP" ]; then
	pass "PHP floor strings agree across header/readme/composer (all 8.2)"
else
	fail "PHP floor strings agree across header/readme/composer (header=$HEADER_PHP readme=$README_PHP composer=$COMPOSER_PHP)"
fi
check_grep "header Requires PHP: 8.2" "$MAIN" "* Requires PHP: 8.2"
check_grep "readme.txt Requires PHP: 8.2" "$PLUGIN_DIR/readme.txt" "^Requires PHP: 8.2"
check_grep "composer.json php floor >=8.2" "$PLUGIN_DIR/composer.json" '"php": ">=8.2"'
check_grep "MIN_PHP_VERSION constant is 8.2" "$MAIN" "const MIN_PHP_VERSION = '8.2';"
check_grep "activation hook runs on_activation guard" "$MAIN" "register_activation_hook( __FILE__, \[ 'Struo_Block_Editor', 'on_activation' \] );"
PHP_GUARD_BODY="$(fn_body "$MAIN" "on_activation")"
if printf '%s' "$PHP_GUARD_BODY" | grep -q "php_version_ok()"; then
	pass "activation refuses servers below the PHP floor"
else
	fail "activation refuses servers below the PHP floor"
fi
ACT_PHP_AT="$(printf '%s' "$PHP_GUARD_BODY" | grep -n 'php_version_ok()' | head -1 | cut -d: -f1)"
ACT_NET_AT="$(printf '%s' "$PHP_GUARD_BODY" | grep -n 'if ( \$network_wide )' | head -1 | cut -d: -f1)"
if [ -n "$ACT_PHP_AT" ] && [ -n "$ACT_NET_AT" ] && [ "$ACT_PHP_AT" -lt "$ACT_NET_AT" ]; then
	pass "on_activation checks the PHP floor BEFORE \$network_wide"
else
	fail "on_activation checks the PHP floor BEFORE \$network_wide"
fi

echo "== 11b. Load-time PHP floor guard =="
GUARD_LINE="$(grep -n "version_compare( PHP_VERSION, '8.2', '<' )" "$MAIN" | head -1 | cut -d: -f1)"
CLASS_LINE="$(grep -n "^final class Struo_Block_Editor {" "$MAIN" | head -1 | cut -d: -f1)"
if [ -n "$GUARD_LINE" ] && [ -n "$CLASS_LINE" ] && [ "$GUARD_LINE" -lt "$CLASS_LINE" ]; then
	pass "load-time guard runs BEFORE the class is declared"
else
	fail "load-time guard runs BEFORE the class is declared"
fi
check_grep "load-time guard hooks admin_notices" "$MAIN" "add_action(
		'admin_notices',"
check_grep "load-time helper is required before floors" "$MAIN" "require_once __DIR__ . '/includes/plugin-entry-deactivate.php';"
check_count "both PHP and WP floors deactivate the active entry" "$MAIN" "struo_deactivate_active_plugin_entry( __FILE__ )" 2
if [ -f "$PLUGIN_DIR/includes/plugin-entry-deactivate.php" ]; then
	pass "includes/plugin-entry-deactivate.php exists"
else
	fail "includes/plugin-entry-deactivate.php exists"
fi
check_count "helper does not mention a former stub basename" "$PLUGIN_DIR/includes/plugin-entry-deactivate.php" "subsurface-ai-block-editor" 0
LOADTIME_RETURN="$(awk '/version_compare\( PHP_VERSION, .8.2., .<. \)/,/^final class/' "$MAIN" | grep -c "^[[:space:]]*return;$")"
if [ "$LOADTIME_RETURN" -ge 1 ]; then
	pass "old-PHP path returns before any plugin code executes"
else
	fail "old-PHP path returns before any plugin code executes"
fi

echo "== 11c. WordPress floor 6.9 =="
HEADER_WP="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Requires at least:[[:space:]]*//p' "$MAIN" | head -1 | tr -d '[:space:]')"
README_WP="$(sed -n 's/^Requires at least:[[:space:]]*//p' "$PLUGIN_DIR/readme.txt" | head -1 | tr -d '[:space:]')"
if [ "$HEADER_WP" = "6.9" ] && [ "$README_WP" = "$HEADER_WP" ]; then
	pass "WP floor strings agree across header/readme (all 6.9)"
else
	fail "WP floor strings agree across header/readme (header=$HEADER_WP readme=$README_WP)"
fi
check_grep "MIN_WP_VERSION constant is 6.9" "$MAIN" "const MIN_WP_VERSION = '6.9';"
if printf '%s' "$PHP_GUARD_BODY" | grep -q "wp_version_ok()"; then
	pass "activation refuses sites below the WP floor"
else
	fail "activation refuses sites below the WP floor"
fi
ACT_WP_AT="$(printf '%s' "$PHP_GUARD_BODY" | grep -n 'wp_version_ok()' | head -1 | cut -d: -f1)"
if [ -n "$ACT_PHP_AT" ] && [ -n "$ACT_WP_AT" ] && [ -n "$ACT_NET_AT" ] && [ "$ACT_PHP_AT" -lt "$ACT_WP_AT" ] && [ "$ACT_WP_AT" -lt "$ACT_NET_AT" ]; then
	pass "on_activation checks PHP then WP before \$network_wide"
else
	fail "on_activation checks PHP then WP before \$network_wide"
fi
WP_GUARD_LINE="$(grep -n "version_compare( \$GLOBALS\['wp_version'\], '6.9', '<' )" "$MAIN" | head -1 | cut -d: -f1)"
if [ -n "$WP_GUARD_LINE" ] && [ -n "$CLASS_LINE" ] && [ "$WP_GUARD_LINE" -lt "$CLASS_LINE" ]; then
	pass "load-time WP guard runs BEFORE the class is declared"
else
	fail "load-time WP guard runs BEFORE the class is declared"
fi

echo "== 11d. Abilities registration policy =="
ABILITIES="$PLUGIN_DIR/includes/class-abilities.php"
if [ -f "$ABILITIES" ]; then pass "includes/class-abilities.php exists"; else fail "includes/class-abilities.php exists"; fi
check_grep "abilities file required from struo.php" "$MAIN" "require_once __DIR__ . '/includes/class-abilities.php';"
check_grep "abilities category hook" "$ABILITIES" "wp_abilities_api_categories_init"
check_grep "abilities init hook" "$ABILITIES" "wp_abilities_api_init"
check_grep "category slug is struo" "$ABILITIES" "const CATEGORY = 'struo';"
for ability in \
	'struo/get-block-catalog' \
	'struo/get-post-blocks' \
	'struo/plan-block-change' \
	'struo/preview-insert' \
	'struo/preview-update' \
	'struo/preview-remove' \
	'struo/preview-batch' \
	'struo/apply-agent-plan'; do
	check_grep "registers $ability" "$ABILITIES" "'$ability'"
done
check_count "no unconstrained update-post-blocks ability" "$ABILITIES" "update-post-blocks" 0
check_count "no per-block acf insert abilities" "$ABILITIES" "wp_register_ability( 'acf/" 0
check_count "read/plan abilities pin mcp.public true (3)" "$ABILITIES" "'public' => true" 3
check_count "preview+apply abilities pin mcp.public false (5)" "$ABILITIES" "'public' => false" 5
check_grep "apply-agent-plan mcp.public forced false after filters" "$ABILITIES" "struo_apply_ability_never_mcp_public"
APPLY_EXEC="$(fn_body "$ABILITIES" "execute_apply_agent_plan")"
if printf '%s' "$APPLY_EXEC" | grep -q "dispatch_internal"; then
	fail "apply ability execute does not use dispatch_internal"
else
	pass "apply ability execute does not use dispatch_internal"
fi
if printf '%s' "$APPLY_EXEC" | grep -q "apply_agent_plan"; then
	pass "apply ability execute calls apply_agent_plan"
else
	fail "apply ability execute calls apply_agent_plan"
fi
if awk '/ABILITY_GET_BLOCK_CATALOG =>/,/ABILITY_PLAN_BLOCK_CHANGE =>/' "$ABILITIES" | grep -q "'ai_editor'"; then
	pass "catalog/get-post-blocks opt into AI Editor"
else
	fail "catalog/get-post-blocks opt into AI Editor"
fi
if awk '/ABILITY_PLAN_BLOCK_CHANGE =>/,/^	public static function can_read_catalog/' "$ABILITIES" | grep -q "'ai_editor'"; then
	fail "plan and preview abilities do not opt into AI Editor"
else
	pass "plan and preview abilities do not opt into AI Editor"
fi
check_grep "abilities execute through dispatch_internal" "$ABILITIES" "Struo_Block_Editor::dispatch_internal("
check_grep "ability execute origin is mcp" "$ABILITIES" "'mcp',"
check_grep "mcp.public override is filter-only" "$ABILITIES" "struo_ability_mcp_public"
check_grep "console status reports abilities platform" "$MAIN" "'abilities' => function_exists( 'wp_register_ability' )"
check_grep "console status reports ai_client platform" "$MAIN" "'ai_client' => function_exists( 'wp_ai_client_prompt' )"
check_grep "console status reports connectors platform" "$MAIN" "'connectors' => self::wp_connectors_available()"

echo "== 17. Former REST delegates are gone =="
if [ -f "$PLUGIN_DIR/includes/class-legacy-rest.php" ]; then
	fail "legacy REST module stays deleted"
else
	pass "legacy REST module stays deleted"
fi

echo "== 18. Hotfix: auth before allowlist in permission path =="
CAN_WRITE_BODY="$(fn_body "$MAIN" "object_write_post")"
WRITE_AUTH_AT="$(printf '%s' "$CAN_WRITE_BODY" | grep -n 'is_user_logged_in' | head -1 | cut -d: -f1)"
WRITE_ENSURE_AT="$(printf '%s' "$CAN_WRITE_BODY" | grep -n 'ensure_write_allowed' | head -1 | cut -d: -f1)"
if [ -n "$WRITE_AUTH_AT" ] && [ -n "$WRITE_ENSURE_AT" ] && [ "$WRITE_AUTH_AT" -lt "$WRITE_ENSURE_AT" ]; then
	pass "can_write_post checks is_user_logged_in BEFORE ensure_write_allowed"
else
	fail "can_write_post checks is_user_logged_in BEFORE ensure_write_allowed"
fi
if printf '%s' "$CAN_WRITE_BODY" | grep -q "rest_forbidden"; then
	pass "anonymous write returns rest_forbidden"
else
	fail "anonymous write returns rest_forbidden"
fi
CAN_READ_BODY="$(fn_body "$MAIN" "object_disclose_post")"
READ_AUTH_AT="$(printf '%s' "$CAN_READ_BODY" | grep -n 'is_user_logged_in' | head -1 | cut -d: -f1)"
READ_ALLOW_AT="$(printf '%s' "$CAN_READ_BODY" | grep -n 'get_allowed_post_ids' | head -1 | cut -d: -f1)"
if [ -n "$READ_AUTH_AT" ] && [ -n "$READ_ALLOW_AT" ] && [ "$READ_AUTH_AT" -lt "$READ_ALLOW_AT" ]; then
	pass "can_read_post checks is_user_logged_in BEFORE the allowlist"
else
	fail "can_read_post checks is_user_logged_in BEFORE the allowlist"
fi
ENSURE_WRITE_BODY="$(fn_body "$MAIN" "ensure_write_allowed")"
ENSURE_AUTH_AT="$(printf '%s' "$ENSURE_WRITE_BODY" | grep -n 'is_user_logged_in' | head -1 | cut -d: -f1)"
if [ -n "$ENSURE_AUTH_AT" ] && [ "$ENSURE_AUTH_AT" -le 4 ]; then
	pass "ensure_write_allowed guards anonymous callers before allowlist/kill-switch"
else
	fail "ensure_write_allowed guards anonymous callers before allowlist/kill-switch"
fi
ENSURE_READ_BODY="$(fn_body "$MAIN" "ensure_read_allowed")"
if printf '%s' "$ENSURE_READ_BODY" | grep -q "is_user_logged_in"; then
	pass "ensure_read_allowed guards anonymous callers"
else
	fail "ensure_read_allowed guards anonymous callers"
fi

echo "== 12. Multisite: per-site activation only =="
if printf '%s' "$PHP_GUARD_BODY" | grep -q 'if ( \$network_wide )'; then
	pass "activation guard refuses network-wide activation"
else
	fail "activation guard refuses network-wide activation"
fi
check_grep "network-wide refusal message" "$MAIN" "Struo supports per-site activation only."
check_count "readme.txt has no Network wording" "$PLUGIN_DIR/readme.txt" "[Nn]etwork" 0

echo "== 13. Composer dev floors =="
check_grep "php_codesniffer floor ^3.13.6" "$PLUGIN_DIR/composer.json" '"squizlabs/php_codesniffer": "\^3.13.6"'
check_grep "wpcs floor ^3.4.1" "$PLUGIN_DIR/composer.json" '"wp-coding-standards/wpcs": "\^3.4.1"'
check_grep "phpunit-polyfills kept ^2.0" "$PLUGIN_DIR/composer.json" '"yoast/phpunit-polyfills": "\^2.0"'
if [ -f "$PLUGIN_DIR/composer.lock" ]; then
	pass "composer.lock is committed"
else
	fail "composer.lock is committed"
fi
check_grep "composer.json pins platform PHP 8.2" "$PLUGIN_DIR/composer.json" '"php": "8.2.0"'
if [ -f "$PLUGIN_DIR/phpcs.xml.dist" ]; then
	pass "phpcs.xml.dist exists"
else
	fail "phpcs.xml.dist exists"
fi

echo "== 14. Docs truth =="
check_grep "readme.txt changelog heading is 0.3.0" "$PLUGIN_DIR/readme.txt" "^= 0.3.0 ="
check_grep "readme.txt Stable tag is 0.3.0" "$PLUGIN_DIR/readme.txt" "Stable tag: 0.3.0"
check_grep "readme.txt Tested up to is 7.1" "$PLUGIN_DIR/readme.txt" "Tested up to: 7.1"
check_count "readme.txt has no Unreleased changelog heading" "$PLUGIN_DIR/readme.txt" "^= Unreleased =" 0
check_count "README does not list request planner_options.envId as a control" "$PLUGIN_DIR/README.md" "planner_options.envId" 0
check_grep "VERSION constant is 0.3.0" "$MAIN" "const VERSION = '0.3.0';"
check_grep "plugin header Version is 0.3.0" "$MAIN" "Version: 0.3.0"
check_grep "description distinguishes content mutations from registry writes" \
	"$PLUGIN_DIR/readme.txt" "registry and admin writes (templates, patterns,"
check_grep "idempotency_key documented as optional" "$PLUGIN_DIR/readme.txt" "optional idempotency_key"
check_grep "readme mutations persist through durable plans" "$PLUGIN_DIR/readme.txt" "plan_id"
check_count "readme does not sell single-use confirmation tokens" "$PLUGIN_DIR/readme.txt" "single-use confirmation token" 0
check_grep "readme documents External services" "$PLUGIN_DIR/readme.txt" "== External services =="
check_count "stale Phase 24 sandbox defaults claim removed" "$PLUGIN_DIR/README.md" "Phase 24" 0
check_grep "fresh installs documented fail-closed" "$PLUGIN_DIR/README.md" "Fresh installs are fail-closed"
check_grep "abilities policy doc exists" "$PLUGIN_DIR/docs/abilities.md" "mcp.public"
check_grep "apply-ability spike stays not MCP-public" "$PLUGIN_DIR/docs/apply-ability-spike.md" "mcp.public"
check_grep "abilities.md points at apply-ability spike" "$PLUGIN_DIR/docs/abilities.md" "apply-ability-spike.md"
if grep -q "struo_approve" "$PLUGIN_DIR/docs/abilities.md" \
	&& grep -q "struo_apply" "$PLUGIN_DIR/docs/abilities.md" \
	&& ! grep -q "Review mints a REST" "$PLUGIN_DIR/docs/abilities.md"; then
	pass "abilities.md documents durable approve/apply"
else
	fail "abilities.md documents durable approve/apply"
fi
check_grep "README documents WordPress Abilities" "$PLUGIN_DIR/README.md" "struo/get-block-catalog"
check_grep "README apply is durable plan_id" "$PLUGIN_DIR/README.md" "sae_plan_apply_required"
check_grep "abilities.md rejects direct REST token apply" "$PLUGIN_DIR/docs/abilities.md" "sae_plan_apply_required"
check_grep "rest-probe expects plan-only dry-run" "$PLUGIN_DIR/tests/rest-probe.sh" "REST-origin dry-run returns plan_id and is non-redeemable"
check_grep "rest-probe rejects token persist" "$PLUGIN_DIR/tests/rest-probe.sh" "sae_plan_apply_required"
check_grep "wp-eval live REST dispatch" "$PLUGIN_DIR/tests/wp-eval-cases.php" "rest_do_request"

echo "== 15. Release zip builder =="
BUILD="$PLUGIN_DIR/bin/build-zip.sh"
if [ -f "$BUILD" ]; then pass "bin/build-zip.sh exists"; else fail "bin/build-zip.sh exists"; fi
for item in struo.php uninstall.php assets block-manifest.json readme.txt README.md CHANGELOG.md LICENSE includes/class-abilities.php includes/class-authority.php includes/class-durable-plans.php includes/class-mutation-journal.php includes/class-mutation-recovery.php includes/class-findings.php includes/class-operation-catalog.php includes/plugin-entry-deactivate.php provider-url-guard.php src/Plugin.php src/Rest/RouteRegistrar.php src/AI/OpenAICompatibleProvider.php; do
	check_grep "build stages $item" "$BUILD" "$item"
done
if grep -q "subsurface-ai-block-editor.php" "$BUILD" || grep -q "class-legacy-rest.php" "$BUILD"; then
	fail "build does not stage former entry or legacy REST"
else
	pass "build does not stage former entry or legacy REST"
fi
if grep -vE '^[[:space:]]*#' "$BUILD" | grep -Eq '(^|[[:space:]]"'\''/])(\.git|docs|tests|composer\.json|\.github)([[:space:]"'\''/]|$)'; then
	fail "build script never stages .git/docs/tests/bin/composer.json/.github"
else
	pass "build script never stages .git/docs/tests/bin/composer.json/.github"
fi
check_grep "dist/ is gitignored" "$PLUGIN_DIR/.gitignore" "^dist/"
check_grep "build is deterministic (LC_ALL=C)" "$BUILD" "export LC_ALL=C"
check_grep "build strips extra fields (zip -X) and dir entries (-D)" "$BUILD" "zip -X -D"
check_grep "build fixes mtimes to the HEAD commit date" "$BUILD" 'git -C "$PLUGIN_DIR" log -1 --format=%ct'
check_grep "build accepts SOURCE_DATE_EPOCH without git" "$BUILD" "SOURCE_DATE_EPOCH"
check_grep "build fails closed on missing files" "$BUILD" "required file missing"

# Runtime: actually BUILD the zip and inspect it.
ZIP_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$MAIN" | head -1 | tr -d '[:space:]')"
ZIP_PATH="$PLUGIN_DIR/dist/struo-$ZIP_VERSION.zip"
if command -v zip >/dev/null 2>&1 && command -v unzip >/dev/null 2>&1; then
	if bash "$BUILD" >/dev/null 2>&1 && [ -f "$ZIP_PATH" ]; then
		pass "build-zip.sh produces dist/struo-$ZIP_VERSION.zip"
	else
		fail "build-zip.sh produces dist/struo-$ZIP_VERSION.zip"
	fi
	unzip -l "$ZIP_PATH" > "$PLUGIN_DIR/dist/.zip-list" 2>/dev/null
	ZIP_LIST="$(cat "$PLUGIN_DIR/dist/.zip-list")"
	for entry in \
		"struo/struo.php" \
		"struo/uninstall.php" \
		"struo/block-manifest.json" \
		"struo/readme.txt" \
		"struo/README.md" \
		"struo/CHANGELOG.md" \
		"struo/LICENSE" \
		"struo/includes/class-abilities.php" \
		"struo/includes/class-authority.php" \
		"struo/includes/class-durable-plans.php" \
		"struo/includes/class-mutation-journal.php" \
		"struo/includes/class-mutation-recovery.php" \
		"struo/includes/class-findings.php" \
		"struo/includes/class-operation-catalog.php" \
		"struo/includes/plugin-entry-deactivate.php" \
		"struo/provider-url-guard.php" \
		"struo/src/Plugin.php" \
		"struo/src/Rest/RouteRegistrar.php" \
		"struo/src/AI/OpenAICompatibleProvider.php" \
		"struo/assets/console.js" \
		"struo/assets/console.css" \
		"struo/assets/work-queue.js" \
		"struo/assets/work-queue.asset.php" \
		"struo/assets/gutenberg-host.js" \
		"struo/assets/gutenberg-host.asset.php"; do
		if printf '%s' "$ZIP_LIST" | grep -q "$entry"; then
			pass "zip contains $entry"
		else
			fail "zip contains $entry"
		fi
	done
	for excluded in " docs/" " tests/" " bin/" " composer.json" " composer.lock" " .github" "/package.json" "/src/work-queue" "/src/gutenberg-host" "/assets/src/" "/node_modules"; do
		if printf '%s' "$ZIP_LIST" | grep -qF "$excluded"; then
			fail "zip excludes${excluded}"
		else
			pass "zip excludes${excluded}"
		fi
	done
	# zip -D emits no directory entries; every listed file must live under
	# the struo/ top-level folder and at least one file must exist.
	if printf '%s' "$ZIP_LIST" | awk '/^ *[0-9]+ +[0-9-]+ +[0-9:]+ +/ { print $4 }' | grep -qv '^struo/'; then
		fail "every zip entry lives under the struo/ top-level folder"
	else
		pass "every zip entry lives under the struo/ top-level folder"
	fi
	if [ "$(printf '%s' "$ZIP_LIST" | awk '/^ *[0-9]+ +[0-9-]+ +[0-9:]+ +/ { print $4 }' | grep -c '^struo/')" -ge 10 ]; then
		pass "zip lists >=10 files under struo/"
	else
		fail "zip lists >=10 files under struo/"
	fi
	rm -rf "$PLUGIN_DIR/dist"
else
	fail "zip/unzip available for runtime zip test"
fi

echo "== 16. CI workflow =="
CI="$PLUGIN_DIR/.github/workflows/ci.yml"
PINS="$PLUGIN_DIR/tests/action-pins.sh"
if [ -f "$CI" ]; then pass ".github/workflows/ci.yml exists"; else fail ".github/workflows/ci.yml exists"; fi
if [ -f "$PINS" ]; then pass "tests/action-pins.sh exists"; else fail "tests/action-pins.sh exists"; fi
for needed in "8.2" "8.3" "8.4" "8.5"; do
	check_grep "CI matrix includes PHP $needed" "$CI" "'$needed'"
done
check_grep "CI runs the smoke harness" "$CI" "bash tests/smoke.sh"
check_grep "CI runs composer install" "$CI" "composer install --no-interaction --no-progress"
check_grep "CI runs composer audit" "$CI" "composer audit"
check_grep "extracted phpcs uses phpcs.xml.dist" "$CI" "vendor/bin/phpcs --standard=phpcs.xml.dist"
check_grep "god-class phpcs stays report-only" "$CI" "PHPCS WordPress standard on struo.php (report-only)"
check_grep "god-class phpcs still continue-on-error" "$CI" "continue-on-error: true"
check_grep "CI builds the release zip" "$CI" "bash bin/build-zip.sh"
check_grep "CI uploads the zip artifact" "$CI" "actions/upload-artifact"
check_grep "CI verifies Action pins on GitHub" "$CI" "action-pins.sh --resolve"
check_grep "CI authenticates Action pin resolution" "$CI" "GITHUB_TOKEN: \${{ secrets.GITHUB_TOKEN }}"
check_grep "action-pins resolve sends Bearer token when set" "$PINS" "Authorization: Bearer"
check_grep "CI checks shell syntax" "$CI" "bash -n tests/smoke.sh"
check_grep "CI checks console.js syntax" "$CI" "node --check assets/console.js"
check_grep "CI runs autodetect cases" "$CI" "node tests/autodetect-cases.mjs"
check_grep "packaging job waits for the matrix, lite eval, and full eval" "$CI" "needs: \\[ ci, eval-lite, eval-full \\]"
check_grep "CI writes a real sha256 sidecar" "$CI" "sha256sum -c"
if grep -q '9e72090525849c5e82e596468b86eb55e9cc5331' "$CI"; then
	fail "CI does not pin the unresolvable setup-php SHA"
else
	pass "CI does not pin the unresolvable setup-php SHA"
fi
check_grep "setup-php pin is 2.35.5 commit" "$CI" "shivammathur/setup-php@bf6b4fbd49ca58e4608c9c89fba0b8d90bd2a39f"
USES_COUNT=$(grep -cE '^[[:space:]]+-?[[:space:]]*uses:' "$CI" || true)
SHA_PINS=$(grep -cE 'uses: .*@[0-9a-f]{40}$' "$CI" || true)
if [ "$USES_COUNT" -eq "$SHA_PINS" ] && [ "$SHA_PINS" -ge 3 ]; then
	pass "every uses: line is SHA-pinned (found $SHA_PINS)"
else
	fail "every uses: line is SHA-pinned (uses=$USES_COUNT sha=$SHA_PINS)"
fi
UNIQUE_SHAS=$(grep -oE '@[0-9a-f]{40}' "$CI" | sort -u | wc -l | tr -d ' ')
if [ "$UNIQUE_SHAS" -eq 4 ]; then
	pass "exactly four unique Action commit SHAs"
else
	fail "exactly four unique Action commit SHAs (found $UNIQUE_SHAS)"
fi
if bash "$PINS" --format "$CI" >/dev/null && bash "$PINS" --selftest >/dev/null; then
	pass "action-pins format + denylist selftest"
else
	fail "action-pins format + denylist selftest"
	bash "$PINS" --format "$CI" || true
	bash "$PINS" --selftest || true
fi
if command -v node >/dev/null 2>&1; then
	if node --check "$PLUGIN_DIR/assets/console.js" && node --check "$PLUGIN_DIR/tests/autodetect-cases.mjs"; then
		pass "node --check console.js and autodetect-cases.mjs"
	else
		fail "node --check console.js and autodetect-cases.mjs"
	fi
	if node "$PLUGIN_DIR/tests/autodetect-cases.mjs" >/dev/null; then
		pass "autodetect-cases.mjs runs"
	else
		fail "autodetect-cases.mjs runs"
	fi
else
	fail "node available for syntax checks"
fi
for script in tests/smoke.sh tests/rest-probe.sh tests/action-pins.sh tests/export-public-cases.sh bin/build-zip.sh bin/export-public.sh; do
	if bash -n "$PLUGIN_DIR/$script"; then
		pass "bash -n $script"
	else
		fail "bash -n $script"
	fi
done
if bash "$PLUGIN_DIR/tests/export-public-cases.sh"; then
	pass "export-public dest safety cases"
else
	fail "export-public dest safety cases"
fi
check_grep "CI declares least-privilege permissions" "$CI" "permissions:"
check_grep "CI permissions are contents: read" "$CI" "contents: read"
check_grep "CI concurrency group with cancel-in-progress" "$CI" "cancel-in-progress: true"
for pruned in "./.git" "./vendor" "./node_modules" "./dist"; do
	check_grep "php -l find prunes $pruned" "$CI" "path $pruned -prune"
done

echo "== 19. Mission Brief Change click stays inside the Target picker =="
CONSOLE_JS="$PLUGIN_DIR/assets/console.js"
check_grep "picker boundary helper exists" "$CONSOLE_JS" "function isInsideTargetPicker"
check_grep "outside-click uses the Target step as the picker root" "$CONSOLE_JS" "isInsideTargetPicker(target)"
check_grep "outside-click does not treat only the combobox as inside" "$CONSOLE_JS" "els.stepTarget || els.postCombobox"
check_grep "Change click still toggles stepTargetExpanded" "$CONSOLE_JS" "state.stepTargetExpanded = !state.stepTargetExpanded"

echo "== 20. n8n bridge artifacts stay out of the tree =="
if [ -d "$PLUGIN_DIR/docs/n8n" ]; then
	fail "docs/n8n/ directory removed"
else
	pass "docs/n8n/ directory removed"
fi
N8N_FORBIDDEN=$(grep -rn \
	--include='*.md' --include='*.php' --include='*.js' --include='*.json' --include='*.yml' --include='*.sh' \
	-e 'docs/n8n' -e '/ai-apply' -e 'phase24-slack-ai-block' -e 'phase24slackbridge' \
	"$PLUGIN_DIR" 2>/dev/null \
	| grep -v '/CHANGELOG.md:' \
	| grep -v '/tests/smoke.sh:' || true)
SLACK_RESPONSE_URL=$(grep -rn \
	--include='*.js' --include='*.json' \
	-e '\$json\.response_url' -e '"url": "={{$json.response_url}}"' \
	"$PLUGIN_DIR" 2>/dev/null || true)
if [ -n "$N8N_FORBIDDEN$SLACK_RESPONSE_URL" ]; then
	fail "n8n bridge denylist residue"
	printf '%s\n' "$N8N_FORBIDDEN" "$SLACK_RESPONSE_URL" >&2
else
	pass "n8n bridge denylist residue"
fi

echo "== 21. Lane 4 multi-page bundle =="
check_grep "BUNDLE_MAX_POSTS is 12" "$MAIN" "const BUNDLE_MAX_POSTS = 12;"
check_grep "bundle_v1 payload type" "$MAIN" "'payload_type' => 'bundle_v1'"
check_grep "selection locks after approve" "$MAIN" "sae_bundle_selection_locked"
check_grep "select CAS conflict" "$MAIN" "sae_bundle_select_conflict"
check_grep "bundle preview envelope" "$MAIN" "present_bundle_plan_envelope"
check_grep "Dismiss bundle control" "$MAIN" "sae-bundle-dismiss"
check_grep "orphan family returns the child" "$PLUGIN_DIR/includes/class-durable-plans.php" "Crash before parent insert"
if fn_body "$CONSOLE_JS" "resetPlanState" | grep -q "setApplyStatus('')"; then
	pass "resetPlanState clears apply status"
else
	fail "resetPlanState clears apply status"
fi
if fn_body "$MAIN" "apply_bundle_remaining" | grep -q "apply_agent_plan"; then
	pass "apply-remaining loops apply_agent_plan"
else
	fail "apply-remaining loops apply_agent_plan"
fi
if fn_body "$MAIN" "bundle_remaining_skip_result" | grep -q "newly_applied" || fn_body "$MAIN" "apply_bundle_remaining" | grep -q "newly_applied"; then
	pass "apply-remaining emits newly_applied"
else
	fail "apply-remaining emits newly_applied"
fi
if fn_body "$MAIN" "prune_durable_plans" | grep -q "keep_ids"; then
	pass "prune still protects orphans with keep_ids"
else
	fail "prune still protects orphans with keep_ids"
fi
if fn_body "$MAIN" "prune_durable_plans" | grep -q "AGENT_PLAN_MAX"; then
	fail "prune does not cancel other families at capacity"
else
	pass "prune does not cancel other families at capacity"
fi
if fn_body "$MAIN" "apply_bundle_remaining" | grep -q "interrupted"; then
	pass "apply-remaining preserves results on kill switch"
else
	fail "apply-remaining preserves results on kill switch"
fi
if fn_body "$MAIN" "approve_bundle_plan" | grep -q "FOR UPDATE"; then
	pass "approve locks the parent revision"
else
	fail "approve locks the parent revision"
fi
if fn_body "$MAIN" "user_can_review_agent_plan" | grep -q "user_can_edit_every_bundle_child"; then
	pass "bundle review requires per-child edit_post"
else
	fail "bundle review requires per-child edit_post"
fi
check_grep "select_revision is required" "$MAIN" "sae_bundle_revision_required"
if fn_body "$MAIN" "present_bundle_plan_envelope" | grep -q "get_durable_plan_record_unchecked"; then
	pass "bundle envelope keeps terminal children"
else
	fail "bundle envelope keeps terminal children"
fi
if grep -q 'DELETE FROM {$plans}' "$PLUGIN_DIR/tests/wp-eval-cases.php"; then
	fail "eval restore does not wipe struo_plans"
else
	pass "eval restore does not wipe struo_plans"
fi
if grep -q 'DELETE FROM {$audit} WHERE id >' "$PLUGIN_DIR/tests/wp-eval-cases.php"; then
	fail "eval restore does not wipe concurrent audit rows"
else
	pass "eval restore does not wipe concurrent audit rows"
fi

echo "== 22. S0a disclosure and copy =="
if fn_body "$MAIN" "can_struo_plan" | grep -q "edit_posts"; then
	fail "can_struo_plan has no edit_posts fallback"
else
	pass "can_struo_plan has no edit_posts fallback"
fi
check_grep "/plan requires struo_plan" "$ROUTES" "'permission_callback' => \[ 'Struo_Block_Editor', 'can_struo_plan' \]"
if grep -B6 "'callback' => \[ 'Struo_Block_Editor', 'dismiss_agent_plan' \]" "$ROUTES" | grep -q "can_struo_approve"; then
	pass "dismiss route uses can_struo_approve"
else
	fail "dismiss route uses can_struo_approve"
fi
check_grep "planner_options are audited and ignored" "$MAIN" "planner_options_ignored"
check_grep "eval harness tags audit rows" "$MAIN" "STRUO_EVAL_HARNESS"
check_grep "explicit targets fail closed before spend" "$MAIN" "function assert_explicit_plan_targets_disclosable"
PLAN_BODY="$(fn_body "$MAIN" "plan_block_change")"
assert_line=$(printf '%s\n' "$PLAN_BODY" | grep -n "assert_explicit_plan_targets_disclosable" | head -1 | cut -d: -f1)
rag_line=$(printf '%s\n' "$PLAN_BODY" | grep -n "retrieve_rag_context" | head -1 | cut -d: -f1)
compile_line=$(printf '%s\n' "$PLAN_BODY" | grep -n "compile_one_mutation_target" | head -1 | cut -d: -f1)
cross_line=$(printf '%s\n' "$PLAN_BODY" | grep -n "prepare_cross_field_response" | head -1 | cut -d: -f1)
if [ -n "$assert_line" ] && [ -n "$rag_line" ] && [ -n "$compile_line" ] && [ -n "$cross_line" ] \
	&& [ "$assert_line" -lt "$rag_line" ] && [ "$assert_line" -lt "$compile_line" ] && [ "$assert_line" -lt "$cross_line" ]; then
	pass "plan denies explicit targets before RAG/planner/cross-field"
else
	fail "plan denies explicit targets before RAG/planner/cross-field (assert=$assert_line rag=$rag_line compile=$compile_line cross=$cross_line)"
fi
if fn_body "$MAIN" "assert_explicit_plan_targets_disclosable" | grep -q "request_wants_bundle"; then
	fail "mixed explicit bundles do not bypass the disclose gate"
else
	pass "mixed explicit bundles do not bypass the disclose gate"
fi
check_grep "mixed explicit bundle zero-spend case" "$PLUGIN_DIR/tests/wp-eval-cases.php" "mixed explicit bundle IDs are denied before RAG/provider work"
if grep -q "LIKE %s\", '%eval_harness%'" "$PLUGIN_DIR/tests/wp-eval-cases.php"; then
	fail "eval restore does not substring-match eval_harness"
else
	pass "eval restore does not substring-match eval_harness"
fi
check_grep "eval restore matches structured harness marker" "$PLUGIN_DIR/tests/wp-eval-cases.php" '"eval_harness":1'
check_grep "console plans from findings" "$CONSOLE_JS" "planFromFinding"
if grep -q "Changes are live" "$CONSOLE_JS"; then
	fail "console does not call a write live"
else
	pass "console does not call a write live"
fi
check_grep "Saved to WordPress copy" "$CONSOLE_JS" "Saved to WordPress"
check_grep "flush failure restores selection" "$CONSOLE_JS" "function restoreBundleSelection"
check_grep "phrase-triggered bundles remain" "$MAIN" "these pages"

echo "== 23. S0b lifecycle and storage honesty =="
check_grep "new plans fail at capacity" "$MAIN" "sae_plan_capacity"
check_grep "capacity helper exists" "$MAIN" "function assert_durable_plan_capacity"
check_grep "plans table requests InnoDB" "$MAIN" "ENGINE=InnoDB"
check_grep "bundle actions require InnoDB" "$MAIN" "function require_innodb_for_bundle"
check_grep "receipt lookup keeps terminals" "$MAIN" "function get_durable_plan_record_for_receipt"
check_grep "plan privacy exporter" "$MAIN" "function export_plan_rows_for_user"
check_grep "plan privacy eraser" "$MAIN" "function erase_plan_rows_for_user"
if fn_body "$MAIN" "can_plan_create_page" | grep -q "plan-create-page"; then
	pass "create-page plan requires struo_plan"
else
	fail "create-page plan requires struo_plan"
fi
if fn_body "$MAIN" "can_manage_template_registry" | grep -q "list-templates"; then
	pass "registry routes use struo_manage_registry"
else
	fail "registry routes use struo_manage_registry"
fi
if fn_body "$MAIN" "can_manage_console_settings" | grep -q "console-kill-switch"; then
	pass "settings routes use struo_manage_settings"
else
	fail "settings routes use struo_manage_settings"
fi
if fn_body "$MAIN" "apply_agent_plan" | grep -q "already_applied"; then
	pass "re-apply of an applied plan reports already_applied"
else
	fail "re-apply of an applied plan reports already_applied"
fi

echo "== 24. S1 authority seam =="
CATALOG="$PLUGIN_DIR/includes/class-operation-catalog.php"
AUTHORITY="$PLUGIN_DIR/includes/class-authority.php"
if [ -f "$CATALOG" ]; then pass "includes/class-operation-catalog.php exists"; else fail "includes/class-operation-catalog.php exists"; fi
if [ -f "$AUTHORITY" ]; then pass "includes/class-authority.php exists"; else fail "includes/class-authority.php exists"; fi
check_grep "catalog required from struo.php" "$MAIN" "require_once __DIR__ . '/includes/class-operation-catalog.php';"
check_grep "authority required from struo.php" "$MAIN" "require_once __DIR__ . '/includes/class-authority.php';"
check_grep "apply-agent-plan catalog row exists" "$CATALOG" "'apply-agent-plan'"
check_grep "apply-agent-plan apply_allowed true" "$CATALOG" "false, 'struo_apply', 'apply_plan', true"
if grep -E "'apply-agent-plan' => self::row\([^)]+true, 'struo_apply'" "$CATALOG" >/dev/null; then
	fail "apply-agent-plan is not mcp.public"
else
	pass "apply-agent-plan is not mcp.public"
fi
if fn_body "$MAIN" "can_read_catalog" | grep -q "edit_posts"; then
	fail "can_read_catalog has no edit_posts leftover"
else
	pass "can_read_catalog has no edit_posts leftover"
fi
if fn_body "$MAIN" "get_block_catalog" | grep -q "edit_posts"; then
	fail "get_block_catalog has no edit_posts leftover"
else
	pass "get_block_catalog has no edit_posts leftover"
fi
if fn_body "$MAIN" "can_read_console_audit" | grep -q "edit_others_posts"; then
	fail "can_read_console_audit has no edit_others_posts leftover"
else
	pass "can_read_console_audit has no edit_others_posts leftover"
fi
check_grep "abilities mcp.public reads catalog" "$ABILITIES" "mcp_public_for_ability"
check_grep "status route uses console-status wrapper" "$MAIN" "can_read_console_status"
check_grep "s1 eval names catalog denial" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s1-e1) author without struo_plan cannot GET /block-catalog or /console/status"

echo "== 25. S2 immutable mutation =="
JOURNAL="$PLUGIN_DIR/includes/class-mutation-journal.php"
if [ -f "$JOURNAL" ]; then pass "includes/class-mutation-journal.php exists"; else fail "includes/class-mutation-journal.php exists"; fi
check_grep "journal required from struo.php" "$MAIN" "require_once __DIR__ . '/includes/class-mutation-journal.php';"
check_grep "build stages class-mutation-journal.php" "$BUILD" "includes/class-mutation-journal.php"
check_grep "struo_events CREATE TABLE InnoDB" "$JOURNAL" "ENGINE=InnoDB"
check_body_absent "product code does not UPDATE struo_events" "$JOURNAL" "append" "UPDATE"
check_grep "get_agent_plan exposes readers_match" "$MAIN" "readers_match"
check_grep "get_agent_plan exposes events" "$MAIN" "'events'"
check_grep "uninstall drops struo_events with plans constant" "$UNINSTALL" "struo_events"
if fn_body "$MAIN" "erase_plan_rows_for_user" | grep -q "erase_for_plan_ids"; then
	pass "plan eraser deletes mutation events first"
else
	fail "plan eraser deletes mutation events first"
fi
if fn_body "$MAIN" "export_plan_rows_for_user" | grep -q "export_for_plan"; then
	pass "plan exporter includes mutation events"
else
	fail "plan exporter includes mutation events"
fi
check_grep "is_journaled_record rejects bundle_id" "$JOURNAL" "bundle_id"
if fn_body "$JOURNAL" "hash_payload_json" | grep -q "wp_json_encode"; then
	fail "hash_payload_json hashes the stored string not a re-encoded array"
else
	pass "hash_payload_json hashes the stored string not a re-encoded array"
fi
check_grep "s2 eval names queued journal" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s2-e1)"

echo "== 26. S3 recoverable verified change =="
RECOVERY="$PLUGIN_DIR/includes/class-mutation-recovery.php"
CATALOG="$PLUGIN_DIR/includes/class-operation-catalog.php"
if [ -f "$RECOVERY" ]; then pass "includes/class-mutation-recovery.php exists"; else fail "includes/class-mutation-recovery.php exists"; fi
check_grep "recovery required from struo.php" "$MAIN" "require_once __DIR__ . '/includes/class-mutation-recovery.php';"
check_grep "build stages class-mutation-recovery.php" "$BUILD" "includes/class-mutation-recovery.php"
if grep -A 0 "rollback-agent-plan" "$CATALOG" | grep -q "false, 'struo_plan', 'write_post', false"; then
	pass "rollback-agent-plan is not mcp.public, apply_allowed false, ability null"
else
	fail "rollback-agent-plan is not mcp.public, apply_allowed false, ability null"
fi
if grep -q "safe_to_retry" "$MAIN" \
	&& grep -q "recovered_persisted" "$MAIN" \
	&& grep -q "outcome_unknown_conflict" "$MAIN" \
	&& fn_body "$MAIN" "apply_agent_plan" | grep -q "recover_journaled_apply"; then
	pass "apply_agent_plan mentions recover outcomes"
else
	fail "apply_agent_plan mentions recover outcomes"
fi
if grep -q "const PLANS_DB_VERSION = 2;" "$MAIN" \
	&& awk '/function ensure_plans_table\(\)/,/^	private static function maybe_migrate_struo_capabilities/' "$MAIN" | grep -q "base_content longtext" \
	&& awk '/function ensure_plans_table\(\)/,/^	private static function maybe_migrate_struo_capabilities/' "$MAIN" | grep -q "expected_content_hash"; then
	pass "PLANS_DB_VERSION is 2 with recovery columns"
else
	fail "PLANS_DB_VERSION is 2 with recovery columns"
fi
if awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q '\$dispatch_args\['"'"'dry_run'"'"'\] = true'; then
	pass "dispatch_internal still forced dry-run"
else
	fail "dispatch_internal still forced dry-run"
fi
if grep -q "struo/apply-agent-plan" "$PLUGIN_DIR/includes/class-abilities.php" \
	&& ! awk '/function register_mcp_tools/,/^	}/' "$MAIN" | grep -q "apply-agent-plan"; then
	pass "no new MCP apply tool"
else
	fail "no new MCP apply tool"
fi
check_grep "s2 eval names still present" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s2-e1)"
check_grep "s3 eval names persist match" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e1)"
check_grep "s3 eval names crash retry" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e2)"
check_grep "s3 eval names recovered persist" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e3)"
check_grep "s3 eval names unknown conflict" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e4)"
check_grep "s3 eval names public evidence" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e5)"
check_grep "s3 eval names rollback restore" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e6)"
check_grep "s3 eval names bundle omit" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e7)"

echo "== 27. S4 one Work Queue =="
WQ_JS="$PLUGIN_DIR/assets/work-queue.js"
WQ_ASSET="$PLUGIN_DIR/assets/work-queue.asset.php"
WQ_SRC="$PLUGIN_DIR/src/work-queue"
if [ -f "$WQ_JS" ]; then pass "assets/work-queue.js exists"; else fail "assets/work-queue.js exists"; fi
if [ -f "$WQ_ASSET" ]; then pass "assets/work-queue.asset.php exists"; else fail "assets/work-queue.asset.php exists"; fi
check_grep "build requires work-queue.js" "$BUILD" "assets/work-queue.js"
check_grep "build requires work-queue.asset.php" "$BUILD" "assets/work-queue.asset.php"
if grep -q "struo-work-queue" "$MAIN" && grep -q "wp-element" "$WQ_ASSET" && grep -q 'id="sae-work-queue"' "$MAIN"; then
	pass "enqueue mentions struo-work-queue and wp-element; #sae-work-queue in render_console_page"
else
	fail "enqueue mentions struo-work-queue and wp-element; #sae-work-queue in render_console_page"
fi
if fn_body "$MAIN" "list_agent_plan_summaries" | grep -q "include_expired" \
	&& ! fn_body "$MAIN" "list_agent_plan_summaries" | grep -q "'origin' => 'mcp'"; then
	pass "list_agent_plan_summaries is Work Queue rest+mcp with include_expired"
else
	fail "list_agent_plan_summaries is Work Queue rest+mcp with include_expired"
fi
if grep -q 'id="sae-agent-plans" hidden' "$MAIN"; then
	pass "#sae-agent-plans section is hidden"
else
	fail "#sae-agent-plans section is hidden"
fi
if awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q '\$dispatch_args\['"'"'dry_run'"'"'\] = true'; then
	pass "dispatch_internal still forced dry-run"
else
	fail "dispatch_internal still forced dry-run"
fi
if grep -q "struo/apply-agent-plan" "$PLUGIN_DIR/includes/class-abilities.php" \
	&& ! awk '/function register_mcp_tools/,/^	}/' "$MAIN" | grep -q "apply-agent-plan"; then
	pass "no new MCP apply tool"
else
	fail "no new MCP apply tool"
fi
check_grep "s3 eval names persist match" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s3-e1)"
if grep -qiE '(^|[^a-zA-Z])live([^a-zA-Z]|$)' "$WQ_SRC/App.js" "$WQ_SRC/index.js"; then
	fail "island source has no live copy"
else
	pass "island source has no live copy"
fi
if grep -q "Saved to WordPress" "$WQ_SRC/App.js"; then
	pass "island uses Saved to WordPress"
else
	fail "island uses Saved to WordPress"
fi
check_grep "s4 eval names rest list" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s4-e1)"
check_grep "s4 eval names mcp list" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s4-e2)"
check_grep "s4 eval names evidence queue" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s4-e3)"
check_grep "s4 eval names bundle omit" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s4-e4)"
check_grep "s4 eval names page_spec omit accepted" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s4-e6)"
if fn_body "$MAIN" "list_agent_plan_summaries" | grep -q "is_recoverable_record"; then
	pass "Work Queue omits bundle_v1 and page_spec_v1 from items"
else
	fail "Work Queue omits bundle_v1 and page_spec_v1 from items"
fi
check_grep "s4 eval names rollback list" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s4-e5)"
check_grep "CI rebuilds Work Queue and fails on drift" "$CI" "git diff --exit-code -- assets/work-queue.js assets/work-queue.asset.php"

echo "== 28. S4.5 durable discovery tracer =="
if fn_body "$MAIN" "list_agent_plans" | grep -q "'discovery'" \
	&& grep -q "'discovery_plans' => self::list_discovery_plan_summaries()" "$MAIN"; then
	pass "s45-sm1 list_agent_plans has discovery; status has discovery_plans"
else
	fail "s45-sm1 list_agent_plans has discovery; status has discovery_plans"
fi
WQ_LINE="$(grep -n 'id="sae-work-queue"' "$MAIN" | head -1 | cut -d: -f1)"
DISC_LINE="$(grep -n 'id="sae-agent-plans"' "$MAIN" | head -1 | cut -d: -f1)"
if grep -n "renderDiscoveryPlans(" "$PLUGIN_DIR/assets/console.js" | grep -vq "function renderDiscoveryPlans" \
	&& grep -q "Multi-page and new pages" "$MAIN" \
	&& [ -n "$WQ_LINE" ] && [ -n "$DISC_LINE" ] && [ "$WQ_LINE" -lt "$DISC_LINE" ]; then
	pass "s45-sm2 loadStatus renders discovery; work-queue is above the list"
else
	fail "s45-sm2 loadStatus renders discovery; work-queue is above the list"
fi
if fn_body "$MAIN" "list_discovery_plan_summaries" | grep -q "bundle_v1" \
	&& fn_body "$MAIN" "list_discovery_plan_summaries" | grep -q "page_spec_v1" \
	&& fn_body "$MAIN" "present_discovery_item" | grep -q "child_count"; then
	pass "s45 list_discovery_plan_summaries keeps waiting bundle_v1 and page_spec_v1"
else
	fail "s45 list_discovery_plan_summaries keeps waiting bundle_v1 and page_spec_v1"
fi
check_grep "s45 eval names rest bundle discovery" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s45-e1)"
check_grep "s45 eval names page_spec discovery" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s45-e3)"
if fn_body "$MAIN" "preview_agent_plan" | grep -q "present_page_spec_plan_envelope" \
	&& grep -q "function present_page_spec_plan_envelope" "$MAIN"; then
	pass "s45-sm3 preview_agent_plan uses present_page_spec_plan_envelope"
else
	fail "s45-sm3 preview_agent_plan uses present_page_spec_plan_envelope"
fi
check_grep "s45 eval names page_spec preview envelope" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s45-e4)"
check_grep "s45 eval names mcp bundle discovery" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s45-e2)"
check_grep "s45 eval names dismiss drops discovery" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s45-e5)"
if fn_body "$MAIN" "apply_bundle_remaining" | grep -q "durable_plan_move" \
	&& awk '/async function handleBundleApplyRemaining/,/async function handleBundleDismiss/' "$PLUGIN_DIR/assets/console.js" | grep -q "loadStatus"; then
	pass "s45 apply remaining marks parent applied and refreshes discovery"
else
	fail "s45 apply remaining marks parent applied and refreshes discovery"
fi
if fn_body "$MAIN" "list_agent_plan_summaries" | grep -q "is_recoverable_record" \
	&& grep -q "data?.items" "$WQ_SRC/App.js"; then
	pass "s45-sm4 Work Queue stays recoverable mutation items-only"
else
	fail "s45-sm4 Work Queue stays recoverable mutation items-only"
fi
if awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q '\$dispatch_args\['"'"'dry_run'"'"'\] = true' \
	&& ! awk '/function register_mcp_tools/,/^	}/' "$MAIN" | grep -q "apply-agent-plan"; then
	pass "s45-sm5 no new MCP apply; dispatch_internal still dry-run"
else
	fail "s45-sm5 no new MCP apply; dispatch_internal still dry-run"
fi

echo "== 29. apply ability door =="
if awk '/function can_apply_agent_plan/,/^	}/' "$PLUGIN_DIR/includes/class-abilities.php" | grep -q "'ability'" \
	&& awk '/function can_struo_apply/,/^	}/' "$MAIN" | grep -q '\$door = '"'"'rest'"'"''; then
	pass "door-sm1 can_apply_agent_plan authorizes door=ability; REST apply still defaults rest"
else
	fail "door-sm1 can_apply_agent_plan authorizes door=ability; REST apply still defaults rest"
fi
check_grep "door eval names mcp vs ability apply" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(door-e1)"

echo "== 30. S9-ro findings tracer =="
CATALOG="$PLUGIN_DIR/includes/class-operation-catalog.php"
CONSOLE_JS="$PLUGIN_DIR/assets/console.js"
if [ -f "$PLUGIN_DIR/includes/class-findings.php" ]; then pass "includes/class-findings.php exists"; else fail "includes/class-findings.php exists"; fi
check_grep "findings required from struo.php" "$MAIN" "require_once __DIR__ . '/includes/class-findings.php';"
check_grep "build stages class-findings.php" "$BUILD" "includes/class-findings.php"
if grep -A 0 "list-findings" "$CATALOG" | grep -q "null, false, 'struo_plan', 'none', false" \
	&& grep -A 0 "get-finding" "$CATALOG" | grep -q "null, false, 'struo_plan', 'none', false"; then
	pass "s9ro-sm1 catalog list-findings and get-finding are plan reads, apply_allowed false, no ability"
else
	fail "s9ro-sm1 catalog list-findings and get-finding are plan reads, apply_allowed false, no ability"
fi
if grep -q "'/console/findings'" "$ROUTES" \
	&& awk '/\$dispatch_ops = \[/,/\];/' "$MAIN" | grep -q "list-findings"; then
	fail "s9ro-sm2 findings route registered; not in dispatch_internal op list"
elif grep -q "'/console/findings'" "$ROUTES" \
	&& ! awk '/\$dispatch_ops = \[/,/\];/' "$MAIN" | grep -q "list-findings" \
	&& ! awk '/\$dispatch_ops = \[/,/\];/' "$MAIN" | grep -q "get-finding"; then
	pass "s9ro-sm2 findings route registered; not in dispatch_internal op list"
else
	fail "s9ro-sm2 findings route registered; not in dispatch_internal op list"
fi
if AP_LINE="$(grep -n 'id="sae-agent-plans"' "$MAIN" | head -1 | cut -d: -f1)" \
	&& F_LINE="$(grep -n 'id="sae-findings"' "$MAIN" | head -1 | cut -d: -f1)" \
	&& [ -n "$AP_LINE" ] && [ -n "$F_LINE" ] && [ "$F_LINE" -gt "$AP_LINE" ] \
	&& grep -q "function loadFindings" "$CONSOLE_JS" \
	&& grep -q "function handleFindingsClick" "$CONSOLE_JS" \
	&& grep -q "function renderFindingDetail" "$CONSOLE_JS" \
	&& ! awk '/function renderFindings/,/async function loadStatus/' "$CONSOLE_JS" | grep -qE 'previewAgentPlan|notifyWorkQueueSelect|hasPlan'; then
	pass "s9ro-sm3 #sae-findings after #sae-agent-plans; findings Open does not previewAgentPlan"
else
	fail "s9ro-sm3 #sae-findings after #sae-agent-plans; findings Open does not previewAgentPlan"
fi
if grep -q "'struo_google_service_account'" "$UNINSTALL" \
	&& grep -q "'struo_ga4_property_id'" "$UNINSTALL" \
	&& grep -q "'struo_gsc_site_url'" "$UNINSTALL" \
	&& grep -q "'struo_findings_'" "$UNINSTALL" \
	&& ! grep -q "struo/list-findings" "$PLUGIN_DIR/includes/class-abilities.php"; then
	pass "s9ro-sm4 uninstall lists Google option names; no findings ability"
else
	fail "s9ro-sm4 uninstall lists Google option names; no findings ability"
fi
check_grep "google service account setting registered" "$MAIN" "sanitize_google_service_account"
check_grep "s9ro eval names disconnected list" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s9ro-e1)"
check_grep "s9ro eval names fixture list" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s9ro-e2)"
check_grep "s9ro eval names open get-one" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s9ro-e3)"
check_grep "s9ro eval names drop non-allowlisted" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s9ro-e4)"
check_grep "s9ro eval names status has no findings key" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s9ro-e5)"
check_grep "s9ro eval names subscriber 403" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s9ro-e6)"
check_grep "s9ro eval names settings never echo private_key" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s9ro-e7)"
if awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q '\$dispatch_args\['"'"'dry_run'"'"'\] = true' \
	&& ! awk '/function register_mcp_tools/,/^	}/' "$MAIN" | grep -q "apply-agent-plan" \
	&& grep -q "oauth2.googleapis.com/token" "$PLUGIN_DIR/includes/class-findings.php" \
	&& grep -q "fetch_failed" "$PLUGIN_DIR/includes/class-findings.php"; then
	pass "s9ro-sm5 dispatch_internal still dry-run; MCP has no apply; Google JWT path exists"
else
	fail "s9ro-sm5 dispatch_internal still dry-run; MCP has no apply; Google JWT path exists"
fi
check_grep "s9ro changelog note" "$PLUGIN_DIR/CHANGELOG.md" "S9-ro: read-only Findings"

echo "== 31. Durable plan store =="
if [ -f "$PLUGIN_DIR/includes/class-durable-plans.php" ]; then pass "includes/class-durable-plans.php exists"; else fail "includes/class-durable-plans.php exists"; fi
check_grep "durable plans required from struo.php" "$MAIN" "require_once __DIR__ . '/includes/class-durable-plans.php';"
check_grep "build stages class-durable-plans.php" "$BUILD" "includes/class-durable-plans.php"
if php "$PLUGIN_DIR/tests/durable-plan-store-cases.php"; then
	pass "dps-sm1 durable-plan-store-cases.php all cases pass"
else
	fail "dps-sm1 durable-plan-store-cases.php all cases pass"
fi
check_grep "dps eval names create/load" "$PLUGIN_DIR/tests/durable-plan-store-cases.php" "(dps-e1)"
check_grep "dps eval names expire/illegal" "$PLUGIN_DIR/tests/durable-plan-store-cases.php" "(dps-e2)"
check_grep "dps eval names prune expire" "$PLUGIN_DIR/tests/durable-plan-store-cases.php" "(dps-e3)"
check_grep "dps eval names capacity" "$PLUGIN_DIR/tests/durable-plan-store-cases.php" "(dps-e4)"
check_grep "dps eval names family cancel" "$PLUGIN_DIR/tests/durable-plan-store-cases.php" "(dps-e5)"
check_grep "dps-e6 named in wp-eval-cases" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(dps-e6)"
check_grep "lite eval names capacity" "$PLUGIN_DIR/tests/wp-eval-lite.php" "(dps-e6) new plan fails with 429 at capacity"
check_grep "lite eval names plan cap" "$PLUGIN_DIR/tests/wp-eval-lite.php" "(lite) author without struo_plan cannot POST /plan"
check_grep "lite eval names dispatch dry-run" "$PLUGIN_DIR/tests/wp-eval-lite.php" "(lite) dispatch_internal forces dry_run and origin mcp"
check_grep "lite eval names planner_options" "$PLUGIN_DIR/tests/wp-eval-lite.php" "(lite) request planner_options are ignored"
check_grep "lite eval names empty prompt HTTP" "$PLUGIN_DIR/tests/wp-eval-lite.php" "(lite) empty prompt fails before provider HTTP"
check_grep "lite eval names oversize prompt HTTP" "$PLUGIN_DIR/tests/wp-eval-lite.php" "(lite) oversize prompt fails before provider HTTP"
check_grep "wp-env maps this plugin" "$PLUGIN_DIR/.wp-env.json" '"plugins"'
check_grep "wp-env 6.9 pins wordpress-6.9.2.zip" "$PLUGIN_DIR/.wp-env.json" "wordpress-6.9.2.zip"
check_grep "wp-env 7.1 maps this plugin" "$PLUGIN_DIR/.wp-env.7.1.json" '"plugins"'
check_grep "wp-env 7.1 pins wordpress-7.1.zip" "$PLUGIN_DIR/.wp-env.7.1.json" "wordpress-7.1.zip"
check_grep "CI runs wp-eval-lite" "$PLUGIN_DIR/.github/workflows/ci.yml" "wp-eval-lite.php"
check_grep "CI runs wp-eval-cases" "$PLUGIN_DIR/.github/workflows/ci.yml" "wp-eval-cases.php"
check_grep "CI lite eval includes WordPress 7.1" "$PLUGIN_DIR/.github/workflows/ci.yml" "config: .wp-env.7.1.json"
check_count "CI does not pass --config to pinned wp-env 10.31 start" "$CI" "wp-env start --config" 0
check_count "CI does not pass --config to pinned wp-env 10.31 run" "$CI" "wp-env run --config" 0
check_grep "CI copies overlay wp-env json for 7.1" "$CI" 'cp -- "${{ matrix.config }}" .wp-env.json'
check_grep "CI wp-env run invokes WP-CLI eval-file" "$CI" "wp-env run cli wp eval-file"
if fn_body "$MAIN" "prune_durable_plans" | grep -q "SET state = 'expired'"; then
	fail "dps-sm2 prune_durable_plans has no raw SET state expired"
else
	pass "dps-sm2 prune_durable_plans has no raw SET state expired"
fi
if grep -q "function read_agent_plan_store" "$MAIN" || grep -q "function write_agent_plan_store" "$MAIN"; then
	fail "dps-sm3 option shim gone"
else
	pass "dps-sm3 option shim gone"
fi
if awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q '\$dispatch_args\['"'"'dry_run'"'"'\] = true' \
	&& ! awk '/function register_mcp_tools/,/^	}/' "$MAIN" | grep -q "apply-agent-plan"; then
	pass "dps-sm4 dispatch_internal still dry-run; MCP has no apply"
else
	fail "dps-sm4 dispatch_internal still dry-run; MCP has no apply"
fi
if grep -q "includes/class-durable-plans.php" "$BUILD"; then
	pass "dps-sm5 zip stages class-durable-plans.php"
else
	fail "dps-sm5 zip stages class-durable-plans.php"
fi
if fn_body "$MAIN" "dismiss_agent_plan" | grep -q "durable_plan_move( 'cancel'" \
	&& fn_body "$MAIN" "apply_agent_plan" | grep -q "claim_apply" \
	&& fn_body "$MAIN" "approve_bundle_plan" | grep -q "durable_plan_move" \
	&& ! grep -q "function transition_durable_plan_state" "$MAIN"; then
	pass "dps callers ask by intent; no from/to writer"
else
	fail "dps callers ask by intent; no from/to writer"
fi
check_grep "dps changelog note" "$PLUGIN_DIR/CHANGELOG.md" "Durable plan store:"
check_grep "AGENT_PLAN_MAX aliases store cap" "$MAIN" "const AGENT_PLAN_MAX = Struo_Durable_Plans::ACTIVE_CAP"
if grep -q "Apply already succeeded" "$CONSOLE_JS" || grep -q "agentPlansEndpoint(agentPlanId, 'dismiss')" "$CONSOLE_JS"; then
	fail "apply does not dismiss the applied row"
else
	pass "apply does not dismiss the applied row"
fi

echo ""
if fn_body "$MAIN" "register_mcp_tools" | grep -q "apply-agent-plan"; then
	fail "MCP catalog has no apply-agent-plan tool"
else
	pass "MCP catalog has no apply-agent-plan tool"
fi
if grep -q "struo/bundle" "$PLUGIN_DIR/includes/class-abilities.php"; then
	fail "no new bundle ability name"
else
	pass "no new bundle ability name"
fi
if awk '/ABILITY_PLAN_BLOCK_CHANGE =>/,/ABILITY_PREVIEW_INSERT =>/' "$PLUGIN_DIR/includes/class-abilities.php" | grep -q "'post_ids'"; then
	pass "plan-block-change ability advertises post_ids"
else
	fail "plan-block-change ability advertises post_ids"
fi

echo "== 31. Per-target compile tracer =="
if grep -q "function compile_one_mutation_target" "$MAIN"; then
	pass "ptc-sm1 compile_one_mutation_target exists"
else
	fail "ptc-sm1 compile_one_mutation_target exists"
fi
BUNDLE_BODY="$(fn_body "$MAIN" "plan_bundle")"
if ! printf '%s\n' "$BUNDLE_BODY" | grep -q "call_ai_planner" \
	&& ! printf '%s\n' "$BUNDLE_BODY" | grep -q "prepare_plan_operation" \
	&& ! printf '%s\n' "$BUNDLE_BODY" | grep -q "prepare_tone_rewrite_plan"; then
	pass "ptc-sm2 plan_bundle has no duplicate compile sequence"
else
	fail "ptc-sm2 plan_bundle has no duplicate compile sequence"
fi
PLAN_BODY="$(fn_body "$MAIN" "plan_block_change")"
AFTER_BUNDLE=$(printf '%s\n' "$PLAN_BODY" | awk '/return self::plan_bundle/,0')
if printf '%s\n' "$AFTER_BUNDLE" | grep -q "compile_one_mutation_target" \
	&& ! printf '%s\n' "$AFTER_BUNDLE" | grep -q "prepare_plan_operation"; then
	pass "ptc-sm3 Improve tail calls compile_one_mutation_target"
else
	fail "ptc-sm3 Improve tail calls compile_one_mutation_target"
fi
assert_line=$(printf '%s\n' "$PLAN_BODY" | grep -n "assert_explicit_plan_targets_disclosable" | head -1 | cut -d: -f1)
rag_line=$(printf '%s\n' "$PLAN_BODY" | grep -n "retrieve_rag_context" | head -1 | cut -d: -f1)
compile_line=$(printf '%s\n' "$PLAN_BODY" | grep -n "compile_one_mutation_target" | head -1 | cut -d: -f1)
if [ -n "$assert_line" ] && [ -n "$rag_line" ] && [ -n "$compile_line" ] \
	&& [ "$assert_line" -lt "$rag_line" ] && [ "$assert_line" -lt "$compile_line" ]; then
	pass "ptc-sm4 disclose gate precedes RAG and compile_one_mutation_target"
else
	fail "ptc-sm4 disclose gate precedes RAG and compile_one_mutation_target (assert=$assert_line rag=$rag_line compile=$compile_line)"
fi
if awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q '\$dispatch_args\['"'"'dry_run'"'"'\] = true' \
	&& ! grep -q "class Planning" "$MAIN" && ! grep -q "class ChangeWorkflow" "$MAIN" \
	&& ! awk '/function register_mcp_tools/,/^	}/' "$MAIN" | grep -q "apply-agent-plan"; then
	pass "ptc-sm5 dispatch_internal still dry-run; no Planning/ChangeWorkflow; MCP has no apply"
else
	fail "ptc-sm5 dispatch_internal still dry-run; no Planning/ChangeWorkflow; MCP has no apply"
fi
if grep -q "function resolve_explicit_mutation_post_id" "$MAIN"; then
	pass "ptc-sm6 sole post_ids pins one-page compile"
else
	fail "ptc-sm6 sole post_ids pins one-page compile"
fi
RETRY_LIST="$(printf '%s\n' "$(fn_body "$MAIN" "compile_one_mutation_target")" | awk '/retryable_plan_errors/,/];/')"
if printf '%s\n' "$RETRY_LIST" | grep -q "sae_plan_missing_fields" \
	|| printf '%s\n' "$RETRY_LIST" | grep -q "sae_plan_missing_target"; then
	fail "ptc-sm7 prepare retry does not hop on missing fields or target"
else
	pass "ptc-sm7 prepare retry does not hop on missing fields or target"
fi
check_grep "ptc-e1 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(ptc-e1)"
check_grep "ptc-e2 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(ptc-e2)"
check_grep "ptc-e3 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(ptc-e3)"
check_grep "ptc-e4 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(ptc-e4)"
check_grep "ptc changelog note" "$PLUGIN_DIR/CHANGELOG.md" "one compile for one page and a bundle child"
if grep -q "(b18) prefer_ai planner error skips remaining" "$PLUGIN_DIR/tests/wp-eval-cases.php"; then
	fail "b18 replaced by ptc-e2"
else
	pass "b18 replaced by ptc-e2"
fi

echo "== 32. S5 Gutenberg host tracer =="
GH_JS="$PLUGIN_DIR/assets/gutenberg-host.js"
GH_ASSET="$PLUGIN_DIR/assets/gutenberg-host.asset.php"
GH_SRC="$PLUGIN_DIR/src/gutenberg-host/index.js"
if [ -f "$GH_JS" ]; then pass "s5-sm1 assets/gutenberg-host.js exists"; else fail "s5-sm1 assets/gutenberg-host.js exists"; fi
if [ -f "$GH_ASSET" ]; then pass "s5-sm1 assets/gutenberg-host.asset.php exists"; else fail "s5-sm1 assets/gutenberg-host.asset.php exists"; fi
check_grep "s5-sm1 build requires gutenberg-host.js" "$BUILD" "assets/gutenberg-host.js"
check_grep "s5-sm1 build requires gutenberg-host.asset.php" "$BUILD" "assets/gutenberg-host.asset.php"
if grep -q "src/gutenberg-host" "$PLUGIN_DIR/dist/.zip-list" 2>/dev/null; then
	fail "s5-sm1 zip excludes src/gutenberg-host"
else
	pass "s5-sm1 zip excludes src/gutenberg-host"
fi
if grep -q "enqueue_block_editor_assets" "$MAIN" \
	&& fn_body "$MAIN" "is_gutenberg_post_editor" | grep -q "'post'" \
	&& ! fn_body "$MAIN" "is_gutenberg_post_editor" | grep -q "site-editor"; then
	pass "s5-sm2 enqueue_block_editor_assets; post editor base only; not site-editor"
else
	fail "s5-sm2 enqueue_block_editor_assets; post editor base only; not site-editor"
fi
if grep -q "'current_post_id'" "$MAIN" \
	&& grep -q "'host'" "$MAIN" \
	&& grep -q "function pinGutenbergTargetFromConfig" "$PLUGIN_DIR/assets/console.js" \
	&& grep -q "function notifyMutationApplied" "$PLUGIN_DIR/assets/console.js" \
	&& grep -q "PluginSidebar" "$GH_SRC"; then
	pass "s5-sm3 host + current_post_id; pinGutenbergTargetFromConfig; PluginSidebar"
else
	fail "s5-sm3 host + current_post_id; pinGutenbergTargetFromConfig; PluginSidebar"
fi
if grep -q "killSwitchToggle" "$PLUGIN_DIR/assets/console.js" \
	&& awk '/els.killSwitchToggle/,/toggleKillSwitch/' "$PLUGIN_DIR/assets/console.js" | grep -q "if (els.killSwitchToggle)" \
	&& grep -q 'id="sae-gutenberg-host"' "$MAIN" \
	&& grep -q "print_gutenberg_host_markup" "$MAIN" \
	&& grep -q "render_brief_panels" "$MAIN" \
	&& grep -q "self::render_brief_panels()" "$MAIN"; then
	pass "s5-sm4 bindEvents guards killSwitchToggle; host markup; render_brief_panels shared"
else
	fail "s5-sm4 bindEvents guards killSwitchToggle; host markup; render_brief_panels shared"
fi
if grep -q "struo-mutation-applied" "$PLUGIN_DIR/assets/console.js" \
	&& grep -q "struo-mutation-applied" "$PLUGIN_DIR/src/work-queue/App.js" \
	&& grep -q "invalidateResolution" "$GH_SRC"; then
	pass "s5 slice3 mutation-applied event; work-queue emits; host invalidates entity"
else
	fail "s5 slice3 mutation-applied event; work-queue emits; host invalidates entity"
fi
if grep -q "gutenberg-host" "$PLUGIN_DIR/webpack.config.js" \
	&& grep -q "assets/gutenberg-host.js" "$PLUGIN_DIR/.github/workflows/ci.yml" \
	&& grep -q "assets/gutenberg-host.asset.php" "$PLUGIN_DIR/.github/workflows/ci.yml"; then
	pass "s5-sm5 webpack gutenberg-host entry; CI git diff includes built host files"
else
	fail "s5-sm5 webpack gutenberg-host entry; CI git diff includes built host files"
fi
if awk '/public static function dispatch_internal/,/^	}/' "$MAIN" | grep -q '\$dispatch_args\['"'"'dry_run'"'"'\] = true' \
	&& ! awk '/function register_mcp_tools/,/^	}/' "$MAIN" | grep -q "apply-agent-plan" \
	&& ! grep -E "'apply-agent-plan' => self::row\([^)]+true, 'struo_apply'" "$PLUGIN_DIR/includes/class-operation-catalog.php" >/dev/null; then
	pass "s5-sm6 no new MCP apply; dispatch_internal still dry-run; apply-agent-plan mcp.public false"
else
	fail "s5-sm6 no new MCP apply; dispatch_internal still dry-run; apply-agent-plan mcp.public false"
fi
check_grep "s5-e1 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s5-e1)"
check_grep "s5-e2 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s5-e2)"
check_grep "s5-e3 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s5-e3)"
check_grep "s5-e4 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s5-e4)"
check_grep "s5-e5 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(s5-e5)"
check_grep "s5 changelog note" "$PLUGIN_DIR/CHANGELOG.md" "Mission Brief in the block editor"

echo "== 33. Review follow-ups F-07–F-10 / F-14 =="
CONSOLE_JS="$PLUGIN_DIR/assets/console.js"
check_grep "setPlanEmptyState writes textContent" "$CONSOLE_JS" "els.planEmptyBody.textContent"
if grep -n "function setPlanEmptyState" -A 20 "$CONSOLE_JS" | grep -q "planEmptyBody.innerHTML"; then
	fail "setPlanEmptyState does not assign innerHTML on planEmptyBody"
else
	pass "setPlanEmptyState does not assign innerHTML on planEmptyBody"
fi
check_grep "request history persist is opt-in" "$CONSOLE_JS" "function persistRequestHistoryEnabled"
check_grep "Clear wipes request history" "$CONSOLE_JS" "clearRequestHistory();"
check_grep "PHP persist_request_history defaults off" "$MAIN" "'persist_request_history'"
check_grep "modal show helper exists" "$CONSOLE_JS" "function showModal"
check_grep "modal hide helper restores focus" "$CONSOLE_JS" "function hideModal"
check_grep "Tab trap exists" "$CONSOLE_JS" "function bindModalFocusTrap"
check_grep "dynamic allowlist invalidate exists" "$MAIN" "function invalidate_dynamic_allowlist_cache"
check_grep "save_post invalidates dynamic allowlist" "$MAIN" "add_action( 'save_post', \[ __CLASS__, 'invalidate_dynamic_allowlist_cache' \] )"
check_grep "f10-e2 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(f10-e2)"
check_grep "f10-e3 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(f10-e3)"
check_grep "f10-e4 named" "$PLUGIN_DIR/tests/wp-eval-cases.php" "(f10-e4)"
GITLEAKS="$PLUGIN_DIR/tests/gitleaks.sh"
if [ -f "$GITLEAKS" ]; then pass "tests/gitleaks.sh exists"; else fail "tests/gitleaks.sh exists"; fi
check_grep "gitleaks version is 8.30.1" "$GITLEAKS" "GITLEAKS_VERSION='8.30.1'"
check_grep "gitleaks linux x64 sha is pinned" "$GITLEAKS" "551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb"
check_grep "CI runs gitleaks history scan" "$CI" "bash tests/gitleaks.sh --history"
check_grep "CI runs gitleaks working tree scan" "$CI" "bash tests/gitleaks.sh --no-git"
check_grep "CI scans the zip with gitleaks" "$CI" "bash tests/gitleaks.sh --no-git --source"
if [ -f "$PLUGIN_DIR/.gitleaks.toml" ]; then pass ".gitleaks.toml exists"; else fail ".gitleaks.toml exists"; fi

echo ""
if [ "$FAILURES" -eq 0 ]; then
	echo "SMOKE RESULT: ALL CHECKS PASSED"
	exit 0
fi
echo "SMOKE RESULT: $FAILURES CHECK(S) FAILED"
exit 1
