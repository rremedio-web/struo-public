<?php
/**
 * Plugin Name: Struo
 * Description: Safe, allowlisted block catalog and block inspection endpoints for AI tooling.
 * Version: 0.3.0
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: Struo
 * Text Domain: struo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load-time deactivation helper (PHP 7-parseable).
require_once __DIR__ . '/includes/plugin-entry-deactivate.php';

// Load-time PHP floor guard (runs BEFORE the class is declared so old-PHP
// servers never parse the plugin body): show an admin notice and
// deactivate the plugin. Nothing below executes on PHP < 8.2.
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {

	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: 1: required PHP version, 2: running PHP version. */
					__( 'Struo requires PHP %1$s or newer, but this server is running PHP %2$s. The plugin has been deactivated.', 'struo' ),
					'8.2',
					PHP_VERSION
				) )
			);
		}
	);
	add_action(
		'admin_init',
		static function () {
			struo_deactivate_active_plugin_entry( __FILE__ );
		}
	);

	return;
}

// Load-time WordPress floor guard: Abilities API is core in 6.9. The
// plugin still feature-detects 7.0 Client/Connectors at runtime.
if ( isset( $GLOBALS['wp_version'] ) && version_compare( $GLOBALS['wp_version'], '6.9', '<' ) ) {

	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: 1: required WordPress version, 2: running WordPress version. */
					__( 'Struo requires WordPress %1$s or newer (Abilities API). This site is running WordPress %2$s. The plugin has been deactivated.', 'struo' ),
					'6.9',
					isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : ''
				) )
			);
		}
	);
	add_action(
		'admin_init',
		static function () {
			struo_deactivate_active_plugin_entry( __FILE__ );
		}
	);

	return;
}

// Provider destination guard: pure-PHP validator, no WordPress dependency
// (tests/provider-url-cases.php exercises it directly).
require_once __DIR__ . '/provider-url-guard.php';

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Struo\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file = __DIR__ . '/src/' . $relative . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);


// BEGIN Struo_Rate_Limit_Cas (atomic fixed-window limiter core; this
// exact block is extracted verbatim by tests/rate-limit-cases.php so the
// pure-PHP cases always test the shipped implementation).
if ( ! class_exists( 'Struo_Rate_Limit_Cas' ) ):

final class Struo_Rate_Limit_Cas {

	const OPTION_PREFIX = 'struo_rl_';
	const CACHE_GROUP = 'struo_rate_limits';

	/**
	 * Aligned fixed-window start for the given window size.
	 */
	/**
	 * WP-free self::uint(): non-negative integer cast.
	 */
	private static function uint( $value ) {
		return abs( intval( $value ) );
	}

	/**
	 * WP-free sanitize_key(): lowercase a-z0-9_- only.
	 */
	private static function key_sanitize( $name ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $name ) );
	}

	public static function window_start( $window, $now = null ) {
		$window = max( 1, self::uint( $window ) );
		$now = null === $now ? time() : self::uint( $now );
		return (int) ( intdiv( $now, $window ) * $window );
	}

	/**
	 * Counter identity: name + window start, so expiry is implicit.
	 */
	public static function counter_name( $name, $window_start ) {
		return self::OPTION_PREFIX . self::key_sanitize( $name ) . '_' . self::uint( $window_start );
	}

	/**
	 * True when a counter row/key belongs to a window older than
	 * $before_start and can be pruned.
	 */
	public static function should_prune( $counter_key, $before_start ) {
		$pos = strrpos( (string) $counter_key, '_' );
		if ( false === $pos ) {
			return false;
		}
		$suffix = substr( (string) $counter_key, $pos + 1 );
		return ctype_digit( $suffix ) && (int) $suffix < self::uint( $before_start );
	}

	/**
	 * Atomically consume one slot from a fixed-window counter.
	 *
	 * @param string $name  Counter identity (user-scoped by the caller).
	 * @param int    $max   Hard cap for the window.
	 * @param int    $window Window size in seconds.
	 * @param array  $store Storage primitives:
	 *               create( key ), increment_if_below( key, max ) => int|false,
	 *               optional prune( before_start ).
	 * @param int|null $now Optional timestamp override (tests).
	 * @return array{allowed:bool,count:int,window_start:int,window_end:int}
	 */
	public static function consume_with_store( $name, $max, $window, array $store, $now = null ) {
		$max = max( 1, self::uint( $max ) );
		$window = max( 1, self::uint( $window ) );
		$window_start = self::window_start( $window, $now );
		$key = self::counter_name( $name, $window_start );

		if ( isset( $store['create'] ) && is_callable( $store['create'] ) ) {
			call_user_func( $store['create'], $key );
		}

		$count = false;
		if ( isset( $store['increment_if_below'] ) && is_callable( $store['increment_if_below'] ) ) {
			$count = call_user_func( $store['increment_if_below'], $key, $max );
		}

		$allowed = false !== $count;
		$count = self::uint( $count );

		if ( isset( $store['maybe_prune'] ) && is_callable( $store['maybe_prune'] ) ) {
			call_user_func( $store['maybe_prune'], $allowed, $window_start );
		} elseif ( ! $allowed && isset( $store['prune'] ) && is_callable( $store['prune'] ) ) {
			call_user_func( $store['prune'], $window_start );
		}

		return [
			'allowed'      => $allowed,
			'count'        => $count,
			'window_start' => $window_start,
			'window_end'   => $window_start + $window,
		];
	}

	/**
	 * Object-cache increment with hard-cap rollback and fail-closed
	 * eviction handling.
	 *
	 * - incr result > max: roll back OUR OWN increment once (decr) and
	 *   deny, so the stored value never exceeds max. A concurrent decr
	 *   race can under-count by at most 1 — it can never over-allow.
	 * - incr miss: try to create; if creation fails but the key is visible
	 *   it already exists -> retry incr once. Two consecutive incr misses
	 *   fail CLOSED (deny) — an evicted key must never yield a fresh
	 *   window of quota.
	 *
	 * @param array $p incr(), add():bool, get():int|false, decr().
	 * @return int|false The count this request set, or false when denied.
	 */
	public static function cache_increment( $key, $max, array $p ) {
		$max = self::uint( $max );

		$count = $p['incr']();
		if ( false === $count ) {
			$created = (bool) $p['add']();
			$exists = $created || false !== $p['get']();
			if ( ! $exists ) {
				return false;
			}
			$count = $p['incr']();
			if ( false === $count ) {
				return false;
			}
		}

		$count = self::uint( $count );
		if ( $count > $max ) {
			$p['decr']();
			return false;
		}

		return $count;
	}

	/**
	 * WordPress-backed store: persistent object cache when present,
	 * otherwise single-row CAS in the options table.
	 */
	public static function wp_store( $window ) {
		$ttl = max( 1, self::uint( $window ) );

		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return [
				'create' => static function ( $key ) use ( $ttl ) {
					if ( false === wp_cache_get( $key, self::CACHE_GROUP ) ) {
						// Atomic creator: only one writer wins; losers fall
						// through to incr on the existing key.
						wp_cache_add( $key, 0, self::CACHE_GROUP, $ttl );
					}
				},
				'increment_if_below' => static function ( $key, $max ) use ( $ttl ) {
					return self::cache_increment( $key, $max, [
						'incr' => static function () use ( $key ) {
							return wp_cache_incr( $key, 1, self::CACHE_GROUP );
						},
						'add' => static function () use ( $key, $ttl ) {
							return (bool) wp_cache_add( $key, 0, self::CACHE_GROUP, $ttl );
						},
						'get' => static function () use ( $key ) {
							$value = wp_cache_get( $key, self::CACHE_GROUP );
							return false === $value ? false : self::uint( $value );
						},
						'decr' => static function () use ( $key ) {
							wp_cache_decr( $key, 1, self::CACHE_GROUP );
						},
					] );
				},
			];
		}

		global $wpdb;
		$op_calls = 0;
		$created_new_window = false;
		return [
			'create' => static function ( $key ) use ( &$created_new_window ) {
				// Atomic creator: unique option_name; concurrent creators
				// lose silently. A freshly created row signals a window
				// rollover -> prune opportunity.
				$created_new_window = add_option( $key, 0, '', 'no' );
			},
			'increment_if_below' => static function ( $key, $max ) use ( $wpdb ) {
				// Read-your-write with explicit upper-bound semantics: the
				// conditional UPDATE alone decides allow/deny atomically;
				// the returned count is what THIS request set (previous+1),
				// which can lag the true value by concurrent wins — it can
				// never over-allow, because the UPDATE required < max.
				$previous = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
						$key
					)
				);
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s AND option_value < %d",
						$key,
						$max
					)
				);
				if ( ! $updated ) {
					return false;
				}
				return min( $max, $previous + 1 );
			},
			'maybe_prune' => static function ( $allowed, $before_start ) use ( $wpdb, &$op_calls, &$created_new_window ) {
				// Bounded opportunistic pruning: on denials (as before), on
				// window rollover, and every 50th allowed call — capped at
				// 200 rows per pass, prefix-scoped, no unbounded scan.
				$op_calls++;
				$due = ! $allowed || $created_new_window || 0 === $op_calls % 50;
				if ( ! $due ) {
					return 0;
				}
				$created_new_window = false;
				return (int) $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST( SUBSTRING_INDEX( option_name, '_', -1 ) AS UNSIGNED ) < %d LIMIT 200",
						$wpdb->esc_like( self::OPTION_PREFIX ) . '%',
						$before_start
					)
				);
			},
		];
	}

	/**
	 * Convenience wrapper over wp_store() for runtime callers.
	 */
	public static function consume( $name, $max, $window, $now = null ) {
		return self::consume_with_store( $name, $max, $window, self::wp_store( $window ), $now );
	}

}
endif;
// END Struo_Rate_Limit_Cas

// Rename guard (PACKAGING.md §2.1): the legacy entry stub and the compat
// drop-in both require this file; only one definition may ever exist.
if ( ! class_exists( 'Struo_Block_Editor' ) ):

require_once __DIR__ . '/includes/class-durable-plans.php';

final class Struo_Block_Editor {
	const VERSION = '0.3.0';
	const OPTION_KEY = 'struo_options';
	const DEFAULT_AI_DISPLAY_NAME = 'Struo';
	const GENERIC_AI_DISPLAY_NAME = 'Struo';
	const GENERIC_CONSOLE_PRIMARY_COLOR = '#3858e9';
	const GENERIC_CONSOLE_ACCENT_COLOR = '#ffb300';
	const LEGACY_SEEDS_FLAG_OPTION = 'struo_legacy_seeds';
	const AUDIT_KEY = 'struo_audit';
	const AUDIT_LIMIT = 100;
	const AUDIT_DB_VERSION = 1;
	const AUDIT_DB_VERSION_OPTION = 'struo_audit_db_version';
	const AUDIT_MIGRATED_OPTION = 'struo_audit_migrated';
	const AUDIT_TABLE_SUFFIX = 'sae_audit_log';
	const AUDIT_TABLE_PRUNE_INTERVAL = 25;
	const AUDIT_TABLE_MAX_ROWS = 5000;
	const AUDIT_RETENTION_CRON_HOOK = 'struo_audit_retention_prune';
	const TRANSIENTS_MIGRATED_FLAG = 'struo_transients_migrated_v1';
	const AUDIT_RETENTION_MAX_DAYS = 3650;
	const RATE_LIMIT_WINDOW = 300;
	const RATE_LIMIT_MAX = 30;
	const PLAN_RATE_LIMIT_KEY_PREFIX = 'struo_plan_rate_';
	const PLAN_RATE_LIMIT_WINDOW = 3600;
	const PLAN_RATE_LIMIT_MAX = 60;
	const PLAN_RATE_LIMIT_MIN_WINDOW = 60;
	const PLAN_RATE_LIMIT_MIN_MAX = 5;
	const OPENAI_API_KEY_OPTION = 'struo_openai_api_key';
	const OPENAI_KEY_HOST_OPTION = 'struo_openai_key_host';
	const GOOGLE_SERVICE_ACCOUNT_OPTION = 'struo_google_service_account';
	const GA4_PROPERTY_ID_OPTION = 'struo_ga4_property_id';
	const GSC_SITE_URL_OPTION = 'struo_gsc_site_url';
	const PLANNER_LAST_HOP_OPTION = 'struo_planner_last_hop';
	const AGENT_PLANS_OPTION = 'struo_agent_plans';
	const AGENT_PLAN_TTL = 86400;
	const AGENT_PLAN_MAX = Struo_Durable_Plans::ACTIVE_CAP;
	const PLANS_TABLE_SUFFIX = 'struo_plans';
	const PLANS_DB_VERSION = 2;
	const PLANS_DB_VERSION_OPTION = 'struo_plans_db_version';
	const PLANS_AGENT_MIGRATED_OPTION = 'struo_plans_agent_migrated_v1';
	const CAP_STRUO_PLAN = 'struo_plan';
	const CAP_STRUO_APPROVE = 'struo_approve';
	const CAP_STRUO_APPLY = 'struo_apply';
	const CAP_STRUO_MANAGE_REGISTRY = 'struo_manage_registry';
	const CAP_STRUO_MANAGE_SETTINGS = 'struo_manage_settings';
	const CAPS_MIGRATED_OPTION = 'struo_caps_migrated_v1';
	const CAPS_SCHEMA_VERSION = 1;
	const PLAN_SPEND_OPTION_PREFIX = 'struo_plan_spend_';
	const PLAN_SPEND_CAP_MAX = 10000000;
	const AI_PROVIDER_DEFAULT = 'auto';
	const PLUGIN_DISPLAY_NAME_OPTION = 'plugin_display_name';
	const TEXT_DOMAIN = 'struo';
	const MAX_BLOCKS = 80;
	const CONFIRM_TTL = 1800;
	const CONFIRM_KEY_PREFIX = 'struo_confirm_';
	const CREATE_PLAN_KEY_PREFIX = 'struo_create_plan_';
	const USER_TEMPLATES_OPTION = 'struo_user_templates';
	const TEMPLATE_REGISTRY_OPTION = 'struo_template_registry_v2';
	const TEMPLATE_REGISTRY_VERSION = 2;
	const PATTERN_REGISTRY_OPTION = 'struo_pattern_registry_v1';
	const PATTERN_REGISTRY_VERSION = 1;
	const OPTIONS_MIGRATION_FLAG = 'struo_options_migrated_v1';

	const IDEMPOTENCY_TTL = 3600;
	const IDEMPOTENCY_KEY_PREFIX = 'struo_idempotency_';
	const RATE_LIMIT_KEY_PREFIX = 'struo_rate_';
	const SESSION_CONTEXT_KEY_PREFIX = 'struo_session_';
	const IDEMPOTENCY_KEY_MAX_LENGTH = 128;
	const PLAN_MAX_REQUEST_LENGTH = 4000;
	const PLAN_MAX_PROMPT_LENGTH = 100000;
	const BATCH_MAX_OPERATIONS = 20;
	const BUNDLE_MAX_POSTS = 12;
	const BUNDLE_ORPHAN_GRACE = 300;
	const ASK_MAX_SUGGESTIONS = 5;
	const SESSION_CONTEXT_TTL = 1800;
	const SESSION_CONTEXT_MAX_ENTRIES = 3;
	const SESSION_CONTEXT_MAX_CHARS = 500;
	const PLANNER_PAGE_CONTEXT_MAX_CHARS = 2000;
	const RAG_CONTEXT_MAX_CHARS = 1500;
	const CROSS_FIELD_SOURCE_MAX_CHARS = 4000;
	const CPT_ALLOWLIST_DEFAULT_LIMIT = 50;
	const CPT_ALLOWLIST_MAX_LIMIT = 200;
	const MANIFEST_FILE = 'block-manifest.json';
	const CONSOLE_MENU_SLUG = 'struo-console';
	const CONSOLE_PAGE_HOOK = 'toplevel_page_struo-console';
	const SETTINGS_PAGE_SLUG = 'struo';
	const REST_NAMESPACE = 'struo/v1';
	const MIN_PHP_VERSION = '8.2';
	const MIN_WP_VERSION = '6.9';

	private static $manifest_cache = null;
	private static $manifest_status = null;
	private static $audit_table_exists_cache = null;
	private static $plans_table_exists_cache = null;
	private static $plans_engine_cache = null;
	private static $allowed_blocks_cache = null;
	private static $parsed_blocks_cache = [];
	private static $rag_context_cache = null;
	private static $rag_context_cache_key = null;
	private static $dynamic_post_ids_cache = null;
	private static $dynamic_templates_cache = [];
	private static $plan_stream_callback = null;
	private static $plan_stream_last_heartbeat_at = 0.0;
	private static $plan_rate_limit_consumed = false;
	private static $legacy_seeds = null;
	private static $legacy_seeds_notice_queued = false;
	private static $ai_client_prompt_active = false;
	private static $ai_client_outbound_prompt = '';
	private static $client_text_generation = null;

	/**
	 * Resolves a wp-config constant by legacy (SAE_) or generic (STRUO_) name.
	 * Legacy names win so existing site configs keep working unchanged.
	 *
	 * @param string $legacy_name  Legacy constant name, e.g. SAE_LEGACY_SEEDS.
	 * @param string $generic_name Generic constant name, e.g. STRUO_LEGACY_SEEDS.
	 * @return mixed Constant value or null when neither is defined.
	 */
	private static function get_config_constant( $legacy_name, $generic_name ) {
		if ( defined( $legacy_name ) ) {
			return constant( $legacy_name );
		}
		if ( defined( $generic_name ) ) {
			return constant( $generic_name );
		}
		return null;
	}

	/**
	 * Pure read of the legacy-seeds decision. Precedence: constant
	 * (short-circuits, filter not applied) > stored flag > filter > generic.
	 * The flag itself is written exactly once by maybe_upgrade_storage(); if
	 * no upgrade has run yet, defaults to the generic profile and queues a
	 * one-time admin notice.
	 */

	private static function legacy_seeds_enabled() {
		if ( null !== self::$legacy_seeds ) {
			return self::$legacy_seeds;
		}

		$seeds_constant = self::get_config_constant( 'SAE_LEGACY_SEEDS', 'STRUO_LEGACY_SEEDS' );
		if ( null !== $seeds_constant ) {
			self::$legacy_seeds = (bool) $seeds_constant;
			return self::$legacy_seeds;
		}

		$stored = get_option( self::LEGACY_SEEDS_FLAG_OPTION, null );
		if ( null === $stored ) {
			self::$legacy_seeds = (bool) apply_filters( 'struo_legacy_seeds_enabled', false );
			self::maybe_queue_legacy_seeds_notice();
			return self::$legacy_seeds;
		}

		self::$legacy_seeds = (bool) apply_filters( 'struo_legacy_seeds_enabled', (bool) $stored );
		return self::$legacy_seeds;
	}

	private static function maybe_queue_legacy_seeds_notice() {
		if ( self::$legacy_seeds_notice_queued || ! is_admin() ) {
			return;
		}

		self::$legacy_seeds_notice_queued = true;
		add_action( 'admin_notices', [ __CLASS__, 'render_legacy_seeds_notice' ] );
	}

	public static function render_legacy_seeds_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Struo: no install-generation marker was found, so generic defaults are active. Define STRUO_LEGACY_SEEDS (or SAE_LEGACY_SEEDS) as true to restore the pre-agnostic seed content.', self::TEXT_DOMAIN )
		);
	}

	private static function get_default_ai_display_name() {
		return self::DEFAULT_AI_DISPLAY_NAME;
	}

	private static function get_default_console_primary_color() {
		return self::GENERIC_CONSOLE_PRIMARY_COLOR;
	}

	private static function get_default_console_accent_color() {
		return self::GENERIC_CONSOLE_ACCENT_COLOR;
	}

	public static function init() {
		add_action( 'plugins_loaded', [ __CLASS__, 'maybe_upgrade_storage' ] );
		add_action( 'init', [ __CLASS__, 'load_textdomain' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect_console_shortcut' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_console_page' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_console_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_gutenberg_host_assets' ] );
		add_action( 'admin_footer', [ __CLASS__, 'print_gutenberg_host_markup' ] );
		add_action( 'admin_init', [ __CLASS__, 'add_privacy_policy_content' ] );
		add_action( self::AUDIT_RETENTION_CRON_HOOK, [ __CLASS__, 'run_audit_retention_prune' ] );
		add_action( 'init', [ __CLASS__, 'maybe_schedule_audit_retention_prune' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ \Struo\Audit\PrivacyExporter::class, 'register_audit_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ \Struo\Audit\PrivacyExporter::class, 'register_audit_eraser' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ \Struo\Audit\PrivacyExporter::class, 'register_plan_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ \Struo\Audit\PrivacyExporter::class, 'register_plan_eraser' ] );
		add_filter( 'option_page_capability_struo', [ __CLASS__, 'settings_page_capability' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
		add_action( 'save_post', [ __CLASS__, 'invalidate_dynamic_allowlist_cache' ] );
		add_action( 'deleted_post', [ __CLASS__, 'invalidate_dynamic_allowlist_cache' ] );
		add_action( 'trashed_post', [ __CLASS__, 'invalidate_dynamic_allowlist_cache' ] );
		add_action( 'untrashed_post', [ __CLASS__, 'invalidate_dynamic_allowlist_cache' ] );
		add_action( 'updated_option', [ __CLASS__, 'maybe_invalidate_dynamic_allowlist_for_option' ], 10, 1 );
		add_action( 'added_option', [ __CLASS__, 'maybe_invalidate_dynamic_allowlist_for_option' ], 10, 1 );
		add_action( 'deleted_option', [ __CLASS__, 'maybe_invalidate_dynamic_allowlist_for_option' ], 10, 1 );
		add_filter( 'mwai_mcp_tools', [ __CLASS__, 'register_mcp_tools' ] );
		add_filter( 'mwai_mcp_callback', [ __CLASS__, 'handle_mcp_call' ], 10, 4 );
		add_filter( 'wp_ai_client_prevent_prompt', [ __CLASS__, 'maybe_prevent_ai_client_prompt' ], 10, 2 );
		if ( class_exists( 'Struo_Abilities' ) ) {
			Struo_Abilities::init();
		}
	}

	public static function on_activation( $network_wide = false ) {
		// PHP floor first: never proceed on an unsupported runtime, even
		// before the multisite decision below.
		if ( ! self::php_version_ok() ) {
			deactivate_plugins( plugin_basename( __FILE__ ), true, (bool) $network_wide );

			wp_die(
				esc_html( sprintf(
					/* translators: 1: required PHP version, 2: running PHP version. */
					__( 'Struo requires PHP %1$s or newer, but this server is running PHP %2$s. The plugin has been deactivated. Upgrade PHP and activate Struo again.', self::TEXT_DOMAIN ),
					self::MIN_PHP_VERSION,
					PHP_VERSION
				) )
			);
		}

		if ( ! self::wp_version_ok() ) {
			deactivate_plugins( plugin_basename( __FILE__ ), true, (bool) $network_wide );

			wp_die(
				esc_html( sprintf(
					/* translators: 1: required WordPress version, 2: running WordPress version. */
					__( 'Struo requires WordPress %1$s or newer (Abilities API), but this site is running WordPress %2$s. The plugin has been deactivated. Upgrade WordPress and activate Struo again.', self::TEXT_DOMAIN ),
					self::MIN_WP_VERSION,
					isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : ''
				) )
			);
		}

		// Multisite is unsupported network-wide by owner decision; per-site
		// activation on a multisite install stays allowed.
		if ( $network_wide ) {
			deactivate_plugins( plugin_basename( __FILE__ ), true, true );

			wp_die(
				esc_html__( 'Struo supports per-site activation only.', self::TEXT_DOMAIN )
			);
		}

		self::activate();
	}

	private static function php_version_ok() {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );
	}

	private static function wp_version_ok() {
		$version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
		if ( '' === $version ) {
			return true;
		}
		return version_compare( $version, self::MIN_WP_VERSION, '>=' );
	}

	public static function activate() {
		self::maybe_upgrade_storage( true );
		self::maybe_schedule_audit_retention_prune();
	}

	/**
	 * One-time activation handoff for the §2.1 rename (struo.php). If the
	 * legacy basename is still flagged active, activate this file's basename
	 * FIRST and only then deactivate the legacy one; on any activation error
	 * the legacy basename stays active (fail open for availability), the
	 * failure is audit logged, and an admin notice is shown. No-op unless
	 * the legacy basename actually appears in active_plugins. Multisite /
	 * network activation is out of scope by owner decision (unsupported).
	 */
	public static function load_textdomain() {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Whether the front-end console shortcut redirect is active.
	 * Precedence matches legacy_seeds_enabled(): constant short-circuits
	 * (filter not applied); otherwise filter over the legacy-seeds default.
	 */
	private static function console_shortcut_enabled() {
		$shortcut_constant = self::get_config_constant( 'SAE_CONSOLE_SHORTCUT', 'STRUO_CONSOLE_SHORTCUT' );
		if ( null !== $shortcut_constant ) {
			return (bool) $shortcut_constant;
		}
		return (bool) apply_filters( 'struo_console_shortcut_enabled', self::legacy_seeds_enabled() );
	}

	/**
	 * Primary shortcut path (filterable).
	 */
	private static function get_console_shortcut_path() {
		return (string) apply_filters( 'struo_console_shortcut_path', 'console' );
	}

	private static function get_default_plugin_display_name() {
		return 'Struo';
	}

	public static function get_plugin_display_name() {
		$options = self::get_options();
		$name = sanitize_text_field( (string) ( $options[ self::PLUGIN_DISPLAY_NAME_OPTION ] ?? '' ) );
		if ( '' === $name ) {
			$name = self::get_default_plugin_display_name();
		}
		return (string) apply_filters( 'struo_plugin_display_name', $name );
	}

	public static function deactivate() {
		self::delete_plugin_transients();
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::AUDIT_RETENTION_CRON_HOOK );
		}
	}

	private static function get_transient_prefixes() {
		return [
			self::CONFIRM_KEY_PREFIX,
			self::CREATE_PLAN_KEY_PREFIX,
			self::IDEMPOTENCY_KEY_PREFIX,
			self::RATE_LIMIT_KEY_PREFIX,
			self::SESSION_CONTEXT_KEY_PREFIX,
		];
	}

	private static function delete_plugin_transients() {
		global $wpdb;

		$prefixes = self::get_transient_prefixes();
		// Sweep BOTH naming generations (lane 6): legacy sae_* rows may
		// survive from before the rename migration.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sae_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sae_' ) . '%',
				$wpdb->esc_like( '_transient_struo_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_struo_' ) . '%'
			)
		);

		if ( ! is_array( $rows ) ) {
			return;
		}

		$names = [];
		foreach ( $rows as $option_name ) {
			$transient_name = preg_replace( '/^_transient_timeout_|^_transient_/', '', (string) $option_name );
			if ( '' === $transient_name || in_array( $transient_name, $names, true ) ) {
				continue;
			}

			$matches_prefix = false;
			foreach ( $prefixes as $prefix ) {
				if ( 0 === strpos( $transient_name, $prefix ) ) {
					$matches_prefix = true;
					break;
				}
			}
			if ( ! $matches_prefix ) {
				continue;
			}

			$names[] = $transient_name;
			delete_transient( $transient_name );
		}
	}

	/**
	 * One-time transient prefix rename (lane 6 / step 2.4, fix 2): legacy
	 * sae_* transients move to struo_* through the TRANSIENT API. Bounded
	 * batches (LIMIT 200, 50 per boot), convergent (every visited row is
	 * either migrated or explicitly removed/skipped), cache-aware
	 * (delete_*_transient first, then explicit bare-key cache deletes),
	 * and the one-time flag is set only when BOTH branches report an empty
	 * pass in the same run.
	 */
	private static function migrate_legacy_transient_prefixes() {
		if ( (int) get_option( self::TRANSIENTS_MIGRATED_FLAG, 0 ) === 1 ) {
			return;
		}

		global $wpdb;
		$skip = [];

		$regular_empty = self::migrate_legacy_transient_batch( $wpdb, $skip, false );
		$site_empty    = self::migrate_legacy_transient_batch( $wpdb, $skip, true );

		if ( $regular_empty && $site_empty ) {
			update_option( self::TRANSIENTS_MIGRATED_FLAG, 1, false );
		}
	}

	/**
	 * Process up to 50 batches of 200 legacy rows for one store.
	 *
	 * @return bool True when a pass found zero remaining (non-skipped) legacy rows.
	 */
	private static function migrate_legacy_transient_batch( $wpdb, array &$skip, $is_site ) {
		$source_prefix = $is_site ? '_site_transient_sae_' : '_transient_sae_';

		for ( $batch = 0; $batch < 50; $batch++ ) {
			$sql  = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s";
			$args = [ $wpdb->esc_like( $source_prefix ) . '%' ];

			if ( ! empty( $skip ) ) {
				$skip         = array_values( array_unique( $skip ) );
				$placeholders = implode( ', ', array_fill( 0, count( $skip ), '%s' ) );
				$sql         .= " AND option_name NOT IN ( $placeholders )";
				foreach ( $skip as $skipped_name ) {
					$args[] = $skipped_name;
				}
			}

			$sql  .= ' LIMIT 200';
			$names = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );

			if ( empty( $names ) ) {
				return true;
			}

			foreach ( $names as $option_name ) {
				$legacy_key = preg_replace( '/^_site_transient_|^_transient_/', '', (string) $option_name );
				$new_key    = preg_replace( '/^sae_/', 'struo_', (string) $legacy_key );

				// Cannot map sae_* -> struo_* (wrong case, empty key, etc.).
				// Still delete the row; remember it so the next SELECT cannot
				// respin the same name if the delete did not take.
				$remove_only = ( '' === $legacy_key || $new_key === $legacy_key );

				if ( $is_site ) {
					$timeout = get_option( '_site_transient_timeout_' . $legacy_key, false );
				} else {
					$timeout = get_option( '_transient_timeout_' . $legacy_key, false );
				}

				// Expired timeout row => skip and delete (never set_transient(..., 0),
				// which would make the new key permanent). No timeout row => ttl 0
				// keeps a legacy-permanent transient permanent.
				$expired = false !== $timeout && (int) $timeout <= time();

				if ( ! $remove_only && ! $expired ) {
					$value      = $is_site ? get_site_transient( $legacy_key ) : get_transient( $legacy_key );
					$new_exists = $is_site
						? ( false !== get_site_transient( $new_key ) )
						: ( false !== get_transient( $new_key ) );

					if ( false !== $value && ! $new_exists ) {
						$ttl = false === $timeout ? 0 : max( 1, (int) $timeout - time() );
						if ( $is_site ) {
							set_site_transient( $new_key, $value, $ttl );
						} else {
							set_transient( $new_key, $value, $ttl );
						}
					}
				}

				if ( $is_site ) {
					delete_site_transient( $legacy_key );
					wp_cache_delete( $legacy_key, 'site-transient' );
					wp_cache_delete( $legacy_key, 'site-transient_timeout' );
				} else {
					delete_transient( $legacy_key );
					wp_cache_delete( $legacy_key, 'transient' );
					wp_cache_delete( $legacy_key, 'transient_timeout' );
				}

				// Raw option_name fallback: delete_transient looks up
				// `_transient_{$legacy_key}`, which misses an unparseable name.
				if ( $wpdb->get_var( $wpdb->prepare(
					"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s",
					$option_name
				) ) ) {
					delete_option( $option_name );
					delete_option( ( $is_site ? '_site_transient_timeout_' : '_transient_timeout_' ) . $legacy_key );
				}

				if ( $wpdb->get_var( $wpdb->prepare(
					"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s",
					$option_name
				) ) ) {
					$skip[] = $option_name;
				}
			}
		}

		return false;
	}

	public static function maybe_upgrade_storage( $force = false ) {
		self::migrate_legacy_transient_prefixes();
		self::maybe_migrate_legacy_option_keys();
		self::maybe_record_legacy_seeds_flag();
		self::maybe_bind_unbound_provider_key_host();
		self::maybe_migrate_struo_capabilities();
		self::maybe_upgrade_plans_storage();
		if ( class_exists( 'Struo_Mutation_Journal' ) ) {
			Struo_Mutation_Journal::ensure_table();
		}

		$stored_version = absint( get_option( self::AUDIT_DB_VERSION_OPTION, 0 ) );
		if ( ! $force && $stored_version >= self::AUDIT_DB_VERSION ) {
			self::maybe_migrate_legacy_audit_entries();
			self::maybe_migrate_legacy_template_registry();
			return;
		}

		if ( ! self::ensure_audit_table() ) {
			return;
		}

		update_option( self::AUDIT_DB_VERSION_OPTION, self::AUDIT_DB_VERSION, false );
		self::maybe_migrate_legacy_audit_entries();
		self::maybe_migrate_legacy_template_registry();
	}

	/**
	 * Marks options as current. Public edition does not copy foreign keys.
	 */
	private static function maybe_migrate_legacy_option_keys() {
		if ( null !== get_option( self::OPTIONS_MIGRATION_FLAG, null ) ) {
			return;
		}

		update_option( self::OPTIONS_MIGRATION_FLAG, 1, false );
	}


	/**
	 * Records the legacy-seeds decision exactly once, based on durable
	 * install markers at upgrade/activation time: audit DB-version option,
	 * audit table existence, or plugin options present. Later reads are pure
	 * (see legacy_seeds_enabled()).
	 */
	private static function maybe_record_legacy_seeds_flag() {
		if ( null !== get_option( self::LEGACY_SEEDS_FLAG_OPTION, null ) ) {
			return;
		}

		$is_legacy_install =
			null !== get_option( self::AUDIT_DB_VERSION_OPTION, null )
			|| self::audit_table_exists()
			|| null !== get_option( self::OPTION_KEY, null );

		update_option( self::LEGACY_SEEDS_FLAG_OPTION, $is_legacy_install ? 1 : 0, false );
	}

	public static function defaults() {
		$default_post_ids = array_map( 'absint', apply_filters( 'struo_default_allowed_post_ids', [] ) );

		return [
			'kill_switch' => 0,
			'allowed_post_ids' => array_values( array_unique( array_filter( $default_post_ids ) ) ),
			'allowed_content_types' => self::get_default_content_type_registry(),
			'ai_display_name' => self::get_default_ai_display_name(),
			'console_primary_color' => self::get_default_console_primary_color(),
			'console_accent_color' => self::get_default_console_accent_color(),
			'ai_persona_tone' => '',
			'ai_persona_reading_level' => '',
			'ai_persona_forbidden_phrases' => [],
			'allowed_block_types' => self::get_builtin_core_block_types(),
			'plan_rate_role_quotas' => [],
			'ai_provider' => self::AI_PROVIDER_DEFAULT,
			'ai_openai_base_url' => 'https://api.openai.com/v1',
			'ai_openai_model' => 'gpt-4o-mini',
			'ai_plan_daily_token_cap' => 0,
			'audit_retention_days' => 90,
			'audit_store_excerpts' => self::legacy_seeds_enabled(),
			self::PLUGIN_DISPLAY_NAME_OPTION => self::get_default_plugin_display_name(),
		];
	}

	private static function get_builtin_core_block_types() {
		// core/html is excluded from the AI-insertable default: raw HTML
		// blocks are a stored-XSS vector for unfiltered_html users. Opt back
		// in explicitly per site with STRUO_ALLOW_CORE_HTML/SAE_ALLOW_CORE_HTML.
		$types = [
			'core/paragraph',
			'core/heading',
			'core/list',
			'core/image',
			'core/button',
			'core/buttons',
			'core/group',
			'core/columns',
			'core/column',
			'core/quote',
		];

		$allow_raw_html = self::get_config_constant( 'SAE_ALLOW_CORE_HTML', 'STRUO_ALLOW_CORE_HTML' );
		if ( null !== $allow_raw_html && $allow_raw_html ) {
			$types[] = 'core/html';
		}

		return $types;
	}

	private static function get_builtin_template_definitions() {
		return self::get_generic_template_definitions();
	}

	private static function get_generic_template_definitions() {
		return [
			'landing-simple' => [
				'label' => 'Landing Simple',
				'description' => 'Heading, paragraph, and button.',
				'post_type' => 'page',
				'page_template' => '',
				'metadata' => [
					'what_it_is' => 'Reusable landing template with a headline, supporting paragraph, and CTA.',
					'purpose' => 'Use for simple landing pages that need one focused message and one clear call to action.',
					'editable_fields' => [ 'Headline', 'Supporting paragraph', 'CTA button' ],
				],
				'blocks' => [
					[
						'block_name' => 'core/heading',
						'fields' => [
							'content' => [
								'type' => 'string',
								'ai_hint' => 'Landing page headline.',
							],
						],
					],
					[
						'block_name' => 'core/paragraph',
						'fields' => [
							'content' => [
								'type' => 'string',
								'ai_hint' => 'Main supporting paragraph.',
							],
						],
					],
					[
						'block_name' => 'core/button',
						'fields' => [
							'text' => [
								'type' => 'string',
								'ai_hint' => 'Call-to-action button label.',
							],
							'url' => [
								'type' => 'url',
								'ai_hint' => 'Destination URL for the CTA button.',
								'default' => '#',
							],
						],
					],
				],
			],
			'blog-post' => [
				'label' => 'Blog Post',
				'description' => 'Blog draft with headline and body sections.',
				'post_type' => 'post',
				'page_template' => '',
				'metadata' => [
					'what_it_is' => 'Reusable blog draft template with a title, introduction, body sections, and conclusion.',
					'purpose' => 'Use for blog posts that need a straightforward editorial structure before the article is written.',
					'editable_fields' => [ 'Post title', 'Introduction', 'Body sections', 'Conclusion' ],
				],
				'blocks' => [
					[
						'block_name' => 'core/heading',
						'fields' => [
							'content' => [
								'type' => 'string',
								'ai_hint' => 'Post title.',
							],
						],
					],
					[
						'block_name' => 'core/paragraph',
						'fields' => [
							'content' => [
								'type' => 'string',
								'ai_hint' => 'Introduction paragraph.',
							],
						],
					],
					[
						'block_name' => 'core/paragraph',
						'fields' => [
							'content' => [
								'type' => 'string',
								'ai_hint' => 'Body section one.',
							],
						],
					],
					[
						'block_name' => 'core/paragraph',
						'fields' => [
							'content' => [
								'type' => 'string',
								'ai_hint' => 'Body section two.',
							],
						],
					],
					[
						'block_name' => 'core/paragraph',
						'fields' => [
							'content' => [
								'type' => 'string',
								'ai_hint' => 'Conclusion paragraph.',
							],
						],
					],
				],
			],
		];
	}

	private static function get_page_templates() {
		$templates = [];
		foreach ( self::get_template_registry_records() as $template_key => $record ) {
			if ( ! self::template_record_is_create_available( $record ) ) {
				continue;
			}
			$template_definition = self::convert_template_record_to_template_definition( $record );
			if ( null === $template_definition ) {
				continue;
			}
			$templates[ $template_key ] = $template_definition;
		}

		if ( ! empty( self::$dynamic_templates_cache ) ) {
			foreach ( self::$dynamic_templates_cache as $dynamic_key => $dynamic_template ) {
				$normalized_key = self::normalize_create_template_key( $dynamic_key );
				if ( '' === $normalized_key || ! is_array( $dynamic_template ) ) {
					continue;
				}
				$templates[ $normalized_key ] = $dynamic_template;
			}
		}

		return $templates;
	}

	private static function get_template_registry_records() {
		$records = self::get_builtin_template_registry_records();
		$saved_records = self::get_saved_template_registry_records();
		if ( ! empty( $saved_records ) ) {
			$records = array_merge( $saved_records, $records );
		}

		return $records;
	}

	private static function get_pattern_registry_records() {
		$records = self::get_builtin_pattern_registry_records();
		$saved_records = self::get_saved_pattern_registry_records();
		if ( ! empty( $saved_records ) ) {
			$records = array_merge( $saved_records, $records );
		}

		return $records;
	}

	private static function get_builtin_template_registry_records() {
		$records = [];
		foreach ( self::get_builtin_template_definitions() as $template_key => $template_definition ) {
			$normalized_key = self::normalize_create_template_key( $template_key );
			if ( '' === $normalized_key || ! is_array( $template_definition ) ) {
				continue;
			}
			$record = self::normalize_template_registry_record(
				$template_definition,
				[
					'key' => $normalized_key,
					'source_type' => 'built_in',
					'creation_mode' => 'code_builtin',
					'status' => 'approved',
				]
			);
			if ( null === $record ) {
				continue;
			}
			$records[ $normalized_key ] = $record;
		}

		return $records;
	}

	private static function get_builtin_pattern_registry_records() {
		$records = [];
		foreach ( self::get_seeded_pattern_registry_seed() as $pattern_key => $pattern_definition ) {
			$normalized_key = self::normalize_create_template_key( $pattern_key );
			if ( '' === $normalized_key || ! is_array( $pattern_definition ) ) {
				continue;
			}
			$record = self::normalize_pattern_registry_record(
				$pattern_definition,
				[
					'key' => $normalized_key,
					'source_type' => 'built_in',
					'status' => 'approved',
				]
			);
			if ( null === $record ) {
				continue;
			}
			$records[ $normalized_key ] = $record;
		}

		return $records;
	}

	private static function get_saved_template_registry_records() {
		$storage = self::get_saved_template_registry_storage();
		$stored_records = is_array( $storage['templates'] ?? null ) ? $storage['templates'] : [];
		if ( empty( $stored_records ) ) {
			return [];
		}

		$records = [];
		foreach ( $stored_records as $template_key => $record ) {
			$normalized_key = self::normalize_create_template_key( $template_key );
			if ( '' === $normalized_key || ! is_array( $record ) ) {
				continue;
			}
			$normalized_record = self::normalize_template_registry_record(
				$record,
				[
					'key' => $normalized_key,
					'source_type' => 'saved',
					'creation_mode' => 'saved_from_receipt',
					'status' => 'approved',
				]
			);
			if ( null === $normalized_record ) {
				continue;
			}
			$records[ $normalized_key ] = $normalized_record;
		}

		return $records;
	}

	private static function get_saved_pattern_registry_records() {
		$storage = self::get_saved_pattern_registry_storage();
		$stored_records = is_array( $storage['patterns'] ?? null ) ? $storage['patterns'] : [];
		if ( empty( $stored_records ) ) {
			return [];
		}

		$records = [];
		foreach ( $stored_records as $pattern_key => $record ) {
			$normalized_key = self::normalize_create_template_key( $pattern_key );
			if ( '' === $normalized_key || ! is_array( $record ) ) {
				continue;
			}
			$normalized_record = self::normalize_pattern_registry_record(
				$record,
				[
					'key' => $normalized_key,
					'source_type' => 'saved',
					'status' => 'draft',
				]
			);
			if ( null === $normalized_record ) {
				continue;
			}
			$records[ $normalized_key ] = $normalized_record;
		}

		return $records;
	}

	private static function get_saved_template_registry_storage() {
		$stored = get_option( self::TEMPLATE_REGISTRY_OPTION, null );
		if ( is_array( $stored ) && is_array( $stored['templates'] ?? null ) ) {
			return [
				'version' => absint( $stored['version'] ?? self::TEMPLATE_REGISTRY_VERSION ),
				'templates' => $stored['templates'],
			];
		}

		self::maybe_migrate_legacy_template_registry();

		$stored = get_option( self::TEMPLATE_REGISTRY_OPTION, null );
		if ( is_array( $stored ) && is_array( $stored['templates'] ?? null ) ) {
			return [
				'version' => absint( $stored['version'] ?? self::TEMPLATE_REGISTRY_VERSION ),
				'templates' => $stored['templates'],
			];
		}

		return [
			'version' => self::TEMPLATE_REGISTRY_VERSION,
			'templates' => [],
		];
	}

	private static function get_saved_pattern_registry_storage() {
		$stored = get_option( self::PATTERN_REGISTRY_OPTION, null );
		if ( is_array( $stored ) && is_array( $stored['patterns'] ?? null ) ) {
			return [
				'version' => absint( $stored['version'] ?? self::PATTERN_REGISTRY_VERSION ),
				'patterns' => $stored['patterns'],
			];
		}

		return [
			'version' => self::PATTERN_REGISTRY_VERSION,
			'patterns' => [],
		];
	}

	private static function maybe_migrate_legacy_template_registry() {
		$existing = get_option( self::TEMPLATE_REGISTRY_OPTION, null );
		if ( is_array( $existing ) && is_array( $existing['templates'] ?? null ) ) {
			return;
		}

		$legacy_templates = get_option( self::USER_TEMPLATES_OPTION, [] );
		if ( ! is_array( $legacy_templates ) || empty( $legacy_templates ) ) {
			return;
		}

		$migrated_records = [];
		foreach ( $legacy_templates as $template_key => $template ) {
			$normalized_key = self::normalize_create_template_key( $template_key );
			if ( '' === $normalized_key || ! is_array( $template ) ) {
				continue;
			}
			$record = self::normalize_template_registry_record(
				$template,
				[
					'key' => $normalized_key,
					'source_type' => 'saved',
					'creation_mode' => 'saved_from_receipt',
					'status' => 'approved',
				]
			);
			if ( null === $record ) {
				continue;
			}
			$migrated_records[ $normalized_key ] = $record;
		}

		if ( empty( $migrated_records ) ) {
			return;
		}

		update_option(
			self::TEMPLATE_REGISTRY_OPTION,
			[
				'version' => self::TEMPLATE_REGISTRY_VERSION,
				'templates' => $migrated_records,
			],
			false
		);
	}

	private static function template_record_is_create_available( array $record ) {
		$status = sanitize_key( (string) ( $record['status'] ?? 'approved' ) );
		return '' === $status || 'approved' === $status;
	}

	private static function theme_has_page_template( $template_file, array $post_types = [ 'page' ] ) {
		$template_file = sanitize_text_field( (string) $template_file );
		if ( '' === $template_file ) {
			return false;
		}

		$post_type = in_array( 'page', $post_types, true ) ? 'page' : (string) reset( $post_types );
		$templates = wp_get_theme()->get_page_templates( null, $post_type );
		return is_array( $templates ) && array_key_exists( $template_file, $templates );
	}

	private static function normalize_template_registry_record( array $template, array $defaults = [] ) {
		$key = self::normalize_create_template_key( $defaults['key'] ?? ( $template['key'] ?? '' ) );
		if ( '' === $key ) {
			return null;
		}

		$blocks = [];
		if ( is_array( $template['structure']['blocks'] ?? null ) ) {
			$blocks = self::normalize_template_definition_blocks( $template['structure']['blocks'] );
		} else {
			$blocks = self::normalize_template_definition_blocks( $template['blocks'] ?? null );
		}
		if ( empty( $blocks ) ) {
			return null;
		}

		$label = self::normalize_create_title( $template['label'] ?? '' );
		if ( '' === $label ) {
			$label = 'Saved Template';
		}

		$description = self::normalize_create_description( $template['description'] ?? '' );
		$source_type = sanitize_key( (string) ( $template['source_type'] ?? ( $defaults['source_type'] ?? 'saved' ) ) );
		if ( ! in_array( $source_type, [ 'built_in', 'saved' ], true ) ) {
			$source_type = 'saved';
		}

		$creation_mode = sanitize_key( (string) ( $template['creation_mode'] ?? ( $defaults['creation_mode'] ?? '' ) ) );
		if ( ! in_array( $creation_mode, [ 'code_builtin', 'saved_from_receipt', 'promoted_page' ], true ) ) {
			$creation_mode = 'built_in' === $source_type ? 'code_builtin' : 'saved_from_receipt';
		}

		$status = sanitize_key( (string) ( $template['status'] ?? ( $defaults['status'] ?? 'approved' ) ) );
		if ( ! in_array( $status, [ 'draft', 'approved', 'hidden' ], true ) ) {
			$status = 'approved';
		}

		$post_types = self::normalize_template_post_types( $template['post_types'] ?? null );
		if ( empty( $post_types ) ) {
			$legacy_post_type = self::normalize_create_post_type( $template['post_type'] ?? 'page' );
			$post_types = [ '' !== $legacy_post_type ? $legacy_post_type : 'page' ];
		}

		$page_template = sanitize_text_field( (string) ( $template['page_template'] ?? '' ) );
		if ( '' !== $page_template && ! self::theme_has_page_template( $page_template, $post_types ) ) {
			// Feature detection: never apply a page template the active theme
			// does not register; the create flow falls back to the default.
			$page_template = '';
		}
		$section_recipe = self::normalize_template_section_recipe(
			$template['section_recipe'] ?? null,
			$blocks
		);
		$families = self::normalize_template_string_list( $template['families'] ?? null );
		$tags = self::normalize_template_string_list( $template['tags'] ?? null );
		$metadata = self::normalize_template_metadata( $template, $blocks );

		$origin = is_array( $template['origin'] ?? null ) ? $template['origin'] : [];
		$created_from_post_id = absint(
			$origin['created_from_post_id']
				?? $template['based_on_post_id']
				?? 0
		);
		$created_by_user_id = absint(
			$origin['created_by_user_id']
				?? $template['created_by']
				?? 0
		);
		$created_at = absint(
			$origin['created_at']
				?? $template['created_at']
				?? time()
		);

		return [
			'key' => $key,
			'label' => $label,
			'description' => $description,
			'source_type' => $source_type,
			'creation_mode' => $creation_mode,
			'status' => $status,
			'post_types' => $post_types,
			'page_template' => $page_template,
			'families' => $families,
			'tags' => $tags,
			'metadata' => $metadata,
			'structure' => [
				'blocks' => $blocks,
			],
			'section_recipe' => $section_recipe,
			'origin' => [
				'created_from_post_id' => $created_from_post_id,
				'created_by_user_id' => $created_by_user_id,
				'created_at' => $created_at,
			],
		];
	}

	private static function normalize_pattern_registry_record( array $pattern, array $defaults = [] ) {
		$key = self::normalize_create_template_key( $defaults['key'] ?? ( $pattern['key'] ?? '' ) );
		if ( '' === $key ) {
			return null;
		}

		$block_name = self::normalize_block_name(
			$pattern['structure']['block_name']
				?? ( $pattern['block_name'] ?? '' )
		);
		if ( '' === $block_name ) {
			return null;
		}

		$fields_input = $pattern['structure']['fields'] ?? ( $pattern['fields'] ?? null );
		$normalized_blocks = self::normalize_template_definition_blocks(
			[
				[
					'block_name' => $block_name,
					'fields' => $fields_input,
				],
			]
		);
		$fields = is_array( $normalized_blocks[0]['fields'] ?? null ) ? $normalized_blocks[0]['fields'] : [];
		if ( empty( $fields ) ) {
			$fields = self::build_dynamic_template_fields_for_block( $block_name );
		}
		if ( empty( $fields ) ) {
			return null;
		}

		$label = self::normalize_create_title( $pattern['label'] ?? '' );
		if ( '' === $label ) {
			$label = self::get_create_block_label( $block_name );
		}

		$description = self::normalize_create_description( $pattern['description'] ?? '' );
		$source_type = sanitize_key( (string) ( $pattern['source_type'] ?? ( $defaults['source_type'] ?? 'saved' ) ) );
		if ( ! in_array( $source_type, [ 'built_in', 'saved' ], true ) ) {
			$source_type = 'saved';
		}

		$status = sanitize_key( (string) ( $pattern['status'] ?? ( $defaults['status'] ?? 'draft' ) ) );
		if ( ! in_array( $status, [ 'draft', 'approved', 'hidden' ], true ) ) {
			$status = 'draft';
		}

		$post_types = self::normalize_template_post_types( $pattern['post_types'] ?? null );
		if ( empty( $post_types ) ) {
			$legacy_post_type = self::normalize_create_post_type( $pattern['post_type'] ?? 'page' );
			$post_types = [ '' !== $legacy_post_type ? $legacy_post_type : 'page' ];
		}

		$families = self::normalize_template_string_list( $pattern['families'] ?? null );
		$tags = self::normalize_template_string_list( $pattern['tags'] ?? null );
		$metadata = self::normalize_template_metadata(
			$pattern,
			[
				[
					'block_name' => $block_name,
					'fields' => $fields,
				],
			]
		);
		$section_recipe_label = self::normalize_pattern_section_recipe_label(
			$pattern['section_recipe_label'] ?? ( $pattern['section_label'] ?? '' ),
			$metadata['family'] ?? '',
			$block_name
		);

		$origin = is_array( $pattern['origin'] ?? null ) ? $pattern['origin'] : [];
		$created_from_post_id = absint(
			$origin['created_from_post_id']
				?? $pattern['created_from_post_id']
				?? 0
		);
		$created_from_block_index = absint(
			$origin['created_from_block_index']
				?? $pattern['created_from_block_index']
				?? 0
		);
		$created_by_user_id = absint(
			$origin['created_by_user_id']
				?? $pattern['created_by']
				?? 0
		);
		$created_at = absint(
			$origin['created_at']
				?? $pattern['created_at']
				?? time()
		);

		return [
			'key' => $key,
			'label' => $label,
			'description' => $description,
			'source_type' => $source_type,
			'status' => $status,
			'post_types' => $post_types,
			'families' => $families,
			'tags' => $tags,
			'metadata' => $metadata,
			'section_recipe_label' => $section_recipe_label,
			'structure' => [
				'block_name' => $block_name,
				'fields' => $fields,
			],
			'origin' => [
				'created_from_post_id' => $created_from_post_id,
				'created_from_block_index' => $created_from_block_index,
				'created_by_user_id' => $created_by_user_id,
				'created_at' => $created_at,
			],
		];
	}

	private static function convert_template_record_to_template_definition( array $record ) {
		$blocks = self::normalize_template_definition_blocks( $record['structure']['blocks'] ?? null );
		if ( empty( $blocks ) ) {
			return null;
		}

		$post_types = self::normalize_template_post_types( $record['post_types'] ?? null );
		$post_type = ! empty( $post_types ) ? $post_types[0] : 'page';
		$label = self::normalize_create_title( $record['label'] ?? '' );
		if ( '' === $label ) {
			$label = 'Saved Template';
		}

		return [
			'label' => $label,
			'description' => self::normalize_create_description( $record['description'] ?? '' ),
			'post_type' => $post_type,
			'page_template' => sanitize_text_field( (string) ( $record['page_template'] ?? '' ) ),
			'blocks' => $blocks,
			'source_type' => sanitize_key( (string) ( $record['source_type'] ?? 'saved' ) ),
			'creation_mode' => sanitize_key( (string) ( $record['creation_mode'] ?? 'saved_from_receipt' ) ),
			'status' => sanitize_key( (string) ( $record['status'] ?? 'approved' ) ),
			'families' => self::normalize_template_string_list( $record['families'] ?? null ),
			'tags' => self::normalize_template_string_list( $record['tags'] ?? null ),
			'metadata' => self::normalize_template_metadata( $record, $blocks ),
			'section_recipe' => self::normalize_template_section_recipe( $record['section_recipe'] ?? null, $blocks ),
			'created_by' => absint( $record['origin']['created_by_user_id'] ?? 0 ),
			'created_at' => absint( $record['origin']['created_at'] ?? 0 ),
			'based_on_post_id' => absint( $record['origin']['created_from_post_id'] ?? 0 ),
		];
	}

	private static function normalize_template_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}

		$post_types = [];
		foreach ( $value as $post_type ) {
			$normalized_post_type = self::normalize_create_post_type( $post_type );
			if ( '' === $normalized_post_type || isset( $post_types[ $normalized_post_type ] ) ) {
				continue;
			}
			$post_types[ $normalized_post_type ] = $normalized_post_type;
		}

		return array_values( $post_types );
	}

	private static function normalize_template_string_list( $value ) {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $value as $item ) {
			$item = sanitize_key( (string) $item );
			if ( '' === $item || isset( $normalized[ $item ] ) ) {
				continue;
			}
			$normalized[ $item ] = $item;
		}

		return array_values( $normalized );
	}

	private static function normalize_pattern_section_recipe_label( $value, $family = '', $block_name = '' ) {
		$label = sanitize_key( (string) $value );
		if ( '' !== $label ) {
			return $label;
		}

		$family_label = sanitize_key( str_replace( ' ', '-', strtolower( sanitize_text_field( (string) $family ) ) ) );
		if ( '' !== $family_label ) {
			return $family_label;
		}

		return sanitize_key( str_replace( ' ', '-', strtolower( self::get_create_block_label( $block_name ) ) ) );
	}

	private static function normalize_template_metadata( array $template, array $blocks ) {
		$raw_metadata = is_array( $template['metadata'] ?? null ) ? $template['metadata'] : [];
		$description  = self::normalize_create_description( $template['description'] ?? '' );

		$family     = self::normalize_template_metadata_text( $raw_metadata['family'] ?? ( $template['family'] ?? '' ), 80 );
		$variant    = self::normalize_template_metadata_text( $raw_metadata['variant'] ?? ( $template['variant'] ?? '' ), 140 );
		$context    = self::normalize_template_metadata_text( $raw_metadata['context'] ?? ( $template['context'] ?? ( $template['context_label'] ?? '' ) ), 80 );
		$what_it_is = self::normalize_template_metadata_text( $raw_metadata['what_it_is'] ?? ( $template['what_it_is'] ?? '' ), 260 );
		$purpose    = self::normalize_template_metadata_text( $raw_metadata['purpose'] ?? ( $template['purpose'] ?? '' ), 260 );

		if ( '' === $what_it_is ) {
			$what_it_is = self::build_template_structural_summary( $blocks );
		}

		if ( '' === $purpose && '' !== $description && 0 !== stripos( $description, 'Promoted from "' ) ) {
			$purpose = self::normalize_template_metadata_text( $description, 260 );
		}

		$editable_fields = self::normalize_template_metadata_list(
			$raw_metadata['editable_fields'] ?? ( $template['editable_fields'] ?? null ),
			16,
			90
		);
		if ( empty( $editable_fields ) ) {
			$editable_fields = self::build_template_editable_field_list( $blocks );
		}

		$selection_notes = self::normalize_template_metadata_list(
			$raw_metadata['selection_notes'] ?? ( $template['selection_notes'] ?? null ),
			6,
			140
		);
		$acf_modules = self::normalize_template_acf_modules(
			$raw_metadata['acf_modules']
				?? ( $raw_metadata['acf_module']
				?? ( $template['acf_modules']
				?? ( $template['acf_module'] ?? null ) ) ),
			$blocks
		);

		return [
			'family' => $family,
			'variant' => $variant,
			'context' => $context,
			'what_it_is' => $what_it_is,
			'purpose' => $purpose,
			'acf_modules' => $acf_modules,
			'editable_fields' => $editable_fields,
			'selection_notes' => $selection_notes,
		];
	}

	private static function normalize_template_metadata_text( $value, $limit = 160 ) {
		$text = sanitize_text_field( (string) $value );
		if ( '' === $text ) {
			return '';
		}
		return self::normalize_prompt_excerpt( $text, absint( $limit ) ?: 160 );
	}

	private static function normalize_template_metadata_list( $value, $max_items = 8, $item_limit = 120 ) {
		if ( ! is_array( $value ) ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$value = preg_split( '/\s*,\s*/', trim( $value ) );
			} else {
				return [];
			}
		}

		$normalized = [];
		foreach ( $value as $item ) {
			$text = self::normalize_template_metadata_text( $item, $item_limit );
			if ( '' === $text ) {
				continue;
			}
			$key = strtolower( $text );
			if ( isset( $normalized[ $key ] ) ) {
				continue;
			}
			$normalized[ $key ] = $text;
			if ( count( $normalized ) >= $max_items ) {
				break;
			}
		}

		return array_values( $normalized );
	}

	private static function normalize_template_acf_modules( $value, array $blocks ) {
		if ( ! is_array( $value ) ) {
			$value = null !== $value && '' !== (string) $value ? [ $value ] : [];
		}

		$modules = [];
		foreach ( $value as $module ) {
			$normalized_module = self::normalize_block_name( $module );
			if ( '' === $normalized_module || 0 !== strpos( $normalized_module, 'acf/' ) ) {
				continue;
			}
			$modules[ $normalized_module ] = $normalized_module;
		}

		if ( ! empty( $modules ) ) {
			return array_values( $modules );
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$block_name = self::normalize_block_name( $block['block_name'] ?? '' );
			if ( '' === $block_name || 0 !== strpos( $block_name, 'acf/' ) ) {
				continue;
			}
			$modules[ $block_name ] = $block_name;
		}

		return array_values( $modules );
	}

	private static function build_template_editable_field_list( array $blocks ) {
		$labels = [];
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ! is_array( $block['fields'] ?? null ) ) {
				continue;
			}
			foreach ( array_keys( $block['fields'] ) as $field_key ) {
				$label = self::humanize_template_field_label( $field_key );
				if ( '' === $label ) {
					continue;
				}
				$labels[ strtolower( $label ) ] = $label;
			}
		}

		return array_slice( array_values( $labels ), 0, 12 );
	}

	private static function humanize_template_field_label( $field_key ) {
		$field_key = sanitize_key( (string) $field_key );
		if ( '' === $field_key ) {
			return '';
		}

		$map = [
			'content' => 'Content',
			'headline' => 'Headline',
			'subheading' => 'Subheading',
			'subtitle' => 'Subtitle',
			'body' => 'Body copy',
			'text' => 'Text',
			'url' => 'Link URL',
			'buttons' => 'CTA buttons',
			'items' => 'List items',
			'cards' => 'Cards',
			'slides' => 'Slides',
			'question' => 'Question',
			'answer' => 'Answer',
			'image' => 'Image',
			'images' => 'Images',
			'logos' => 'Logos',
		];
		if ( isset( $map[ $field_key ] ) ) {
			return $map[ $field_key ];
		}

		$label = str_replace( [ '-', '_' ], ' ', $field_key );
		$label = ucwords( $label );
		$label = preg_replace( '/\bFaq\b/', 'FAQ', $label );
		$label = preg_replace( '/\bCta\b/', 'CTA', $label );
		$label = preg_replace( '/\bAi\b/', 'AI', $label );
		return trim( (string) $label );
	}

	private static function build_template_structural_summary( array $blocks ) {
		$recipe = self::normalize_template_section_recipe( null, $blocks );
		if ( empty( $recipe ) ) {
			$count = count( $blocks );
			if ( $count < 1 ) {
				return '';
			}
			return sprintf( 'Reusable template with %d section%s.', $count, 1 === $count ? '' : 's' );
		}

		$labels = array_map( [ __CLASS__, 'humanize_template_recipe_item' ], array_slice( $recipe, 0, 5 ) );
		$labels = array_values( array_filter( $labels ) );
		if ( empty( $labels ) ) {
			return '';
		}

		if ( 1 === count( $labels ) ) {
			return sprintf( 'Reusable template centered on %s.', strtolower( $labels[0] ) );
		}
		if ( 2 === count( $labels ) ) {
			return sprintf(
				'Reusable template with %s and %s sections.',
				strtolower( $labels[0] ),
				strtolower( $labels[1] )
			);
		}

		$last = array_pop( $labels );
		return sprintf(
			'Reusable template with %s, and %s sections.',
			strtolower( implode( ', ', $labels ) ),
			strtolower( $last )
		);
	}

	private static function humanize_template_recipe_item( $item ) {
		$item = sanitize_key( (string) $item );
		if ( '' === $item ) {
			return '';
		}

		$label = str_replace( [ '-', '_' ], ' ', $item );
		$label = ucwords( $label );
		$label = preg_replace( '/\bFaq\b/', 'FAQ', $label );
		$label = preg_replace( '/\bCta\b/', 'CTA', $label );
		$label = preg_replace( '/\bAi\b/', 'AI', $label );
		return trim( (string) $label );
	}

	private static function get_seeded_template_module_catalog() {
		if ( ! self::legacy_seeds_enabled() ) {
			return [];
		}

		return [
			'acf/logo-carousel-v2' => [
				'family' => 'Trust Strip',
				'variant' => 'Logo Marquee / Light',
			],
			'acf/sticky-features-stack' => [
				'family' => 'Section',
				'variant' => 'Two Column / Sticky Stack / Dark',
			],
			'acf/customer-slider-v2' => [
				'family' => 'Social Proof',
				'variant' => 'Customer Slider / Light',
			],
			'acf/three-resources-cards' => [
				'family' => 'Section',
				'variant' => 'Three Resources / Light',
			],
			'acf/customer-testimonials-2' => [
				'family' => 'Social Proof',
				'variant' => 'Testimonials Grid / Light',
			],
			'acf/faq-v2' => [
				'family' => 'FAQ',
				'variant' => 'Split / FAQ Accordion / Light',
			],
			'acf/hero-tilt-media' => [
				'family' => 'Hero',
				'variant' => 'Centered / Tilt Media / Dark',
			],
			'acf/homepage-blogroll' => [
				'family' => 'Blog Highlights',
				'variant' => 'Featured + List / Blogroll / Light',
			],
		];
	}

	private static function get_seeded_pattern_registry_seed() {
		if ( ! self::legacy_seeds_enabled() ) {
			return [];
		}

		return [
			'trust-strip-logo-marquee-light' => [
				'label' => 'Trust Strip / Logo Marquee / Light',
				'description' => 'Logo-driven trust strip for quick credibility and partner proof.',
				'source_type' => 'built_in',
				'status' => 'approved',
				'post_types' => [ 'page' ],
				'families' => [ 'trust-strip' ],
				'tags' => [ 'logos', 'trust', 'light' ],
				'metadata' => [
					'family' => 'Trust Strip',
					'variant' => 'Logo Marquee / Light',
					'context' => 'Trust',
					'what_it_is' => 'A lightweight proof strip anchored by customer or partner logos.',
					'purpose' => 'Use when a page needs immediate trust signals near the top of the narrative.',
					'acf_modules' => [ 'acf/logo-carousel-v2' ],
				],
				'section_recipe_label' => 'trust-strip',
				'structure' => [
					'block_name' => 'acf/logo-carousel-v2',
				],
			],
			'section-sticky-stack-dark' => [
				'label' => 'Section / Two Column / Sticky Stack / Dark',
				'description' => 'Two-column explainer with a sticky narrative stack and richer feature detail.',
				'source_type' => 'built_in',
				'status' => 'approved',
				'post_types' => [ 'page' ],
				'families' => [ 'section' ],
				'tags' => [ 'features', 'two-column', 'dark' ],
				'metadata' => [
					'family' => 'Section',
					'variant' => 'Two Column / Sticky Stack / Dark',
					'context' => 'Feature explanation',
					'what_it_is' => 'A sticky-stack section for layered product explanation or capability storytelling.',
					'purpose' => 'Use when the page needs more depth than simple cards and benefits from anchored scrolling context.',
					'acf_modules' => [ 'acf/sticky-features-stack' ],
				],
				'section_recipe_label' => 'section',
				'structure' => [
					'block_name' => 'acf/sticky-features-stack',
				],
			],
			'social-proof-customer-slider-light' => [
				'label' => 'Social Proof / Customer Slider / Light',
				'description' => 'Customer-story slider for social proof with a lighter surface treatment.',
				'source_type' => 'built_in',
				'status' => 'approved',
				'post_types' => [ 'page' ],
				'families' => [ 'social-proof' ],
				'tags' => [ 'testimonials', 'slider', 'light' ],
				'metadata' => [
					'family' => 'Social Proof',
					'variant' => 'Customer Slider / Light',
					'context' => 'Customer proof',
					'what_it_is' => 'A rotating customer proof section with multiple quotes or customer stories.',
					'purpose' => 'Use when a page needs strong narrative proof without expanding into a large testimonial grid.',
					'acf_modules' => [ 'acf/customer-slider-v2' ],
				],
				'section_recipe_label' => 'social-proof',
				'structure' => [
					'block_name' => 'acf/customer-slider-v2',
				],
			],
			'section-three-resources-light' => [
				'label' => 'Section / Three Resources / Light',
				'description' => 'Three-card resource section for related assets, guides, or follow-on reading.',
				'source_type' => 'built_in',
				'status' => 'approved',
				'post_types' => [ 'page', 'post' ],
				'families' => [ 'section' ],
				'tags' => [ 'resources', 'cards', 'light' ],
				'metadata' => [
					'family' => 'Section',
					'variant' => 'Three Resources / Light',
					'context' => 'Related content',
					'what_it_is' => 'A compact three-card module for supporting content or next-step resources.',
					'purpose' => 'Use when a page should route readers into guides, docs, or related proof without a full blog roll.',
					'acf_modules' => [ 'acf/three-resources-cards' ],
				],
				'section_recipe_label' => 'resources',
				'structure' => [
					'block_name' => 'acf/three-resources-cards',
				],
			],
			'social-proof-testimonials-grid-light' => [
				'label' => 'Social Proof / Testimonials Grid / Light',
				'description' => 'Grid-based testimonial section for denser social proof.',
				'source_type' => 'built_in',
				'status' => 'approved',
				'post_types' => [ 'page' ],
				'families' => [ 'social-proof' ],
				'tags' => [ 'testimonials', 'grid', 'light' ],
				'metadata' => [
					'family' => 'Social Proof',
					'variant' => 'Testimonials Grid / Light',
					'context' => 'Customer proof',
					'what_it_is' => 'A multi-card testimonial grid for pages that need a heavier proof surface.',
					'purpose' => 'Use when multiple customer quotes should be scanned quickly without interactive motion.',
					'acf_modules' => [ 'acf/customer-testimonials-2' ],
				],
				'section_recipe_label' => 'social-proof',
				'structure' => [
					'block_name' => 'acf/customer-testimonials-2',
				],
			],
			'faq-accordion-split-light' => [
				'label' => 'FAQ / Split / Accordion / Light',
				'description' => 'Split FAQ section with an accordion answer stack.',
				'source_type' => 'built_in',
				'status' => 'approved',
				'post_types' => [ 'page' ],
				'families' => [ 'faq' ],
				'tags' => [ 'faq', 'accordion', 'light' ],
				'metadata' => [
					'family' => 'FAQ',
					'variant' => 'Split / FAQ Accordion / Light',
					'context' => 'Objections and clarification',
					'what_it_is' => 'A split FAQ layout with supporting intro copy and an accordion answer stack.',
					'purpose' => 'Use when the page needs to resolve common objections or implementation questions near the end.',
					'acf_modules' => [ 'acf/faq-v2' ],
				],
				'section_recipe_label' => 'faq',
				'structure' => [
					'block_name' => 'acf/faq-v2',
				],
			],
			'blog-highlights-featured-list-light' => [
				'label' => 'Blog Highlights / Featured + List / Light',
				'description' => 'Featured-plus-list content rail for recent or related articles.',
				'source_type' => 'built_in',
				'status' => 'approved',
				'post_types' => [ 'page', 'post' ],
				'families' => [ 'blog-highlights' ],
				'tags' => [ 'blog', 'related-content', 'light' ],
				'metadata' => [
					'family' => 'Blog Highlights',
					'variant' => 'Featured + List / Blogroll / Light',
					'context' => 'Related content',
					'what_it_is' => 'A featured article plus list module for surfacing recent or related content.',
					'purpose' => 'Use when the page should bridge into editorial content without becoming a full archive.',
					'acf_modules' => [ 'acf/homepage-blogroll' ],
				],
				'section_recipe_label' => 'blog-highlights',
				'structure' => [
					'block_name' => 'acf/homepage-blogroll',
				],
			],
		];
	}

	private static function build_pattern_metadata_defaults_for_block( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );
		$defaults = [];
		foreach ( self::get_seeded_pattern_registry_seed() as $seed ) {
			if ( ! is_array( $seed ) ) {
				continue;
			}
			$seed_block_name = self::normalize_block_name( $seed['structure']['block_name'] ?? '' );
			if ( '' === $seed_block_name || $seed_block_name !== $block_name ) {
				continue;
			}

			$seed_metadata = is_array( $seed['metadata'] ?? null ) ? $seed['metadata'] : [];
			foreach ( [ 'family', 'variant', 'context', 'what_it_is', 'purpose' ] as $metadata_key ) {
				$value = sanitize_text_field( (string) ( $seed_metadata[ $metadata_key ] ?? '' ) );
				if ( '' !== $value ) {
					$defaults[ $metadata_key ] = $value;
				}
			}
			$acf_modules = self::normalize_template_acf_modules(
				$seed_metadata['acf_modules'] ?? ( $seed_metadata['acf_module'] ?? null ),
				[
					[
						'block_name' => $block_name,
					],
				]
			);
			if ( ! empty( $acf_modules ) ) {
				$defaults['acf_modules'] = $acf_modules;
			}
			break;
		}

		if ( empty( $defaults['acf_modules'] ) && '' !== $block_name && 0 === strpos( $block_name, 'acf/' ) ) {
			$defaults['acf_modules'] = [ $block_name ];
		}

		return $defaults;
	}

	private static function get_seeded_template_module_descriptors( array $modules ) {
		$catalog = self::get_seeded_template_module_catalog();
		$descriptors = [];
		foreach ( $modules as $module ) {
			$module = self::normalize_block_name( $module );
			if ( '' === $module || ! isset( $catalog[ $module ] ) ) {
				continue;
			}
			$entry = $catalog[ $module ];
			$descriptor = trim(
				implode(
					' / ',
					array_filter(
						[
							sanitize_text_field( (string) ( $entry['family'] ?? '' ) ),
							sanitize_text_field( (string) ( $entry['variant'] ?? '' ) ),
						]
					)
				)
			);
			if ( '' === $descriptor ) {
				continue;
			}
			$descriptors[ strtolower( $descriptor ) ] = $descriptor;
		}

		return array_values( $descriptors );
	}

	private static function normalize_template_section_recipe( $value, array $blocks ) {
		$recipe = [];
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$item = sanitize_key( (string) $item );
				if ( '' === $item || isset( $recipe[ $item ] ) ) {
					continue;
				}
				$recipe[ $item ] = $item;
			}
		}

		if ( ! empty( $recipe ) ) {
			return array_values( $recipe );
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$block_name = self::normalize_block_name( $block['block_name'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}
			$label_key = sanitize_key( str_replace( ' ', '-', strtolower( self::get_create_block_label( $block_name ) ) ) );
			if ( '' === $label_key || isset( $recipe[ $label_key ] ) ) {
				continue;
			}
			$recipe[ $label_key ] = $label_key;
		}

		return array_values( $recipe );
	}

	private static function normalize_template_definition_blocks( $blocks ) {
		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			return [];
		}

		$normalized_blocks = [];
		foreach ( array_values( $blocks ) as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$block_name = self::normalize_block_name( $block['block_name'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}

			$fields = [];
			if ( is_array( $block['fields'] ?? null ) ) {
				foreach ( $block['fields'] as $field_name => $field_definition ) {
					$field_key = sanitize_key( (string) $field_name );
					if ( '' === $field_key || ! is_array( $field_definition ) ) {
						continue;
					}

					$field_type = sanitize_key( (string) ( $field_definition['type'] ?? '' ) );
					if ( '' === $field_type ) {
						$field_type = 'text';
					}

					$normalized_field = [
						'type' => $field_type,
					];

					$ai_hint = sanitize_text_field( (string) ( $field_definition['ai_hint'] ?? '' ) );
					if ( '' !== $ai_hint ) {
						$normalized_field['ai_hint'] = $ai_hint;
					}

					if ( array_key_exists( 'default', $field_definition ) && is_scalar( $field_definition['default'] ) ) {
						$normalized_field['default'] = sanitize_text_field( (string) $field_definition['default'] );
					}

					$fields[ $field_key ] = $normalized_field;
				}
			}

			if ( empty( $fields ) ) {
				$fields = self::build_dynamic_template_fields_for_block( $block_name );
			}

			$normalized_blocks[] = [
				'block_name' => $block_name,
				'fields' => $fields,
			];
		}

		return $normalized_blocks;
	}

	private static function build_user_pattern_key( $label, array $existing_patterns = [] ) {
		$base_slug = sanitize_title( (string) $label );
		if ( '' === $base_slug ) {
			$base_slug = 'saved-pattern';
		}

		$base_key = self::normalize_create_template_key( 'pattern-' . $base_slug );
		if ( '' === $base_key ) {
			$base_key = 'pattern-saved-pattern';
		}

		$candidate = $base_key;
		$suffix = 2;
		while ( isset( $existing_patterns[ $candidate ] ) ) {
			$candidate = self::normalize_create_template_key( sprintf( '%s-%d', $base_key, $suffix ) );
			$suffix++;
		}

		return $candidate;
	}

	private static function is_promotable_pattern_block_name( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );
		if ( '' === $block_name ) {
			return false;
		}

		if ( 0 === strpos( $block_name, 'acf/' ) ) {
			return true;
		}

		return in_array( $block_name, [ 'core/group', 'core/columns' ], true );
	}

	private static function build_user_template_key( $label, array $existing_templates = [] ) {
		$base_slug = sanitize_title( (string) $label );
		if ( '' === $base_slug ) {
			$base_slug = 'saved-template';
		}

		$base_key = self::normalize_create_template_key( 'user-' . $base_slug );
		if ( '' === $base_key ) {
			$base_key = 'user-saved-template';
		}

		$candidate = $base_key;
		$suffix = 2;
		while ( isset( $existing_templates[ $candidate ] ) ) {
			$candidate = self::normalize_create_template_key( sprintf( '%s-%d', $base_key, $suffix ) );
			$suffix++;
		}

		return $candidate;
	}

	private static function is_dynamic_template_key( $template_key ) {
		$template_key = self::normalize_create_template_key( $template_key );
		return '' !== $template_key && 0 === strpos( $template_key, 'dynamic-' );
	}

	private static function build_create_template_rationale( $template_mode, $template_label, $post_type = 'page', $template_description = '' ) {
		$template_mode        = 'custom' === sanitize_key( (string) $template_mode ) ? 'custom' : 'known';
		$template_label       = sanitize_text_field( (string) $template_label );
		$template_description = sanitize_text_field( (string) $template_description );
		$content_label        = 'post' === self::normalize_create_post_type( $post_type ) ? 'blog post' : 'page';

		if ( 'custom' === $template_mode ) {
			return sprintf(
				'No strong built-in template match was found, so a custom structure was generated for this %s request.',
				$content_label
			);
		}

		if ( '' !== $template_description ) {
			return sprintf(
				'Using %1$s because it matches this request: %2$s',
				$template_label,
				rtrim( $template_description, ". \t\n\r\0\x0B" ) . '.'
			);
		}

		return sprintf(
			'Using %1$s because it is a strong built-in structure for this %2$s request.',
			$template_label,
			$content_label
		);
	}

	private static function build_create_template_meta( $template_key, $post_type = 'page', $template = null, array $args = [] ) {
		$template_key = self::normalize_create_template_key( $template_key );
		$post_type    = self::normalize_create_post_type( $post_type );
		if ( '' === $post_type ) {
			$post_type = 'page';
		}

		$explicit_mode = sanitize_key( (string) ( $args['mode'] ?? '' ) );
		$template_mode = in_array( $explicit_mode, [ 'known', 'custom' ], true )
			? $explicit_mode
			: ( self::is_dynamic_template_key( $template_key ) ? 'custom' : 'known' );
		$template_data = is_array( $template ) ? $template : [];
		$template_label = sanitize_text_field( (string) ( $template_data['label'] ?? '' ) );
		if ( array_key_exists( 'label', $args ) ) {
			$explicit_label = sanitize_text_field( (string) $args['label'] );
			if ( '' !== $explicit_label ) {
				$template_label = $explicit_label;
			}
		}
		$template_description = sanitize_text_field( (string) ( $template_data['description'] ?? '' ) );
		if ( array_key_exists( 'description', $args ) ) {
			$explicit_description = sanitize_text_field( (string) $args['description'] );
			if ( '' !== $explicit_description ) {
				$template_description = $explicit_description;
			}
		}
		if ( '' === $template_description && is_array( $template_data['metadata'] ?? null ) ) {
			$template_description = self::normalize_create_description(
				$template_data['metadata']['purpose']
					?? ( $template_data['metadata']['what_it_is'] ?? '' )
			);
		}

		if ( '' === $template_label ) {
			$template_label = 'custom' === $template_mode ? 'AI-generated outline' : 'Built-in template';
		}

		$template_rationale = sanitize_text_field( (string) ( $args['rationale'] ?? '' ) );
		if ( '' === $template_rationale ) {
			$template_rationale = self::build_create_template_rationale(
				$template_mode,
				$template_label,
				$post_type,
				$template_description
			);
		}

		return [
			'template_mode' => $template_mode,
			'template_label' => $template_label,
			'template_rationale' => $template_rationale,
		];
	}

	private static function build_create_outline_template_meta( array $outline, $post_type = 'page', $title = '' ) {
		$post_type = self::normalize_create_post_type( $post_type );
		if ( '' === $post_type ) {
			$post_type = 'page';
		}

		$template_key = self::find_template_key_for_outline( $outline, $post_type );
		if ( '' !== $template_key ) {
			$template = self::get_page_templates()[ $template_key ] ?? null;
			$meta = self::build_create_template_meta( $template_key, $post_type, $template );
			$meta['template'] = $template_key;
			return $meta;
		}

		$custom_label = 'AI-generated outline';
		$title_excerpt = self::normalize_prompt_excerpt( sanitize_text_field( (string) $title ), 60 );
		if ( '' !== $title_excerpt ) {
			$custom_label = sprintf( 'Custom outline: %s', $title_excerpt );
		}

		$meta = self::build_create_template_meta(
			'',
			$post_type,
			null,
			[
				'mode' => 'custom',
				'label' => $custom_label,
			]
		);
		$meta['template'] = '';
		return $meta;
	}

	private static function get_discoverable_content_types() {
		$skip_types = [
			'attachment',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_navigation',
			'wp_font_face',
			'wp_font_family',
			'wp_global_styles',
		];
		$content_types = [];

		$registered_types = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $registered_types as $post_type ) {
			$type_name = sanitize_key( is_object( $post_type ) && isset( $post_type->name ) ? (string) $post_type->name : '' );
			if ( '' === $type_name || in_array( $type_name, $skip_types, true ) ) {
				continue;
			}

			$has_blocks = post_type_supports( $type_name, 'editor' );
			$label = '';
			if ( is_object( $post_type ) && isset( $post_type->labels->singular_name ) ) {
				$label = sanitize_text_field( (string) $post_type->labels->singular_name );
			}
			if ( '' === $label && is_object( $post_type ) && isset( $post_type->label ) ) {
				$label = sanitize_text_field( (string) $post_type->label );
			}
			if ( '' === $label ) {
				$label = ucwords( str_replace( '-', ' ', $type_name ) );
			}

			$published_count = 0;
			$counts = wp_count_posts( $type_name );
			if ( is_object( $counts ) && isset( $counts->publish ) ) {
				$published_count = absint( $counts->publish );
			}

			$content_types[ $type_name ] = [
				'name' => $type_name,
				'label' => $label,
				'has_blocks' => (bool) $has_blocks,
				'count' => $published_count,
			];
		}

		ksort( $content_types );
		return $content_types;
	}

	private static function get_default_content_type_registry() {
		// Fresh (non-legacy) installs fail closed: no dynamic post
		// discovery until an admin explicitly enables the type in
		// Settings. Legacy installs keep their historical defaults.
		if ( self::legacy_seeds_enabled() ) {
			$post_defaults = [
				'enabled' => true,
				'limit' => self::CPT_ALLOWLIST_DEFAULT_LIMIT,
				'discovery' => 'recent_modified',
			];
		} else {
			$post_defaults = [
				'enabled' => false,
				'limit' => 0,
				'discovery' => 'recent_modified',
			];
		}

		$registry = [
			'page' => [
				'enabled' => true,
				'limit' => 0,
				'discovery' => 'manual',
			],
			'post' => $post_defaults,
		];

		foreach ( self::get_discoverable_content_types() as $type_name => $type_info ) {
			if ( isset( $registry[ $type_name ] ) || empty( $type_info['has_blocks'] ) ) {
				continue;
			}
			$registry[ $type_name ] = [
				'enabled' => false,
				'limit' => self::CPT_ALLOWLIST_DEFAULT_LIMIT,
				'discovery' => 'recent_modified',
			];
		}

		return $registry;
	}

	private static function normalize_content_type_registry( $registry_input ) {
		$registry_input = is_array( $registry_input ) ? $registry_input : [];
		$defaults = self::get_default_content_type_registry();
		$normalized = [];

		foreach ( $defaults as $type_name => $default_settings ) {
			$submitted = is_array( $registry_input[ $type_name ] ?? null ) ? $registry_input[ $type_name ] : [];
			$has_submitted_type = array_key_exists( $type_name, $registry_input );
			$enabled = $has_submitted_type
				? ! empty( $submitted['enabled'] )
				: ! empty( $default_settings['enabled'] );
			$discovery = sanitize_key( (string) ( $submitted['discovery'] ?? $default_settings['discovery'] ?? 'manual' ) );
			if ( ! in_array( $discovery, [ 'manual', 'recent_modified' ], true ) ) {
				$discovery = 'manual';
			}
			$limit = absint( $submitted['limit'] ?? $default_settings['limit'] ?? 0 );
			if ( 'page' === $type_name || 'manual' === $discovery ) {
				$limit = 0;
			} else {
				if ( $limit <= 0 ) {
					$limit = self::CPT_ALLOWLIST_DEFAULT_LIMIT;
				}
				$limit = min( self::CPT_ALLOWLIST_MAX_LIMIT, $limit );
			}
			if ( 'page' === $type_name ) {
				$discovery = 'manual';
				$enabled = true;
			}

			$normalized[ $type_name ] = [
				'enabled' => (bool) $enabled,
				'limit' => $limit,
				'discovery' => $discovery,
			];
		}

		return $normalized;
	}

	private static function get_content_type_registry( $options = null ) {
		$options = is_array( $options ) ? $options : self::get_options();
		$raw_options = get_option( self::OPTION_KEY, [] );
		$raw_options = is_array( $raw_options ) ? $raw_options : [];
		$has_new_registry = array_key_exists( 'allowed_content_types', $raw_options );
		$has_legacy_blog = array_key_exists( 'allow_blog_posts', $raw_options ) || array_key_exists( 'blog_post_limit', $raw_options );

		if ( ! $has_new_registry && $has_legacy_blog ) {
			$registry = self::get_default_content_type_registry();
			$post_enabled = ! empty( $raw_options['allow_blog_posts'] );
			$post_limit = absint( $raw_options['blog_post_limit'] ?? self::CPT_ALLOWLIST_DEFAULT_LIMIT );
			if ( $post_limit <= 0 ) {
				$post_limit = self::CPT_ALLOWLIST_DEFAULT_LIMIT;
			}
			$post_limit = min( self::CPT_ALLOWLIST_MAX_LIMIT, $post_limit );
			$registry['post']['enabled'] = (bool) $post_enabled;
			$registry['post']['limit'] = $post_enabled ? $post_limit : self::CPT_ALLOWLIST_DEFAULT_LIMIT;
			return $registry;
		}

		$configured_registry = is_array( $options['allowed_content_types'] ?? null ) ? $options['allowed_content_types'] : [];
		return self::normalize_content_type_registry( $configured_registry );
	}

	private static function get_all_discoverable_block_types() {
		$blocks = [];

		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			$registry = WP_Block_Type_Registry::get_instance();
			$registered_blocks = $registry ? $registry->get_all_registered() : [];

			foreach ( $registered_blocks as $registered_block ) {
				$name = '';
				if ( is_object( $registered_block ) && isset( $registered_block->name ) ) {
					$name = (string) $registered_block->name;
				}
				$name = self::normalize_block_name( $name );
				if ( '' === $name ) {
					continue;
				}

				$title = '';
				if ( is_object( $registered_block ) && isset( $registered_block->title ) ) {
					$title = sanitize_text_field( (string) $registered_block->title );
				}
				if ( '' === $title ) {
					$parts = explode( '/', $name );
					$title = ucwords( str_replace( '-', ' ', end( $parts ) ) );
				}

				$blocks[ $name ] = [
					'name' => $name,
					'title' => $title,
					'group' => 0 === strpos( $name, 'core/' ) ? 'core' : 'manual',
				];
			}
		}

		if ( function_exists( 'acf_get_block_types' ) ) {
			foreach ( acf_get_block_types() as $acf_block ) {
				$name = isset( $acf_block['name'] ) ? self::normalize_block_name( $acf_block['name'] ) : '';
				if ( '' === $name ) {
					continue;
				}

				$title = sanitize_text_field( (string) ( $acf_block['title'] ?? $name ) );
				if ( '' === $title ) {
					$title = $name;
				}

				$blocks[ $name ] = [
					'name' => $name,
					'title' => $title,
					'group' => 'acf',
				];
			}
		}

		ksort( $blocks );
		return $blocks;
	}

	public static function get_options() {
		$options = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( is_array( $options ) ? $options : [], self::defaults() );
	}

	/**
	 * Drops any cached copy of the plugin options so subsequent
	 * get_options() calls re-read from the DB. No-op today (get_options()
	 * is not memoized); exists as a documented reset for tests and future
	 * caching layers.
	 */
	public static function flush_options_cache() {
		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		self::invalidate_dynamic_allowlist_cache();
	}

	public static function register_settings() {
		register_setting(
			'struo',
			self::OPTION_KEY,
			[
				'type' => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_options' ],
				'default' => self::defaults(),
			]
		);
		register_setting(
			'struo',
			self::OPENAI_API_KEY_OPTION,
			[
				'type' => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_openai_api_key' ],
				'default' => '',
				'show_in_rest' => false,
			]
		);
		register_setting(
			'struo',
			self::GOOGLE_SERVICE_ACCOUNT_OPTION,
			[
				'type' => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_google_service_account' ],
				'default' => '',
				'show_in_rest' => false,
			]
		);
		register_setting(
			'struo',
			self::GA4_PROPERTY_ID_OPTION,
			[
				'type' => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_ga4_property_id' ],
				'default' => '',
				'show_in_rest' => false,
			]
		);
		register_setting(
			'struo',
			self::GSC_SITE_URL_OPTION,
			[
				'type' => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_gsc_site_url' ],
				'default' => '',
				'show_in_rest' => false,
			]
		);
	}

	public static function sanitize_openai_api_key( $value ) {
		$existing = (string) get_option( self::OPENAI_API_KEY_OPTION, '' );
		$remove = ! empty( $_POST['struo_remove_openai_api_key'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- settings API already verified the options form.
		if ( $remove ) {
			delete_option( self::OPENAI_KEY_HOST_OPTION );
			return '';
		}

		$value = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $value ) {
			return $existing;
		}

		// Bind a non-empty saved key to the provider host it was saved for.
		// Re-saving the key re-binds it.
		$parsed = @parse_url( self::get_openai_base_url() );
		if ( is_array( $parsed ) && ! empty( $parsed['host'] ) ) {
			update_option( self::OPENAI_KEY_HOST_OPTION, strtolower( (string) $parsed['host'] ), false );
		}

		return $value;
	}

	public static function sanitize_google_service_account( $value ) {
		$value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';
		$existing = (string) get_option( self::GOOGLE_SERVICE_ACCOUNT_OPTION, '' );
		if ( '' === $value ) {
			return $existing;
		}
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded )
			|| '' === trim( (string) ( $decoded['client_email'] ?? '' ) )
			|| '' === trim( (string) ( $decoded['private_key'] ?? '' ) )
		) {
			return $existing;
		}
		Struo_Findings::bust_cache();
		return $value;
	}

	public static function sanitize_ga4_property_id( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = preg_replace( '#^properties/#', '', $value );
		Struo_Findings::bust_cache();
		return is_string( $value ) ? $value : '';
	}

	public static function sanitize_gsc_site_url( $value ) {
		$value = trim( (string) $value );
		if ( 0 === stripos( $value, 'sc-domain:' ) ) {
			$domain = sanitize_text_field( substr( $value, strlen( 'sc-domain:' ) ) );
			$value = '' === $domain ? '' : 'sc-domain:' . $domain;
		} else {
			$value = esc_url_raw( $value );
			if ( '' !== $value && 0 !== strpos( $value, 'https://' ) ) {
				$value = '';
			}
		}
		Struo_Findings::bust_cache();
		return $value;
	}

	public static function sanitize_options( $input ) {
		$input = is_array( $input ) ? $input : [];

		$kill_switch = ! empty( $input['kill_switch'] ) ? 1 : 0;
		$allowed_posts = self::sanitize_list( $input['allowed_post_ids'] ?? [] );
		$allowed_posts = array_values( array_filter( array_map( 'absint', $allowed_posts ) ) );
		$content_types_input = is_array( $input['allowed_content_types'] ?? null ) ? $input['allowed_content_types'] : [];
		$current_content_types = self::get_content_type_registry();
		$allowed_content_types = self::normalize_content_type_registry(
			! empty( $content_types_input ) ? $content_types_input : $current_content_types
		);
		$ai_display_name = self::sanitize_ai_display_name( $input['ai_display_name'] ?? self::get_default_ai_display_name() );
		$console_primary_color = self::sanitize_hex_color_with_default(
			$input['console_primary_color'] ?? self::get_default_console_primary_color(),
			self::get_default_console_primary_color()
		);
		$console_accent_color = self::sanitize_hex_color_with_default(
			$input['console_accent_color'] ?? self::get_default_console_accent_color(),
			self::get_default_console_accent_color()
		);
		$persona_tone = sanitize_text_field( (string) ( $input['ai_persona_tone'] ?? '' ) );
		$persona_tone = self::normalize_prompt_excerpt( $persona_tone, 80 );
		$persona_reading_level = self::sanitize_persona_reading_level( $input['ai_persona_reading_level'] ?? '' );
		$persona_forbidden = array_map(
			static function ( $phrase ) {
				return self::normalize_prompt_excerpt( $phrase, 80 );
			},
			self::sanitize_list( $input['ai_persona_forbidden_phrases'] ?? [] )
		);
		$persona_forbidden = array_values( array_filter( array_unique( $persona_forbidden ) ) );
		if ( count( $persona_forbidden ) > 12 ) {
			$persona_forbidden = array_slice( $persona_forbidden, 0, 12 );
		}

		$allowed_blocks_checked = $input['allowed_block_types'] ?? [];
		$manual_blocks = self::sanitize_list( $input['manual_block_types'] ?? '' );
		$allowed_blocks = self::normalize_block_names(
			array_merge(
				is_array( $allowed_blocks_checked ) ? $allowed_blocks_checked : self::sanitize_list( $allowed_blocks_checked ),
				$manual_blocks
			)
		);

		$plan_quotas_input = is_array( $input['plan_rate_role_quotas'] ?? null ) ? $input['plan_rate_role_quotas'] : [];
		$plan_quotas = [];
		foreach ( $plan_quotas_input as $quota_role => $quota_value ) {
			$quota_role_key = sanitize_key( (string) $quota_role );
			if ( '' === $quota_role_key ) {
				continue;
			}
			$quota_int = absint( $quota_value );
			if ( $quota_int <= 0 ) {
				continue;
			}
			$plan_quotas[ $quota_role_key ] = $quota_int;
		}
		ksort( $plan_quotas );

		$ai_provider = sanitize_key( (string) ( $input['ai_provider'] ?? self::AI_PROVIDER_DEFAULT ) );
		if ( ! in_array( $ai_provider, [ 'auto', 'ai_engine', 'openai' ], true ) ) {
			$ai_provider = self::AI_PROVIDER_DEFAULT;
		}
		$openai_base_url = esc_url_raw( (string) ( $input['ai_openai_base_url'] ?? 'https://api.openai.com/v1' ) );
		if ( '' === $openai_base_url ) {
			$openai_base_url = 'https://api.openai.com/v1';
		}
		$parsed_provider = wp_parse_url( $openai_base_url );
		$provider_host = strtolower( (string) ( $parsed_provider['host'] ?? '' ) );
		if ( '' === $provider_host || ! in_array( $provider_host, self::get_allowed_provider_hosts(), true ) ) {
			$openai_base_url = 'https://api.openai.com/v1';
		}
		$openai_model = sanitize_text_field( (string) ( $input['ai_openai_model'] ?? 'gpt-4o-mini' ) );
		if ( array_key_exists( 'ai_plan_daily_token_cap', $input ) ) {
			$daily_token_cap = min( self::PLAN_SPEND_CAP_MAX, absint( $input['ai_plan_daily_token_cap'] ) );
		} else {
			$daily_token_cap = min( self::PLAN_SPEND_CAP_MAX, absint( self::get_options()['ai_plan_daily_token_cap'] ?? 0 ) );
		}
		$plugin_display_name = sanitize_text_field( (string) ( $input[ self::PLUGIN_DISPLAY_NAME_OPTION ] ?? '' ) );
		if ( '' === $plugin_display_name ) {
			$plugin_display_name = self::get_default_plugin_display_name();
		}
		$plugin_display_name = self::normalize_prompt_excerpt( $plugin_display_name, 60 );

		return [
			'kill_switch' => $kill_switch,
			'allowed_post_ids' => $allowed_posts,
			'allowed_content_types' => $allowed_content_types,
			'ai_display_name' => $ai_display_name,
			'console_primary_color' => $console_primary_color,
			'console_accent_color' => $console_accent_color,
			'ai_persona_tone' => $persona_tone,
			'ai_persona_reading_level' => $persona_reading_level,
			'ai_persona_forbidden_phrases' => $persona_forbidden,
			'allowed_block_types' => $allowed_blocks,
			'plan_rate_role_quotas' => $plan_quotas,
			'ai_provider' => $ai_provider,
			'ai_openai_base_url' => $openai_base_url,
			'ai_openai_model' => $openai_model,
			'ai_plan_daily_token_cap' => $daily_token_cap,
			'audit_retention_days' => min( self::AUDIT_RETENTION_MAX_DAYS, absint( $input['audit_retention_days'] ?? 90 ) ),
			'audit_store_excerpts' => ! empty( $input['audit_store_excerpts'] ) ? 1 : 0,
			self::PLUGIN_DISPLAY_NAME_OPTION => $plugin_display_name,
		];
	}

	private static function sanitize_list( $value ) {
		if ( is_array( $value ) ) {
			$list = $value;
		} else {
			$list = preg_split( '/[\r\n,]+/', (string) $value );
		}

		return array_values( array_filter( array_map( 'trim', $list ) ) );
	}

	private static function sanitize_persona_reading_level( $value ) {
		$normalized = sanitize_key( (string) $value );
		$allowed = [
			'grade_6',
			'grade_8',
			'grade_10',
			'college',
			'executive',
		];
		return in_array( $normalized, $allowed, true ) ? $normalized : '';
	}

	private static function sanitize_ai_display_name( $value ) {
		$name = sanitize_text_field( (string) $value );
		$name = self::normalize_prompt_excerpt( $name, 48 );
		return '' !== $name ? $name : self::get_default_ai_display_name();
	}

	private static function sanitize_hex_color_with_default( $value, $default ) {
		$sanitized = sanitize_hex_color( (string) $value );
		if ( is_string( $sanitized ) && '' !== $sanitized ) {
			return strtolower( $sanitized );
		}

		$fallback = sanitize_hex_color( (string) $default );
		return is_string( $fallback ) && '' !== $fallback ? strtolower( $fallback ) : '#000000';
	}

	private static function adjust_hex_color( $hex, $percent ) {
		$hex = self::sanitize_hex_color_with_default( $hex, '#000000' );
		$percent = max( -1, min( 1, (float) $percent ) );
		$rgb = sscanf( ltrim( $hex, '#' ), '%02x%02x%02x' );
		if ( ! is_array( $rgb ) || 3 !== count( $rgb ) ) {
			return '#000000';
		}

		$channels = array_map(
			static function ( $channel ) use ( $percent ) {
				$channel = (int) $channel;
				if ( $percent >= 0 ) {
					$channel += (int) round( ( 255 - $channel ) * $percent );
				} else {
					$channel += (int) round( $channel * $percent );
				}
				return max( 0, min( 255, $channel ) );
			},
			$rgb
		);

		return sprintf( '#%02x%02x%02x', $channels[0], $channels[1], $channels[2] );
	}

	private static function hex_to_rgb_csv( $hex ) {
		$hex = self::sanitize_hex_color_with_default( $hex, '#000000' );
		$rgb = sscanf( ltrim( $hex, '#' ), '%02x%02x%02x' );
		if ( ! is_array( $rgb ) || 3 !== count( $rgb ) ) {
			return '0, 0, 0';
		}

		return implode(
			', ',
			array_map(
				static function ( $channel ) {
					return (string) max( 0, min( 255, (int) $channel ) );
				},
				$rgb
			)
		);
	}

	private static function get_site_context_label() {
		$site_name = sanitize_text_field( get_bloginfo( 'name' ) );
		return '' !== $site_name ? $site_name : 'this WordPress site';
	}

	private static function get_ai_scope_label( $scope ) {
		return sprintf( 'Struo %s', sanitize_text_field( (string) $scope ) );
	}

	private static function get_console_branding_config() {
		$options = self::get_options();
		$display_name = self::sanitize_ai_display_name( $options['ai_display_name'] ?? self::get_default_ai_display_name() );
		$primary = self::sanitize_hex_color_with_default(
			$options['console_primary_color'] ?? self::get_default_console_primary_color(),
			self::get_default_console_primary_color()
		);
		$accent = self::sanitize_hex_color_with_default(
			$options['console_accent_color'] ?? self::get_default_console_accent_color(),
			self::get_default_console_accent_color()
		);
		$upper_name = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $display_name ) : strtoupper( $display_name );

		return [
			'display_name' => $display_name,
			'display_name_upper' => $upper_name,
			'console_title' => sprintf( '%s Console', $display_name ),
			'settings_title' => sprintf( '%s Block Editor', $display_name ),
			'site_name' => self::get_site_context_label(),
			'colors' => [
				'primary' => $primary,
				'primary_rgb' => self::hex_to_rgb_csv( $primary ),
				'primary_soft' => self::adjust_hex_color( $primary, 0.32 ),
				'primary_strong' => self::adjust_hex_color( $primary, -0.16 ),
				'accent' => $accent,
				'accent_rgb' => self::hex_to_rgb_csv( $accent ),
				'accent_soft' => self::adjust_hex_color( $accent, 0.18 ),
			],
		];
	}

	private static function get_console_theme_inline_css() {
		$branding = self::get_console_branding_config();
		$colors = is_array( $branding['colors'] ?? null ) ? $branding['colors'] : [];

		$display_name = self::get_default_ai_display_name();
		$primary_default = self::get_default_console_primary_color();
		$accent_default = self::get_default_console_accent_color();

		return sprintf(
			':root{--sae-brand-primary:%1$s;--sae-brand-primary-rgb:%2$s;--sae-brand-primary-soft:%3$s;--sae-brand-primary-strong:%4$s;--sae-brand-accent:%5$s;--sae-brand-accent-rgb:%6$s;--sae-brand-accent-soft:%7$s;}',
			esc_html( $colors['primary'] ?? $primary_default ),
			esc_html( $colors['primary_rgb'] ?? self::hex_to_rgb_csv( $primary_default ) ),
			esc_html( $colors['primary_soft'] ?? self::adjust_hex_color( $primary_default, 0.32 ) ),
			esc_html( $colors['primary_strong'] ?? self::adjust_hex_color( $primary_default, -0.16 ) ),
			esc_html( $colors['accent'] ?? $accent_default ),
			esc_html( $colors['accent_rgb'] ?? self::hex_to_rgb_csv( $accent_default ) ),
			esc_html( $colors['accent_soft'] ?? self::adjust_hex_color( $accent_default, 0.18 ) )
		);
	}

	private static function sanitize_bundle_name( $value ) {
		$name = sanitize_text_field( (string) $value );
		return self::normalize_prompt_excerpt( $name, 80 );
	}

	private static function normalize_block_name( $name ) {
		$name = strtolower( sanitize_text_field( (string) $name ) );
		if ( empty( $name ) ) {
			return '';
		}

		if ( strpos( $name, '/' ) === false ) {
			$slug = sanitize_title( $name );
			$core_aliases = [
				'paragraph' => 'paragraph',
				'heading' => 'heading',
				'title' => 'heading',
				'list' => 'list',
				'image' => 'image',
				'button' => 'button',
				'buttons' => 'buttons',
				'group' => 'group',
				'columns' => 'columns',
				'column' => 'column',
				'quote' => 'quote',
				'separator' => 'separator',
				'spacer' => 'spacer',
				'html' => 'html',
			];

			if ( isset( $core_aliases[ $slug ] ) ) {
				$name = 'core/' . $core_aliases[ $slug ];
			} else {
				$name = 'acf/' . $slug;
			}
		}

		return $name;
	}

	private static function normalize_block_names( $list ) {
		$names = [];
		foreach ( self::sanitize_list( $list ) as $name ) {
			$normalized = self::normalize_block_name( $name );
			if ( empty( $normalized ) ) {
				continue;
			}
			$names[] = $normalized;
		}
		return array_values( array_unique( $names ) );
	}

	public static function register_settings_page() {
		add_options_page(
			self::get_plugin_display_name(),
			self::get_plugin_display_name(),
			self::CAP_STRUO_MANAGE_SETTINGS,
			self::SETTINGS_PAGE_SLUG,
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function settings_page_capability() {
		return self::CAP_STRUO_MANAGE_SETTINGS;
	}

	public static function register_console_page() {
		$display_name = self::get_plugin_display_name();
		add_menu_page(
			$display_name,
			$display_name,
			'edit_posts',
			self::CONSOLE_MENU_SLUG,
			[ __CLASS__, 'render_console_page' ],
			'dashicons-superhero-alt',
			58
		);
	}

	private static $brief_host = 'console';

	private static $brief_current_post_id = 0;

	public static function enqueue_console_assets( $hook ) {
		if ( self::CONSOLE_PAGE_HOOK !== $hook ) {
			return;
		}

		self::$brief_host = 'console';
		self::$brief_current_post_id = 0;
		self::enqueue_brief_app_assets();
	}

	private static function is_gutenberg_post_editor() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->is_block_editor ) ) {
			return false;
		}
		return 'post' === $screen->base;
	}

	private static function gutenberg_editor_post_id() {
		$post = get_post();
		if ( $post instanceof WP_Post ) {
			$post_id = absint( $post->ID );
			if ( $post_id > 0 ) {
				return $post_id;
			}
		}
		$from_query = absint( get_the_ID() );
		if ( $from_query > 0 ) {
			return $from_query;
		}
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- editor screen query var, not a mutating form
			return absint( wp_unslash( $_GET['post'] ) );
		}
		return 0;
	}

	public static function enqueue_gutenberg_host_assets() {
		if ( ! self::is_gutenberg_post_editor() ) {
			return;
		}

		self::$brief_host = 'gutenberg';
		self::$brief_current_post_id = self::gutenberg_editor_post_id();
		self::enqueue_brief_app_assets();

		$plugin_url = trailingslashit( plugin_dir_url( __FILE__ ) );
		$plugin_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		$asset_path = $plugin_path . 'assets/gutenberg-host.asset.php';
		if ( ! is_readable( $asset_path ) ) {
			return;
		}
		$asset = include $asset_path;
		if ( ! is_array( $asset ) ) {
			$asset = [];
		}
		$deps = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : [];
		$deps[] = 'sae-console';
		$deps[] = 'struo-work-queue';
		wp_enqueue_script(
			'struo-gutenberg-host',
			$plugin_url . 'assets/gutenberg-host.js',
			array_values( array_unique( $deps ) ),
			(string) ( $asset['version'] ?? self::VERSION ),
			true
		);
	}

	public static function print_gutenberg_host_markup() {
		if ( ! self::is_gutenberg_post_editor() ) {
			return;
		}
		?>
		<div id="sae-gutenberg-host" class="sae-console sae-console--gutenberg" hidden>
			<section class="sae-console__grid" id="sae-primary-grid">
				<?php self::render_brief_panels(); ?>
			</section>
		</div>
		<?php
	}

	private static function enqueue_brief_app_assets() {
		$plugin_url = trailingslashit( plugin_dir_url( __FILE__ ) );
		$plugin_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		$console_css_path = $plugin_path . 'assets/console.css';
		$console_js_path = $plugin_path . 'assets/console.js';
		$console_css_ver = file_exists( $console_css_path ) ? (string) filemtime( $console_css_path ) : self::VERSION;
		$console_js_ver = file_exists( $console_js_path ) ? (string) filemtime( $console_js_path ) : self::VERSION;
		wp_enqueue_style(
			'sae-console',
			$plugin_url . 'assets/console.css',
			[],
			$console_css_ver
		);
		wp_add_inline_style( 'sae-console', self::get_console_theme_inline_css() );
		wp_enqueue_script(
			'sae-console',
			$plugin_url . 'assets/console.js',
			[],
			$console_js_ver,
			true
		);
		wp_localize_script(
			'sae-console',
			'saeConsoleConfig',
			self::get_console_frontend_config()
		);
		$work_queue_asset_path = $plugin_path . 'assets/work-queue.asset.php';
		if ( is_readable( $work_queue_asset_path ) ) {
			$work_queue_asset = include $work_queue_asset_path;
			if ( ! is_array( $work_queue_asset ) ) {
				$work_queue_asset = [];
			}
			$work_queue_deps = is_array( $work_queue_asset['dependencies'] ?? null ) ? $work_queue_asset['dependencies'] : [];
			$work_queue_deps[] = 'sae-console';
			wp_enqueue_script(
				'struo-work-queue',
				$plugin_url . 'assets/work-queue.js',
				$work_queue_deps,
				(string) ( $work_queue_asset['version'] ?? $console_js_ver ),
				true
			);
		}
	}

	private static function get_console_frontend_config() {
		$branding = self::get_console_branding_config();
		$status_route = rest_url( 'struo/v1/console/status' );
		$audit_route = rest_url( 'struo/v1/console/audit' );
		$audit_export_route = rest_url( 'struo/v1/console/audit/export' );
		$plan_route = rest_url( 'struo/v1/plan' );
		$plan_stream_route = rest_url( 'struo/v1/plan/stream' );
		$plan_create_route = rest_url( 'struo/v1/pages/plan-create' );
		$create_page_route = rest_url( 'struo/v1/pages/create' );
		$template_registry_route = rest_url( 'struo/v1/templates' );
		$promote_page_template_route = rest_url( 'struo/v1/templates/promote-page' );
		$save_created_template_route = rest_url( 'struo/v1/templates/save-created' );
		$pattern_registry_route = rest_url( 'struo/v1/patterns' );
		$promote_section_pattern_route = rest_url( 'struo/v1/patterns/promote-section' );
		$kill_switch_route = rest_url( 'struo/v1/console/kill-switch' );
		$agent_plans_route = rest_url( 'struo/v1/console/agent-plans' );
		$findings_route = rest_url( 'struo/v1/console/findings' );
		$batch_base_route = rest_url( 'struo/v1/posts/' );

		return [
			'host' => self::$brief_host,
			'current_post_id' => absint( self::$brief_current_post_id ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'rest' => [
				'status' => $status_route,
				'audit' => $audit_route,
				'audit_export' => $audit_export_route,
				'plan' => $plan_route,
				'plan_stream' => $plan_stream_route,
				'plan_create' => $plan_create_route,
				'create_page' => $create_page_route,
				'templates' => $template_registry_route,
				'promote_page_template' => $promote_page_template_route,
				'save_created_template' => $save_created_template_route,
				'patterns' => $pattern_registry_route,
				'promote_section_pattern' => $promote_section_pattern_route,
				'kill_switch' => $kill_switch_route,
				'agent_plans' => $agent_plans_route,
				'findings' => $findings_route,
				'batch_base' => $batch_base_route,
			],
			'admin' => [
				'post_edit_base' => admin_url( 'post.php' ),
				'settings' => add_query_arg( [ 'page' => self::SETTINGS_PAGE_SLUG ], admin_url( 'options-general.php' ) ),
			],
			'branding' => $branding,
			'confirm_ttl' => self::CONFIRM_TTL,
			'batch_max_operations' => self::BATCH_MAX_OPERATIONS,
			'features' => [
				'plan_streaming' => self::is_plan_streaming_enabled(),
				'persist_request_history' => (bool) apply_filters( 'struo_console_persist_request_history',
					false
				),
			],
			'strings' => [
				'network_error' => __( 'Network request failed. Retry and check staging connectivity.', self::TEXT_DOMAIN ),
				'plan_required' => __( 'Generate a plan before apply.', self::TEXT_DOMAIN ),
				'token_expired' => __( 'Confirmation token expired. Generate a new plan.', self::TEXT_DOMAIN ),
			],
		];
	}

	private static function is_plan_streaming_enabled() {
		$enabled = true;
		return (bool) apply_filters( 'struo_console_enable_plan_streaming', $enabled );
	}

	public static function maybe_redirect_console_shortcut() {
		if ( is_admin() ) {
			return;
		}

		if ( ! self::console_shortcut_enabled() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $request_uri ) {
			return;
		}

		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return;
		}

		$normalized_path = trim( $path, '/' );
		// 'console' is unconditional so an empty/filtered shortcut value can
		// never leave only the legacy alias active.
		$shortcut_paths = array_values(
			array_unique(
				array_merge(
					[ 'console' ],
					array_filter( [ (string) self::get_console_shortcut_path() ] )
				)
			)
		);
		if ( ! in_array( $normalized_path, $shortcut_paths, true ) ) {
			return;
		}

		$target = admin_url( 'admin.php?page=' . self::CONSOLE_MENU_SLUG );
		if ( ! is_user_logged_in() ) {
			$target = wp_login_url( $target );
		}

		wp_safe_redirect( $target, 302 );
		exit;
	}

	private static function render_brief_panels() {
		?>
				<article class="sae-panel sae-panel--compose" id="sae-compose-panel">
					<h2 id="sae-compose-title"><?php echo esc_html__( 'Request Composer', self::TEXT_DOMAIN ); ?></h2>
					<p class="sae-muted" id="sae-compose-subtitle"><?php echo esc_html__( 'Use single intent for natural-language plans, or switch to batch mode for multiple explicit operations.', self::TEXT_DOMAIN ); ?></p>
					<label class="sae-toggle" id="sae-batch-mode-toggle">
						<input type="checkbox" id="sae-batch-mode" />
						<span><?php echo esc_html__( 'Batch mode (multi-operation)', self::TEXT_DOMAIN ); ?></span>
					</label>
					<section class="sae-step sae-step--target" id="sae-step-target" aria-labelledby="sae-step-target-label">
						<p class="sae-step__label" id="sae-step-target-label"><span class="sae-step__index" aria-hidden="true">1</span> Target</p>
						<div class="sae-step__summary" id="sae-step-target-summary" hidden>
							<p class="sae-step__picked" id="sae-step-target-picked"></p>
							<span class="sae-step__tag" id="sae-step-target-tag" hidden><?php echo esc_html__( 'Auto-detected', self::TEXT_DOMAIN ); ?></span>
							<button type="button" class="sae-step__change" id="sae-step-target-change" aria-expanded="false" aria-controls="sae-step-target-body"><?php echo esc_html__( 'Change', self::TEXT_DOMAIN ); ?></button>
						</div>
						<div class="sae-step__body" id="sae-step-target-body">
							<div class="sae-step__body-inner">
					<div class="sae-col">
						<label class="sae-field" for="sae-post-id"><?php echo esc_html__( 'Target page', self::TEXT_DOMAIN ); ?></label>
						<div class="sae-post-combobox" id="sae-post-combobox">
							<div class="sae-post-combobox__controls">
								<input type="search" id="sae-post-filter" class="sae-post-combobox__input" placeholder="<?php echo esc_attr__( 'Search allowlisted pages and posts…', self::TEXT_DOMAIN ); ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="sae-post-combobox-list" aria-haspopup="listbox" />
								<button type="button" class="button button-secondary sae-post-combobox__clear" id="sae-post-clear"><?php echo esc_html__( 'Auto-detect', self::TEXT_DOMAIN ); ?></button>
							</div>
							<p class="sae-post-combobox__meta" id="sae-post-combobox-meta"><?php echo esc_html__( 'Leave blank to auto-detect from your request.', self::TEXT_DOMAIN ); ?></p>
							<div class="sae-post-combobox__list" id="sae-post-combobox-list" role="listbox" aria-label="<?php echo esc_attr__( 'Allowlisted pages', self::TEXT_DOMAIN ); ?>" hidden></div>
						</div>
						<select id="sae-post-type-filter" aria-label="<?php echo esc_attr__( 'Filter post types', self::TEXT_DOMAIN ); ?>">
							<option value=""><?php echo esc_html__( 'All types', self::TEXT_DOMAIN ); ?></option>
							<option value="page"><?php echo esc_html__( 'Pages', self::TEXT_DOMAIN ); ?></option>
							<option value="post"><?php echo esc_html__( 'Blogs', self::TEXT_DOMAIN ); ?></option>
						</select>
						<select id="sae-post-id" hidden aria-hidden="true" tabindex="-1">
							<option value=""><?php echo esc_html__( 'Auto-detect from request', self::TEXT_DOMAIN ); ?></option>
						</select>
						<p class="sae-help" id="sae-post-help"><?php echo esc_html__( 'Select the target page. Use filters to narrow to pages or blogs.', self::TEXT_DOMAIN ); ?></p>
					</div>
							</div>
						</div>
					</section>
					<div class="sae-scout-live" id="sae-scout-live" hidden>
						<button type="button" class="sae-scout-live__toggle" id="sae-scout-live-toggle" aria-expanded="false" aria-controls="sae-scout-live-panel">
							<span class="sae-scout-live__dot" aria-hidden="true"></span>
							<span class="sae-scout-live__text" id="sae-scout-live-text"></span>
						</button>
						<div class="sae-scout-live__panel" id="sae-scout-live-panel" hidden></div>
					</div>
					<div class="sae-work-queue" id="sae-work-queue"></div>
					<section class="sae-agent-plans" id="sae-agent-plans" hidden>
						<p class="sae-step__label" id="sae-agent-plans-label"><?php echo esc_html__( 'Multi-page and new pages', self::TEXT_DOMAIN ); ?></p>
						<p class="sae-help" id="sae-agent-plans-help"><?php echo esc_html__( 'Still here after you refresh. Same review as when you planned them.', self::TEXT_DOMAIN ); ?></p>
						<div class="sae-agent-plans__list" id="sae-agent-plans-list"></div>
					</section>
					<section class="sae-findings" id="sae-findings">
						<p class="sae-step__label" id="sae-findings-label"><?php echo esc_html__( 'Findings', self::TEXT_DOMAIN ); ?></p>
						<p class="sae-help" id="sae-findings-help"><?php echo esc_html__( 'What Search Console and Analytics already know. You read these. You do not apply them.', self::TEXT_DOMAIN ); ?></p>
						<div class="sae-findings__list" id="sae-findings-list"></div>
					</section>
					<section class="sae-step sae-step--change" id="sae-step-change" aria-labelledby="sae-step-change-label">
						<p class="sae-step__label" id="sae-step-change-label"><span class="sae-step__index" aria-hidden="true">2</span> Change</p>
						<div class="sae-step__body">
						<div class="sae-intent-rail" id="sae-intent-rail" role="group" aria-label="<?php echo esc_attr__( 'Intent', self::TEXT_DOMAIN ); ?>">
							<button type="button" class="button button-secondary sae-intent-rail__button" data-intent-rail="edit" aria-pressed="false"><?php echo esc_html__( 'Edit', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary sae-intent-rail__button" data-intent-rail="analyze" aria-pressed="false"><?php echo esc_html__( 'Analyze', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary sae-intent-rail__button" data-intent-rail="create-page" aria-pressed="false"><?php echo esc_html__( 'Create page', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary sae-intent-rail__button" data-intent-rail="create-blog" aria-pressed="false"><?php echo esc_html__( 'Create blog post', self::TEXT_DOMAIN ); ?></button>
						</div>
						<p class="sae-help sae-intent-rail__hint" id="sae-intent-rail-hint"><?php echo esc_html__( 'Choose the kind of task first, then describe it in one sentence.', self::TEXT_DOMAIN ); ?></p>
						<div id="sae-single-mode-wrap">
							<label class="sae-field" for="sae-request" id="sae-request-label"><?php echo esc_html__( 'Request', self::TEXT_DOMAIN ); ?></label>
							<div class="sae-request-shell">
							<textarea id="sae-request" rows="4" placeholder="<?php echo esc_attr__( 'Shorten the pricing hero to one line…', self::TEXT_DOMAIN ); ?>"></textarea>
							<div class="sae-quick-actions" id="sae-quick-actions" aria-label="<?php echo esc_attr__( 'Quick actions', self::TEXT_DOMAIN ); ?>">
								<div class="sae-quick-actions__row">
									<span class="sae-quick-actions__label"><?php echo esc_html__( 'Core actions', self::TEXT_DOMAIN ); ?></span>
									<p class="sae-quick-actions__hint"><?php echo esc_html__( 'Quick copy improvements for any page or post.', self::TEXT_DOMAIN ); ?></p>
									<div class="sae-quick-actions__list">
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="rewrite" title="<?php echo esc_attr__( 'Rewrite key copy to be clearer and more compelling.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Rewrite', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="shorten" title="<?php echo esc_attr__( 'Shorten copy while keeping the same meaning.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Shorten', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="simplify" title="<?php echo esc_attr__( 'Simplify language to improve readability.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Simplify', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="translate" title="<?php echo esc_attr__( 'Translate key copy while preserving product names.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Translate', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="seo_meta" title="<?php echo esc_attr__( 'Generate SEO title and meta description ideas.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'SEO meta', self::TEXT_DOMAIN ); ?></button>
									<button type="button" class="button button-secondary sae-quick-action" data-quick-action="audit" title="<?php echo esc_attr__( 'Audit this page for clarity, CTA strength, SEO, and accessibility.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Audit', self::TEXT_DOMAIN ); ?></button>
									</div>
								</div>
								<div class="sae-quick-actions__row sae-quick-actions__row--blog" id="sae-quick-actions-blog" hidden>
									<span class="sae-quick-actions__label"><?php echo esc_html__( 'Blog tools', self::TEXT_DOMAIN ); ?></span>
									<p class="sae-quick-actions__hint"><?php echo esc_html__( 'Generate common blog fields from existing content.', self::TEXT_DOMAIN ); ?></p>
									<div class="sae-quick-actions__list">
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="improve_intro" title="<?php echo esc_attr__( 'Rewrite the opening paragraph to hook readers faster.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Improve intro', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="suggest_title" title="<?php echo esc_attr__( 'Suggest stronger headline options.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Suggest title', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="generate_excerpt" title="<?php echo esc_attr__( 'Create a concise summary excerpt from body content.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Generate excerpt', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="meta_description" title="<?php echo esc_attr__( 'Generate a meta description for search previews.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Meta description', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary sae-quick-action" data-quick-action="featured_image_alt" title="<?php echo esc_attr__( 'Generate descriptive alt text for the featured image.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Image alt text', self::TEXT_DOMAIN ); ?></button>
									</div>
								</div>
							</div>
							</div>
							<div class="sae-quickstart" id="sae-quickstart" aria-label="Quick-start examples">
								<div class="sae-quickstart__row" id="sae-quickstart-dynamic"></div>
							</div>
							<div class="sae-block-browser" id="sae-block-browser" hidden>
								<div class="sae-block-browser__header">
									<h3><?php echo esc_html__( 'What blocks can I change?', self::TEXT_DOMAIN ); ?></h3>
									<button type="button" class="button button-secondary" id="sae-block-browser-refresh"><?php echo esc_html__( 'Refresh blocks', self::TEXT_DOMAIN ); ?></button>
								</div>
								<p class="sae-help sae-block-browser__status" id="sae-block-browser-status"><?php echo esc_html__( 'Select a page to load editable blocks.', self::TEXT_DOMAIN ); ?></p>
								<div class="sae-block-browser__list" id="sae-block-browser-list"></div>
							</div>
					</div>
						</div>
					</section>
					<div id="sae-batch-mode-wrap" hidden>
						<p class="sae-muted"><?php echo esc_html__( 'Build explicit operations. Batch dry-run uses the same durable plan_id approve/apply flow.', self::TEXT_DOMAIN ); ?></p>
						<div class="sae-col">
							<label class="sae-field" for="sae-batch-bundle-name"><?php echo esc_html__( 'Bundle name (optional)', self::TEXT_DOMAIN ); ?></label>
							<input type="text" id="sae-batch-bundle-name" maxlength="80" placeholder="<?php echo esc_attr__( 'Homepage CTA refresh', self::TEXT_DOMAIN ); ?>" />
							<p class="sae-help"><?php echo esc_html__( 'Name this batch so reviewers can identify it in dry-runs and history.', self::TEXT_DOMAIN ); ?></p>
						</div>
						<div class="sae-batch-list" id="sae-batch-list" aria-live="polite"></div>
						<div class="sae-actions sae-actions--batch">
							<button type="button" class="button button-secondary" id="sae-batch-add-update"><?php echo esc_html__( 'Add Update', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary" id="sae-batch-add-insert"><?php echo esc_html__( 'Add Insert', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary" id="sae-batch-add-remove"><?php echo esc_html__( 'Add Remove', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary" id="sae-batch-clear"><?php echo esc_html__( 'Clear Batch', self::TEXT_DOMAIN ); ?></button>
						</div>
					</div>
					<section class="sae-step sae-step--plan" id="sae-step-plan" aria-labelledby="sae-step-plan-label">
						<p class="sae-step__label" id="sae-step-plan-label"><span class="sae-step__index" aria-hidden="true">3</span> Plan</p>
					<div class="sae-actions sae-brief-footer" id="sae-brief-footer">
						<button type="button" class="button button-primary" id="sae-generate-plan"><?php echo esc_html__( 'Plan changes', self::TEXT_DOMAIN ); ?></button>
						<button type="button" class="button button-secondary" id="sae-clear" title="<?php echo esc_attr__( 'Clear composer and in-memory request history', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Clear', self::TEXT_DOMAIN ); ?></button>
					</div>
					</section>
					<details class="sae-advanced" id="sae-advanced">
						<summary aria-expanded="false"><?php echo esc_html__( 'Advanced', self::TEXT_DOMAIN ); ?></summary>
						<div class="sae-advanced-tools">
							<button type="button" class="button button-secondary" data-scout-action="promote-pattern" title="<?php echo esc_attr__( 'Save the selected section as a reusable pattern.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Promote section', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary" data-scout-action="manage-patterns" title="<?php echo esc_attr__( 'Open the pattern library.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Pattern library', self::TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-secondary" data-scout-action="focus-composer" title="<?php echo esc_attr__( 'Jump back to the change field.', self::TEXT_DOMAIN ); ?>"><?php echo esc_html__( 'Use composer', self::TEXT_DOMAIN ); ?></button>
						</div>
						<div class="sae-row">
							<div class="sae-col">
								<label class="sae-field" for="sae-response-mode"><?php echo esc_html__( 'Detail level', self::TEXT_DOMAIN ); ?></label>
								<select id="sae-response-mode">
									<option value="compact"><?php echo esc_html__( 'Compact', self::TEXT_DOMAIN ); ?></option>
									<option value="verbose"><?php echo esc_html__( 'Verbose', self::TEXT_DOMAIN ); ?></option>
								</select>
							</div>
						</div>
						<div class="sae-toggles">
							<label><input type="checkbox" id="sae-prefer-ai" /> <?php echo esc_html__( 'Always use AI planner', self::TEXT_DOMAIN ); ?></label>
							<label><input type="checkbox" id="sae-dry-run-preview" checked /> <?php echo esc_html__( 'Include dry-run preview', self::TEXT_DOMAIN ); ?></label>
						</div>
					</details>
					<p class="sae-status" id="sae-compose-status" role="status" aria-live="polite"></p>
					<p class="sae-help sae-rate-limit-hint" id="sae-rate-limit-hint" hidden></p>
				</article>

					<article class="sae-panel sae-panel--review" id="sae-review-panel">
						<h2 id="sae-review-title"><?php echo esc_html__( 'Plan Review', self::TEXT_DOMAIN ); ?></h2>
						<p class="sae-muted" id="sae-review-subtitle"><?php echo esc_html__( 'Validate target, payload, and risk before apply.', self::TEXT_DOMAIN ); ?></p>
							<div id="sae-plan-empty" class="sae-empty">
								<p class="sae-empty__title" id="sae-plan-empty-title"><?php echo esc_html__( 'No plan loaded.', self::TEXT_DOMAIN ); ?></p>
								<p class="sae-empty__subtitle" id="sae-plan-empty-subtitle"><?php echo esc_html__( "Type what you'd like to change, or pick a suggestion below.", self::TEXT_DOMAIN ); ?></p>
								<div id="sae-plan-empty-body" hidden></div>
								<div class="sae-plan-skeleton" aria-hidden="true">
									<div class="sae-skeleton-line sae-skeleton-line--wide"></div>
									<div class="sae-skeleton-line"></div>
									<div class="sae-skeleton-line sae-skeleton-line--short"></div>
								</div>
							</div>
							<div id="sae-plan-content" hidden>
								<div class="sae-plan-request" id="sae-plan-request" hidden></div>
								<div class="sae-simple-summary" id="sae-simple-summary" hidden>
									<p class="sae-simple-summary__eyebrow" id="sae-simple-summary-eyebrow"><?php echo esc_html__( 'Change at a glance', self::TEXT_DOMAIN ); ?></p>
									<p class="sae-simple-summary__status" id="sae-simple-summary-status" hidden></p>
									<p class="sae-simple-summary__headline" id="sae-simple-summary-headline"></p>
									<p class="sae-simple-summary__details" id="sae-simple-summary-details"></p>
									<div class="sae-simple-summary__glance" id="sae-simple-summary-glance" hidden></div>
									<div class="sae-simple-summary__signals" id="sae-simple-summary-signals" hidden>
										<span class="sae-confidence-pill" id="sae-simple-summary-confidence"></span>
									</div>
									<p class="sae-simple-summary__scope" id="sae-simple-summary-scope" hidden></p>
									<p class="sae-simple-summary__safety" id="sae-simple-summary-safety" hidden></p>
								</div>
								<div class="sae-simple-preview" id="sae-simple-preview" hidden>
									<h3><?php echo esc_html__( 'Change preview', self::TEXT_DOMAIN ); ?></h3>
									<div class="sae-simple-preview-list" id="sae-simple-preview-list"></div>
								</div>
								<div class="sae-kv" id="sae-plan-summary"></div>
								<div class="sae-diff-wrap" id="sae-plan-diff-wrap" hidden>
									<h3><?php echo esc_html__( 'Field Diff', self::TEXT_DOMAIN ); ?></h3>
									<div class="sae-diff-list" id="sae-plan-diff-list"></div>
								</div>
								<div class="sae-bundle-wrap" id="sae-bundle-wrap" hidden>
									<h3><?php echo esc_html__( 'Allowlisted pages', self::TEXT_DOMAIN ); ?></h3>
									<p class="sae-help" id="sae-bundle-meta"></p>
									<table class="sae-bundle-table">
										<thead>
											<tr>
												<th><?php echo esc_html__( 'Include', self::TEXT_DOMAIN ); ?></th>
												<th><?php echo esc_html__( 'Page', self::TEXT_DOMAIN ); ?></th>
												<th><?php echo esc_html__( 'Field', self::TEXT_DOMAIN ); ?></th>
												<th><?php echo esc_html__( 'Before → after', self::TEXT_DOMAIN ); ?></th>
												<th><?php echo esc_html__( 'Apply', self::TEXT_DOMAIN ); ?></th>
											</tr>
										</thead>
										<tbody id="sae-bundle-rows"></tbody>
									</table>
									<div class="sae-bundle-actions" id="sae-bundle-actions">
										<button type="button" class="button button-primary" id="sae-bundle-approve"><?php echo esc_html__( 'Approve selected', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button button-secondary" id="sae-bundle-apply-remaining" disabled><?php echo esc_html__( 'Apply remaining', self::TEXT_DOMAIN ); ?></button>
										<button type="button" class="button" id="sae-bundle-dismiss"><?php echo esc_html__( 'Dismiss bundle', self::TEXT_DOMAIN ); ?></button>
									</div>
									<ul class="sae-bundle-skipped" id="sae-bundle-skipped" hidden></ul>
									<p class="sae-help" id="sae-bundle-note"><?php echo esc_html__( 'Review first. Apply writes only the selected allowlisted pages. Agents cannot apply.', self::TEXT_DOMAIN ); ?></p>
								</div>
								<div class="sae-badges" id="sae-plan-badges"></div>
								<div class="sae-warning-list" id="sae-plan-warnings"></div>
								<div id="sae-vector-sources-wrap" hidden>
									<details class="sae-vector-details">
										<summary><?php echo esc_html__( 'Similar pages used for context', self::TEXT_DOMAIN ); ?></summary>
										<ul id="sae-vector-sources-list" class="sae-vector-sources-list"></ul>
									</details>
								</div>
								<div class="sae-ask-wrap" id="sae-ask-wrap" hidden>
									<h3><?php echo esc_html__( 'Suggestions', self::TEXT_DOMAIN ); ?></h3>
									<p class="sae-muted"><?php echo esc_html__( 'Pick one suggestion to turn it into an editable request.', self::TEXT_DOMAIN ); ?></p>
									<div class="sae-ask-list" id="sae-ask-list"></div>
								</div>
								<details class="sae-dev-details" id="sae-dev-details">
									<summary><?php echo esc_html__( 'Developer details', self::TEXT_DOMAIN ); ?></summary>
									<div class="sae-json-wrap" id="sae-plan-payload-wrap">
										<h3><?php echo esc_html__( 'Payload', self::TEXT_DOMAIN ); ?></h3>
									<pre id="sae-plan-payload"></pre>
								</div>
								<div class="sae-json-wrap" id="sae-dry-run-wrap">
									<h3><?php echo esc_html__( 'Dry-Run Preview', self::TEXT_DOMAIN ); ?></h3>
									<pre id="sae-plan-dry-run"></pre>
								</div>
							</details>
							<div class="sae-token" id="sae-token-wrap" hidden>
								<span class="sae-token__label"><?php echo esc_html__( 'Confirmation token', self::TEXT_DOMAIN ); ?></span>
								<code id="sae-token-value"></code>
								<span id="sae-token-expiry"></span>
							</div>
							<div class="sae-confirm-remove" id="sae-remove-confirm-wrap" hidden>
								<label class="sae-field" for="sae-remove-confirm-input"><?php echo esc_html__( 'Destructive action confirm phrase', self::TEXT_DOMAIN ); ?></label>
								<input type="text" id="sae-remove-confirm-input" placeholder="<?php echo esc_attr__( 'Type APPLY REMOVE', self::TEXT_DOMAIN ); ?>" autocomplete="off" />
								</div>
								<div class="sae-actions" id="sae-plan-actions">
									<button type="button" class="button button-primary" id="sae-apply" disabled><?php echo esc_html__( 'Approve & Apply', self::TEXT_DOMAIN ); ?></button>
									<button type="button" class="button button-secondary" id="sae-cancel-plan"><?php echo esc_html__( 'Cancel Plan', self::TEXT_DOMAIN ); ?></button>
								</div>
								<p class="sae-status" id="sae-apply-status" role="status" aria-live="polite"></p>
							</div>
						<div class="sae-receipt" id="sae-receipt" hidden></div>
					</article>
		<?php
	}

	public static function render_console_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', self::TEXT_DOMAIN ) );
		}
		$branding = self::get_console_branding_config();
		?>
		<div class="sae-console" id="sae-console-root">
			<header class="sae-console__header">
				<div>
					<p class="sae-console__eyebrow"><?php echo esc_html( $branding['display_name_upper'] ); ?></p>
					<h1><?php echo esc_html__( 'Console', self::TEXT_DOMAIN ); ?></h1>
					<p id="sae-header-subtitle"><?php echo esc_html__( 'Pick a page, say what to change, press Plan changes. Nothing applies until you approve the plan.', self::TEXT_DOMAIN ); ?></p>
					<p class="sae-platform-hint" id="sae-platform-hint" hidden></p>
				</div>
				<div class="sae-console__header-actions">
					<button type="button" class="button button-secondary" id="sae-toggle-console-mode" aria-pressed="false"><?php echo esc_html__( 'Switch to Dev Mode', self::TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-secondary" id="sae-toggle-app-mode" aria-pressed="false"><?php echo esc_html__( 'Enable App Mode', self::TEXT_DOMAIN ); ?></button>
					<a class="button button-secondary sae-admin-escape" id="sae-admin-escape" href="<?php echo esc_url( admin_url() ); ?>"><?php echo esc_html__( 'WP Admin →', self::TEXT_DOMAIN ); ?></a>
					<button type="button" class="button button-secondary" id="sae-refresh-status"><?php echo esc_html__( 'Refresh Status', self::TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-secondary" id="sae-refresh-history"><?php echo esc_html__( 'Refresh History', self::TEXT_DOMAIN ); ?></button>
				</div>
			</header>

			<section class="sae-console__grid" id="sae-primary-grid">
				<?php self::render_brief_panels(); ?>

			</section>

			<section class="sae-console__grid sae-console__grid--secondary" id="sae-secondary-grid">
				<article class="sae-panel sae-panel--safety" id="sae-safety-panel">
					<h2><?php echo esc_html__( 'Safety & Health', self::TEXT_DOMAIN ); ?></h2>
					<div id="sae-health-cards" class="sae-health"></div>
					<div class="sae-kill-switch">
						<span id="sae-kill-switch-label"><?php echo esc_html__( 'Kill switch: --', self::TEXT_DOMAIN ); ?></span>
						<button type="button" class="button" id="sae-kill-switch-toggle"><?php echo esc_html__( 'Toggle', self::TEXT_DOMAIN ); ?></button>
					</div>
				</article>
				<article class="sae-panel sae-panel--history">
					<h2><?php echo esc_html__( 'Run History', self::TEXT_DOMAIN ); ?></h2>
					<div class="sae-history-controls" id="sae-history-controls">
						<select id="sae-history-action-filter">
							<option value=""><?php echo esc_html__( 'All actions', self::TEXT_DOMAIN ); ?></option>
							<option value="plan">plan</option>
							<option value="batch">batch</option>
							<option value="insert">insert</option>
							<option value="update">update</option>
							<option value="remove">remove</option>
							<option value="dry_run_insert">dry_run_insert</option>
							<option value="dry_run_update">dry_run_update</option>
							<option value="dry_run_remove">dry_run_remove</option>
						</select>
						<label class="sae-toggle sae-toggle--inline">
							<input type="checkbox" id="sae-history-hide-reads" checked />
							<span><?php echo esc_html__( 'Hide read-only', self::TEXT_DOMAIN ); ?></span>
						</label>
						<select id="sae-history-export-format" aria-label="<?php echo esc_attr__( 'Export format', self::TEXT_DOMAIN ); ?>">
							<option value="csv"><?php echo esc_html__( 'CSV export', self::TEXT_DOMAIN ); ?></option>
							<option value="json"><?php echo esc_html__( 'JSON export', self::TEXT_DOMAIN ); ?></option>
						</select>
						<button type="button" class="button button-secondary" id="sae-history-apply-filter"><?php echo esc_html__( 'Filter', self::TEXT_DOMAIN ); ?></button>
						<button type="button" class="button button-secondary" id="sae-history-export"><?php echo esc_html__( 'Export', self::TEXT_DOMAIN ); ?></button>
					</div>
					<div class="sae-history-wrap">
						<table class="sae-history-table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Time', self::TEXT_DOMAIN ); ?></th>
									<th><?php echo esc_html__( 'Action', self::TEXT_DOMAIN ); ?></th>
									<th><?php echo esc_html__( 'Post', self::TEXT_DOMAIN ); ?></th>
									<th><?php echo esc_html__( 'User', self::TEXT_DOMAIN ); ?></th>
									<th><?php echo esc_html__( 'Details', self::TEXT_DOMAIN ); ?></th>
								</tr>
							</thead>
							<tbody id="sae-history-body">
								<tr><td colspan="5" class="sae-empty"><?php echo esc_html__( 'Loading history…', self::TEXT_DOMAIN ); ?></td></tr>
							</tbody>
						</table>
					</div>
				</article>
			</section>
		</div>
		<?php
	}

	/**
	 * Suggested privacy-policy text (lane 3b): discloses what Struo may send
	 * off-site when AI planning is used and what the audit log stores.
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$provider_host = (string) wp_parse_url( self::get_openai_base_url(), PHP_URL_HOST );
		$backend = self::get_ai_planner_backend();
		if ( 'client' === $backend ) {
			$provider = __( "the site's configured WordPress AI Client / Connectors provider", self::TEXT_DOMAIN );
		} elseif ( 'openai' === $backend && '' !== $provider_host ) {
			$provider = sprintf(
				/* translators: %s: provider host name. */
				__( 'the configured OpenAI-compatible provider at "%s"', self::TEXT_DOMAIN ),
				$provider_host
			);
		} else {
			$provider = __( 'the configured AI Engine provider', self::TEXT_DOMAIN );
		}

		$content = '<p>' . sprintf(
			/* translators: %s: provider description. */
			__( 'Struo is an AI-assisted block editor. When AI planning is used, content from allowlisted pages may be sent off-site to %s. Depending on the request this can include: page titles, block excerpts, the current text of fields being edited, and — when retrieval (RAG) is enabled — short excerpts of other allowlisted pages.', self::TEXT_DOMAIN ),
			esc_html( $provider )
		) . '</p>';

		$content .= '<p>' . __( 'For each write or plan action, Struo stores an audit entry containing: the WordPress user ID, the action name, the target post ID, and — when excerpt storage is enabled — short before/after excerpts of at most 120 characters. With excerpt storage disabled, only a short hash and the length of each excerpt is stored, never the raw text.', self::TEXT_DOMAIN ) . '</p>';

		$content .= '<p>' . __( 'Audit entries are retained for at most 90 days by default (configurable by the site administrator) and at most 5,000 rows in total. Site administrators can export or erase this data per user with the WordPress personal-data tools.', self::TEXT_DOMAIN ) . '</p>';

		wp_add_privacy_policy_content( 'Struo', $content );
	}

	public static function render_settings_page() {
		$options = self::get_options();
		$branding = self::get_console_branding_config();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $branding['settings_title'] ); ?></h1>
			<?php
			$privacy_provider_host = (string) wp_parse_url( self::get_openai_base_url(), PHP_URL_HOST );
			$privacy_backend = self::get_ai_planner_backend();
			if ( 'client' === $privacy_backend ) {
				$privacy_provider = __( 'WordPress AI Client / Connectors', self::TEXT_DOMAIN );
			} elseif ( 'openai' === $privacy_backend && '' !== $privacy_provider_host ) {
				$privacy_provider = $privacy_provider_host;
			} else {
				$privacy_provider = __( 'the configured AI Engine provider', self::TEXT_DOMAIN );
			}
			?>
			<div class="notice notice-info" id="struo-privacy-disclosure">
				<p>
					<strong><?php esc_html_e( 'Privacy notice:', self::TEXT_DOMAIN ); ?></strong>
					<?php
					printf(
						/* translators: %s: provider host name. */
						esc_html__( 'When AI planning is used, content from allowlisted pages (page titles, block excerpts, current field text, and RAG excerpts when enabled) is sent off-site to %s. Audit entries store the acting user ID, action, post ID, and before/after excerpts per the excerpt-storage setting below.', self::TEXT_DOMAIN ),
						'<code>' . esc_html( $privacy_provider ) . '</code>'
					);
					?>
				</p>
			</div>
			<form method="post" action="options.php">
				<?php settings_fields( 'struo' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Kill switch (disable writes)</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[kill_switch]" value="1" <?php checked( 1, $options['kill_switch'] ); ?> />
								Disable all write endpoints.
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Audit retention (days)</th>
						<td>
							<input type="number" min="0" max="<?php echo esc_attr( self::AUDIT_RETENTION_MAX_DAYS ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[audit_retention_days]" value="<?php echo esc_attr( $options['audit_retention_days'] ); ?>" class="small-text" />
							<p class="description">Audit entries older than this are deleted daily. 0 keeps only the 5,000-row volume cap.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Store audit excerpts</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[audit_store_excerpts]" value="1" <?php checked( ! empty( $options['audit_store_excerpts'] ) ); ?> />
								When off, audit entries store only a short hash + length of before/after excerpts instead of text.
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Allowed post IDs</th>
						<td>
							<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allowed_post_ids]" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", $options['allowed_post_ids'] ) ); ?></textarea>
							<p class="description">One post ID per line. Requests are blocked unless the post ID is allowlisted.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Content types</th>
						<td>
							<?php
							$discoverable_types = self::get_discoverable_content_types();
							$content_type_registry = self::get_content_type_registry( $options );
							$option_key = esc_attr( self::OPTION_KEY );
							?>
							<fieldset>
								<legend class="screen-reader-text">Content types</legend>
								<?php foreach ( $discoverable_types as $type_name => $type_info ) : ?>
									<?php
									$settings = is_array( $content_type_registry[ $type_name ] ?? null ) ? $content_type_registry[ $type_name ] : [
										'enabled' => false,
										'limit' => self::CPT_ALLOWLIST_DEFAULT_LIMIT,
										'discovery' => 'recent_modified',
									];
									$is_page_type = 'page' === $type_name;
									$is_enabled = ! empty( $settings['enabled'] ) || $is_page_type;
									$discovery = sanitize_key( (string) ( $settings['discovery'] ?? 'recent_modified' ) );
									if ( ! in_array( $discovery, [ 'manual', 'recent_modified' ], true ) ) {
										$discovery = 'recent_modified';
									}
									if ( $is_page_type ) {
										$discovery = 'manual';
									}
									$limit = absint( $settings['limit'] ?? self::CPT_ALLOWLIST_DEFAULT_LIMIT );
									if ( $is_page_type || 'manual' === $discovery ) {
										$limit = 0;
									} elseif ( $limit <= 0 ) {
										$limit = self::CPT_ALLOWLIST_DEFAULT_LIMIT;
									}
									$limit = min( self::CPT_ALLOWLIST_MAX_LIMIT, $limit );
									?>
									<div style="display:flex; align-items:center; gap:12px; margin-bottom:8px; padding:8px; background:#f9f9f9; border:1px solid #e0e0e0; border-radius:4px;">
										<label style="min-width:220px;">
											<input
												type="checkbox"
												name="<?php echo $option_key; ?>[allowed_content_types][<?php echo esc_attr( $type_name ); ?>][enabled]"
												value="1"
												<?php checked( $is_enabled ); ?>
												<?php disabled( $is_page_type ); ?>
											/>
											<?php if ( $is_page_type ) : ?>
												<input type="hidden" name="<?php echo $option_key; ?>[allowed_content_types][page][enabled]" value="1" />
											<?php endif; ?>
											<strong><?php echo esc_html( $type_info['label'] ); ?></strong>
											<span style="color:#666; font-size:12px;">(<?php echo esc_html( absint( $type_info['count'] ?? 0 ) ); ?> published)</span>
										</label>
										<?php if ( ! $is_page_type ) : ?>
											<label style="font-size:13px;">
												Limit:
												<input
													type="number"
													name="<?php echo $option_key; ?>[allowed_content_types][<?php echo esc_attr( $type_name ); ?>][limit]"
													value="<?php echo esc_attr( $limit ); ?>"
													min="1"
													max="<?php echo esc_attr( self::CPT_ALLOWLIST_MAX_LIMIT ); ?>"
													style="width:72px;"
												/>
											</label>
											<input
												type="hidden"
												name="<?php echo $option_key; ?>[allowed_content_types][<?php echo esc_attr( $type_name ); ?>][discovery]"
												value="<?php echo esc_attr( $discovery ); ?>"
											/>
											<span style="color:#666; font-size:12px;">Auto-discovers recent published entries</span>
										<?php else : ?>
											<input type="hidden" name="<?php echo $option_key; ?>[allowed_content_types][page][limit]" value="0" />
											<input type="hidden" name="<?php echo $option_key; ?>[allowed_content_types][page][discovery]" value="manual" />
											<span style="color:#666; font-size:12px;">Uses manual post IDs above</span>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</fieldset>
							<p class="description" style="margin-top:8px;">
								Content types are auto-discovered from WordPress. Check to allow AI editing. Pages always use manual post IDs; other enabled types auto-discover recent published posts up to the selected limit.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Allowed block types</th>
						<td>
							<?php
							$option_key = esc_attr( self::OPTION_KEY );
							$discoverable_blocks = self::get_all_discoverable_block_types();
							$allowed_blocks = self::normalize_block_names( $options['allowed_block_types'] ?? [] );
							$grouped_blocks = [
								'core' => [],
								'acf' => [],
								'manual' => [],
							];

							foreach ( $discoverable_blocks as $block ) {
								$group = $block['group'] ?? 'manual';
								if ( ! isset( $grouped_blocks[ $group ] ) ) {
									$group = 'manual';
								}
								$grouped_blocks[ $group ][] = $block;
							}

							foreach ( $allowed_blocks as $allowed_block_name ) {
								if ( isset( $discoverable_blocks[ $allowed_block_name ] ) ) {
									continue;
								}
								$grouped_blocks['manual'][] = [
									'name' => $allowed_block_name,
									'title' => $allowed_block_name,
									'group' => 'manual',
								];
							}
							?>

							<fieldset>
								<legend class="screen-reader-text">Allowed block types</legend>

								<?php if ( ! empty( $grouped_blocks['core'] ) ) : ?>
									<p><strong>Core blocks</strong></p>
									<?php foreach ( $grouped_blocks['core'] as $block ) : ?>
										<label style="display:block; margin-bottom:4px;">
											<input
												type="checkbox"
												name="<?php echo $option_key; ?>[allowed_block_types][]"
												value="<?php echo esc_attr( $block['name'] ); ?>"
												<?php checked( in_array( $block['name'], $allowed_blocks, true ) ); ?>
											/>
											<code><?php echo esc_html( $block['name'] ); ?></code>
											&mdash; <?php echo esc_html( $block['title'] ); ?>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>

								<?php if ( ! empty( $grouped_blocks['acf'] ) ) : ?>
									<p style="margin-top:12px;"><strong>ACF blocks</strong> (<?php echo esc_html( count( $grouped_blocks['acf'] ) ); ?> registered)</p>
									<label style="display:block; margin-bottom:8px;">
										<input type="checkbox" id="sae-select-all-acf-blocks" />
										<em>Select / deselect all ACF blocks</em>
									</label>
									<?php foreach ( $grouped_blocks['acf'] as $block ) : ?>
										<label style="display:block; margin-bottom:4px;">
											<input
												type="checkbox"
												class="sae-acf-block-allowed"
												name="<?php echo $option_key; ?>[allowed_block_types][]"
												value="<?php echo esc_attr( $block['name'] ); ?>"
												<?php checked( in_array( $block['name'], $allowed_blocks, true ) ); ?>
											/>
											<code><?php echo esc_html( $block['name'] ); ?></code>
											&mdash; <?php echo esc_html( $block['title'] ); ?>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>

								<?php if ( ! empty( $grouped_blocks['manual'] ) ) : ?>
									<p style="margin-top:12px;"><strong>Manual / other blocks</strong></p>
									<?php foreach ( $grouped_blocks['manual'] as $block ) : ?>
										<label style="display:block; margin-bottom:4px;">
											<input
												type="checkbox"
												name="<?php echo $option_key; ?>[allowed_block_types][]"
												value="<?php echo esc_attr( $block['name'] ); ?>"
												<?php checked( in_array( $block['name'], $allowed_blocks, true ) ); ?>
											/>
											<code><?php echo esc_html( $block['name'] ); ?></code>
											&mdash; <?php echo esc_html( $block['title'] ); ?>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</fieldset>

							<p class="description" style="margin-top:8px;">
								Blocks are auto-discovered from the WordPress registry and ACF.
								New blocks appear here automatically when registered.
							</p>

							<details style="margin-top:8px;">
								<summary>Add blocks manually (advanced)</summary>
								<textarea
									name="<?php echo $option_key; ?>[manual_block_types]"
									rows="3"
									class="large-text code"
									placeholder="One block name per line (e.g. acf/custom-card)"
								></textarea>
								<p class="description">Use only when a block is not discoverable yet.</p>
							</details>

							<script>
							(function() {
								var selectAll = document.getElementById('sae-select-all-acf-blocks');
								if (!selectAll) {
									return;
								}
								var acfCheckboxes = document.querySelectorAll('input.sae-acf-block-allowed');
								if (!acfCheckboxes.length) {
									selectAll.disabled = true;
									return;
								}
								var syncSelectAll = function() {
									var allChecked = true;
									acfCheckboxes.forEach(function(checkbox) {
										if (!checkbox.checked) {
											allChecked = false;
										}
									});
									selectAll.checked = allChecked;
								};
								selectAll.addEventListener('change', function() {
									acfCheckboxes.forEach(function(checkbox) {
										checkbox.checked = selectAll.checked;
									});
								});
								acfCheckboxes.forEach(function(checkbox) {
									checkbox.addEventListener('change', syncSelectAll);
								});
								syncSelectAll();
							})();
							</script>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Planner backend', self::TEXT_DOMAIN ); ?></th>
						<td>
							<?php
							$planner_has_client = function_exists( 'wp_ai_client_prompt' );
							$planner_has_connectors = function_exists( 'wp_get_connectors' );
							$selected_provider = self::get_ai_provider();
							?>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]">
								<option value="auto" <?php selected( 'auto', $selected_provider ); ?>><?php esc_html_e( 'Auto (Client when a text_generation model exists, else AI Engine, else OpenAI-compatible)', self::TEXT_DOMAIN ); ?></option>
								<option value="ai_engine" <?php selected( 'ai_engine', $selected_provider ); ?>><?php esc_html_e( 'AI Engine', self::TEXT_DOMAIN ); ?></option>
								<option value="openai" <?php selected( 'openai', $selected_provider ); ?>><?php esc_html_e( 'OpenAI-compatible HTTP (last resort; unused on WordPress 7 when Auto is selected)', self::TEXT_DOMAIN ); ?></option>
							</select>
							<p class="description">
								<?php
								if ( $planner_has_client ) {
									if ( $planner_has_connectors ) {
										esc_html_e( 'WordPress 7 AI Client is available. Auto and AI Engine both use site Connectors (AI Engine can gateway them).', self::TEXT_DOMAIN );
										echo ' ';
										echo '<a href="' . esc_url( admin_url( 'options-general.php?page=connectors' ) ) . '">' . esc_html__( 'Settings → Connectors', self::TEXT_DOMAIN ) . '</a>.';
										echo ' ';
										esc_html_e( 'The OpenAI-compatible HTTP path is unused unless you pin it. Apply is durable plan_id after Approve (console/REST, or the private apply ability).', self::TEXT_DOMAIN );
									} else {
										esc_html_e( 'WordPress 7 AI Client is available. Auto and AI Engine both use the site Client; Connectors were not detected. Apply is durable plan_id after Approve (console/REST, or the private apply ability).', self::TEXT_DOMAIN );
									}
								} else {
									esc_html_e( 'This site is below WordPress 7.0, so Auto falls back to AI Engine, then the OpenAI-compatible HTTP adapter. Upgrade to 7.0 to plan through wp_ai_client_prompt() / Connectors.', self::TEXT_DOMAIN );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'OpenAI-compatible API key', self::TEXT_DOMAIN ); ?></th>
						<td>
							<?php
							$key_source = self::openai_key_source();
							$key_saved = 'option' === $key_source;
							?>
							<?php if ( 'constant' === $key_source ) : ?>
								<p>
									<?php esc_html_e( 'The key is loaded from STRUO_OPENAI_API_KEY (or SAE_OPENAI_API_KEY) in wp-config.php. That is the preferred store. The database copy is unused while the constant is set.', self::TEXT_DOMAIN ); ?>
								</p>
							<?php else : ?>
								<input
									type="password"
									class="regular-text"
									name="<?php echo esc_attr( self::OPENAI_API_KEY_OPTION ); ?>"
									value=""
									autocomplete="new-password"
									placeholder="<?php echo $key_saved ? esc_attr__( 'already saved', self::TEXT_DOMAIN ) : ''; ?>"
								/>
								<p>
									<label>
										<input type="checkbox" name="struo_remove_openai_api_key" value="1" />
										<?php esc_html_e( 'Remove the saved API key', self::TEXT_DOMAIN ); ?>
									</label>
								</p>
								<p class="description">
									<?php esc_html_e( 'Prefer STRUO_OPENAI_API_KEY in wp-config.php. A key saved here is stored in the database as plaintext. Leave the field empty to keep the current key. The key is never shown after save. Empty submit does not delete it — use Remove.', self::TEXT_DOMAIN ); ?>
								</p>
							<?php endif; ?>
							<p class="description">
								<?php esc_html_e( 'HTTP planner requests use a 45s timeout and do not follow redirects, so the bearer key is never sent to a different host.', self::TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Google service account', self::TEXT_DOMAIN ); ?></th>
						<td>
							<?php
							$google_sa_saved = '' !== (string) get_option( self::GOOGLE_SERVICE_ACCOUNT_OPTION, '' );
							?>
							<textarea
								name="<?php echo esc_attr( self::GOOGLE_SERVICE_ACCOUNT_OPTION ); ?>"
								rows="4"
								class="large-text code"
								autocomplete="off"
								placeholder="<?php echo $google_sa_saved ? esc_attr__( 'already saved', self::TEXT_DOMAIN ) : ''; ?>"
							></textarea>
							<p class="description"><?php esc_html_e( 'Paste the service account JSON. Leave blank to keep the saved key. Struo never shows it again. Findings stay empty until this plus the two fields below are set. Struo requests Search Console and Analytics for allowlisted page URLs only.', self::TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'GA4 property ID', self::TEXT_DOMAIN ); ?></th>
						<td>
							<input
								type="text"
								class="regular-text code"
								name="<?php echo esc_attr( self::GA4_PROPERTY_ID_OPTION ); ?>"
								value="<?php echo esc_attr( (string) get_option( self::GA4_PROPERTY_ID_OPTION, '' ) ); ?>"
								placeholder="123456789"
								autocomplete="off"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Search Console site', self::TEXT_DOMAIN ); ?></th>
						<td>
							<input
								type="text"
								class="regular-text code"
								name="<?php echo esc_attr( self::GSC_SITE_URL_OPTION ); ?>"
								value="<?php echo esc_attr( (string) get_option( self::GSC_SITE_URL_OPTION, '' ) ); ?>"
								placeholder="sc-domain:example.com or https://example.com/"
								autocomplete="off"
							/>
							<p class="description"><?php esc_html_e( 'Use sc-domain:example.com for a domain property, or the https URL Search Console lists.', self::TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Daily plan token cap', self::TEXT_DOMAIN ); ?></th>
						<td>
							<input
								type="number"
								class="small-text"
								min="0"
								max="<?php echo esc_attr( (string) self::PLAN_SPEND_CAP_MAX ); ?>"
								step="1"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_plan_daily_token_cap]"
								value="<?php echo esc_attr( (string) absint( $options['ai_plan_daily_token_cap'] ?? 0 ) ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Estimated prompt tokens per calendar day (site timezone). 0 disables the cap. Constants STRUO_PLAN_DAILY_TOKEN_CAP / SAE_PLAN_DAILY_TOKEN_CAP override this field. Over-cap plans return sae_plan_spend_cap (HTTP 429) and do not call the provider.', self::TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">AI name</th>
						<td>
							<input
								type="text"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_display_name]"
								value="<?php echo esc_attr( (string) ( $options['ai_display_name'] ?? self::get_default_ai_display_name() ) ); ?>"
								placeholder="<?php echo esc_attr( self::get_default_ai_display_name() ); ?>"
							/>
							<p class="description">White-label name shown in the console and used for non-theme-specific AI labeling.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Console primary color</th>
						<td>
							<input
								type="color"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[console_primary_color]"
								value="<?php echo esc_attr( (string) ( $options['console_primary_color'] ?? self::get_default_console_primary_color() ) ); ?>"
							/>
							<span class="description" style="margin-left:8px;"><?php echo esc_html( strtoupper( (string) ( $options['console_primary_color'] ?? self::get_default_console_primary_color() ) ) ); ?></span>
							<p class="description">Drives primary console highlights, buttons, and active states.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Console accent color</th>
						<td>
							<input
								type="color"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[console_accent_color]"
								value="<?php echo esc_attr( (string) ( $options['console_accent_color'] ?? self::get_default_console_accent_color() ) ); ?>"
							/>
							<span class="description" style="margin-left:8px;"><?php echo esc_html( strtoupper( (string) ( $options['console_accent_color'] ?? self::get_default_console_accent_color() ) ) ); ?></span>
							<p class="description">Drives secondary gradients, warnings, and accent surfaces in the console.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">AI Persona — preferred tone</th>
						<td>
							<input
								type="text"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_persona_tone]"
								value="<?php echo esc_attr( (string) ( $options['ai_persona_tone'] ?? '' ) ); ?>"
								placeholder="Confident, clear, and direct"
							/>
							<p class="description">Optional. Guides AI output tone across planner, ideation, and rewrites.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">AI Persona — reading level</th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_persona_reading_level]">
								<option value="" <?php selected( '', (string) ( $options['ai_persona_reading_level'] ?? '' ) ); ?>>Default</option>
								<option value="grade_6" <?php selected( 'grade_6', (string) ( $options['ai_persona_reading_level'] ?? '' ) ); ?>>Grade 6</option>
								<option value="grade_8" <?php selected( 'grade_8', (string) ( $options['ai_persona_reading_level'] ?? '' ) ); ?>>Grade 8</option>
								<option value="grade_10" <?php selected( 'grade_10', (string) ( $options['ai_persona_reading_level'] ?? '' ) ); ?>>Grade 10</option>
								<option value="college" <?php selected( 'college', (string) ( $options['ai_persona_reading_level'] ?? '' ) ); ?>>College</option>
								<option value="executive" <?php selected( 'executive', (string) ( $options['ai_persona_reading_level'] ?? '' ) ); ?>>Executive</option>
							</select>
							<p class="description">Optional readability target for generated text.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">AI Persona — forbidden phrases</th>
						<td>
							<textarea
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_persona_forbidden_phrases]"
								rows="4"
								class="large-text code"
								placeholder="best-in-class&#10;industry-leading"
							><?php echo esc_textarea( implode( "\n", is_array( $options['ai_persona_forbidden_phrases'] ?? null ) ? $options['ai_persona_forbidden_phrases'] : [] ) ); ?></textarea>
							<p class="description">Optional. One phrase per line that AI should avoid in outputs.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function register_rest_routes() {
		\Struo\Rest\RouteRegistrar::register( self::REST_NAMESPACE );
	}

	public static function can_read_catalog() {
		return Struo_Authority::authorize( 'get-block-catalog' );
	}

	public static function can_read_console_status() {
		return Struo_Authority::authorize( 'console-status' );
	}

	public static function can_list_findings() {
		return Struo_Authority::authorize( 'list-findings' );
	}

	public static function can_get_finding() {
		return Struo_Authority::authorize( 'get-finding' );
	}

	public static function can_struo_plan() {
		return Struo_Authority::authorize( 'plan-block-change' );
	}

	public static function object_disclose_post( $post_id ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', [ 'status' => rest_authorization_required_code() ] );
		}

		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to read this post.', [ 'status' => 403 ] );
		}

		$allowed_posts = self::get_allowed_post_ids();
		if ( empty( $allowed_posts ) || ! in_array( $post_id, $allowed_posts, true ) ) {
			return new WP_Error( 'sae_post_not_allowed', 'Post ID is not allowlisted.', [ 'status' => 403 ] );
		}

		return true;
	}

	public static function object_write_post( $post_id ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', [ 'status' => rest_authorization_required_code() ] );
		}

		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}

		return self::ensure_write_allowed( $post_id, false );
	}

	public static function object_review_plan( WP_REST_Request $request ) {
		return self::can_review_agent_plan_request( $request );
	}

	public static function object_create_post_type( $post_type ) {
		return self::can_user_create_post_type( $post_type );
	}

	private static function user_can_disclose_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$allowed_posts = self::get_allowed_post_ids();

		return ! empty( $allowed_posts ) && in_array( $post_id, $allowed_posts, true );
	}

	/**
	 * Fail closed on named post IDs before RAG or planner work.
	 * Create ignores leftover post_id. Phrase-only bundles have no
	 * explicit IDs. Any named ID the caller cannot disclose is 403.
	 *
	 * @param mixed $explicit_post_id Parsed plan/payload/url post_id.
	 * @return true|WP_Error
	 */
	private static function assert_explicit_plan_targets_disclosable( array $payload, array $plan, $explicit_post_id, $request_text, $intent ) {
		if ( 'create' === sanitize_key( (string) $intent ) ) {
			return true;
		}

		$ids = self::unique_positive_ids( $payload['post_ids'] ?? [] );
		$single = absint( $explicit_post_id );
		if ( $single <= 0 ) {
			$single = absint( $plan['post_id'] ?? ( $payload['post_id'] ?? 0 ) );
		}
		if ( $single > 0 && ! in_array( $single, $ids, true ) ) {
			$ids[] = $single;
		}
		if ( empty( $ids ) ) {
			return true;
		}

		unset( $request_text );
		$allowed_posts = self::get_allowed_post_ids();
		foreach ( $ids as $id ) {
			if ( empty( $allowed_posts ) || ! in_array( $id, $allowed_posts, true ) ) {
				return new WP_Error(
					'sae_plan_post_not_allowed',
					'Resolved post_id is not allowlisted.',
					[ 'status' => 403, 'post_id' => $id ]
				);
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error(
					'sae_insufficient_permissions',
					'Insufficient permissions to plan this post.',
					[ 'status' => 403, 'post_id' => $id ]
				);
			}
		}

		return true;
	}

	private static function note_eval_disclosure_spend( $kind ) {
		if ( ! defined( 'STRUO_EVAL_HARNESS' ) || ! STRUO_EVAL_HARNESS ) {
			return;
		}
		if ( ! isset( $GLOBALS['struo_eval_disclosure_spend'] ) || ! is_array( $GLOBALS['struo_eval_disclosure_spend'] ) ) {
			$GLOBALS['struo_eval_disclosure_spend'] = [
				'rag' => 0,
				'provider' => 0,
			];
		}
		$kind = 'rag' === $kind ? 'rag' : 'provider';
		$GLOBALS['struo_eval_disclosure_spend'][ $kind ] = absint( $GLOBALS['struo_eval_disclosure_spend'][ $kind ] ?? 0 ) + 1;
	}

	public static function can_struo_approve( WP_REST_Request $request ) {
		return Struo_Authority::authorize( 'approve-agent-plan', [ 'request' => $request ] );
	}

	public static function can_struo_apply( WP_REST_Request $request, $door = 'rest' ) {
		return Struo_Authority::authorize( 'apply-agent-plan', [ 'request' => $request ], $door );
	}

	public static function can_rollback_agent_plan( WP_REST_Request $request ) {
		$record = self::get_durable_plan_record_unchecked( (string) $request['id'] );
		$post_id = is_array( $record ) ? absint( $record['post_id'] ?? 0 ) : 0;

		return Struo_Authority::authorize(
			'rollback-agent-plan',
			[
				'post_id' => $post_id,
				'request' => $request,
			]
		);
	}

	private static function can_review_agent_plan_request( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', [ 'status' => rest_authorization_required_code() ] );
		}

		$record = self::get_durable_plan_record_unchecked( (string) $request['id'] );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( ! self::user_can_review_agent_plan( $record ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to review this plan.', [ 'status' => 403 ] );
		}

		return true;
	}

	public static function can_read_post( WP_REST_Request $request ) {
		return Struo_Authority::authorize(
			'get-post-blocks',
			[
				'post_id' => self::request_post_id( $request ),
				'request' => $request,
			]
		);
	}

	public static function can_write_post( WP_REST_Request $request ) {
		return Struo_Authority::authorize(
			'preview-update',
			[
				'post_id' => self::request_post_id( $request ),
				'request' => $request,
			]
		);
	}

	public static function can_plan_create_page( WP_REST_Request $request ) {
		$permission = Struo_Authority::authorize( 'plan-create-page' );
		if ( true !== $permission ) {
			return $permission;
		}

		$payload = self::get_request_payload( $request );
		$outline = self::normalize_create_outline( $payload['outline'] ?? null );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}
		$template_key = self::normalize_create_template_key( $payload['template'] ?? '' );
		$request_post_type = self::normalize_create_post_type( $payload['post_type'] ?? '' );
		if ( ! empty( $outline ) ) {
			if ( '' === $request_post_type ) {
				$request_post_type = 'page';
			}
			return self::can_user_create_post_type( $request_post_type );
		}
		if ( '' === $template_key ) {
			if ( 'post' === $request_post_type ) {
				return self::can_user_create_post_type( 'post' );
			}
			return self::can_user_create_post_type( '' === $request_post_type ? 'page' : $request_post_type );
		}

		$template = self::get_page_templates()[ $template_key ] ?? null;
		if ( ! is_array( $template ) ) {
			return new WP_Error( 'sae_create_template_invalid', 'Template is not valid.', [ 'status' => 400 ] );
		}

		$post_type = self::normalize_create_post_type( $template['post_type'] ?? 'page' );
		if ( '' === $post_type ) {
			return new WP_Error( 'sae_create_post_type_invalid', 'Template post_type is invalid.', [ 'status' => 400 ] );
		}
		if ( '' !== $request_post_type && $request_post_type !== $post_type ) {
			return new WP_Error( 'sae_create_template_post_type_mismatch', 'Template post_type does not match requested post_type.', [ 'status' => 400 ] );
		}

		return self::can_user_create_post_type( $post_type );
	}

	public static function can_apply_create_page( WP_REST_Request $request ) {
		$permission = Struo_Authority::authorize( 'apply-create-page' );
		if ( true !== $permission ) {
			return $permission;
		}

		$payload = self::get_request_payload( $request );
		$idempotency_key = self::normalize_idempotency_key( $payload['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}
		if ( '' === $idempotency_key ) {
			return true;
		}

		$plan = self::get_create_plan_snapshot( $idempotency_key );
		if ( ! is_array( $plan ) ) {
			return true;
		}

		$post_type = self::normalize_create_post_type( $plan['post_type'] ?? 'page' );
		if ( '' === $post_type ) {
			return new WP_Error( 'sae_create_post_type_invalid', 'Create plan post_type is invalid.', [ 'status' => 400 ] );
		}

		return self::can_user_create_post_type( $post_type );
	}

	public static function can_save_created_template( WP_REST_Request $request ) {
		$permission = Struo_Authority::authorize( 'save-created-template' );
		if ( true !== $permission ) {
			return $permission;
		}

		$idempotency_key = self::normalize_idempotency_key( $request['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}
		if ( '' === $idempotency_key ) {
			return new WP_Error( 'sae_invalid_idempotency_key', 'idempotency_key is required.', [ 'status' => 400 ] );
		}

		return true;
	}

	public static function can_manage_template_registry() {
		return Struo_Authority::authorize( 'list-templates' );
	}

	public static function can_promote_page_template( WP_REST_Request $request ) {
		$payload = self::get_request_payload( $request );
		$post_id = absint( $payload['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'sae_post_id_required', 'post_id is required.', [ 'status' => 400 ] );
		}

		return Struo_Authority::authorize( 'promote-page', [ 'post_id' => $post_id ] );
	}

	public static function can_promote_pattern_section( WP_REST_Request $request ) {
		$payload = self::get_request_payload( $request );
		$post_id = absint( $payload['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'sae_post_id_required', 'post_id is required.', [ 'status' => 400 ] );
		}

		$block_index = intval( $payload['block_index'] ?? -1 );
		if ( $block_index < 0 ) {
			return new WP_Error( 'sae_block_index_required', 'block_index is required.', [ 'status' => 400 ] );
		}

		return Struo_Authority::authorize( 'promote-section', [ 'post_id' => $post_id ] );
	}

	private static function validate_template_source_post_access( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'sae_post_id_required', 'post_id is required.', [ 'status' => 400 ] );
		}

		$allowed_posts = self::get_allowed_post_ids();
		if ( empty( $allowed_posts ) || ! in_array( $post_id, $allowed_posts, true ) ) {
			return new WP_Error( 'sae_post_not_allowed', 'Post ID is not allowlisted.', [ 'status' => 403 ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to read this post.', [ 'status' => 403 ] );
		}

		return true;
	}

	private static function can_user_create_post_type( $post_type ) {
		$post_type = self::normalize_create_post_type( $post_type );
		if ( '' === $post_type ) {
			return new WP_Error( 'sae_create_post_type_invalid', 'Post type is invalid.', [ 'status' => 400 ] );
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! is_object( $post_type_object ) || empty( $post_type_object->cap ) || empty( $post_type_object->cap->create_posts ) ) {
			return new WP_Error( 'sae_create_post_type_unavailable', 'Post type cannot be created by this route.', [ 'status' => 400 ] );
		}

		$create_cap = sanitize_key( (string) $post_type_object->cap->create_posts );
		if ( '' === $create_cap || ! current_user_can( $create_cap ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to create this post type.', [ 'status' => 403 ] );
		}

		return true;
	}

	public static function can_manage_console_settings() {
		return Struo_Authority::authorize( 'console-kill-switch' );
	}

	public static function can_read_console_audit() {
		return Struo_Authority::authorize( 'console-audit' );
	}

	public static function get_allowed_post_ids() {
		$options = self::get_options();
		$manual_ids = array_values( array_filter( array_map( 'absint', $options['allowed_post_ids'] ?? [] ) ) );
		$dynamic_ids = self::get_dynamic_allowlisted_post_ids();
		if ( empty( $dynamic_ids ) ) {
			return $manual_ids;
		}
		return array_values( array_unique( array_merge( $manual_ids, $dynamic_ids ) ) );
	}

	private static function dynamic_allowlist_cache_key() {
		return 'dynamic_post_ids_' . (int) get_current_blog_id();
	}

	public static function invalidate_dynamic_allowlist_cache() {
		self::$dynamic_post_ids_cache = null;
		wp_cache_delete( self::dynamic_allowlist_cache_key(), 'struo' );
	}

	public static function maybe_invalidate_dynamic_allowlist_for_option( $option ) {
		if ( self::OPTION_KEY === (string) $option ) {
			self::invalidate_dynamic_allowlist_cache();
		}
	}

	private static function get_dynamic_allowlisted_post_ids() {
		if ( null !== self::$dynamic_post_ids_cache ) {
			return self::$dynamic_post_ids_cache;
		}

		$cached = wp_cache_get( self::dynamic_allowlist_cache_key(), 'struo' );
		if ( is_array( $cached ) ) {
			self::$dynamic_post_ids_cache = array_values( array_unique( array_filter( array_map( 'absint', $cached ) ) ) );
			return self::$dynamic_post_ids_cache;
		}

		$registry = self::get_content_type_registry();
		$discoverable_types = self::get_discoverable_content_types();
		$dynamic_ids = [];

		foreach ( $registry as $type_name => $settings ) {
			$type_name = sanitize_key( (string) $type_name );
			if ( '' === $type_name || ! isset( $discoverable_types[ $type_name ] ) ) {
				continue;
			}
			if ( empty( $discoverable_types[ $type_name ]['has_blocks'] ) ) {
				continue;
			}
			if ( empty( $settings['enabled'] ) ) {
				continue;
			}
			$discovery = sanitize_key( (string) ( $settings['discovery'] ?? 'manual' ) );
			if ( 'manual' === $discovery ) {
				continue;
			}
			$limit = absint( $settings['limit'] ?? self::CPT_ALLOWLIST_DEFAULT_LIMIT );
			if ( $limit <= 0 ) {
				$limit = self::CPT_ALLOWLIST_DEFAULT_LIMIT;
			}
			$limit = min( self::CPT_ALLOWLIST_MAX_LIMIT, $limit );

			$posts = get_posts(
				[
					'post_type' => $type_name,
					'post_status' => 'publish',
					'posts_per_page' => $limit,
					'orderby' => 'modified',
					'order' => 'DESC',
					'fields' => 'ids',
					'no_found_rows' => true,
				]
			);
			if ( is_array( $posts ) ) {
				$dynamic_ids = array_merge( $dynamic_ids, $posts );
			}
		}

		self::$dynamic_post_ids_cache = array_values( array_unique( array_filter( array_map( 'absint', $dynamic_ids ) ) ) );
		wp_cache_set( self::dynamic_allowlist_cache_key(), self::$dynamic_post_ids_cache, 'struo', 60 );
		return self::$dynamic_post_ids_cache;
	}

	public static function get_allowed_block_types() {
		if ( null !== self::$allowed_blocks_cache ) {
			return self::$allowed_blocks_cache;
		}

		$options = self::get_options();
		$configured = $options['allowed_block_types'] ?? [];
		self::$allowed_blocks_cache = self::normalize_block_names(
			array_merge(
				self::get_builtin_core_block_types(),
				is_array( $configured ) ? $configured : self::sanitize_list( $configured )
			)
		);

		return self::$allowed_blocks_cache;
	}

	private static function get_parsed_blocks( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! self::user_can_disclose_post( $post_id ) ) {
			return [];
		}

		$post_modified_gmt = (string) get_post_field( 'post_modified_gmt', $post_id );
		$post_modified = '' !== $post_modified_gmt ? $post_modified_gmt : (string) get_post_field( 'post_modified', $post_id );
		$cache_key = sprintf( '%d:%s', $post_id, $post_modified );
		if ( ! isset( self::$parsed_blocks_cache[ $cache_key ] ) ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			self::$parsed_blocks_cache[ $cache_key ] = parse_blocks( $content );
		}

		return self::$parsed_blocks_cache[ $cache_key ];
	}

	private static function invalidate_parsed_blocks_cache( $post_id = 0 ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			self::$parsed_blocks_cache = [];
			return;
		}

		$prefix = $post_id . ':';
		foreach ( array_keys( self::$parsed_blocks_cache ) as $cache_key ) {
			if ( 0 === strpos( $cache_key, $prefix ) ) {
				unset( self::$parsed_blocks_cache[ $cache_key ] );
			}
		}
	}

	private static function get_manifest_file_path() {
		return trailingslashit( plugin_dir_path( __FILE__ ) ) . self::MANIFEST_FILE;
	}

	private static function has_manifest_file() {
		if ( null !== self::$manifest_status ) {
			return self::$manifest_status;
		}
		self::$manifest_status = is_readable( self::get_manifest_file_path() );
		return self::$manifest_status;
	}

	private static function get_block_manifest() {
		if ( null !== self::$manifest_cache ) {
			return self::$manifest_cache;
		}

		$manifest_path = self::get_manifest_file_path();
		$manifest_version = is_readable( $manifest_path ) ? (string) filemtime( $manifest_path ) : 'missing';
		$cache_key = 'manifest:' . md5( $manifest_path . '|' . $manifest_version );
		$cached_manifest = wp_cache_get( $cache_key, 'struo' );
		if ( is_array( $cached_manifest ) ) {
			self::$manifest_cache = $cached_manifest;
			return self::$manifest_cache;
		}

		self::$manifest_cache = [];
		if ( ! self::has_manifest_file() ) {
			return self::$manifest_cache;
		}

		$contents = file_get_contents( $manifest_path );
		if ( false === $contents || '' === $contents ) {
			return self::$manifest_cache;
		}

		$decoded = json_decode( $contents, true );
		if ( ! is_array( $decoded ) ) {
			return self::$manifest_cache;
		}

		$raw_blocks = $decoded['blocks'] ?? $decoded;
		if ( ! is_array( $raw_blocks ) ) {
			return self::$manifest_cache;
		}

		foreach ( $raw_blocks as $raw_block_name => $raw_policy ) {
			$block_name = self::normalize_block_name( $raw_block_name );
			if ( empty( $block_name ) ) {
				continue;
			}
			if ( ! is_array( $raw_policy ) ) {
				continue;
			}
			self::$manifest_cache[ $block_name ] = self::normalize_manifest_policy( $raw_policy );
		}

		wp_cache_set( $cache_key, self::$manifest_cache, 'struo', HOUR_IN_SECONDS );

		return self::$manifest_cache;
	}

	private static function normalize_manifest_policy( array $policy ) {
		$normalized = [
			'purpose' => sanitize_text_field( (string) ( $policy['purpose'] ?? '' ) ),
			'allowed_ops' => [ 'read', 'insert', 'update', 'remove' ],
			'max_per_page' => null,
			'allowed_parents' => null,
			'safe_defaults' => [],
			'required_on_insert' => [],
			'required_repeater_subfields' => [],
			'locked_fields' => [],
			'field_aliases' => [],
			'repeater_aliases' => [],
		];

		$allowed_ops = self::sanitize_list( $policy['allowed_ops'] ?? [] );
		$allowed_ops = array_values(
			array_intersect(
				array_map( 'sanitize_key', $allowed_ops ),
				[ 'read', 'insert', 'update', 'remove' ]
			)
		);
		if ( ! empty( $allowed_ops ) ) {
			$normalized['allowed_ops'] = array_values( array_unique( $allowed_ops ) );
		}

		if ( isset( $policy['max_per_page'] ) && '' !== $policy['max_per_page'] && null !== $policy['max_per_page'] ) {
			$max = absint( $policy['max_per_page'] );
			$normalized['max_per_page'] = $max > 0 ? $max : null;
		}

		if ( array_key_exists( 'allowed_parents', $policy ) ) {
			if ( is_array( $policy['allowed_parents'] ) ) {
				$parents = [];
				foreach ( self::sanitize_list( $policy['allowed_parents'] ) as $parent_name ) {
					if ( 'root' === $parent_name ) {
						$parents[] = 'root';
						continue;
					}

					$normalized_parent = self::normalize_block_name( $parent_name );
					if ( ! empty( $normalized_parent ) ) {
						$parents[] = $normalized_parent;
					}
				}
				$normalized['allowed_parents'] = array_values( array_unique( $parents ) );
			} else {
				$normalized['allowed_parents'] = null;
			}
		}

		if ( isset( $policy['safe_defaults'] ) && is_array( $policy['safe_defaults'] ) ) {
			$normalized['safe_defaults'] = $policy['safe_defaults'];
		}

		$required_on_insert = array_filter(
			array_map( 'sanitize_key', self::sanitize_list( $policy['required_on_insert'] ?? [] ) )
		);
		if ( ! empty( $required_on_insert ) ) {
			$normalized['required_on_insert'] = array_values( array_unique( $required_on_insert ) );
		}

		$normalized['required_repeater_subfields'] = self::normalize_required_repeater_subfields(
			$policy['required_repeater_subfields'] ?? []
		);
		$locked_fields = array_filter(
			array_map( 'sanitize_key', self::sanitize_list( $policy['locked_fields'] ?? [] ) )
		);
		if ( ! empty( $locked_fields ) ) {
			$normalized['locked_fields'] = array_values( array_unique( $locked_fields ) );
		}
		$normalized['field_aliases'] = self::normalize_alias_map( $policy['field_aliases'] ?? [] );
		$normalized['repeater_aliases'] = self::normalize_repeater_aliases( $policy['repeater_aliases'] ?? [] );

		return $normalized;
	}

	private static function normalize_alias_map( $alias_map ) {
		$normalized = [];
		if ( ! is_array( $alias_map ) ) {
			return $normalized;
		}

		foreach ( $alias_map as $alias => $canonical ) {
			$alias_name = sanitize_key( (string) $alias );
			$canonical_name = sanitize_key( (string) $canonical );
			if ( empty( $alias_name ) || empty( $canonical_name ) || $alias_name === $canonical_name ) {
				continue;
			}
			$normalized[ $alias_name ] = $canonical_name;
		}

		return $normalized;
	}

	private static function normalize_repeater_aliases( $repeater_aliases ) {
		$normalized = [];
		if ( ! is_array( $repeater_aliases ) ) {
			return $normalized;
		}

		foreach ( $repeater_aliases as $repeater_name => $alias_map ) {
			$repeater_name = sanitize_key( (string) $repeater_name );
			if ( empty( $repeater_name ) || ! is_array( $alias_map ) ) {
				continue;
			}

			$normalized_map = self::normalize_alias_map( $alias_map );
			if ( ! empty( $normalized_map ) ) {
				$normalized[ $repeater_name ] = $normalized_map;
			}
		}

		return $normalized;
	}

	private static function normalize_required_repeater_subfields( $required_repeater_subfields ) {
		$normalized = [];
		if ( ! is_array( $required_repeater_subfields ) ) {
			return $normalized;
		}

		foreach ( $required_repeater_subfields as $repeater_name => $required_fields ) {
			$repeater_name = sanitize_key( (string) $repeater_name );
			if ( empty( $repeater_name ) ) {
				continue;
			}

			$required_list = array_filter(
				array_map( 'sanitize_key', self::sanitize_list( $required_fields ) )
			);
			if ( ! empty( $required_list ) ) {
				$normalized[ $repeater_name ] = array_values( array_unique( $required_list ) );
			}
		}

		return $normalized;
	}

	private static function get_manifest_policy( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );
		if ( empty( $block_name ) ) {
			return null;
		}

		$manifest = self::get_block_manifest();
		if ( ! isset( $manifest[ $block_name ] ) || ! is_array( $manifest[ $block_name ] ) ) {
			return null;
		}

		return $manifest[ $block_name ];
	}

	private static function policy_allows_operation( $policy, $operation ) {
		if ( ! is_array( $policy ) ) {
			return true;
		}
		$allowed_ops = $policy['allowed_ops'] ?? [ 'read', 'insert', 'update', 'remove' ];
		if ( ! is_array( $allowed_ops ) ) {
			return true;
		}
		return in_array( sanitize_key( (string) $operation ), $allowed_ops, true );
	}

	private static function policy_allows_parent( $policy, $parent_block_name ) {
		if ( ! is_array( $policy ) ) {
			return true;
		}
		if ( ! array_key_exists( 'allowed_parents', $policy ) || null === $policy['allowed_parents'] ) {
			return true;
		}
		$allowed_parents = is_array( $policy['allowed_parents'] ) ? $policy['allowed_parents'] : [];
		if ( empty( $allowed_parents ) ) {
			return false;
		}
		if ( 'root' === $parent_block_name ) {
			return in_array( 'root', $allowed_parents, true );
		}
		return in_array( $parent_block_name, $allowed_parents, true );
	}

	private static function get_parent_block_name( array $blocks, array $parent_path ) {
		if ( empty( $parent_path ) ) {
			return 'root';
		}

		$parent = self::get_block_by_path( $blocks, $parent_path );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}

		return sanitize_text_field( (string) ( $parent['blockName'] ?? '' ) );
	}

	private static function count_blocks_by_name( array $blocks, $block_name ) {
		$count = 0;
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				$count++;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$count += self::count_blocks_by_name( $block['innerBlocks'], $block_name );
			}
		}
		return $count;
	}

	private static function apply_alias_map_to_row( array $row, array $alias_map ) {
		foreach ( $alias_map as $alias => $canonical ) {
			if ( ! array_key_exists( $alias, $row ) ) {
				continue;
			}
			if ( ! array_key_exists( $canonical, $row ) ) {
				$row[ $canonical ] = $row[ $alias ];
			}
			unset( $row[ $alias ] );
		}
		return $row;
	}

	private static function apply_manifest_aliases( array $fields, $policy ) {
		if ( ! is_array( $policy ) ) {
			return $fields;
		}

		$field_aliases = is_array( $policy['field_aliases'] ?? null ) ? $policy['field_aliases'] : [];
		if ( ! empty( $field_aliases ) ) {
			$fields = self::apply_alias_map_to_row( $fields, $field_aliases );
		}

		$repeater_aliases = is_array( $policy['repeater_aliases'] ?? null ) ? $policy['repeater_aliases'] : [];
		if ( empty( $repeater_aliases ) ) {
			return $fields;
		}

		foreach ( $repeater_aliases as $repeater_name => $alias_map ) {
			if ( ! array_key_exists( $repeater_name, $fields ) || ! is_array( $fields[ $repeater_name ] ) ) {
				continue;
			}
			if ( ! is_array( $alias_map ) || empty( $alias_map ) ) {
				continue;
			}

			$rows = [];
			foreach ( $fields[ $repeater_name ] as $index => $row ) {
				if ( ! is_array( $row ) ) {
					$rows[ $index ] = $row;
					continue;
				}
				$rows[ $index ] = self::apply_alias_map_to_row( $row, $alias_map );
			}
			$fields[ $repeater_name ] = $rows;
		}

		return $fields;
	}

	private static function is_empty_contract_value( $value ) {
		if ( null === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}

		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return false;
	}

	private static function validate_manifest_field_contracts( array $fields, $policy, $is_insert, array &$errors ) {
		if ( ! is_array( $policy ) ) {
			return true;
		}

		if ( $is_insert ) {
			$required_on_insert = is_array( $policy['required_on_insert'] ?? null ) ? $policy['required_on_insert'] : [];
			foreach ( $required_on_insert as $required_field ) {
				if ( ! array_key_exists( $required_field, $fields ) || self::is_empty_contract_value( $fields[ $required_field ] ) ) {
					$errors[] = sprintf( 'Field contract requires "%s" on insert.', $required_field );
				}
			}
		}

		$required_repeater_subfields = is_array( $policy['required_repeater_subfields'] ?? null ) ? $policy['required_repeater_subfields'] : [];
		foreach ( $required_repeater_subfields as $repeater_name => $required_subfields ) {
			if ( ! array_key_exists( $repeater_name, $fields ) ) {
				if ( $is_insert ) {
					$errors[] = sprintf( 'Field contract requires repeater "%s" on insert.', $repeater_name );
				}
				continue;
			}

			$rows = $fields[ $repeater_name ];
			if ( ! is_array( $rows ) || empty( $rows ) ) {
				$errors[] = sprintf( 'Field "%s" must contain at least one row.', $repeater_name );
				continue;
			}

			foreach ( $rows as $row_index => $row ) {
				if ( ! is_array( $row ) ) {
					$errors[] = sprintf( 'Field "%s" row %d must be an object.', $repeater_name, $row_index );
					continue;
				}
				foreach ( $required_subfields as $subfield_name ) {
					if ( ! array_key_exists( $subfield_name, $row ) || self::is_empty_contract_value( $row[ $subfield_name ] ) ) {
						$errors[] = sprintf( 'Field "%s" row %d requires "%s".', $repeater_name, $row_index, $subfield_name );
					}
				}
			}
		}

		return empty( $errors );
	}

	private static function validate_locked_fields( array $fields, $policy, array &$errors ) {
		if ( ! is_array( $policy ) ) {
			return true;
		}

		$locked_fields = is_array( $policy['locked_fields'] ?? null ) ? $policy['locked_fields'] : [];
		if ( empty( $locked_fields ) ) {
			return true;
		}

		foreach ( $locked_fields as $locked_field ) {
			if ( array_key_exists( $locked_field, $fields ) ) {
				$errors[] = sprintf( 'Field "%s" is locked by policy.', $locked_field );
			}
		}

		return empty( $errors );
	}

	public static function get_block_catalog() {
		$allowed_blocks = self::get_allowed_block_types();
		$manifest_enabled = self::has_manifest_file();
		$manifest = self::get_block_manifest();

		if ( empty( $allowed_blocks ) ) {
			self::audit_log( 'catalog_empty', [ 'reason' => 'no allowed blocks configured' ] );
			return [
				'generated_at' => current_time( 'mysql' ),
				'count' => 0,
				'allowed_blocks' => [],
				'manifest_enabled' => $manifest_enabled,
				'warnings' => [ 'No allowed blocks configured.' ],
			];
		}

		$acf_available = function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' );

		$catalog = [];
		$warnings = [];
		foreach ( $allowed_blocks as $block_name ) {
			$block_title = self::get_core_block_label( $block_name );
			$fields = [];

			if ( self::is_core_block( $block_name ) ) {
				$fields = array_values( self::get_core_block_schema( $block_name ) );
			} elseif ( $acf_available ) {
				$block_title = $block_name;
				if ( function_exists( 'acf_get_block_type' ) ) {
					$block_type = acf_get_block_type( $block_name );
					if ( ! $block_type && 0 === strpos( $block_name, 'acf/' ) ) {
						$block_type = acf_get_block_type( substr( $block_name, 4 ) );
					}
					if ( $block_type && ! empty( $block_type['title'] ) ) {
						$block_title = $block_type['title'];
					}
				}

				$field_groups = acf_get_field_groups( [ 'block' => $block_name ] );
				foreach ( $field_groups as $group ) {
					$group_fields = acf_get_fields( $group['key'] );
					if ( is_array( $group_fields ) ) {
						foreach ( $group_fields as $field ) {
							$fields[] = self::normalize_acf_field( $field );
						}
					}
				}
			} else {
				$warnings[] = sprintf( 'ACF schema unavailable for "%s".', $block_name );
			}

			$catalog[] = [
				'block_name' => $block_name,
				'label' => $block_title,
				'fields' => $fields,
				'policy' => $manifest[ $block_name ] ?? null,
				'targeting_hint' => 'Prefer anchor or block_id for stable targeting; index_path may shift after inserts/removes.',
			];
		}

		self::audit_log( 'catalog_read', [ 'count' => count( $catalog ) ] );

		return [
			'generated_at' => current_time( 'mysql' ),
			'count' => count( $catalog ),
			'manifest_enabled' => $manifest_enabled,
			'allowed_blocks' => $catalog,
			'warnings' => array_values( array_unique( $warnings ) ),
		];
	}

	private static function is_core_block( $block_name ) {
		return 0 === strpos( (string) $block_name, 'core/' );
	}

	private static function get_core_block_label( $block_name ) {
		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			$registry = WP_Block_Type_Registry::get_instance();
			$block_type = $registry->get_registered( $block_name );
			if ( $block_type && ! empty( $block_type->title ) ) {
				return (string) $block_type->title;
			}
		}

		$slug = preg_replace( '#^core/#', '', (string) $block_name );
		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	private static function get_core_block_schema( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );

		switch ( $block_name ) {
			case 'core/paragraph':
				return [
					'content' => [
						'key' => '',
						'name' => 'content',
						'label' => 'Content',
						'type' => 'wysiwyg',
						'required' => true,
						'instructions' => 'Paragraph content (rich text allowed).',
					],
				];
			case 'core/heading':
				return [
					'content' => [
						'key' => '',
						'name' => 'content',
						'label' => 'Content',
						'type' => 'wysiwyg',
						'required' => true,
						'instructions' => 'Heading content (rich text allowed).',
					],
					'level' => [
						'key' => '',
						'name' => 'level',
						'label' => 'Level',
						'type' => 'number',
						'required' => false,
						'instructions' => 'Heading level from 1 to 6.',
						],
					];
				case 'core/list':
					return [
						'items' => [
							'key' => '',
							'name' => 'items',
							'label' => 'Items',
							'type' => 'repeater',
							'required' => true,
							'instructions' => 'Ordered list items. Each row becomes one bullet or numbered item.',
							'sub_fields' => [
								[
									'key' => '',
									'name' => 'content',
									'label' => 'Content',
									'type' => 'wysiwyg',
									'required' => true,
									'instructions' => 'List item content.',
								],
							],
						],
						'ordered' => [
							'key' => '',
							'name' => 'ordered',
							'label' => 'Ordered',
							'type' => 'true_false',
							'required' => false,
							'instructions' => 'Use a numbered list instead of bullets.',
						],
					];
				case 'core/image':
					return [
						'id' => [
							'key' => '',
							'name' => 'id',
							'label' => 'Attachment ID',
							'type' => 'number',
							'required' => false,
							'instructions' => 'Optional attachment ID for a media-library image.',
						],
						'url' => [
							'key' => '',
							'name' => 'url',
							'label' => 'Image URL',
							'type' => 'url',
							'required' => false,
							'instructions' => 'Fallback image URL when no attachment ID is provided.',
						],
						'alt' => [
							'key' => '',
							'name' => 'alt',
							'label' => 'Alt Text',
							'type' => 'text',
							'required' => false,
							'instructions' => 'Accessible alt text for the image.',
						],
						'caption' => [
							'key' => '',
							'name' => 'caption',
							'label' => 'Caption',
							'type' => 'wysiwyg',
							'required' => false,
							'instructions' => 'Optional figure caption.',
						],
						'size_slug' => [
							'key' => '',
							'name' => 'size_slug',
							'label' => 'Size Slug',
							'type' => 'text',
							'required' => false,
							'instructions' => 'Optional image size slug, for example full or large.',
						],
					];
				case 'core/quote':
					return [
						'content' => [
							'key' => '',
							'name' => 'content',
						'label' => 'Quote',
						'type' => 'wysiwyg',
						'required' => true,
						'instructions' => 'Quote content (rich text allowed).',
					],
					'citation' => [
						'key' => '',
						'name' => 'citation',
						'label' => 'Citation',
						'type' => 'text',
						'required' => false,
						'instructions' => 'Optional quote citation.',
						],
					];
				case 'core/button':
					return [
						'text' => [
						'key' => '',
						'name' => 'text',
						'label' => 'Text',
						'type' => 'text',
						'required' => true,
						'instructions' => 'Button label text.',
					],
					'url' => [
						'key' => '',
						'name' => 'url',
						'label' => 'URL',
						'type' => 'url',
						'required' => false,
							'instructions' => 'Destination URL for the button.',
						],
					];
				case 'core/buttons':
					return [
						'buttons' => [
							'key' => '',
							'name' => 'buttons',
							'label' => 'Buttons',
							'type' => 'repeater',
							'required' => true,
							'instructions' => 'One or more buttons in the button group.',
							'sub_fields' => [
								[
									'key' => '',
									'name' => 'text',
									'label' => 'Text',
									'type' => 'text',
									'required' => true,
									'instructions' => 'Button label.',
								],
								[
									'key' => '',
									'name' => 'url',
									'label' => 'URL',
									'type' => 'url',
									'required' => false,
									'instructions' => 'Button destination URL.',
								],
							],
						],
					];
				case 'core/group':
					return [
						'inner_blocks' => [
							'key' => '',
							'name' => 'inner_blocks',
							'label' => 'Inner Blocks',
							'type' => 'blocks',
							'required' => false,
							'instructions' => 'Ordered child blocks inside the group.',
						],
					];
				case 'core/column':
					return [
						'inner_blocks' => [
							'key' => '',
							'name' => 'inner_blocks',
							'label' => 'Inner Blocks',
							'type' => 'blocks',
							'required' => false,
							'instructions' => 'Ordered child blocks inside this column.',
						],
					];
				case 'core/columns':
					return [
						'columns' => [
							'key' => '',
							'name' => 'columns',
							'label' => 'Columns',
							'type' => 'repeater',
							'required' => true,
							'instructions' => 'Each row becomes one column.',
							'sub_fields' => [
								[
									'key' => '',
									'name' => 'inner_blocks',
									'label' => 'Inner Blocks',
									'type' => 'blocks',
									'required' => false,
									'instructions' => 'Ordered child blocks for this column.',
								],
							],
						],
					];
				case 'core/html':
					return [
						'html' => [
							'key' => '',
							'name' => 'html',
						'label' => 'HTML',
						'type' => 'wysiwyg',
						'required' => true,
						'instructions' => 'Raw semantic HTML (sanitized).',
					],
				];
			default:
				return [];
		}
	}

	private static function normalize_acf_field( $field ) {
		if ( ! is_array( $field ) ) {
			return [];
		}

		$normalized = [
			'key' => $field['key'] ?? '',
			'name' => $field['name'] ?? '',
			'label' => $field['label'] ?? '',
			'type' => $field['type'] ?? '',
			'required' => ! empty( $field['required'] ),
			'instructions' => $field['instructions'] ?? '',
		];

		if ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
			$normalized['choices'] = $field['choices'];
		}

		if ( isset( $field['return_format'] ) ) {
			$normalized['return_format'] = $field['return_format'];
		}

		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$normalized['sub_fields'] = array_values(
				array_filter(
					array_map( [ __CLASS__, 'normalize_acf_field' ], $field['sub_fields'] )
				)
			);
		}

		return $normalized;
	}

	public static function get_post_blocks( WP_REST_Request $request ) {
		$post_id = self::request_post_id( $request );
		$permission = self::ensure_read_allowed( $post_id );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'sae_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}

		$blocks = self::get_parsed_blocks( $post_id );

		$summary = self::summarize_blocks( $blocks );
		self::audit_log( 'post_blocks_read', [ 'post_id' => $post_id, 'blocks' => count( $summary ) ] );

		return [
			'post_id' => $post_id,
			'post_title' => get_the_title( $post_id ),
			'post_status' => get_post_status( $post_id ),
			'block_count' => count( $summary ),
			'blocks' => $summary,
		];
	}

	public static function insert_block( WP_REST_Request $request ) {
		$origin = self::request_token_origin( $request );
		$context = self::request_token_context( $request );
		$post_id = self::request_post_id( $request );
		$payload = self::get_request_payload( $request );

		$block_name = self::normalize_block_name( $payload['block_name'] ?? '' );
		if ( empty( $block_name ) ) {
			return new WP_Error( 'sae_missing_block_name', 'block_name is required.', [ 'status' => 400 ] );
		}

		$allowed_blocks = self::get_allowed_block_types();
		if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
			return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
		}

		$fields = $payload['fields'] ?? [];
		if ( ! is_array( $fields ) ) {
			return new WP_Error( 'sae_invalid_fields', 'fields must be an object.', [ 'status' => 400 ] );
		}

		$dry_run = array_key_exists( 'dry_run', $payload ) ? (bool) $payload['dry_run'] : true;
		$confirmation_token = sanitize_text_field( $payload['confirmation_token'] ?? '' );
		$idempotency_key = self::normalize_idempotency_key( $payload['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}
		$position = is_array( $payload['position'] ?? null ) ? $payload['position'] : [];
		$parent_path = is_array( $payload['parent_path'] ?? null ) ? $payload['parent_path'] : [];
		$response_mode = self::resolve_response_mode( $payload );
		$write_context = self::rest_plan_only_context( $dry_run, $origin, $context );

		$result = self::apply_insert( $post_id, $block_name, $fields, $position, $parent_path, $dry_run, $confirmation_token, $idempotency_key, $response_mode, $origin, $write_context );

		return self::maybe_attach_rest_mutation_plan_envelope(
			$origin,
			$dry_run,
			$post_id,
			'insert',
			sprintf( '/wp-json/struo/v1/posts/%d/blocks/insert', $post_id ),
			[
				'block_name' => $block_name,
				'fields' => $fields,
				'position' => $position,
				'parent_path' => $parent_path,
			],
			$result
		);
	}

	public static function update_block( WP_REST_Request $request ) {
		$origin = self::request_token_origin( $request );
		$context = self::request_token_context( $request );
		$post_id = self::request_post_id( $request );
		$payload = self::get_request_payload( $request );

		$target = is_array( $payload['target'] ?? null ) ? $payload['target'] : [];
		if ( empty( $target ) ) {
			return new WP_Error( 'sae_missing_target', 'target is required.', [ 'status' => 400 ] );
		}

		$fields = $payload['fields'] ?? [];
		if ( ! is_array( $fields ) ) {
			return new WP_Error( 'sae_invalid_fields', 'fields must be an object.', [ 'status' => 400 ] );
		}

		$dry_run = array_key_exists( 'dry_run', $payload ) ? (bool) $payload['dry_run'] : true;
		$confirmation_token = sanitize_text_field( $payload['confirmation_token'] ?? '' );
		$idempotency_key = self::normalize_idempotency_key( $payload['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}
		$response_mode = self::resolve_response_mode( $payload );
		$write_context = self::rest_plan_only_context( $dry_run, $origin, $context );

		$result = self::apply_update( $post_id, $target, $fields, $dry_run, $confirmation_token, $idempotency_key, $response_mode, $origin, $write_context );

		return self::maybe_attach_rest_mutation_plan_envelope(
			$origin,
			$dry_run,
			$post_id,
			'update',
			sprintf( '/wp-json/struo/v1/posts/%d/blocks/update', $post_id ),
			[
				'target' => $target,
				'fields' => $fields,
			],
			$result
		);
	}

	public static function remove_block( WP_REST_Request $request ) {
		$origin = self::request_token_origin( $request );
		$context = self::request_token_context( $request );
		$post_id = self::request_post_id( $request );
		$payload = self::get_request_payload( $request );

		$target = is_array( $payload['target'] ?? null ) ? $payload['target'] : [];
		$remove_all = ! empty( $payload['remove_all'] );
		$block_name = self::normalize_block_name( $payload['block_name'] ?? '' );
		$dry_run = array_key_exists( 'dry_run', $payload ) ? (bool) $payload['dry_run'] : true;
		$confirmation_token = sanitize_text_field( $payload['confirmation_token'] ?? '' );
		$idempotency_key = self::normalize_idempotency_key( $payload['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}
		$response_mode = self::resolve_response_mode( $payload );

		if ( $remove_all && empty( $block_name ) ) {
			return new WP_Error( 'sae_missing_block_name', 'block_name is required when remove_all is true.', [ 'status' => 400 ] );
		}

		if ( ! $remove_all && empty( $target ) ) {
			return new WP_Error( 'sae_missing_target', 'target is required unless remove_all is true.', [ 'status' => 400 ] );
		}

		$write_context = self::rest_plan_only_context( $dry_run, $origin, $context );
		$result = self::apply_remove( $post_id, $target, $block_name, $remove_all, $dry_run, $confirmation_token, $idempotency_key, $response_mode, $origin, $write_context );

		return self::maybe_attach_rest_mutation_plan_envelope(
			$origin,
			$dry_run,
			$post_id,
			'remove',
			sprintf( '/wp-json/struo/v1/posts/%d/blocks/remove', $post_id ),
			[
				'target' => $target,
				'block_name' => $block_name,
				'remove_all' => (bool) $remove_all,
			],
			$result
		);
	}

	public static function batch_blocks( WP_REST_Request $request ) {
		$origin = self::request_token_origin( $request );
		$context = self::request_token_context( $request );
		$post_id = self::request_post_id( $request );
		$payload = self::get_request_payload( $request );
		$operations = is_array( $payload['operations'] ?? null ) ? $payload['operations'] : [];
		$bundle_name = self::sanitize_bundle_name( $payload['bundle_name'] ?? '' );
		if ( empty( $operations ) ) {
			return new WP_Error( 'sae_batch_missing_operations', 'operations must be a non-empty array.', [ 'status' => 400 ] );
		}

		if ( count( $operations ) > self::BATCH_MAX_OPERATIONS ) {
			return new WP_Error(
				'sae_batch_too_many_operations',
				sprintf( 'operations cannot exceed %d in a single batch.', self::BATCH_MAX_OPERATIONS ),
				[ 'status' => 400, 'max_operations' => self::BATCH_MAX_OPERATIONS ]
			);
		}

		$dry_run = array_key_exists( 'dry_run', $payload ) ? (bool) $payload['dry_run'] : true;
		$confirmation_token = sanitize_text_field( $payload['confirmation_token'] ?? '' );
		$idempotency_key = self::normalize_idempotency_key( $payload['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}
		$response_mode = self::resolve_response_mode( $payload );
		$write_context = self::rest_plan_only_context( $dry_run, $origin, $context );

		$result = self::apply_batch( $post_id, $operations, $dry_run, $confirmation_token, $idempotency_key, $response_mode, $bundle_name, $origin, $write_context );

		return self::maybe_attach_rest_mutation_plan_envelope(
			$origin,
			$dry_run,
			$post_id,
			'batch',
			sprintf( '/wp-json/struo/v1/posts/%d/blocks/batch', $post_id ),
			[
				'bundle_name' => $bundle_name,
				'operations' => $operations,
			],
			$result,
			'[batch-composer]'
		);
	}

	public static function update_post_field( WP_REST_Request $request ) {
		$origin = self::request_token_origin( $request );
		$context = self::request_token_context( $request );
		$post_id = self::request_post_id( $request );
		$payload = self::get_request_payload( $request );
		$field = self::normalize_cross_field_name( $payload['field'] ?? '' );
		$value = self::normalize_cross_field_value( $field, $payload['value'] ?? '' );
		if ( '' === $field ) {
			return new WP_Error( 'sae_field_not_allowed', 'Field is not allowed for cross-field update.', [ 'status' => 400 ] );
		}
		if ( '' === $value ) {
			return new WP_Error( 'sae_invalid_field_value', 'value is required for cross-field updates.', [ 'status' => 400 ] );
		}

		$dry_run = array_key_exists( 'dry_run', $payload ) ? (bool) $payload['dry_run'] : true;
		$confirmation_token = sanitize_text_field( (string) ( $payload['confirmation_token'] ?? '' ) );
		$idempotency_key = self::normalize_idempotency_key( $payload['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}

		$write_context = self::rest_plan_only_context( $dry_run, $origin, $context );
		$result = self::apply_cross_field_update( $post_id, $field, $value, $dry_run, $confirmation_token, $idempotency_key, $origin, $write_context );

		return self::maybe_attach_rest_mutation_plan_envelope(
			$origin,
			$dry_run,
			$post_id,
			'cross_field',
			sprintf( '/wp-json/struo/v1/posts/%d/fields/update', $post_id ),
			[
				'field' => $field,
				'value' => $value,
			],
			$result
		);
	}

	public static function plan_create_page( WP_REST_Request $request ) {
		$origin = self::request_token_origin( $request );
		$context = self::request_token_context( $request );
		$payload = self::get_request_payload( $request );
		$title = self::normalize_create_title( $payload['title'] ?? '' );
		if ( '' === $title ) {
			return new WP_Error( 'sae_create_title_required', 'title is required.', [ 'status' => 400 ] );
		}

		$description = self::normalize_create_description( $payload['description'] ?? '' );
		$outline = self::normalize_create_outline( $payload['outline'] ?? null );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}

		$template_key = self::normalize_create_template_key( $payload['template'] ?? '' );
		$request_post_type = self::normalize_create_post_type( $payload['post_type'] ?? '' );
		$dynamic_template = null;
		if ( ! empty( $outline ) ) {
			$outline_payload = self::convert_outline_to_plan_create_payload(
				$outline,
				$title,
				$description,
				$request_post_type,
				$template_key
			);
			if ( is_wp_error( $outline_payload ) ) {
				return $outline_payload;
			}

			$template_key = self::normalize_create_template_key( $outline_payload['template'] ?? '' );
			$request_post_type = self::normalize_create_post_type( $outline_payload['post_type'] ?? '' );
			$dynamic_template = is_array( $outline_payload['dynamic_template'] ?? null ) ? $outline_payload['dynamic_template'] : null;
			if ( is_array( $dynamic_template ) ) {
				self::register_dynamic_template( $template_key, $dynamic_template );
			}
		}

		if ( '' === $template_key && 'post' === $request_post_type ) {
			$template_key = 'blog-post';
		}
		if ( '' === $template_key ) {
			return new WP_Error( 'sae_create_template_required', 'template is required.', [ 'status' => 400 ] );
		}

		$templates = self::get_page_templates();
		$template = $templates[ $template_key ] ?? null;
		if ( ! is_array( $template ) ) {
			return new WP_Error( 'sae_create_template_invalid', 'template is not recognized.', [ 'status' => 400 ] );
		}

		$post_type = self::normalize_create_post_type( $template['post_type'] ?? 'page' );
		if ( '' === $post_type ) {
			return new WP_Error( 'sae_create_post_type_invalid', 'Template post_type is invalid.', [ 'status' => 400 ] );
		}
		if ( '' !== $request_post_type && $request_post_type !== $post_type ) {
			return new WP_Error( 'sae_create_template_post_type_mismatch', 'Template post_type does not match requested post_type.', [ 'status' => 400 ] );
		}

		$create_permission = self::ensure_create_allowed( $post_type, false );
		if ( is_wp_error( $create_permission ) ) {
			return $create_permission;
		}

		$selected_sections = self::normalize_create_selected_sections( $payload['selected_sections'] ?? null, $template['blocks'] ?? [] );
		if ( is_wp_error( $selected_sections ) ) {
			return $selected_sections;
		}

		$operations = self::build_create_operations( $template_key, $template, $selected_sections );
		if ( empty( $operations ) ) {
			return new WP_Error( 'sae_create_template_empty', 'No valid operations were generated for this template.', [ 'status' => 400 ] );
		}
		$allowed_blocks = self::get_allowed_block_types();
		foreach ( $operations as $operation_entry ) {
			$operation_block_name = self::normalize_block_name( $operation_entry['block_name'] ?? '' );
			if ( '' === $operation_block_name || empty( $allowed_blocks ) || ! in_array( $operation_block_name, $allowed_blocks, true ) ) {
				return new WP_Error( 'sae_create_template_block_not_allowed', 'Template includes block types that are not allowlisted.', [ 'status' => 403 ] );
			}
		}
		$idempotency_key = wp_generate_uuid4();
		if ( '' === $idempotency_key ) {
			return new WP_Error( 'sae_idempotency_key_unavailable', 'Could not generate idempotency key.', [ 'status' => 500 ] );
		}

		$plan_snapshot = [
			'action' => 'create_page_v2',
			'title' => $title,
			'description' => $description,
			'template' => $template_key,
			'post_type' => $post_type,
			'page_template' => sanitize_text_field( (string) ( $template['page_template'] ?? '' ) ),
			'selected_sections' => $selected_sections,
			'operations' => $operations,
			'outline' => $outline,
			'created_at' => time(),
		];
		if ( is_array( $dynamic_template ) ) {
			$plan_snapshot['dynamic_template'] = $dynamic_template;
		}
		$plan_snapshot = array_merge(
			$plan_snapshot,
			self::build_create_template_meta(
				$template_key,
				$post_type,
				$template,
				[
					'label' => is_array( $dynamic_template ) ? ( $dynamic_template['label'] ?? '' ) : '',
					'description' => is_array( $dynamic_template ) ? ( $dynamic_template['description'] ?? '' ) : '',
				]
			)
		);
		self::store_create_plan_snapshot( $idempotency_key, $plan_snapshot );

		$page_spec = self::compile_page_spec_from_create_snapshot( $plan_snapshot, $idempotency_key );
		if ( is_wp_error( $page_spec ) ) {
			return $page_spec;
		}

		$preview = [
			'intent' => 'create',
			'title' => $title,
			'post_type' => $post_type,
			'template' => $template_key,
			'template_mode' => sanitize_key( (string) ( $plan_snapshot['template_mode'] ?? '' ) ),
			'template_label' => sanitize_text_field( (string) ( $plan_snapshot['template_label'] ?? '' ) ),
			'template_rationale' => sanitize_text_field( (string) ( $plan_snapshot['template_rationale'] ?? '' ) ),
			'description' => $description,
			'outline' => $outline,
			'operations' => is_array( $page_spec['operations'] ?? null ) ? $page_spec['operations'] : $operations,
			'blocks_created' => absint( $page_spec['block_count'] ?? 0 ),
			'blocks_preview' => is_array( $page_spec['blocks_preview'] ?? null ) ? $page_spec['blocks_preview'] : [],
			'content_hash' => (string) ( $page_spec['content_hash'] ?? '' ),
			'idempotency_key' => $idempotency_key,
			'confirmation' => [
				'redeemable' => false,
				'origin' => self::normalize_token_origin( $origin ),
				'message' => 'Plan-only: approve and apply via the durable page_spec envelope.',
			],
		];

		$envelope_response = [
			'request' => '[page-create]',
			'intent' => 'create',
			'post' => [
				'post_id' => 0,
				'post_title' => $title,
				'post_slug' => sanitize_title( $title ),
				'post_url' => '',
			],
			'operation' => 'create',
			'endpoint' => '/wp-json/struo/v1/pages/create',
			'payload' => $page_spec,
			'dry_run' => $preview,
		];

		return self::attach_durable_page_spec_envelope( 'rest', $envelope_response, $preview );
	}

	public static function apply_create_page( WP_REST_Request $request ) {
		return new WP_Error(
			'sae_plan_apply_required',
			'Direct writes require an approved durable plan. Use plan_id → approve → apply.',
			[ 'status' => 403 ]
		);
	}

	/**
	 * Compile a page_spec_v1 artifact during planning (copy + blocks + exact serialized content).
	 *
	 * @return array|WP_Error
	 */
	private static function compile_page_spec_from_create_snapshot( array $plan_snapshot, $idempotency_key ) {
		$idempotency_key = sanitize_text_field( (string) $idempotency_key );
		$title = sanitize_text_field( (string) ( $plan_snapshot['title'] ?? '' ) );
		$post_type = self::normalize_create_post_type( $plan_snapshot['post_type'] ?? 'page' );
		$template_key = self::normalize_create_template_key( $plan_snapshot['template'] ?? '' );
		if ( '' === $title || '' === $post_type || '' === $template_key || '' === $idempotency_key ) {
			return new WP_Error( 'sae_page_spec_incomplete', 'PageSpec compile requires title, post_type, template, and idempotency_key.', [ 'status' => 400 ] );
		}

		$dynamic_template = is_array( $plan_snapshot['dynamic_template'] ?? null ) ? $plan_snapshot['dynamic_template'] : null;
		if ( is_array( $dynamic_template ) ) {
			self::register_dynamic_template( $template_key, $dynamic_template );
		}

		$selected_sections = is_array( $plan_snapshot['selected_sections'] ?? null ) ? $plan_snapshot['selected_sections'] : [];
		$page_copy = self::generate_page_copy(
			$template_key,
			(string) ( $plan_snapshot['description'] ?? '' ),
			$title,
			[
				'selected_sections' => $selected_sections,
				'post_type' => $post_type,
			]
		);
		if ( is_wp_error( $page_copy ) ) {
			// Plan-time compile must still freeze exact content when the AI
			// planner is offline: fall back to template defaults.
			if ( ! in_array( $page_copy->get_error_code(), [ 'sae_copy_generation_unavailable', 'sae_planner_unavailable', 'sae_provider_url_refused', 'sae_provider_key_host_mismatch' ], true ) ) {
				return $page_copy;
			}
			$page_copy = [];
		}

		$built_blocks = self::build_blocks_from_template(
			$template_key,
			is_array( $page_copy ) ? $page_copy : [],
			[
				'selected_sections' => $selected_sections,
			]
		);
		if ( is_wp_error( $built_blocks ) ) {
			return $built_blocks;
		}

		$serialized_content = self::sanitize_generated_post_content( (string) ( $built_blocks['serialized'] ?? '' ) );
		if ( '' === trim( $serialized_content ) ) {
			return new WP_Error( 'sae_create_content_invalid', 'Generated page content is empty after sanitization.', [ 'status' => 500 ] );
		}

		$parsed_blocks = is_array( $built_blocks['blocks'] ?? null ) ? $built_blocks['blocks'] : [];
		$blocks_preview = self::summarize_blocks( $parsed_blocks );

		return [
			'payload_type' => 'page_spec_v1',
			'idempotency_key' => $idempotency_key,
			'title' => $title,
			'post_type' => $post_type,
			'page_template' => sanitize_text_field( (string) ( $plan_snapshot['page_template'] ?? '' ) ),
			'template' => $template_key,
			'template_mode' => sanitize_key( (string) ( $plan_snapshot['template_mode'] ?? '' ) ),
			'template_label' => sanitize_text_field( (string) ( $plan_snapshot['template_label'] ?? '' ) ),
			'template_rationale' => sanitize_text_field( (string) ( $plan_snapshot['template_rationale'] ?? '' ) ),
			'selected_sections' => $selected_sections,
			'serialized_content' => $serialized_content,
			'content_hash' => hash( 'sha256', $serialized_content ),
			'block_count' => absint( $built_blocks['block_count'] ?? count( $parsed_blocks ) ),
			'blocks_preview' => $blocks_preview,
			'operations' => is_array( $built_blocks['operations'] ?? null ) ? $built_blocks['operations'] : [],
		];
	}

	/**
	 * @return array|WP_Error
	 */
	private static function normalize_page_spec_payload( array $payload ) {
		$serialized_content = self::sanitize_generated_post_content( (string) ( $payload['serialized_content'] ?? '' ) );
		if ( '' === trim( $serialized_content ) ) {
			return new WP_Error( 'sae_page_spec_missing_content', 'page_spec_v1 requires serialized_content.', [ 'status' => 400 ] );
		}

		$content_hash = sanitize_text_field( (string) ( $payload['content_hash'] ?? '' ) );
		$computed = hash( 'sha256', $serialized_content );
		if ( '' !== $content_hash && ! hash_equals( $content_hash, $computed ) ) {
			return new WP_Error( 'sae_page_spec_hash_mismatch', 'page_spec content_hash does not match serialized_content.', [ 'status' => 400 ] );
		}

		$title = sanitize_text_field( (string) ( $payload['title'] ?? '' ) );
		$post_type = self::normalize_create_post_type( $payload['post_type'] ?? 'page' );
		if ( '' === $title || '' === $post_type ) {
			return new WP_Error( 'sae_page_spec_incomplete', 'page_spec_v1 requires title and post_type.', [ 'status' => 400 ] );
		}

		return [
			'payload_type' => 'page_spec_v1',
			'idempotency_key' => sanitize_text_field( (string) ( $payload['idempotency_key'] ?? '' ) ),
			'title' => $title,
			'post_type' => $post_type,
			'page_template' => sanitize_text_field( (string) ( $payload['page_template'] ?? '' ) ),
			'template' => self::normalize_create_template_key( $payload['template'] ?? '' ),
			'template_mode' => sanitize_key( (string) ( $payload['template_mode'] ?? '' ) ),
			'template_label' => sanitize_text_field( (string) ( $payload['template_label'] ?? '' ) ),
			'template_rationale' => sanitize_text_field( (string) ( $payload['template_rationale'] ?? '' ) ),
			'selected_sections' => is_array( $payload['selected_sections'] ?? null ) ? $payload['selected_sections'] : [],
			'serialized_content' => $serialized_content,
			'content_hash' => $computed,
			'block_count' => absint( $payload['block_count'] ?? 0 ),
			'blocks_preview' => is_array( $payload['blocks_preview'] ?? null ) ? $payload['blocks_preview'] : [],
			'operations' => is_array( $payload['operations'] ?? null ) ? $payload['operations'] : [],
		];
	}

	/**
	 * Apply a frozen page_spec_v1: create draft and write exact stored content (no AI/template regen).
	 *
	 * @return array|WP_Error
	 */
	private static function execute_page_spec_apply( array $page_spec, array $context = [], $idempotency_key = '' ) {
		$page_spec = self::normalize_page_spec_payload( $page_spec );
		if ( is_wp_error( $page_spec ) ) {
			return $page_spec;
		}

		$idempotency_key = sanitize_text_field( (string) ( $idempotency_key !== '' ? $idempotency_key : ( $page_spec['idempotency_key'] ?? '' ) ) );
		$operation = [
			'action' => 'page_spec_v1',
			'post_id' => 0,
			'payload' => [
				'content_hash' => $page_spec['content_hash'],
				'idempotency_key' => $idempotency_key,
			],
		];

		if ( '' !== $idempotency_key ) {
			$idempotent_result = self::get_idempotent_response( $idempotency_key, $operation );
			if ( is_wp_error( $idempotent_result ) ) {
				return $idempotent_result;
			}
			if ( is_array( $idempotent_result ) ) {
				self::audit_log(
					'idempotent_replay_page_spec',
					[
						'post_id' => absint( $idempotent_result['post_id'] ?? 0 ),
						'template' => sanitize_text_field( (string) ( $page_spec['template'] ?? '' ) ),
						'idempotency_key' => $idempotency_key,
						'content_hash' => $page_spec['content_hash'],
					]
				);
				return $idempotent_result;
			}
		}

		$plan_id = sanitize_text_field( (string) ( $context['durable_plan_apply'] ?? '' ) );
		if ( '' === $plan_id ) {
			return new WP_Error( 'sae_page_spec_apply_requires_plan', 'page_spec_v1 apply requires a durable plan_id claim.', [ 'status' => 403 ] );
		}

		$post_type = $page_spec['post_type'];
		$permission = self::ensure_create_allowed( $post_type, true );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$insert_post = [
			'post_title' => wp_slash( (string) $page_spec['title'] ),
			'post_type' => $post_type,
			'post_status' => 'draft',
		];
		$page_template = sanitize_text_field( (string) ( $page_spec['page_template'] ?? '' ) );
		if ( 'page' === $post_type && '' !== $page_template ) {
			$insert_post['page_template'] = $page_template;
		}

		$new_post_id = wp_insert_post( $insert_post, true );
		if ( is_wp_error( $new_post_id ) ) {
			return new WP_Error( 'sae_create_post_failed', $new_post_id->get_error_message(), [ 'status' => 500 ] );
		}
		$new_post_id = absint( $new_post_id );
		if ( $new_post_id <= 0 ) {
			return new WP_Error( 'sae_create_post_failed', 'Could not create draft post.', [ 'status' => 500 ] );
		}

		$sanitized_content = (string) $page_spec['serialized_content'];
		$updated_post = wp_update_post(
			[
				'ID' => $new_post_id,
				'post_content' => wp_slash( $sanitized_content ),
			],
			true
		);
		if ( is_wp_error( $updated_post ) ) {
			wp_delete_post( $new_post_id, true );
			return new WP_Error( 'sae_create_content_update_failed', $updated_post->get_error_message(), [ 'status' => 500 ] );
		}

		self::invalidate_parsed_blocks_cache( $new_post_id );

		$written_hash = hash( 'sha256', (string) get_post_field( 'post_content', $new_post_id ) );
		$hash_matched = hash_equals( $page_spec['content_hash'], $written_hash );

		$auto_allowlist = (bool) apply_filters( 'struo_auto_allowlist_created_pages',
			true,
			$new_post_id,
			$page_spec
		);
		if ( $auto_allowlist ) {
			$allowlist_result = self::add_manual_allowlisted_post_id( $new_post_id );
			if ( is_wp_error( $allowlist_result ) ) {
				wp_delete_post( $new_post_id, true );
				return $allowlist_result;
			}
		}

		$result = [
			'post_id' => $new_post_id,
			'post_url' => esc_url_raw( (string) get_permalink( $new_post_id ) ),
			'edit_url' => esc_url_raw( admin_url( 'post.php?post=' . $new_post_id . '&action=edit' ) ),
			'post_type' => $post_type,
			'template_used' => sanitize_text_field( (string) ( $page_spec['template'] ?? '' ) ),
			'template_mode' => sanitize_key( (string) ( $page_spec['template_mode'] ?? '' ) ),
			'template_label' => sanitize_text_field( (string) ( $page_spec['template_label'] ?? '' ) ),
			'template_rationale' => sanitize_text_field( (string) ( $page_spec['template_rationale'] ?? '' ) ),
			'blocks_created' => absint( $page_spec['block_count'] ?? 0 ),
			'operations' => is_array( $page_spec['operations'] ?? null ) ? $page_spec['operations'] : [],
			'content_hash' => $page_spec['content_hash'],
			'written_content_hash' => $written_hash,
			'content_hash_matched' => $hash_matched,
			'status' => 'draft',
			'payload_type' => 'page_spec_v1',
		];
		if ( '' !== $idempotency_key ) {
			$result = self::with_idempotency_meta( $result, $idempotency_key, false );
			self::store_idempotent_response( $idempotency_key, $operation, $result );
		}

		self::audit_log(
			'page_spec_apply',
			[
				'post_id' => $new_post_id,
				'title' => sanitize_text_field( (string) $page_spec['title'] ),
				'template' => sanitize_text_field( (string) ( $page_spec['template'] ?? '' ) ),
				'block_count' => absint( $page_spec['block_count'] ?? 0 ),
				'idempotency_key' => $idempotency_key,
				'content_hash' => $page_spec['content_hash'],
				'content_hash_matched' => $hash_matched,
				'plan_id' => $plan_id,
			]
		);

		return $result;
	}

	private static function execute_create_page_apply( $idempotency_key, $confirmation_token = '', array $context = [] ) {
		$idempotency_key = sanitize_text_field( (string) $idempotency_key );
		if ( '' === $idempotency_key ) {
			return new WP_Error( 'sae_invalid_idempotency_key', 'idempotency_key is required.', [ 'status' => 400 ] );
		}

		$plan_snapshot = self::get_create_plan_snapshot( $idempotency_key );
		if ( ! is_array( $plan_snapshot ) ) {
			return new WP_Error( 'sae_create_plan_expired', 'Create plan is missing or expired. Generate a new plan.', [ 'status' => 409 ] );
		}
		$template_key = self::normalize_create_template_key( $plan_snapshot['template'] ?? '' );
		$dynamic_template = is_array( $plan_snapshot['dynamic_template'] ?? null ) ? $plan_snapshot['dynamic_template'] : null;
		if ( '' !== $template_key && is_array( $dynamic_template ) ) {
			self::register_dynamic_template( $template_key, $dynamic_template );
		}
		$template = self::get_page_templates()[ $template_key ] ?? null;

		$operation = self::build_create_operation_payload(
			'create_page_v2',
			$idempotency_key,
			$plan_snapshot,
			[
				'content_generation' => true,
			]
		);
		$idempotent_result = self::get_idempotent_response( $idempotency_key, $operation );
		if ( is_wp_error( $idempotent_result ) ) {
			return $idempotent_result;
		}
		if ( is_array( $idempotent_result ) ) {
			self::audit_log(
				'idempotent_replay_page_create',
				[
					'post_id' => absint( $idempotent_result['post_id'] ?? 0 ),
					'template' => sanitize_text_field( (string) ( $plan_snapshot['template'] ?? '' ) ),
					'idempotency_key' => $idempotency_key,
				]
			);
			return $idempotent_result;
		}

		$confirmed = self::ensure_create_write_confirmation( $confirmation_token, $operation, $plan_snapshot, $context );
		if ( is_wp_error( $confirmed ) ) {
			return $confirmed;
		}

		$post_type = self::normalize_create_post_type( $plan_snapshot['post_type'] ?? 'page' );
		if ( '' === $post_type ) {
			return new WP_Error( 'sae_create_post_type_invalid', 'Create plan post_type is invalid.', [ 'status' => 400 ] );
		}

		$permission = self::ensure_create_allowed( $post_type, true );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$insert_post = [
			'post_title' => wp_slash( (string) ( $plan_snapshot['title'] ?? '' ) ),
			'post_type' => $post_type,
			'post_status' => 'draft',
		];
		$page_template = sanitize_text_field( (string) ( $plan_snapshot['page_template'] ?? '' ) );
		if ( 'page' === $post_type && '' !== $page_template ) {
			$insert_post['page_template'] = $page_template;
		}

		$new_post_id = wp_insert_post( $insert_post, true );
		if ( is_wp_error( $new_post_id ) ) {
			return new WP_Error( 'sae_create_post_failed', $new_post_id->get_error_message(), [ 'status' => 500 ] );
		}
		$new_post_id = absint( $new_post_id );
		if ( $new_post_id <= 0 ) {
			return new WP_Error( 'sae_create_post_failed', 'Could not create draft post.', [ 'status' => 500 ] );
		}

		$page_copy = self::generate_page_copy(
			(string) ( $plan_snapshot['template'] ?? '' ),
			(string) ( $plan_snapshot['description'] ?? '' ),
			(string) ( $plan_snapshot['title'] ?? '' ),
			[
				'selected_sections' => is_array( $plan_snapshot['selected_sections'] ?? null ) ? $plan_snapshot['selected_sections'] : [],
				'post_type' => $post_type,
			]
		);
		if ( is_wp_error( $page_copy ) ) {
			wp_delete_post( $new_post_id, true );
			return $page_copy;
		}

		$built_blocks = self::build_blocks_from_template(
			(string) ( $plan_snapshot['template'] ?? '' ),
			is_array( $page_copy ) ? $page_copy : [],
			[
				'selected_sections' => is_array( $plan_snapshot['selected_sections'] ?? null ) ? $plan_snapshot['selected_sections'] : [],
			]
		);
		if ( is_wp_error( $built_blocks ) ) {
			wp_delete_post( $new_post_id, true );
			return $built_blocks;
		}

		$serialized_content = (string) ( $built_blocks['serialized'] ?? '' );
		$sanitized_content = self::sanitize_generated_post_content( $serialized_content );
		if ( '' === trim( $sanitized_content ) ) {
			wp_delete_post( $new_post_id, true );
			return new WP_Error( 'sae_create_content_invalid', 'Generated page content is empty after sanitization.', [ 'status' => 500 ] );
		}

		$updated_post = wp_update_post(
			[
				'ID' => $new_post_id,
				'post_content' => wp_slash( $sanitized_content ),
			],
			true
		);
		if ( is_wp_error( $updated_post ) ) {
			wp_delete_post( $new_post_id, true );
			return new WP_Error( 'sae_create_content_update_failed', $updated_post->get_error_message(), [ 'status' => 500 ] );
		}

		self::invalidate_parsed_blocks_cache( $new_post_id );

		$auto_allowlist = (bool) apply_filters( 'struo_auto_allowlist_created_pages', true, $new_post_id, $plan_snapshot );
		if ( $auto_allowlist ) {
			$allowlist_result = self::add_manual_allowlisted_post_id( $new_post_id );
			if ( is_wp_error( $allowlist_result ) ) {
				wp_delete_post( $new_post_id, true );
				return $allowlist_result;
			}
		}

		$result = [
			'post_id' => $new_post_id,
			'post_url' => esc_url_raw( (string) get_permalink( $new_post_id ) ),
			'edit_url' => esc_url_raw( admin_url( 'post.php?post=' . $new_post_id . '&action=edit' ) ),
			'post_type' => $post_type,
			'template_used' => sanitize_text_field( (string) ( $plan_snapshot['template'] ?? '' ) ),
			'template_mode' => sanitize_key( (string) ( $plan_snapshot['template_mode'] ?? '' ) ),
			'template_label' => sanitize_text_field( (string) ( $plan_snapshot['template_label'] ?? '' ) ),
			'template_rationale' => sanitize_text_field( (string) ( $plan_snapshot['template_rationale'] ?? '' ) ),
			'blocks_created' => absint( $built_blocks['block_count'] ?? 0 ),
			'operations' => is_array( $built_blocks['operations'] ?? null ) ? $built_blocks['operations'] : [],
			'status' => 'draft',
		];
		$result = self::with_idempotency_meta( $result, $idempotency_key, false );
		self::store_idempotent_response( $idempotency_key, $operation, $result );

		self::audit_log(
			'page_create',
			[
				'post_id' => $new_post_id,
				'title' => sanitize_text_field( (string) ( $plan_snapshot['title'] ?? '' ) ),
				'template' => sanitize_text_field( (string) ( $plan_snapshot['template'] ?? '' ) ),
				'template_label' => sanitize_text_field( (string) ( $plan_snapshot['template_label'] ?? '' ) ),
				'block_count' => $result['blocks_created'],
				'idempotency_key' => $idempotency_key,
				'before_excerpt' => self::audit_excerpt( '' ),
				'after_excerpt' => self::audit_excerpt( (string) get_post_field( 'post_content', $new_post_id ) ),
			]
		);

		return $result;
	}

	public static function save_created_template( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$payload = self::get_request_payload( $request );
		$idempotency_key = self::normalize_idempotency_key( $payload['idempotency_key'] ?? '' );
		if ( is_wp_error( $idempotency_key ) ) {
			return $idempotency_key;
		}
		if ( '' === $idempotency_key ) {
			return new WP_Error( 'sae_invalid_idempotency_key', 'idempotency_key is required.', [ 'status' => 400 ] );
		}

		$idempotency_record = get_transient( self::get_idempotency_cache_key( $idempotency_key ) );
		if ( ! is_array( $idempotency_record ) ) {
			return new WP_Error( 'sae_template_save_expired', 'Create receipt is missing or expired. Create a new draft first.', [ 'status' => 409 ] );
		}
		$current_user_id = get_current_user_id();
		if ( absint( $idempotency_record['user_id'] ?? 0 ) !== $current_user_id ) {
			return new WP_Error( 'sae_idempotency_user_mismatch', 'This create receipt belongs to a different user.', [ 'status' => 403 ] );
		}

		$plan_snapshot = self::get_create_plan_snapshot( $idempotency_key );
		if ( ! is_array( $plan_snapshot ) ) {
			return new WP_Error( 'sae_template_save_expired', 'Create plan context is missing or expired. Generate the draft again before saving it as a template.', [ 'status' => 409 ] );
		}

		$existing_templates = self::get_page_templates();
		$source_template = null;
		$template_key = self::normalize_create_template_key( $plan_snapshot['template'] ?? '' );
		if ( is_array( $plan_snapshot['dynamic_template'] ?? null ) ) {
			$source_template = $plan_snapshot['dynamic_template'];
		} elseif ( '' !== $template_key && is_array( $existing_templates[ $template_key ] ?? null ) ) {
			$source_template = $existing_templates[ $template_key ];
		}

		if ( ! is_array( $source_template ) ) {
			return new WP_Error( 'sae_template_source_missing', 'The source structure for this draft is no longer available.', [ 'status' => 409 ] );
		}

		$label = self::normalize_create_title( $payload['label'] ?? '' );
		if ( '' === $label ) {
			return new WP_Error( 'sae_template_label_required', 'Template label is required.', [ 'status' => 400 ] );
		}

		$description = self::normalize_create_description( $payload['description'] ?? '' );
		if ( '' === $description ) {
			$description = sprintf(
				'Saved from draft flow for "%s".',
				self::normalize_prompt_excerpt( sanitize_text_field( (string) ( $plan_snapshot['title'] ?? '' ) ), 80 )
			);
		}

		$registry_storage = self::get_saved_template_registry_storage();
		$stored_templates = is_array( $registry_storage['templates'] ?? null ) ? $registry_storage['templates'] : [];

		$user_template_key = self::build_user_template_key(
			$label,
			array_merge( $stored_templates, $existing_templates )
		);

		$based_on_post_id = absint( $payload['based_on_post_id'] ?? 0 );
		$normalized_template = self::normalize_template_registry_record(
			[
				'key' => $user_template_key,
				'label' => $label,
				'description' => $description,
				'post_types' => [
					$source_template['post_type'] ?? ( $plan_snapshot['post_type'] ?? 'page' ),
				],
				'page_template' => $source_template['page_template'] ?? '',
				'structure' => [
					'blocks' => $source_template['blocks'] ?? [],
				],
				'source_type' => 'saved',
				'creation_mode' => 'saved_from_receipt',
				'status' => 'approved',
				'origin' => [
					'created_by_user_id' => $current_user_id,
					'created_at' => time(),
					'created_from_post_id' => $based_on_post_id,
				],
			],
			[
				'key' => $user_template_key,
				'source_type' => 'saved',
				'creation_mode' => 'saved_from_receipt',
				'status' => 'approved',
			]
		);

		if ( null === $normalized_template ) {
			return new WP_Error( 'sae_template_invalid', 'The saved template structure is invalid.', [ 'status' => 400 ] );
		}

		$stored_templates[ $user_template_key ] = $normalized_template;
		update_option(
			self::TEMPLATE_REGISTRY_OPTION,
			[
				'version' => self::TEMPLATE_REGISTRY_VERSION,
				'templates' => $stored_templates,
			],
			false
		);

		self::audit_log(
			'template_save_created',
			[
				'template' => $user_template_key,
				'template_label' => $label,
				'based_on_post_id' => $based_on_post_id,
				'idempotency_key' => $idempotency_key,
			]
		);

		return [
			'template_key' => $user_template_key,
			'template_label' => $label,
			'template_description' => $description,
			'template_mode' => 'known',
			'source_type' => 'saved',
		];
	}

	public static function list_template_records( WP_REST_Request $request ) {
		$requested_post_type = self::normalize_create_post_type( $request['post_type'] ?? '' );
		$requested_status = sanitize_key( (string) ( $request['status'] ?? '' ) );
		$requested_source_type = sanitize_key( (string) ( $request['source_type'] ?? '' ) );
		$include_hidden = rest_sanitize_boolean( $request['include_hidden'] ?? false );

		$templates = [];
		foreach ( self::get_template_registry_records() as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$status = sanitize_key( (string) ( $record['status'] ?? 'approved' ) );
			if ( ! $include_hidden && 'hidden' === $status ) {
				continue;
			}
			if ( '' !== $requested_status && $requested_status !== $status ) {
				continue;
			}
			$source_type = sanitize_key( (string) ( $record['source_type'] ?? '' ) );
			if ( '' !== $requested_source_type && $requested_source_type !== $source_type ) {
				continue;
			}
			if ( '' !== $requested_post_type ) {
				$post_types = self::normalize_template_post_types( $record['post_types'] ?? null );
				if ( ! in_array( $requested_post_type, $post_types, true ) ) {
					continue;
				}
			}
			$templates[] = $record;
		}

		self::audit_log(
			'template_registry_read',
			[
				'count' => count( $templates ),
				'post_type' => $requested_post_type,
				'status' => $requested_status,
				'source_type' => $requested_source_type,
				'include_hidden' => $include_hidden,
			]
		);

		return [
			'templates' => $templates,
			'meta' => [
				'count' => count( $templates ),
			],
		];
	}

	public static function promote_page_template_preview( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$payload = self::get_request_payload( $request );
		$post_id = absint( $payload['post_id'] ?? 0 );
		$access = self::validate_template_source_post_access( $post_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$existing_templates = array_merge(
			self::get_saved_template_registry_storage()['templates'] ?? [],
			self::get_template_registry_records()
		);
		$post_title = self::normalize_create_title( get_the_title( $post_id ) );
		$label = self::normalize_create_title( $payload['label'] ?? $post_title );
		if ( '' === $label ) {
			$label = 'Promoted Template';
		}
		$description = self::normalize_create_description( $payload['description'] ?? '' );
		if ( '' === $description ) {
			$description = sprintf( 'Promoted from "%s".', $post_title );
		}
		$metadata = is_array( $payload['metadata'] ?? null ) ? $payload['metadata'] : [];
		$status = sanitize_key( (string) ( $payload['status'] ?? 'draft' ) );
		if ( ! in_array( $status, [ 'draft', 'approved', 'hidden' ], true ) ) {
			$status = 'draft';
		}

		$template_key = self::build_user_template_key( $label, $existing_templates );
		$preview = self::extract_template_record_from_post(
			$post_id,
			[
				'key' => $template_key,
				'label' => $label,
				'description' => $description,
				'metadata' => $metadata,
				'status' => $status,
			]
		);
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		self::audit_log(
			'template_promote_preview',
			[
				'post_id' => $post_id,
				'template' => $template_key,
				'status' => $status,
			]
		);

		return $preview;
	}

	public static function save_template_record( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$payload = self::get_request_payload( $request );
		$template = is_array( $payload['template'] ?? null ) ? $payload['template'] : null;
		if ( ! is_array( $template ) ) {
			return new WP_Error( 'sae_template_required', 'template is required.', [ 'status' => 400 ] );
		}

		$label = self::normalize_create_title( $template['label'] ?? '' );
		if ( '' === $label ) {
			return new WP_Error( 'sae_template_label_required', 'Template label is required.', [ 'status' => 400 ] );
		}

		$storage = self::get_saved_template_registry_storage();
		$saved_templates = is_array( $storage['templates'] ?? null ) ? $storage['templates'] : [];
		$existing_templates = self::get_template_registry_records();

		$template_key = self::normalize_create_template_key( $template['key'] ?? '' );
		if ( '' === $template_key ) {
			$template_key = self::build_user_template_key(
				$label,
				array_merge( $saved_templates, $existing_templates )
			);
		} elseif ( isset( $existing_templates[ $template_key ] ) && ! isset( $saved_templates[ $template_key ] ) ) {
			return new WP_Error( 'sae_template_key_reserved', 'Template key conflicts with a built-in template.', [ 'status' => 409 ] );
		}

		$normalized_template = self::normalize_template_registry_record(
			$template,
			[
				'key' => $template_key,
				'source_type' => 'saved',
				'creation_mode' => sanitize_key( (string) ( $template['creation_mode'] ?? 'promoted_page' ) ),
				'status' => sanitize_key( (string) ( $template['status'] ?? 'draft' ) ),
			]
		);
		if ( null === $normalized_template ) {
			return new WP_Error( 'sae_template_invalid', 'The template record is invalid.', [ 'status' => 400 ] );
		}

		$saved_templates[ $template_key ] = $normalized_template;
		update_option(
			self::TEMPLATE_REGISTRY_OPTION,
			[
				'version' => self::TEMPLATE_REGISTRY_VERSION,
				'templates' => $saved_templates,
			],
			false
		);

		self::audit_log(
			'template_save',
			[
				'template' => $template_key,
				'template_label' => sanitize_text_field( (string) ( $normalized_template['label'] ?? '' ) ),
				'creation_mode' => sanitize_key( (string) ( $normalized_template['creation_mode'] ?? '' ) ),
				'status' => sanitize_key( (string) ( $normalized_template['status'] ?? '' ) ),
			]
		);

		return [
			'ok' => true,
			'template' => $normalized_template,
		];
	}

	public static function list_pattern_records( WP_REST_Request $request ) {
		$requested_post_type = self::normalize_create_post_type( $request['post_type'] ?? '' );
		$requested_status = sanitize_key( (string) ( $request['status'] ?? '' ) );
		$requested_source_type = sanitize_key( (string) ( $request['source_type'] ?? '' ) );
		$requested_block_name = self::normalize_block_name( $request['block_name'] ?? '' );
		$include_hidden = rest_sanitize_boolean( $request['include_hidden'] ?? false );

		$patterns = [];
		foreach ( self::get_pattern_registry_records() as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$status = sanitize_key( (string) ( $record['status'] ?? 'draft' ) );
			if ( ! $include_hidden && 'hidden' === $status ) {
				continue;
			}
			if ( '' !== $requested_status && $requested_status !== $status ) {
				continue;
			}
			$source_type = sanitize_key( (string) ( $record['source_type'] ?? '' ) );
			if ( '' !== $requested_source_type && $requested_source_type !== $source_type ) {
				continue;
			}
			if ( '' !== $requested_post_type ) {
				$post_types = self::normalize_template_post_types( $record['post_types'] ?? null );
				if ( ! in_array( $requested_post_type, $post_types, true ) ) {
					continue;
				}
			}
			if ( '' !== $requested_block_name ) {
				$block_name = self::normalize_block_name( $record['structure']['block_name'] ?? '' );
				if ( $requested_block_name !== $block_name ) {
					continue;
				}
			}
			$patterns[] = $record;
		}

		self::audit_log(
			'pattern_registry_read',
			[
				'count' => count( $patterns ),
				'post_type' => $requested_post_type,
				'status' => $requested_status,
				'source_type' => $requested_source_type,
				'block_name' => $requested_block_name,
				'include_hidden' => $include_hidden,
			]
		);

		return [
			'patterns' => $patterns,
			'meta' => [
				'count' => count( $patterns ),
			],
		];
	}

	public static function save_pattern_record( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$payload = self::get_request_payload( $request );
		$pattern = is_array( $payload['pattern'] ?? null ) ? $payload['pattern'] : null;
		if ( ! is_array( $pattern ) ) {
			return new WP_Error( 'sae_pattern_required', 'pattern is required.', [ 'status' => 400 ] );
		}

		$label = self::normalize_create_title( $pattern['label'] ?? '' );
		if ( '' === $label ) {
			return new WP_Error( 'sae_pattern_label_required', 'Pattern label is required.', [ 'status' => 400 ] );
		}

		$storage = self::get_saved_pattern_registry_storage();
		$saved_patterns = is_array( $storage['patterns'] ?? null ) ? $storage['patterns'] : [];
		$existing_patterns = self::get_pattern_registry_records();
		$current_user_id = get_current_user_id();

		$pattern_key = self::normalize_create_template_key( $pattern['key'] ?? '' );
		if ( '' === $pattern_key ) {
			$pattern_key = self::build_user_pattern_key(
				$label,
				array_merge( $saved_patterns, $existing_patterns )
			);
		} elseif ( isset( $existing_patterns[ $pattern_key ] ) && ! isset( $saved_patterns[ $pattern_key ] ) ) {
			return new WP_Error( 'sae_pattern_key_reserved', 'Pattern key conflicts with a built-in pattern.', [ 'status' => 409 ] );
		}

		$existing_saved_pattern = is_array( $saved_patterns[ $pattern_key ] ?? null ) ? $saved_patterns[ $pattern_key ] : [];
		$existing_origin = is_array( $existing_saved_pattern['origin'] ?? null ) ? $existing_saved_pattern['origin'] : [];
		$pattern['origin'] = array_merge(
			$existing_origin,
			is_array( $pattern['origin'] ?? null ) ? $pattern['origin'] : [],
			[
				'created_by_user_id' => absint( $existing_origin['created_by_user_id'] ?? $current_user_id ),
				'created_at' => absint( $existing_origin['created_at'] ?? time() ),
			]
		);

		$normalized_pattern = self::normalize_pattern_registry_record(
			$pattern,
			[
				'key' => $pattern_key,
				'source_type' => 'saved',
				'status' => sanitize_key( (string) ( $pattern['status'] ?? 'draft' ) ),
			]
		);
		if ( null === $normalized_pattern ) {
			return new WP_Error( 'sae_pattern_invalid', 'The pattern record is invalid.', [ 'status' => 400 ] );
		}

		$saved_patterns[ $pattern_key ] = $normalized_pattern;
		update_option(
			self::PATTERN_REGISTRY_OPTION,
			[
				'version' => self::PATTERN_REGISTRY_VERSION,
				'patterns' => $saved_patterns,
			],
			false
		);

		self::audit_log(
			'pattern_save',
			[
				'pattern' => $pattern_key,
				'pattern_label' => sanitize_text_field( (string) ( $normalized_pattern['label'] ?? '' ) ),
				'status' => sanitize_key( (string) ( $normalized_pattern['status'] ?? '' ) ),
				'block_name' => sanitize_text_field( (string) ( $normalized_pattern['structure']['block_name'] ?? '' ) ),
			]
		);

		return [
			'ok' => true,
			'pattern' => $normalized_pattern,
		];
	}

	public static function promote_section_pattern_preview( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$payload = self::get_request_payload( $request );
		$post_id = absint( $payload['post_id'] ?? 0 );
		$block_index = intval( $payload['block_index'] ?? -1 );
		$access = self::validate_template_source_post_access( $post_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( $block_index < 0 ) {
			return new WP_Error( 'sae_block_index_required', 'block_index is required.', [ 'status' => 400 ] );
		}

		$preview = self::extract_pattern_record_from_post_block(
			$post_id,
			$block_index,
			[
				'label' => $payload['label'] ?? '',
				'description' => $payload['description'] ?? '',
				'metadata' => is_array( $payload['metadata'] ?? null ) ? $payload['metadata'] : [],
				'status' => sanitize_key( (string) ( $payload['status'] ?? 'draft' ) ),
			]
		);
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		self::audit_log(
			'pattern_promote_preview',
			[
				'post_id' => $post_id,
				'block_index' => $block_index,
				'pattern' => sanitize_key( (string) ( $preview['pattern']['key'] ?? '' ) ),
			]
		);

		return $preview;
	}

	private static function extract_template_record_from_post( $post_id, array $args = [] ) {
		$post_id = absint( $post_id );
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return new WP_Error( 'sae_post_not_found', 'Source post was not found.', [ 'status' => 404 ] );
		}

		$post_type = self::normalize_create_post_type( $post->post_type );
		if ( '' === $post_type ) {
			return new WP_Error( 'sae_create_post_type_invalid', 'Source post type is not supported.', [ 'status' => 400 ] );
		}

		$build_result = self::build_template_blocks_from_post( $post_id );
		if ( is_wp_error( $build_result ) ) {
			return $build_result;
		}

		$template_record = self::normalize_template_registry_record(
			[
				'key' => self::normalize_create_template_key( $args['key'] ?? '' ),
				'label' => self::normalize_create_title( $args['label'] ?? $post->post_title ),
				'description' => self::normalize_create_description( $args['description'] ?? '' ),
				'metadata' => is_array( $args['metadata'] ?? null )
					? $args['metadata']
					: [
						'context' => self::normalize_prompt_excerpt( sanitize_text_field( (string) $post->post_title ), 80 ),
					],
				'post_types' => [ $post_type ],
				'page_template' => 'page' === $post_type ? sanitize_text_field( (string) get_page_template_slug( $post_id ) ) : '',
				'structure' => [
					'blocks' => $build_result['blocks'],
				],
				'section_recipe' => self::normalize_template_section_recipe( null, $build_result['blocks'] ),
				'source_type' => 'saved',
				'creation_mode' => 'promoted_page',
				'status' => sanitize_key( (string) ( $args['status'] ?? 'draft' ) ),
				'origin' => [
					'created_from_post_id' => $post_id,
					'created_by_user_id' => get_current_user_id(),
					'created_at' => time(),
				],
			],
			[
				'key' => self::normalize_create_template_key( $args['key'] ?? '' ),
				'source_type' => 'saved',
				'creation_mode' => 'promoted_page',
				'status' => sanitize_key( (string) ( $args['status'] ?? 'draft' ) ),
			]
		);

		if ( null === $template_record ) {
			return new WP_Error( 'sae_template_invalid', 'The promoted template structure is invalid.', [ 'status' => 400 ] );
		}

		return [
			'template' => $template_record,
			'warnings' => $build_result['warnings'],
			'family_candidates' => [],
		];
	}

	private static function extract_pattern_record_from_post_block( $post_id, $block_index, array $args = [] ) {
		$post_id = absint( $post_id );
		$block_index = absint( $block_index );
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return new WP_Error( 'sae_post_not_found', 'Source post was not found.', [ 'status' => 404 ] );
		}

		$post_type = self::normalize_create_post_type( $post->post_type );
		if ( '' === $post_type ) {
			return new WP_Error( 'sae_create_post_type_invalid', 'Source post type is not supported.', [ 'status' => 400 ] );
		}

		$parsed_blocks = self::get_parsed_blocks( $post_id );
		if ( ! isset( $parsed_blocks[ $block_index ] ) || ! is_array( $parsed_blocks[ $block_index ] ) ) {
			return new WP_Error( 'sae_pattern_block_not_found', 'Selected top-level block was not found.', [ 'status' => 404 ] );
		}

		$parsed_block = $parsed_blocks[ $block_index ];
		$block_name = self::normalize_block_name( $parsed_block['blockName'] ?? '' );
		if ( '' === $block_name ) {
			return new WP_Error( 'sae_pattern_block_invalid', 'Selected block is not promotable.', [ 'status' => 400 ] );
		}

		$allowed_lookup = array_fill_keys( self::get_allowed_block_types(), true );
		if ( empty( $allowed_lookup[ $block_name ] ) ) {
			return new WP_Error( 'sae_pattern_block_not_allowed', 'Selected block is not allowlisted for patterns.', [ 'status' => 403 ] );
		}

		if ( ! self::is_promotable_pattern_block_name( $block_name ) ) {
			return new WP_Error( 'sae_pattern_block_too_atomic', 'Selected block is too small to promote as a reusable pattern.', [ 'status' => 400 ] );
		}

		$field_definitions = self::build_promoted_template_fields_for_block( $block_name );
		if ( empty( $field_definitions ) ) {
			return new WP_Error( 'sae_pattern_fields_empty', 'Selected block does not expose reusable editable fields.', [ 'status' => 400 ] );
		}

		$post_title = self::normalize_create_title( get_the_title( $post_id ) );
		$block_label = self::get_create_block_label( $block_name );
		$metadata = array_merge(
			self::build_pattern_metadata_defaults_for_block( $block_name ),
			is_array( $args['metadata'] ?? null ) ? $args['metadata'] : []
		);

		$label = self::normalize_create_title( $args['label'] ?? '' );
		if ( '' === $label ) {
			$label = $block_label;
		}

		$description = self::normalize_create_description( $args['description'] ?? '' );
		if ( '' === $description ) {
			$description = sprintf(
				'Promoted from section %d on "%s".',
				$block_index + 1,
				$post_title
			);
		}

		$existing_patterns = array_merge(
			self::get_saved_pattern_registry_storage()['patterns'] ?? [],
			self::get_pattern_registry_records()
		);
		$pattern_key = self::normalize_create_template_key( $args['key'] ?? '' );
		if ( '' === $pattern_key ) {
			$pattern_key = self::build_user_pattern_key( $label, $existing_patterns );
		}

		$pattern_record = self::normalize_pattern_registry_record(
			[
				'key' => $pattern_key,
				'label' => $label,
				'description' => $description,
				'metadata' => $metadata,
				'post_types' => [ $post_type ],
				'families' => '' !== sanitize_text_field( (string) ( $metadata['family'] ?? '' ) )
					? [ sanitize_key( (string) $metadata['family'] ) ]
					: [],
				'structure' => [
					'block_name' => $block_name,
					'fields' => $field_definitions,
				],
				'source_type' => 'saved',
				'status' => sanitize_key( (string) ( $args['status'] ?? 'draft' ) ),
				'origin' => [
					'created_from_post_id' => $post_id,
					'created_from_block_index' => $block_index,
					'created_by_user_id' => get_current_user_id(),
					'created_at' => time(),
				],
			],
			[
				'key' => $pattern_key,
				'source_type' => 'saved',
				'status' => sanitize_key( (string) ( $args['status'] ?? 'draft' ) ),
			]
		);
		if ( null === $pattern_record ) {
			return new WP_Error( 'sae_pattern_invalid', 'The promoted pattern structure is invalid.', [ 'status' => 400 ] );
		}

		return [
			'pattern' => $pattern_record,
			'block_label' => $block_label,
			'section_index' => $block_index,
		];
	}

	private static function build_template_blocks_from_post( $post_id ) {
		$parsed_blocks = self::get_parsed_blocks( $post_id );
		if ( empty( $parsed_blocks ) ) {
			return new WP_Error( 'sae_template_source_empty', 'Source page does not contain any blocks.', [ 'status' => 400 ] );
		}

		$allowed_lookup = array_fill_keys( self::get_allowed_block_types(), true );
		$warnings = [];
		$blocks = [];

		foreach ( $parsed_blocks as $parsed_block ) {
			if ( ! is_array( $parsed_block ) ) {
				continue;
			}

			$block_name = self::normalize_block_name( $parsed_block['blockName'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}
			if ( empty( $allowed_lookup[ $block_name ] ) ) {
				$warnings[] = sprintf( 'Skipped non-allowlisted block: %s.', $block_name );
				continue;
			}

			$field_definitions = self::build_promoted_template_fields_for_block( $block_name );
			if ( empty( $field_definitions ) ) {
				$warnings[] = sprintf( 'Skipped block without usable editable fields: %s.', $block_name );
				continue;
			}

			$blocks[] = [
				'block_name' => $block_name,
				'fields' => $field_definitions,
			];
		}

		if ( count( $blocks ) < 2 ) {
			return new WP_Error( 'sae_template_source_too_small', 'Page has too few sections for a useful template.', [ 'status' => 400 ] );
		}

		return [
			'blocks' => $blocks,
			'warnings' => array_values( array_unique( $warnings ) ),
		];
	}

	private static function build_promoted_template_fields_for_block( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );
		if ( '' === $block_name ) {
			return [];
		}

		if ( ! self::is_core_block( $block_name ) ) {
			$schema = self::get_block_schema( $block_name );
			if ( empty( $schema ) ) {
				return [];
			}
		}

		return self::build_dynamic_template_fields_for_block( $block_name );
	}

	private static function normalize_create_template_key( $value ) {
		return sanitize_key( (string) $value );
	}

	private static function normalize_create_post_type( $value ) {
		$post_type = sanitize_key( (string) $value );
		if ( '' === $post_type ) {
			return '';
		}
		$post_type_object = get_post_type_object( $post_type );
		if ( ! is_object( $post_type_object ) ) {
			return '';
		}
		return $post_type;
	}

	private static function normalize_create_title( $value ) {
		$title = sanitize_text_field( (string) $value );
		$title = trim( preg_replace( '/\s+/', ' ', $title ) );
		if ( strlen( $title ) > 160 ) {
			$title = substr( $title, 0, 160 );
		}
		return $title;
	}

	private static function normalize_create_description( $value ) {
		$description = sanitize_textarea_field( (string) $value );
		$description = trim( preg_replace( '/\s+/', ' ', $description ) );
		if ( strlen( $description ) > 1000 ) {
			$description = substr( $description, 0, 1000 );
		}
		return $description;
	}

	private static function normalize_create_outline( $value ) {
		if ( null === $value || '' === $value ) {
			return [];
		}
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'sae_create_outline_invalid', 'outline must be an array of section entries.', [ 'status' => 400 ] );
		}

		$outline = array_values( $value );
		$count = count( $outline );
		if ( $count < 1 || $count > 12 ) {
			return new WP_Error( 'sae_create_outline_invalid', 'outline must include between 1 and 12 sections.', [ 'status' => 400 ] );
		}

		$allowed_blocks = self::get_allowed_block_types();
		$normalized = [];
		foreach ( $outline as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				return new WP_Error(
					'sae_create_outline_invalid',
					sprintf( 'outline entry %d must be an object.', (int) $index ),
					[ 'status' => 400 ]
				);
			}

			$section = self::normalize_prompt_excerpt( sanitize_text_field( (string) ( $entry['section'] ?? ( $entry['title'] ?? '' ) ) ), 90 );
			$purpose = self::normalize_prompt_excerpt( sanitize_text_field( (string) ( $entry['purpose'] ?? ( $entry['description'] ?? '' ) ) ), 240 );
			$block_name = self::normalize_block_name( $entry['block_name'] ?? ( $entry['block'] ?? '' ) );
			$reasoning = self::normalize_prompt_excerpt( sanitize_text_field( (string) ( $entry['reasoning'] ?? '' ) ), 240 );

			if ( '' === $section || '' === $purpose || '' === $block_name ) {
				return new WP_Error(
					'sae_create_outline_invalid',
					sprintf( 'outline entry %d is missing section, purpose, or block_name.', (int) $index ),
					[ 'status' => 400 ]
				);
			}
			if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
				return new WP_Error(
					'sae_create_template_block_not_allowed',
					sprintf( 'outline block_name "%s" is not allowlisted.', $block_name ),
					[ 'status' => 403, 'block_name' => $block_name ]
				);
			}

			$normalized[] = [
				'section' => $section,
				'purpose' => $purpose,
				'block_name' => $block_name,
				'reasoning' => $reasoning,
			];
		}

		return $normalized;
	}

	private static function resolve_outline_post_type( array $outline, $requested_post_type = '' ) {
		$post_type = self::normalize_create_post_type( $requested_post_type );
		if ( '' !== $post_type ) {
			return $post_type;
		}

		$matched_template_key = self::find_template_key_for_outline( $outline, '' );
		if ( '' !== $matched_template_key ) {
			$matched_template = self::get_page_templates()[ $matched_template_key ] ?? null;
			$matched_post_type = self::normalize_create_post_type( is_array( $matched_template ) ? ( $matched_template['post_type'] ?? '' ) : '' );
			if ( '' !== $matched_post_type ) {
				return $matched_post_type;
			}
		}

		return 'page';
	}

	private static function find_template_key_for_outline( array $outline, $post_type = '' ) {
		if ( empty( $outline ) ) {
			return '';
		}

		$outline_blocks = array_map(
			static function ( $entry ) {
				return self::normalize_block_name( is_array( $entry ) ? ( $entry['block_name'] ?? '' ) : '' );
			},
			$outline
		);
		if ( in_array( '', $outline_blocks, true ) ) {
			return '';
		}

		$normalized_post_type = self::normalize_create_post_type( $post_type );
		foreach ( self::get_page_templates() as $template_key => $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}
			$template_post_type = self::normalize_create_post_type( $template['post_type'] ?? 'page' );
			if ( '' !== $normalized_post_type && $template_post_type !== $normalized_post_type ) {
				continue;
			}
			$template_blocks = is_array( $template['blocks'] ?? null ) ? array_values( $template['blocks'] ) : [];
			if ( count( $template_blocks ) !== count( $outline_blocks ) ) {
				continue;
			}

			$matches = true;
			foreach ( $template_blocks as $index => $template_block ) {
				$template_block_name = self::normalize_block_name( is_array( $template_block ) ? ( $template_block['block_name'] ?? '' ) : '' );
				if ( '' === $template_block_name || $template_block_name !== $outline_blocks[ $index ] ) {
					$matches = false;
					break;
				}
			}

			if ( $matches ) {
				return self::normalize_create_template_key( $template_key );
			}
		}

		return '';
	}

	private static function convert_outline_to_plan_create_payload( array $outline, $title, $description, $requested_post_type = '', $requested_template_key = '' ) {
		$post_type = self::resolve_outline_post_type( $outline, $requested_post_type );
		$template_key = self::normalize_create_template_key( $requested_template_key );
		$dynamic_template = null;

		$templates = self::get_page_templates();
		if ( '' !== $template_key && 'dynamic' !== $template_key ) {
			$template = $templates[ $template_key ] ?? null;
			if ( ! is_array( $template ) ) {
				return new WP_Error( 'sae_create_template_invalid', 'template is not recognized.', [ 'status' => 400 ] );
			}
			$template_post_type = self::normalize_create_post_type( $template['post_type'] ?? 'page' );
			if ( '' !== $template_post_type && $template_post_type !== $post_type ) {
				return new WP_Error( 'sae_create_template_post_type_mismatch', 'Template post_type does not match requested post_type.', [ 'status' => 400 ] );
			}
		} else {
			$matched_template_key = self::find_template_key_for_outline( $outline, $post_type );
			if ( '' !== $matched_template_key ) {
				$template_key = $matched_template_key;
			} else {
				$outline_hash = md5(
					(string) wp_json_encode(
						[
							'post_type' => $post_type,
							'outline' => $outline,
							'title' => $title,
						]
					)
				);
				$template_key = 'dynamic-' . substr( $outline_hash, 0, 12 );
				$dynamic_template = self::build_dynamic_template_from_outline( $outline, $post_type, $title, $description );
				self::register_dynamic_template( $template_key, $dynamic_template );
			}
		}

		$selected_sections = range( 0, count( $outline ) - 1 );

		return [
			'title' => $title,
			'description' => $description,
			'post_type' => $post_type,
			'template' => $template_key,
			'outline' => $outline,
			'selected_sections' => $selected_sections,
			'dynamic_template' => $dynamic_template,
		];
	}

	private static function build_dynamic_template_from_outline( array $outline, $post_type = 'page', $title = '', $description = '' ) {
		$normalized_post_type = self::normalize_create_post_type( $post_type );
		if ( '' === $normalized_post_type ) {
			$normalized_post_type = 'page';
		}

		$blocks = [];
		foreach ( $outline as $entry ) {
			$block_name = self::normalize_block_name( is_array( $entry ) ? ( $entry['block_name'] ?? '' ) : '' );
			if ( '' === $block_name ) {
				continue;
			}
			$section = sanitize_text_field( (string) ( is_array( $entry ) ? ( $entry['section'] ?? '' ) : '' ) );
			$purpose = sanitize_text_field( (string) ( is_array( $entry ) ? ( $entry['purpose'] ?? '' ) : '' ) );
			$blocks[] = [
				'block_name' => $block_name,
				'fields' => self::build_dynamic_template_fields_for_block( $block_name, $section, $purpose ),
			];
		}

		$label = sprintf(
			'Custom Outline%s',
			'' !== $title ? ': ' . self::normalize_prompt_excerpt( sanitize_text_field( $title ), 60 ) : ''
		);
		$template_description = '' !== $description
			? self::normalize_prompt_excerpt( sanitize_text_field( $description ), 180 )
			: 'Generated from AI outline.';

		$dynamic_page_template = '';

		return [
			'label' => $label,
			'description' => $template_description,
			'post_type' => $normalized_post_type,
			'page_template' => $dynamic_page_template,
			'blocks' => $blocks,
		];
	}

	private static function build_dynamic_template_fields_for_block( $block_name, $section = '', $purpose = '' ) {
		$block_name = self::normalize_block_name( $block_name );
		$schema = self::get_block_schema( $block_name );
		if ( ! is_array( $schema ) ) {
			$schema = [];
		}

		$fields = [];
		foreach ( $schema as $field_name => $field_schema ) {
			$field_key = sanitize_key( (string) $field_name );
			if ( '' === $field_key ) {
				continue;
			}
			$field_type = sanitize_key( (string) ( is_array( $field_schema ) ? ( $field_schema['type'] ?? 'text' ) : 'text' ) );
			if ( '' === $field_type ) {
				$field_type = 'text';
			}
			if ( ! self::is_dynamic_create_field_ai_safe( $block_name, $field_key, is_array( $field_schema ) ? $field_schema : [] ) ) {
				continue;
			}
			$field_label = sanitize_text_field( (string) ( is_array( $field_schema ) ? ( $field_schema['label'] ?? '' ) : '' ) );
			$field_instructions = sanitize_text_field( (string) ( is_array( $field_schema ) ? ( $field_schema['instructions'] ?? '' ) : '' ) );
			$hint_parts = [];
			if ( '' !== $section ) {
				$hint_parts[] = sprintf( 'Section: %s', $section );
			}
			if ( '' !== $purpose ) {
				$hint_parts[] = sprintf( 'Purpose: %s', $purpose );
			}
			if ( '' !== $field_label ) {
				$hint_parts[] = sprintf( 'Field: %s', $field_label );
			}
			if ( '' !== $field_instructions ) {
				$hint_parts[] = $field_instructions;
			}
			$ai_hint = trim( implode( '. ', $hint_parts ) );
			if ( '' === $ai_hint ) {
				$ai_hint = sprintf( 'Provide copy for %s.', $field_key );
			}

			$field_definition = [
				'type' => $field_type,
				'ai_hint' => $ai_hint,
			];
			if ( is_array( $field_schema ) && array_key_exists( 'default_value', $field_schema ) ) {
				$default_value = $field_schema['default_value'];
				if ( is_scalar( $default_value ) ) {
					$field_definition['default'] = sanitize_text_field( (string) $default_value );
				}
			}
			$fields[ $field_key ] = $field_definition;
		}

		if ( empty( $fields ) ) {
			$fields['content'] = [
				'type' => 'text',
				'ai_hint' => 'Provide copy for this section.',
			];
		}

		return $fields;
	}

	private static function is_dynamic_create_field_ai_safe( $block_name, $field_key, array $field_schema ) {
		$field_type = sanitize_key( (string) ( $field_schema['type'] ?? '' ) );
		if ( '' === $field_type ) {
			$field_type = 'text';
		}

		if ( 'core/list' === $block_name && 'items' === $field_key ) {
			return true;
		}
		if ( 'core/buttons' === $block_name && 'buttons' === $field_key ) {
			return true;
		}

		return in_array(
			$field_type,
			[
				'text',
				'textarea',
				'wysiwyg',
				'url',
				'email',
				'number',
				'true_false',
				'link',
			],
			true
		);
	}

	private static function register_dynamic_template( $template_key, array $definition ) {
		$normalized_key = self::normalize_create_template_key( $template_key );
		if ( '' === $normalized_key ) {
			return '';
		}
		if ( empty( $definition['blocks'] ) || ! is_array( $definition['blocks'] ) ) {
			return '';
		}

		self::$dynamic_templates_cache[ $normalized_key ] = $definition;
		return $normalized_key;
	}

	private static function normalize_create_selected_sections( $value, array $blocks ) {
		$block_count = count( $blocks );
		if ( $block_count <= 0 ) {
			return new WP_Error( 'sae_create_template_empty', 'Template does not contain any sections.', [ 'status' => 400 ] );
		}

		if ( null === $value || '' === $value ) {
			return range( 0, $block_count - 1 );
		}

		if ( ! is_array( $value ) ) {
			return new WP_Error( 'sae_create_sections_invalid', 'selected_sections must be an array of section indexes.', [ 'status' => 400 ] );
		}

		$selected = [];
		$seen = [];
		foreach ( $value as $raw_index ) {
			if ( ! is_numeric( $raw_index ) ) {
				return new WP_Error( 'sae_create_sections_invalid', 'selected_sections must contain numeric indexes.', [ 'status' => 400 ] );
			}
			$index = (int) $raw_index;
			if ( $index < 0 || $index >= $block_count ) {
				return new WP_Error( 'sae_create_sections_invalid', 'selected_sections contains an out-of-range index.', [ 'status' => 400 ] );
			}
			if ( isset( $seen[ $index ] ) ) {
				continue;
			}
			$seen[ $index ] = true;
			$selected[] = $index;
		}

		if ( empty( $selected ) ) {
			return new WP_Error( 'sae_create_sections_empty', 'At least one section must be selected.', [ 'status' => 400 ] );
		}

		return $selected;
	}

	private static function build_create_operations( $template_key, array $template, array $selected_sections ) {
		$blocks = is_array( $template['blocks'] ?? null ) ? array_values( $template['blocks'] ) : [];
		$operations = [];

		foreach ( $selected_sections as $section_index ) {
			if ( ! isset( $blocks[ $section_index ] ) || ! is_array( $blocks[ $section_index ] ) ) {
				continue;
			}
			$block = $blocks[ $section_index ];
			$block_name = self::normalize_block_name( $block['block_name'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}

			$field_summaries = [];
			$fields = is_array( $block['fields'] ?? null ) ? $block['fields'] : [];
			foreach ( $fields as $field_name => $field_definition ) {
				if ( ! is_array( $field_definition ) ) {
					continue;
				}
				$field_summaries[] = [
					'name' => sanitize_key( (string) $field_name ),
					'type' => sanitize_key( (string) ( $field_definition['type'] ?? '' ) ),
					'ai_hint' => sanitize_text_field( (string) ( $field_definition['ai_hint'] ?? '' ) ),
				];
			}

			$operations[] = [
				'template' => sanitize_key( (string) $template_key ),
				'section_index' => absint( $section_index ),
				'block_name' => $block_name,
				'label' => self::get_create_block_label( $block_name ),
				'fields' => $field_summaries,
			];
		}

		return $operations;
	}

	private static function get_create_block_label( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );
		if ( '' === $block_name ) {
			return 'Block';
		}
		if ( self::is_core_block( $block_name ) ) {
			return self::get_core_block_label( $block_name );
		}
		$slug = preg_replace( '#^acf/#', '', $block_name );
		return ucwords( str_replace( '-', ' ', (string) $slug ) );
	}

	private static function get_create_plan_cache_key( $idempotency_key ) {
		return self::CREATE_PLAN_KEY_PREFIX . md5( (string) $idempotency_key );
	}

	private static function store_create_plan_snapshot( $idempotency_key, array $plan_snapshot ) {
		if ( '' === $idempotency_key || empty( $plan_snapshot ) ) {
			return;
		}
		$cache_key = self::get_create_plan_cache_key( $idempotency_key );
		set_transient( $cache_key, $plan_snapshot, self::CONFIRM_TTL );
	}

	private static function get_create_plan_snapshot( $idempotency_key ) {
		if ( '' === $idempotency_key ) {
			return null;
		}
		$cache_key = self::get_create_plan_cache_key( $idempotency_key );
		$stored = get_transient( $cache_key );
		return is_array( $stored ) ? $stored : null;
	}

	private static function delete_create_plan_snapshot( $idempotency_key ) {
		if ( '' === $idempotency_key ) {
			return;
		}
		$cache_key = self::get_create_plan_cache_key( $idempotency_key );
		delete_transient( $cache_key );
	}

	private static function build_create_operation_payload( $action, $idempotency_key, array $plan_snapshot, array $extra_payload = [] ) {
		$base_payload = [
			'idempotency_key' => (string) $idempotency_key,
			'template' => sanitize_key( (string) ( $plan_snapshot['template'] ?? '' ) ),
			'post_type' => sanitize_key( (string) ( $plan_snapshot['post_type'] ?? '' ) ),
			'title' => sanitize_text_field( (string) ( $plan_snapshot['title'] ?? '' ) ),
			'description' => sanitize_text_field( (string) ( $plan_snapshot['description'] ?? '' ) ),
			'selected_sections' => is_array( $plan_snapshot['selected_sections'] ?? null ) ? $plan_snapshot['selected_sections'] : [],
			'operations' => is_array( $plan_snapshot['operations'] ?? null ) ? $plan_snapshot['operations'] : [],
		];
		if ( ! empty( $extra_payload ) ) {
			$base_payload = array_merge( $base_payload, $extra_payload );
		}

		return self::build_operation_payload(
			$action,
			0,
			$base_payload
		);
	}

	private static function hash_create_plan_snapshot( array $plan_snapshot ) {
		$canonical = self::canonicalize_value( $plan_snapshot );
		$json = wp_json_encode( $canonical );
		$salt = wp_salt( 'auth' );
		return hash_hmac( 'sha256', (string) $json, (string) $salt );
	}

	private static function generate_page_copy( $template_key, $description, $title, array $context = [] ) {
		$template_key = self::normalize_create_template_key( $template_key );
		$templates = self::get_page_templates();
		$template = $templates[ $template_key ] ?? null;
		if ( ! is_array( $template ) ) {
			return new WP_Error( 'sae_create_template_invalid', 'template is not recognized.', [ 'status' => 400 ] );
		}

		$prompt = self::build_page_copy_prompt( $template_key, $template, $description, $title, $context );
		$result = self::call_ai_json_query( $prompt, [], 'AI copy generation returned a non-JSON response.' );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			if ( in_array( $code, [ 'sae_planner_unavailable', 'sae_provider_url_refused', 'sae_provider_key_host_mismatch' ], true ) ) {
				return new WP_Error( 'sae_copy_generation_unavailable', 'AI copy generation is unavailable in this environment.', [ 'status' => 503 ] );
			}
			return new WP_Error( 'sae_copy_generation_failed', $result->get_error_message(), [ 'status' => 502 ] );
		}

		if ( ! is_array( $result ) ) {
			return new WP_Error( 'sae_copy_generation_failed', 'AI copy generation returned a non-JSON response.', [ 'status' => 502 ] );
		}

		return self::validate_page_copy_result( $result, $template_key, $context );
	}

	private static function build_page_copy_prompt( $template_key, array $template, $description, $title, array $context = [] ) {
		$title = self::normalize_create_title( $title );
		$description = self::normalize_create_description( $description );
		$blocks = is_array( $template['blocks'] ?? null ) ? array_values( $template['blocks'] ) : [];
		$selected_sections = self::normalize_create_selected_sections( $context['selected_sections'] ?? null, $blocks );
		if ( is_wp_error( $selected_sections ) ) {
			$selected_sections = range( 0, max( 0, count( $blocks ) - 1 ) );
		}

		$lines = [];
		$lines[] = sprintf(
			"You are generating copy for a new \"%s\" page titled \"%s\".",
			sanitize_text_field( (string) ( $template['label'] ?? $template_key ) ),
			str_replace( '"', "'", $title )
		);
		$lines[] = sprintf(
			'Site context: %s. Keep copy aligned to the site voice, audience, and existing content.',
			self::get_site_context_label()
		);
		$lines[] = 'Page purpose: ' . ( '' !== $description ? str_replace( '"', "'", $description ) : 'Create clear, conversion-focused marketing copy.' );
		$lines[] = 'Generate copy for each section listed below.';

		foreach ( $selected_sections as $section_index ) {
			$block = is_array( $blocks[ $section_index ] ?? null ) ? $blocks[ $section_index ] : [];
			$block_name = self::normalize_block_name( $block['block_name'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}
			$field_schema = is_array( $block['fields'] ?? null ) ? $block['fields'] : [];
			$field_prompts = [];
			foreach ( $field_schema as $field_name => $field_definition ) {
				$field_key = sanitize_key( (string) $field_name );
				if ( '' === $field_key ) {
					continue;
				}
				$field_type = sanitize_key( (string) ( $field_definition['type'] ?? 'text' ) );
				$field_hint = sanitize_text_field( (string) ( $field_definition['ai_hint'] ?? '' ) );
				$field_prompt = $field_key . ' (' . ( '' !== $field_type ? $field_type : 'text' ) . ')';
				if ( '' !== $field_hint ) {
					$field_prompt .= ': ' . str_replace( '"', "'", $field_hint );
				}
				$field_prompts[] = $field_prompt;
			}
			if ( empty( $field_prompts ) ) {
				$field_prompts[] = 'content (text)';
			}
			$lines[] = sprintf(
				'Section %d: %s — Fields: %s',
				(int) $section_index,
				$block_name,
				implode( '; ', $field_prompts )
			);
		}

		$lines[] = 'Return ONLY valid JSON.';
		$lines[] = 'JSON schema: {"sections":[{"index":0,"fields":{"field_name":"value"}}]}';
		$lines[] = 'Do not include markdown, comments, or any text outside JSON.';

		return implode( "\n", $lines );
	}

	private static function validate_page_copy_result( array $result, $template_key, array $context = [] ) {
		$template_key = self::normalize_create_template_key( $template_key );
		$template = self::get_page_templates()[ $template_key ] ?? null;
		if ( ! is_array( $template ) ) {
			return new WP_Error( 'sae_create_template_invalid', 'template is not recognized.', [ 'status' => 400 ] );
		}

		$sections = is_array( $result['sections'] ?? null ) ? $result['sections'] : null;
		if ( ! is_array( $sections ) || empty( $sections ) ) {
			return new WP_Error( 'sae_copy_generation_failed', 'AI copy generation returned an invalid sections payload.', [ 'status' => 502 ] );
		}

		$blocks = is_array( $template['blocks'] ?? null ) ? array_values( $template['blocks'] ) : [];
		$selected_sections = self::normalize_create_selected_sections( $context['selected_sections'] ?? null, $blocks );
		if ( is_wp_error( $selected_sections ) ) {
			$selected_sections = range( 0, max( 0, count( $blocks ) - 1 ) );
		}
		$allowed_indexes = [];
		foreach ( $selected_sections as $index ) {
			$allowed_indexes[ (int) $index ] = true;
		}

		$normalized = [];
		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( ! array_key_exists( 'index', $section ) || ! is_numeric( $section['index'] ) ) {
				continue;
			}
			$index = (int) $section['index'];
			if ( ! isset( $allowed_indexes[ $index ] ) ) {
				continue;
			}

			$fields = is_array( $section['fields'] ?? null ) ? $section['fields'] : [];
			if ( empty( $fields ) ) {
				continue;
			}

			$section_fields = [];
			foreach ( $fields as $field_name => $raw_value ) {
				$field_key = sanitize_key( (string) $field_name );
				if ( '' === $field_key ) {
					continue;
				}

				$sanitized_value = self::sanitize_generated_copy_value( $raw_value );
				if ( is_wp_error( $sanitized_value ) ) {
					return $sanitized_value;
				}
				$section_fields[ $field_key ] = $sanitized_value;
			}

			if ( ! empty( $section_fields ) ) {
				$normalized[ $index ] = $section_fields;
			}
		}

		if ( empty( $normalized ) ) {
			return new WP_Error( 'sae_copy_generation_failed', 'AI copy generation did not return usable section fields.', [ 'status' => 502 ] );
		}

		ksort( $normalized );
		return $normalized;
	}

	private static function sanitize_generated_copy_value( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = [];
			foreach ( $value as $key => $item ) {
				$item_value = self::sanitize_generated_copy_value( $item );
				if ( is_wp_error( $item_value ) ) {
					return $item_value;
				}
				$sanitized[ $key ] = $item_value;
			}
			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( null === $value ) {
			return '';
		}

		if ( ! is_string( $value ) ) {
			return new WP_Error( 'sae_copy_generation_failed', 'AI copy generation returned unsupported field value types.', [ 'status' => 502 ] );
		}

		if ( preg_match( '/<\s*script\b|on[a-z0-9_:-]+\s*=|javascript\s*:/i', $value ) ) {
			return new WP_Error( 'sae_copy_generation_failed', 'AI copy generation returned unsafe field content.', [ 'status' => 502 ] );
		}

		$sanitized = sanitize_textarea_field( $value );
		$sanitized = trim( preg_replace( '/\s+/', ' ', (string) $sanitized ) );
		return $sanitized;
	}

	private static function build_blocks_from_template( $template_key, array $field_values_by_section, array $context = [] ) {
		$template_key = self::normalize_create_template_key( $template_key );
		$template = self::get_page_templates()[ $template_key ] ?? null;
		if ( ! is_array( $template ) ) {
			return new WP_Error( 'sae_create_template_invalid', 'template is not recognized.', [ 'status' => 400 ] );
		}

		$blocks = is_array( $template['blocks'] ?? null ) ? array_values( $template['blocks'] ) : [];
		$selected_sections = self::normalize_create_selected_sections( $context['selected_sections'] ?? null, $blocks );
		if ( is_wp_error( $selected_sections ) ) {
			return $selected_sections;
		}

		$parsed_blocks = [];
		$operations = [];
		foreach ( $selected_sections as $section_index ) {
			$block = is_array( $blocks[ $section_index ] ?? null ) ? $blocks[ $section_index ] : null;
			if ( ! is_array( $block ) ) {
				return new WP_Error( 'sae_create_template_invalid', 'Template contains invalid section definitions.', [ 'status' => 400 ] );
			}

			$block_name = self::normalize_block_name( $block['block_name'] ?? '' );
			if ( '' === $block_name ) {
				return new WP_Error( 'sae_create_template_invalid', 'Template contains an invalid block name.', [ 'status' => 400 ] );
			}

			$schema = self::get_block_schema( $block_name );
			if ( empty( $schema ) ) {
				return new WP_Error( 'sae_create_schema_missing', sprintf( 'No schema available for %s.', $block_name ), [ 'status' => 400 ] );
			}

			$template_field_defs = is_array( $block['fields'] ?? null ) ? $block['fields'] : [];
			$defaults = self::build_create_default_fields( $block_name, $schema, $template_field_defs );
			$incoming_fields = is_array( $field_values_by_section[ $section_index ] ?? null ) ? $field_values_by_section[ $section_index ] : [];
			$incoming_fields = self::normalize_generated_create_fields( $block_name, $schema, $incoming_fields );
			$fields = $defaults;
			foreach ( $incoming_fields as $field_name => $field_value ) {
				$field_key = sanitize_key( (string) $field_name );
				if ( '' === $field_key || ! isset( $schema[ $field_key ] ) ) {
					continue;
				}
				if ( ! empty( $template_field_defs ) && ! isset( $template_field_defs[ $field_key ] ) ) {
					continue;
				}

				$field_value = self::coerce_generated_create_field_value( $block_name, $field_key, $schema[ $field_key ], $field_value );
				$field_type = sanitize_key( (string) ( $schema[ $field_key ]['type'] ?? '' ) );
				if ( in_array( $field_type, [ 'url', 'link' ], true ) ) {
					$candidate = is_string( $field_value ) ? esc_url_raw( $field_value ) : '';
					if ( '' === $candidate ) {
						continue;
					}
					$fields[ $field_key ] = $candidate;
					continue;
				}

				$fields[ $field_key ] = $field_value;
			}

			$errors = [];
			$is_valid = self::validate_fields( $schema, $fields, $errors, false );
			if ( ! $is_valid ) {
				return new WP_Error(
					'sae_create_fields_invalid',
					sprintf( 'Generated fields are invalid for section %d.', (int) $section_index ),
					[
						'status' => 400,
						'errors' => $errors,
						'block_name' => $block_name,
						'section' => (int) $section_index,
					]
				);
			}

			if ( self::is_core_block( $block_name ) ) {
				$parsed = self::build_core_block( $block_name, $fields );
			} else {
				$acf_data = self::build_acf_data( $schema, $fields );
				$parsed = self::build_block( $block_name, $acf_data );
			}
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}

			$parsed_blocks[] = $parsed;
			$operations[] = [
				'index' => (int) $section_index,
				'block_name' => $block_name,
				'status' => 'created',
			];
		}

		$serialized = serialize_blocks( $parsed_blocks );
		return [
			'blocks' => $parsed_blocks,
			'serialized' => $serialized,
			'operations' => $operations,
			'block_count' => count( $parsed_blocks ),
		];
	}

	private static function build_create_default_fields( $block_name, array $schema, array $template_field_defs = [] ) {
		$block_name = self::normalize_block_name( $block_name );
		$defaults = [];
		foreach ( $schema as $field_name => $field_schema ) {
			$field_key = sanitize_key( (string) $field_name );
			if ( '' === $field_key ) {
				continue;
			}

			$field_definition = is_array( $template_field_defs[ $field_key ] ?? null ) ? $template_field_defs[ $field_key ] : [];
			if ( array_key_exists( 'default', $field_definition ) ) {
				$defaults[ $field_key ] = $field_definition['default'];
				continue;
			}

			$field_type = sanitize_key( (string) ( $field_schema['type'] ?? '' ) );
			if ( 'core/heading' === $block_name && 'level' === $field_key ) {
				$defaults[ $field_key ] = 2;
				continue;
			}
			if ( 'core/button' === $block_name && 'text' === $field_key ) {
				$defaults[ $field_key ] = 'Learn more';
				continue;
			}
			if ( 'core/button' === $block_name && 'url' === $field_key ) {
				$defaults[ $field_key ] = '#';
				continue;
			}
			if ( 'core/list' === $block_name && 'items' === $field_key ) {
				$defaults[ $field_key ] = [
					[ 'content' => 'First point' ],
					[ 'content' => 'Second point' ],
				];
				continue;
			}
			if ( 'core/buttons' === $block_name && 'buttons' === $field_key ) {
				$defaults[ $field_key ] = [
					[
						'text' => 'Learn more',
						'url' => '#',
					],
				];
				continue;
			}
			if ( 'core/group' === $block_name && 'inner_blocks' === $field_key ) {
				$defaults[ $field_key ] = [];
				continue;
			}
			if ( 'core/column' === $block_name && 'inner_blocks' === $field_key ) {
				$defaults[ $field_key ] = [];
				continue;
			}
			if ( 'core/columns' === $block_name && 'columns' === $field_key ) {
				$defaults[ $field_key ] = [
					[ 'inner_blocks' => [] ],
					[ 'inner_blocks' => [] ],
				];
				continue;
			}
			if ( in_array( $field_type, [ 'text', 'textarea', 'wysiwyg', 'url' ], true ) ) {
				$defaults[ $field_key ] = '';
			}
		}
		return $defaults;
	}

	private static function normalize_generated_create_fields( $block_name, array $schema, array $fields ) {
		if ( empty( $fields ) ) {
			return [];
		}

		$normalized_fields = $fields;
		$policy = self::get_manifest_policy( $block_name );
		if ( is_array( $policy ) ) {
			$normalized_fields = self::apply_manifest_aliases( $normalized_fields, $policy );
		}

		foreach ( $schema as $field_name => $field_schema ) {
			$field_key = sanitize_key( (string) $field_name );
			if ( '' === $field_key || ! is_array( $field_schema ) ) {
				continue;
			}

			$alias_map = self::get_generated_create_field_aliases( $block_name, $field_key, $field_schema );
			if ( ! empty( $alias_map ) ) {
				$normalized_fields = self::apply_alias_map_to_row( $normalized_fields, $alias_map );
			}

			$field_type = sanitize_key( (string) ( $field_schema['type'] ?? '' ) );
			if ( 'repeater' === $field_type && array_key_exists( $field_key, $normalized_fields ) ) {
				$normalized_fields[ $field_key ] = self::normalize_generated_create_repeater_rows(
					$block_name,
					$field_key,
					$field_schema,
					$normalized_fields[ $field_key ]
				);
			}
		}

		return $normalized_fields;
	}

	private static function normalize_generated_create_repeater_rows( $block_name, $field_key, array $field_schema, $field_value ) {
		$rows = is_array( $field_value ) ? $field_value : [ $field_value ];
		if ( is_array( $rows ) && self::is_assoc_array( $rows ) ) {
			$rows = [ $rows ];
		}
		$sub_schema = self::index_sub_fields( $field_schema );
		if ( empty( $sub_schema ) ) {
			return $field_value;
		}

		$normalized_rows = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$normalized_rows[] = $row;
				continue;
			}

			$normalized_row = $row;
			foreach ( $sub_schema as $sub_field_name => $sub_field_schema ) {
				$sub_field_key = sanitize_key( (string) $sub_field_name );
				if ( '' === $sub_field_key || ! is_array( $sub_field_schema ) ) {
					continue;
				}

				$alias_map = self::get_generated_create_field_aliases( $block_name, $sub_field_key, $sub_field_schema );
				if ( ! empty( $alias_map ) ) {
					$normalized_row = self::apply_alias_map_to_row( $normalized_row, $alias_map );
				}
			}

			$normalized_rows[] = $normalized_row;
		}

		return $normalized_rows;
	}

	private static function get_generated_create_field_aliases( $block_name, $field_key, array $field_schema ) {
		$field_type = sanitize_key( (string) ( $field_schema['type'] ?? '' ) );
		$field_key = sanitize_key( (string) $field_key );
		$alias_map = [];

		if ( 'content' === $field_key ) {
			$alias_map = array_merge(
				$alias_map,
				[
					'text'     => 'content',
					'label'    => 'content',
					'title'    => 'content',
					'headline' => 'content',
					'heading'  => 'content',
					'copy'     => 'content',
					'body'     => 'content',
					'value'    => 'content',
					'item'     => 'content',
				]
			);
		} elseif ( 'text' === $field_key || in_array( $field_type, [ 'text', 'textarea', 'wysiwyg' ], true ) ) {
			$canonical_text_key = $field_key;
			$alias_map = array_merge(
				$alias_map,
				[
					'label'    => $canonical_text_key,
					'title'    => $canonical_text_key,
					'headline' => $canonical_text_key,
					'heading'  => $canonical_text_key,
					'content'  => $canonical_text_key,
					'copy'     => $canonical_text_key,
					'body'     => $canonical_text_key,
					'value'    => $canonical_text_key,
					'item'     => $canonical_text_key,
				]
			);
		}

		if ( 'url' === $field_key || 'link' === $field_key || in_array( $field_type, [ 'url', 'link' ], true ) ) {
			$canonical_link_key = $field_key;
			$alias_map = array_merge(
				$alias_map,
				[
					'href'       => $canonical_link_key,
					'button_link'=> $canonical_link_key,
					'link_url'   => $canonical_link_key,
					'target_url' => $canonical_link_key,
				]
			);
			if ( 'url' === $canonical_link_key ) {
				$alias_map['link'] = 'url';
			} else {
				$alias_map['url'] = 'link';
			}
		}

		if ( 'core/buttons' === $block_name && 'buttons' === $field_key ) {
			$alias_map['items'] = 'buttons';
		}

		return self::normalize_alias_map( $alias_map );
	}

	private static function coerce_generated_create_field_value( $block_name, $field_key, array $field_schema, $field_value ) {
		$field_type = sanitize_key( (string) ( $field_schema['type'] ?? '' ) );
		if ( 'repeater' !== $field_type ) {
			return $field_value;
		}

		return self::coerce_generated_create_repeater_value( $block_name, $field_key, $field_schema, $field_value );
	}

	private static function coerce_generated_create_repeater_value( $block_name, $field_key, array $field_schema, $field_value ) {
		$rows = is_array( $field_value ) ? $field_value : [ $field_value ];
		if ( is_array( $rows ) && self::is_assoc_array( $rows ) ) {
			$rows = [ $rows ];
		}
		$sub_schema = self::index_sub_fields( $field_schema );
		if ( empty( $sub_schema ) ) {
			return $field_value;
		}

		$primary_text_key = '';
		if ( isset( $sub_schema['content'] ) ) {
			$primary_text_key = 'content';
		} elseif ( isset( $sub_schema['text'] ) ) {
			$primary_text_key = 'text';
		}
		if ( '' === $primary_text_key ) {
			return $field_value;
		}

		$coerced_rows = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$coerced_rows[] = $row;
				continue;
			}

			if ( is_scalar( $row ) || null === $row ) {
				$text = trim( sanitize_textarea_field( (string) $row ) );
				if ( '' === $text ) {
					continue;
				}

				$coerced_row = [
					$primary_text_key => $text,
				];
				if ( 'core/buttons' === $block_name && 'buttons' === $field_key && isset( $sub_schema['url'] ) ) {
					$coerced_row['url'] = '#';
				}
				$coerced_rows[] = $coerced_row;
				continue;
			}

			$coerced_rows[] = $row;
		}

		return $coerced_rows;
	}

	private static function sanitize_generated_post_content( $content ) {
		$content = (string) $content;
		if ( '' === trim( $content ) ) {
			return '';
		}

		$comments = [];
		$tokenized = preg_replace_callback(
			'/<!--\s*\/?wp:.*?-->/s',
			static function ( $matches ) use ( &$comments ) {
				$token = '__SAE_BLOCK_COMMENT_' . count( $comments ) . '__';
				$comments[ $token ] = (string) $matches[0];
				return $token;
			},
			$content
		);

		$sanitized = wp_kses_post( (string) $tokenized );
		if ( ! empty( $comments ) ) {
			$sanitized = strtr( $sanitized, $comments );
		}

		return $sanitized;
	}

	private static function add_manual_allowlisted_post_id( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'sae_invalid_post_id', 'post_id must be greater than zero.', [ 'status' => 400 ] );
		}

		$options = self::get_options();
		$manual_ids = array_values( array_filter( array_map( 'absint', $options['allowed_post_ids'] ?? [] ) ) );
		if ( in_array( $post_id, $manual_ids, true ) ) {
			return true;
		}

		$manual_ids[] = $post_id;
		$manual_ids = array_values( array_unique( array_map( 'absint', $manual_ids ) ) );
		sort( $manual_ids );
		$options['allowed_post_ids'] = $manual_ids;

		$updated = update_option( self::OPTION_KEY, $options, false );
		if ( false === $updated && self::get_options()['allowed_post_ids'] !== $manual_ids ) {
			return new WP_Error( 'sae_allowlist_update_failed', 'Failed to update allowlisted post IDs.', [ 'status' => 500 ] );
		}

		return true;
	}

	private static function get_cross_field_definitions() {
		return [
			'excerpt' => [
				'label' => 'Excerpt',
				'max_chars' => 300,
			],
			'meta_description' => [
				'label' => 'Meta Description',
				'max_chars' => 160,
			],
			'featured_image_alt' => [
				'label' => 'Featured Image Alt Text',
				'max_chars' => 125,
			],
		];
	}

	private static function normalize_cross_field_name( $value ) {
		$raw = strtolower( trim( (string) $value ) );
		if ( '' === $raw ) {
			return '';
		}

		$raw = str_replace( '-', '_', $raw );
		$alias_map = [
			'excerpt' => 'excerpt',
			'post_excerpt' => 'excerpt',
			'meta_description' => 'meta_description',
			'meta_desc' => 'meta_description',
			'metadesc' => 'meta_description',
			'seo_meta' => 'meta_description',
			'featured_image_alt' => 'featured_image_alt',
			'image_alt' => 'featured_image_alt',
			'alt_text' => 'featured_image_alt',
			'alt' => 'featured_image_alt',
		];
		$normalized = sanitize_key( $raw );
		if ( isset( $alias_map[ $normalized ] ) ) {
			$normalized = $alias_map[ $normalized ];
		}

		$definitions = self::get_cross_field_definitions();
		if ( ! isset( $definitions[ $normalized ] ) ) {
			return '';
		}

		return $normalized;
	}

	private static function normalize_cross_field_value( $field, $value ) {
		$field = self::normalize_cross_field_name( $field );
		if ( '' === $field ) {
			return '';
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$text = wp_strip_all_tags( (string) $value );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}

		$definitions = self::get_cross_field_definitions();
		$max_chars = absint( $definitions[ $field ]['max_chars'] ?? 0 );
		if ( $max_chars > 0 ) {
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
			if ( $length > $max_chars ) {
				$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_chars ) : substr( $text, 0, $max_chars );
				$text = rtrim( $text );
			}
		}

		return sanitize_text_field( $text );
	}

	private static function read_post_field_value( $post_id, $field ) {
		$post_id = absint( $post_id );
		$field = self::normalize_cross_field_name( $field );
		if ( $post_id <= 0 || '' === $field ) {
			return '';
		}

		switch ( $field ) {
			case 'excerpt':
				$post = get_post( $post_id );
				return $post ? (string) $post->post_excerpt : '';
			case 'meta_description':
				$yoast = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
				if ( '' !== trim( $yoast ) ) {
					return $yoast;
				}
				return (string) get_post_meta( $post_id, 'rank_math_description', true );
			case 'featured_image_alt':
				$thumbnail_id = absint( get_post_thumbnail_id( $post_id ) );
				if ( $thumbnail_id <= 0 ) {
					return '';
				}
				return (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
		}

		return '';
	}

	private static function write_post_field_value( $post_id, $field, $value ) {
		$post_id = absint( $post_id );
		$field = self::normalize_cross_field_name( $field );
		$value = self::normalize_cross_field_value( $field, $value );
		if ( $post_id <= 0 || '' === $field ) {
			return new WP_Error( 'sae_field_not_allowed', 'Field is not allowed for cross-field update.', [ 'status' => 400 ] );
		}
		if ( '' === $value ) {
			return new WP_Error( 'sae_invalid_field_value', 'Value is required for cross-field updates.', [ 'status' => 400 ] );
		}

		switch ( $field ) {
			case 'excerpt':
				$updated = wp_update_post(
					[
						'ID' => $post_id,
						'post_excerpt' => wp_kses_post( $value ),
					],
					true
				);
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
				return true;
			case 'meta_description':
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $value ) );
				update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $value ) );
				return true;
			case 'featured_image_alt':
				$thumbnail_id = absint( get_post_thumbnail_id( $post_id ) );
				if ( $thumbnail_id <= 0 ) {
					return new WP_Error( 'sae_no_featured_image', 'Post has no featured image.', [ 'status' => 400 ] );
				}
				update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field( $value ) );
				return true;
		}

		return new WP_Error( 'sae_field_not_allowed', 'Field is not allowed for cross-field update.', [ 'status' => 400 ] );
	}

	private static function apply_cross_field_update( $post_id, $field, $value, $dry_run, $confirmation_token = '', $idempotency_key = '', $origin = 'rest', array $context = [] ) {
		$post_id = absint( $post_id );
		$field = self::normalize_cross_field_name( $field );
		$value = self::normalize_cross_field_value( $field, $value );
		$dry_run = (bool) $dry_run;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'sae_missing_post', 'Post ID is required.', [ 'status' => 400 ] );
		}
		if ( '' === $field ) {
			return new WP_Error( 'sae_field_not_allowed', 'Field is not allowed for cross-field update.', [ 'status' => 400 ] );
		}
		if ( '' === $value ) {
			return new WP_Error( 'sae_invalid_field_value', 'Value is required for cross-field updates.', [ 'status' => 400 ] );
		}

		$permission = self::ensure_write_allowed( $post_id, ! $dry_run );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'sae_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}
		if ( 'featured_image_alt' === $field && absint( get_post_thumbnail_id( $post_id ) ) <= 0 ) {
			return new WP_Error( 'sae_no_featured_image', 'Post has no featured image.', [ 'status' => 400 ] );
		}

		$current_value = self::normalize_cross_field_value( $field, self::read_post_field_value( $post_id, $field ) );
		$operation = [
			'action' => 'cross_field',
			'post_id' => $post_id,
			'payload' => [
				'field' => $field,
				'value' => $value,
			],
		];

		$confirmation = [];
		if ( $dry_run ) {
			$confirmation = self::issue_confirmation_token( $operation, $post_id, $origin, $context );
		} else {
			$confirmed = self::ensure_write_confirmation( $confirmation_token, $operation, $post_id, $context );
			if ( is_wp_error( $confirmed ) ) {
				return $confirmed;
			}
		}

		if ( ! $dry_run ) {
			$idempotent_result = self::get_idempotent_response( $idempotency_key, $operation );
			if ( is_wp_error( $idempotent_result ) ) {
				return $idempotent_result;
			}
			if ( is_array( $idempotent_result ) ) {
				return $idempotent_result;
			}
		}

		$response = [
			'ok' => true,
			'dry_run' => $dry_run,
			'action' => 'field_update',
			'post_id' => $post_id,
			'post_title' => get_the_title( $post_id ),
			'field' => $field,
			'old_value' => $current_value,
			'new_value' => $value,
			'change' => [
				'delta' => 0,
			],
		];
		if ( ! empty( $confirmation ) ) {
			$response['confirmation'] = $confirmation;
		}

		if ( $dry_run ) {
			return $response;
		}

		$written = self::write_post_field_value( $post_id, $field, $value );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		$result = self::with_idempotency_meta( $response, $idempotency_key, false );
		self::store_idempotent_response( $idempotency_key, $operation, $result );
		self::audit_log(
			'field_update',
			[
				'post_id' => $post_id,
				'field' => $field,
				'from' => self::audit_excerpt( $current_value ),
				'to' => self::audit_excerpt( $value ),
				'idempotency_key' => $idempotency_key,
			]
		);

		return $result;
	}

	private static function build_cross_field_source_content( WP_Post $post ) {
		$content = wp_strip_all_tags( (string) $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}

		$max_chars = absint( self::CROSS_FIELD_SOURCE_MAX_CHARS );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $content ) : strlen( $content );
		if ( $max_chars > 0 && $length > $max_chars ) {
			$content = function_exists( 'mb_substr' ) ? mb_substr( $content, 0, $max_chars ) : substr( $content, 0, $max_chars );
			$content = rtrim( $content ) . '…';
		}
		return $content;
	}

	private static function prepare_cross_field_response( $post_id, $field, $request_text, array $payload = [], $origin = 'rest', array $context = [] ) {
		$post_id = absint( $post_id );
		$field = self::normalize_cross_field_name( $field );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'sae_cross_field_no_post', 'Post ID required for cross-field generation.', [ 'status' => 400 ] );
		}
		if ( '' === $field ) {
			return new WP_Error( 'sae_field_not_allowed', 'Field is not allowed for cross-field update.', [ 'status' => 400 ] );
		}

		$read_permission = self::ensure_read_allowed( $post_id );
		if ( is_wp_error( $read_permission ) ) {
			return $read_permission;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'sae_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}
		if ( 'featured_image_alt' === $field && absint( get_post_thumbnail_id( $post_id ) ) <= 0 ) {
			return new WP_Error( 'sae_no_featured_image', 'Post has no featured image.', [ 'status' => 400 ] );
		}

		$current_value = self::normalize_cross_field_value( $field, self::read_post_field_value( $post_id, $field ) );
		$source_content = self::build_cross_field_source_content( $post );
		if ( '' === $source_content ) {
			return new WP_Error( 'sae_cross_field_empty_source', 'Post content is empty. Nothing to generate from.', [ 'status' => 422 ] );
		}

		$normalized_request = self::sanitize_plan_request_text( $request_text );
		if ( '' === $normalized_request ) {
			$definitions = self::get_cross_field_definitions();
			$field_label = isset( $definitions[ $field ]['label'] ) ? $definitions[ $field ]['label'] : $field;
			$normalized_request = sprintf( 'Generate %s from this content.', strtolower( (string) $field_label ) );
		}

		$ai_result = self::call_ai_cross_field_planner(
			$normalized_request,
			[
				'post_id' => $post_id,
				'post_title' => get_the_title( $post_id ),
				'post_type' => sanitize_key( (string) $post->post_type ),
				'field' => $field,
				'current_value' => $current_value,
				'source_content' => $source_content,
			],
			$payload
		);
		if ( is_wp_error( $ai_result ) ) {
			return $ai_result;
		}

		$proposed_value = self::normalize_cross_field_value( $field, $ai_result['proposed_value'] ?? '' );
		if ( '' === $proposed_value ) {
			return new WP_Error( 'sae_cross_field_empty_output', 'AI generation returned an empty value.', [ 'status' => 502 ] );
		}
		$rationale = sanitize_text_field( (string) ( $ai_result['rationale'] ?? '' ) );
		$field_context = self::rest_plan_only_context( true, $origin, $context );
		$dry_run = self::apply_cross_field_update( $post_id, $field, $proposed_value, true, '', '', $origin, $field_context );
		if ( is_wp_error( $dry_run ) ) {
			return $dry_run;
		}

		$rag_query = '' !== $normalized_request ? $normalized_request : (string) $field;
		$rag_context = self::retrieve_rag_context( $rag_query );

		return [
			'request' => $normalized_request,
			'intent' => 'do',
			'planner' => [
				'source' => 'ai_engine',
				'ai_used' => true,
				'warnings' => [],
				'vector' => self::build_vector_meta_payload( $rag_context ),
			],
			'post' => [
				'post_id' => $post_id,
				'post_title' => get_the_title( $post_id ),
				'post_slug' => (string) $post->post_name,
				'post_url' => get_permalink( $post_id ),
				'post_type' => sanitize_key( (string) $post->post_type ),
			],
			'operation' => 'cross_field',
			'endpoint' => '/wp-json/struo/v1/posts/' . $post_id . '/fields/update',
			'payload' => [
				'field' => $field,
				'value' => $proposed_value,
				'current_value' => $current_value,
				'rationale' => $rationale,
			],
			'dry_run' => array_merge(
				is_array( $dry_run ) ? $dry_run : [],
				[
					'rationale' => $rationale,
				]
			),
			'vector_sources' => self::build_vector_sources_payload( $rag_context ),
		];
	}

	private static function summarize_cross_field_session_entry( array $response ) {
		$field = sanitize_key( (string) ( $response['payload']['field'] ?? '' ) );
		$field_label = str_replace( '_', ' ', $field );
		$post_label = sanitize_text_field( (string) ( $response['post']['post_title'] ?? '' ) );
		if ( '' === $post_label ) {
			$post_label = sanitize_text_field( (string) ( $response['post']['post_slug'] ?? '' ) );
		}
		if ( '' === $post_label ) {
			$post_label = 'the selected page';
		}
		if ( '' === $field_label ) {
			$field_label = 'content field';
		}
		return sprintf( 'Prepared %s update for %s.', $field_label, $post_label );
	}

	private static function begin_sse_response() {
		nocache_headers();
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, no-transform' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
		}
		@ini_set( 'zlib.output_compression', '0' );
		if ( function_exists( 'ob_implicit_flush' ) ) {
			ob_implicit_flush( true );
		}
	}

	private static function emit_sse_event( $event, array $payload = [] ) {
		$event_name = sanitize_key( (string) $event );
		if ( '' === $event_name ) {
			$event_name = 'message';
		}

		$json = wp_json_encode( $payload );
		if ( false === $json ) {
			$json = '{}';
		}

		echo 'event: ' . $event_name . "\n";
		$lines = preg_split( "/\r\n|\n|\r/", (string) $json );
		foreach ( $lines as $line ) {
			echo 'data: ' . $line . "\n";
		}
		echo "\n";

		@ob_flush();
		flush();
	}

	private static function stream_plan_response_data( $result ) {
		if ( $result instanceof WP_REST_Response ) {
			$data = $result->get_data();
			return is_array( $data ) ? $data : [];
		}
		if ( is_array( $result ) ) {
			return $result;
		}
		return [];
	}

	private static function set_plan_stream_callback( $callback = null ) {
		self::$plan_stream_callback = is_callable( $callback ) ? $callback : null;
		self::$plan_stream_last_heartbeat_at = 0.0;
	}

	private static function has_plan_stream_callback() {
		return is_callable( self::$plan_stream_callback );
	}

	private static function emit_plan_stream_heartbeat( $message = 'Still working…', $minimum_interval = 4.0 ) {
		if ( ! self::has_plan_stream_callback() ) {
			return;
		}

		$now = microtime( true );
		$interval = max( 0.0, (float) $minimum_interval );
		if ( self::$plan_stream_last_heartbeat_at > 0 && ( $now - self::$plan_stream_last_heartbeat_at ) < $interval ) {
			return;
		}

		self::$plan_stream_last_heartbeat_at = $now;
		self::emit_sse_event(
			'heartbeat',
			[
				'message' => sanitize_text_field( (string) $message ),
				'at' => gmdate( 'c' ),
			]
		);
	}

	public static function handle_ai_query_stream_event( $event ) {
		if ( ! self::has_plan_stream_callback() ) {
			return;
		}

		if ( is_object( $event ) && method_exists( $event, 'to_array' ) ) {
			$event = $event->to_array();
		}
		if ( ! is_array( $event ) ) {
			self::emit_plan_stream_heartbeat( 'Still working…', 8.0 );
			return;
		}

		$type = sanitize_key( (string) ( $event['type'] ?? '' ) );
		$subtype = sanitize_key( (string) ( $event['subtype'] ?? '' ) );
		$content = '';
		if ( is_string( $event['data'] ?? null ) || is_numeric( $event['data'] ?? null ) ) {
			$content = sanitize_text_field( (string) $event['data'] );
		}

		if ( 'status' === $subtype ) {
			self::emit_plan_stream_heartbeat( '' !== $content ? $content : 'Planner is still working…', 0.0 );
			return;
		}

		if ( 'heartbeat' === $subtype ) {
			self::emit_plan_stream_heartbeat( '' !== $content ? $content : 'Still working…', 0.0 );
			return;
		}

		if ( 'error' === $type ) {
			self::emit_plan_stream_heartbeat( '' !== $content ? $content : 'Planner encountered a temporary issue and is retrying…', 1.5 );
			return;
		}

		$message = 'Still working…';
		if ( 'thinking' === $subtype ) {
			$message = 'Reasoning through the request…';
		} elseif ( 'tool_call' === $subtype ) {
			$message = 'Running planner tools…';
		} elseif ( 'tool_result' === $subtype ) {
			$message = 'Integrating tool results…';
		} elseif ( 'web_search' === $subtype || 'file_search' === $subtype || 'embeddings' === $subtype ) {
			$message = 'Pulling supporting context…';
		} elseif ( 'image_gen' === $subtype ) {
			$message = 'Generating supporting assets…';
		} elseif ( 'mcp_discovery' === $subtype ) {
			$message = 'Checking available tools…';
		} elseif ( 'end' === $type ) {
			$message = 'Finishing the response…';
		}

		self::emit_plan_stream_heartbeat( $message, 8.0 );
	}

	private static function emit_streamed_plan_items( array $data ) {
		$intent = sanitize_key( (string) ( $data['intent'] ?? '' ) );

		if ( 'ask' === $intent ) {
			$suggestions = is_array( $data['suggestions'] ?? null ) ? $data['suggestions'] : [];
			self::emit_sse_event(
				'suggestions_meta',
				[
					'count' => count( $suggestions ),
				]
			);
			foreach ( $suggestions as $index => $suggestion ) {
				if ( $index > 0 ) {
					usleep( 10000 );
				}
				self::emit_sse_event(
					'suggestion',
					[
						'index' => absint( $index ),
						'suggestion' => is_array( $suggestion ) ? $suggestion : [],
					]
				);
			}
			return;
		}

		if ( 'audit' === $intent ) {
			$audit = is_array( $data['audit'] ?? null ) ? $data['audit'] : [];
			$issues = is_array( $audit['issues'] ?? null ) ? $audit['issues'] : [];
			self::emit_sse_event(
				'audit_meta',
				[
					'score' => absint( $audit['score'] ?? 0 ),
					'summary' => sanitize_text_field( (string) ( $audit['summary'] ?? '' ) ),
					'issue_count' => count( $issues ),
				]
			);
			foreach ( $issues as $index => $issue ) {
				if ( $index > 0 ) {
					usleep( 10000 );
				}
				self::emit_sse_event(
					'audit_issue',
					[
						'index' => absint( $index ),
						'issue' => is_array( $issue ) ? $issue : [],
					]
				);
			}
			return;
		}

		if ( 'create' === $intent ) {
			$outline = is_array( $data['outline'] ?? null ) ? $data['outline'] : [];
			self::emit_sse_event(
				'outline_meta',
				[
					'title' => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
					'template_mode' => sanitize_key( (string) ( $data['template_mode'] ?? '' ) ),
					'template_label' => sanitize_text_field( (string) ( $data['template_label'] ?? '' ) ),
					'template_rationale' => sanitize_text_field( (string) ( $data['template_rationale'] ?? '' ) ),
					'section_count' => count( $outline ),
				]
			);
			foreach ( $outline as $index => $section ) {
				if ( $index > 0 ) {
					usleep( 10000 );
				}
				self::emit_sse_event(
					'outline_section',
					[
						'index' => absint( $index ),
						'section' => is_array( $section ) ? $section : [],
					]
				);
			}
		}
	}

	public static function plan_block_change_stream( WP_REST_Request $request ) {
		$plan_throttle = self::check_plan_rate_limit();
		if ( true !== $plan_throttle ) {
			return self::build_plan_rate_limit_response( $plan_throttle, 'plan_stream' );
		}

		if ( ! self::is_plan_streaming_enabled() || headers_sent() ) {
			return self::plan_block_change( $request );
		}

		self::begin_sse_response();
		self::emit_sse_event(
			'stage',
			[
				'stage' => 'parsing',
				'message' => 'Parsing request…',
			]
		);
		self::emit_sse_event(
			'stage',
			[
				'stage' => 'ai',
				'message' => 'Building your plan…',
			]
		);

		self::set_plan_stream_callback( [ __CLASS__, 'handle_ai_query_stream_event' ] );
		try {
			$result = self::plan_block_change( $request );
		} finally {
			self::set_plan_stream_callback( null );
		}
		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status = is_array( $error_data ) ? absint( $error_data['status'] ?? 500 ) : 500;
			self::emit_sse_event(
				'error',
				[
					'code' => (string) $result->get_error_code(),
					'message' => (string) $result->get_error_message(),
					'status' => $status > 0 ? $status : 500,
				]
			);
			self::emit_sse_event( 'done', [ 'ok' => false ] );
			exit;
		}

		$data = self::stream_plan_response_data( $result );
		self::emit_streamed_plan_items( $data );
		self::emit_sse_event(
			'result',
			[
				'response' => $data,
			]
		);
		self::emit_sse_event( 'done', [ 'ok' => true ] );
		exit;
	}

	private static function get_ai_provider() {
		$provider = self::get_options()['ai_provider'] ?? self::AI_PROVIDER_DEFAULT;
		$provider_constant = self::get_config_constant( 'SAE_AI_PROVIDER', 'STRUO_AI_PROVIDER' );
		if ( null !== $provider_constant ) {
			$provider = $provider_constant;
		}
		$provider = sanitize_key( (string) apply_filters( 'struo_provider', $provider ) );
		return in_array( $provider, [ 'auto', 'ai_engine', 'openai' ], true ) ? $provider : self::AI_PROVIDER_DEFAULT;
	}

	/**
	 * Resolved planner hop. Auto and the AI Engine pin use the WordPress
	 * AI Client only when a text_generation model exists. Otherwise Auto
	 * falls through to AI Engine, then the OpenAI-compatible HTTP adapter.
	 * The `openai` pin always keeps HTTP.
	 */
	private static function get_ai_planner_backend() {
		$provider = self::get_ai_provider();
		if ( 'openai' === $provider ) {
			return 'openai';
		}

		if ( self::wp_ai_client_has_text_generation() ) {
			return 'client';
		}

		global $mwai;
		if ( is_object( $mwai ) && method_exists( $mwai, 'simpleJsonQuery' ) ) {
			return 'ai_engine';
		}

		return 'openai';
	}

	/**
	 * True when wp_ai_client_prompt() can actually generate text. Function
	 * existence is not enough: an unconfigured Client throws
	 * "No models found that support text_generation".
	 */
	private static function wp_ai_client_has_text_generation() {
		if ( null !== self::$client_text_generation ) {
			return self::$client_text_generation;
		}

		self::$client_text_generation = false;
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		try {
			$builder = wp_ai_client_prompt( 'ping' );
			if ( ! is_object( $builder ) || ! method_exists( $builder, 'is_supported_for_text_generation' ) ) {
				return false;
			}
			$supported = $builder->is_supported_for_text_generation();
			self::$client_text_generation = true === $supported;
		} catch ( \Throwable $exception ) {
			self::$client_text_generation = false;
		}

		return self::$client_text_generation;
	}

	private static function wp_connectors_available() {
		return function_exists( 'wp_get_connectors' );
	}

	private static function get_openai_api_key() {
		$key_constant = self::get_config_constant( 'SAE_OPENAI_API_KEY', 'STRUO_OPENAI_API_KEY' );
		if ( null !== $key_constant && '' !== (string) $key_constant ) {
			return (string) $key_constant;
		}
		return (string) get_option( self::OPENAI_API_KEY_OPTION, '' );
	}

	private static function get_openai_base_url() {
		$base_url_constant = self::get_config_constant( 'SAE_OPENAI_BASE_URL', 'STRUO_OPENAI_BASE_URL' );
		if ( null !== $base_url_constant && '' !== (string) $base_url_constant ) {
			return esc_url_raw( (string) $base_url_constant );
		}
		$base_url = esc_url_raw( (string) ( self::get_options()['ai_openai_base_url'] ?? 'https://api.openai.com/v1' ) );
		return '' !== $base_url ? $base_url : 'https://api.openai.com/v1';
	}

	private static function get_openai_model() {
		$model_constant = self::get_config_constant( 'SAE_OPENAI_MODEL', 'STRUO_OPENAI_MODEL' );
		if ( null !== $model_constant && '' !== (string) $model_constant ) {
			return sanitize_text_field( (string) $model_constant );
		}
		$model = sanitize_text_field( (string) ( self::get_options()['ai_openai_model'] ?? 'gpt-4o-mini' ) );
		return '' !== $model ? $model : 'gpt-4o-mini';
	}

	/**
	 * Chat Completions is the default HTTP hop. Muse Spark (OpenCode Zen)
	 * answers on /responses and 500s on /chat/completions.
	 */
	private static function get_openai_http_api_style( $model ) {
		$constant = self::get_config_constant( 'SAE_OPENAI_API_STYLE', 'STRUO_OPENAI_API_STYLE' );
		if ( null !== $constant && '' !== (string) $constant ) {
			$style = sanitize_key( (string) $constant );
			if ( in_array( $style, [ 'chat', 'responses' ], true ) ) {
				return $style;
			}
		}
		$model = strtolower( (string) $model );
		if ( 0 === strpos( $model, 'muse-spark' ) ) {
			return 'responses';
		}
		return 'chat';
	}

	private static function openai_responses_output_text( $decoded ) {
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		if ( isset( $decoded['output_text'] ) && is_string( $decoded['output_text'] ) && '' !== $decoded['output_text'] ) {
			return $decoded['output_text'];
		}
		$chunks = [];
		foreach ( (array) ( $decoded['output'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			foreach ( (array) ( $item['content'] ?? [] ) as $part ) {
				if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
					$chunks[] = $part['text'];
				}
			}
		}
		return implode( '', $chunks );
	}

	private static function decode_openai_json_content( $content, $invalid_message ) {
		$content = trim( (string) $content );
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/s', $content, $matches ) ) {
			$content = trim( (string) $matches[1] );
		}
		$result = json_decode( $content, true );
		if ( is_array( $result ) ) {
			return $result;
		}
		$start = strpos( $content, '{' );
		$end = strrpos( $content, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$result = json_decode( substr( $content, $start, $end - $start + 1 ), true );
			if ( is_array( $result ) ) {
				return $result;
			}
		}
		return new WP_Error( 'sae_planner_invalid', $invalid_message, [ 'status' => 502 ] );
	}

	/**
	 * Approved provider hosts: api.openai.com, openrouter.ai, plus the
	 * configured host when it comes from a constant. Integrations may
	 * extend via the dual-tag filter.
	 */
	private static function get_allowed_provider_hosts() {
		$hosts = [ 'api.openai.com', 'openrouter.ai' ];

		$base_url_constant = self::get_config_constant( 'SAE_OPENAI_BASE_URL', 'STRUO_OPENAI_BASE_URL' );
		if ( null !== $base_url_constant && '' !== (string) $base_url_constant ) {
			$parsed = @parse_url( esc_url_raw( (string) $base_url_constant ) );
			if ( is_array( $parsed ) && ! empty( $parsed['host'] ) ) {
				$hosts[] = strtolower( (string) $parsed['host'] );
			}
		}

		return array_values( array_unique( array_map( 'strtolower', array_filter( array_map( 'trim', $hosts ) ) ) ) );
	}

	private static function provider_guard_config() {
		return [
			'allow_insecure' => (bool) self::get_config_constant( 'STRUO_ALLOW_INSECURE_PROVIDER_URL', 'STRUO_ALLOW_INSECURE_PROVIDER_URL' ),
			'allow_private'  => (bool) self::get_config_constant( 'SAE_ALLOW_PRIVATE_PROVIDER_URL', 'STRUO_ALLOW_PRIVATE_PROVIDER_URL' ),
			'allowed_hosts'  => self::get_allowed_provider_hosts(),
		];
	}

	/**
	 * Validate the provider destination WITHOUT auditing (health/status).
	 */
	private static function inspect_provider_destination( $base_url ) {
		return Struo_Provider_Url_Guard::validate(
			$base_url,
			self::provider_guard_config(),
			null
		);
	}

	/**
	 * Validate the provider destination before sending anything, auditing
	 * every refusal (no key material in the row).
	 */
	private static function validate_provider_destination( $base_url ) {
		$result = self::inspect_provider_destination( $base_url );

		if ( ! $result['ok'] ) {
			self::audit_log(
				'provider_url_refused',
				[
					'code' => sanitize_key( (string) $result['code'] ),
					'host' => sanitize_text_field( (string) $result['host'] ),
				]
			);
			return new WP_Error(
				'sae_provider_url_refused',
				sprintf( 'Provider destination refused (%s): %s', $result['code'], $result['message'] ),
				[ 'status' => 503 ]
			);
		}

		return $result;
	}

	/**
	 * Where the OpenAI key comes from: wp-config constant or DB option.
	 */
	private static function openai_key_source() {
		$key_constant = self::get_config_constant( 'SAE_OPENAI_API_KEY', 'STRUO_OPENAI_API_KEY' );
		if ( null !== $key_constant && '' !== (string) $key_constant ) {
			return 'constant';
		}
		return '' !== (string) get_option( self::OPENAI_API_KEY_OPTION, '' ) ? 'option' : 'none';
	}

	/**
	 * Bind a stored API key that has no host yet to the current destination.
	 * Existing installs keep working after this option is introduced; a later
	 * host change still refuses until the key is re-saved.
	 */
	private static function maybe_bind_unbound_provider_key_host() {
		if ( '' !== strtolower( trim( (string) get_option( self::OPENAI_KEY_HOST_OPTION, '' ) ) ) ) {
			return;
		}
		if ( '' === (string) get_option( self::OPENAI_API_KEY_OPTION, '' ) ) {
			return;
		}
		$parsed = @parse_url( self::get_openai_base_url() );
		if ( is_array( $parsed ) && ! empty( $parsed['host'] ) ) {
			update_option( self::OPENAI_KEY_HOST_OPTION, strtolower( (string) $parsed['host'] ), false );
		}
	}

	/**
	 * A DB-stored key is bound to the host it was saved for. If the base URL
	 * host changed, the key is NOT sent until an admin re-saves it.
	 * Constant-provided keys are exempt but logged.
	 */
	private static function check_provider_key_host( $host ) {
		$key_source = self::openai_key_source();
		if ( 'none' === $key_source ) {
			return true;
		}

		$saved_host = strtolower( trim( (string) get_option( self::OPENAI_KEY_HOST_OPTION, '' ) ) );
		if ( '' === $saved_host && 'option' === $key_source ) {
			update_option( self::OPENAI_KEY_HOST_OPTION, strtolower( $host ), false );
			return true;
		}
		if ( $saved_host === strtolower( $host ) ) {
			return true;
		}

		if ( 'constant' === $key_source ) {
			self::audit_log(
				'provider_key_constant_host_mismatch',
				[
					'saved_host' => $saved_host,
					'url_host'   => strtolower( $host ),
				]
			);
			return true;
		}

		self::audit_log(
			'provider_key_host_mismatch',
			[
				'saved_host' => $saved_host,
				'url_host'   => strtolower( $host ),
			]
		);
		return new WP_Error(
			'sae_provider_key_host_mismatch',
			sprintf( 'The stored provider API key was saved for host "%s" but the destination is "%s". Re-save the API key in Settings to send requests to the new host.', $saved_host, strtolower( $host ) ),
			[ 'status' => 503 ]
		);
	}

	private static function call_openai_json_query( $prompt, array $options = [], $invalid_message = 'AI planner returned a non-JSON response.' ) {
		$api_key = self::get_openai_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'sae_planner_unavailable', 'OpenAI-compatible provider is not configured.', [ 'status' => 503 ] );
		}

		$base_url = untrailingslashit( self::get_openai_base_url() );

		$destination = self::validate_provider_destination( $base_url );
		if ( is_wp_error( $destination ) ) {
			return new WP_Error(
				'sae_planner_unavailable',
				$destination->get_error_message(),
				[
					'status' => 503,
					'reason' => $destination->get_error_code(),
				]
			);
		}
		$host = (string) $destination['host'];

		$key_check = self::check_provider_key_host( $host );
		if ( is_wp_error( $key_check ) ) {
			return new WP_Error(
				'sae_planner_unavailable',
				$key_check->get_error_message(),
				[
					'status' => 503,
					'reason' => $key_check->get_error_code(),
				]
			);
		}

		$model = self::get_openai_model();
		$api_style = self::get_openai_http_api_style( $model );
		$json_instruction = $prompt;
		$streaming = self::has_plan_stream_callback();

		if ( 'responses' === $api_style ) {
			$endpoint = $base_url . '/responses';
			$body = [
				'model' => $model,
				'input' => $json_instruction,
			];
			if ( isset( $options['temperature'] ) && is_numeric( $options['temperature'] ) ) {
				$body['temperature'] = (float) $options['temperature'];
			}
			if ( isset( $options['max_tokens'] ) && is_numeric( $options['max_tokens'] ) ) {
				$body['max_output_tokens'] = max( 1, (int) $options['max_tokens'] );
			}
			$streaming = false;
		} else {
			$endpoint = $base_url . '/chat/completions';
			$body = [
				'model' => $model,
				'messages' => [
					[
						'role' => 'user',
						'content' => $json_instruction,
					],
				],
				'response_format' => [ 'type' => 'json_object' ],
			];
			if ( isset( $options['temperature'] ) && is_numeric( $options['temperature'] ) ) {
				$body['temperature'] = (float) $options['temperature'];
			}
			if ( isset( $options['max_tokens'] ) && is_numeric( $options['max_tokens'] ) ) {
				$body['max_tokens'] = max( 1, (int) $options['max_tokens'] );
			}
			if ( $streaming ) {
				$body['stream'] = true;
			}
		}

		$response = \Struo\AI\OpenAICompatibleProvider::post_json( $endpoint, $api_key, $body );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sae_planner_error', $response->get_error_message(), [ 'status' => 502 ] );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = (string) wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = sprintf( 'Provider returned HTTP %d.', $status_code );
			$decoded_error = json_decode( $raw_body, true );
			if ( is_array( $decoded_error ) && isset( $decoded_error['error']['message'] ) ) {
				$error_message .= ' ' . sanitize_text_field( (string) $decoded_error['error']['message'] );
			}
			if ( 429 === $status_code ) {
				return new WP_Error( 'sae_planner_unavailable', $error_message, [ 'status' => 503 ] );
			}
			return new WP_Error( 'sae_planner_error', $error_message, [ 'status' => 502 ] );
		}

		$content = '';
		if ( $streaming ) {
			foreach ( preg_split( "/\r\n|\n|\r/", $raw_body ) as $line ) {
				$line = trim( (string) $line );
				if ( 0 !== strpos( $line, 'data:' ) ) {
					continue;
				}
				$payload = trim( substr( $line, 5 ) );
				if ( '' === $payload || '[DONE]' === $payload ) {
					continue;
				}
				$chunk = json_decode( $payload, true );
				$delta = is_array( $chunk ) ? (string) ( $chunk['choices'][0]['delta']['content'] ?? '' ) : '';
				if ( '' !== $delta ) {
					$content .= $delta;
					self::emit_plan_stream_heartbeat( 'Generating plan…', 2.0 );
				}
			}
		} else {
			$decoded = json_decode( $raw_body, true );
			if ( 'responses' === $api_style ) {
				$content = self::openai_responses_output_text( $decoded );
			} else {
				$content = '';
			}
			if ( '' === $content && is_array( $decoded ) ) {
				$content = (string) ( $decoded['choices'][0]['message']['content'] ?? '' );
			}
		}

		return self::decode_openai_json_content( $content, $invalid_message );
	}

	private static function estimate_plan_tokens( $prompt ) {
		return max( 1, (int) ceil( strlen( (string) $prompt ) / 4 ) );
	}

	private static function plan_spend_option_key() {
		$day = function_exists( 'wp_date' ) ? wp_date( 'Ymd' ) : gmdate( 'Ymd' );
		return self::PLAN_SPEND_OPTION_PREFIX . preg_replace( '/[^0-9]/', '', (string) $day );
	}

	private static function plan_spend_reset_at() {
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		try {
			$now = new DateTimeImmutable( 'now', $tz );
			return $now->setTime( 0, 0, 0 )->modify( '+1 day' )->getTimestamp();
		} catch ( \Exception $exception ) {
			return strtotime( 'tomorrow UTC' );
		}
	}

	private static function get_plan_daily_token_cap() {
		$cap = absint( self::get_options()['ai_plan_daily_token_cap'] ?? 0 );
		$constant = self::get_config_constant( 'SAE_PLAN_DAILY_TOKEN_CAP', 'STRUO_PLAN_DAILY_TOKEN_CAP' );
		if ( null !== $constant ) {
			$cap = absint( $constant );
		}
		$cap = absint( apply_filters( 'struo_plan_daily_token_cap', $cap ) );
		return min( self::PLAN_SPEND_CAP_MAX, $cap );
	}

	private static function get_plan_spend_used() {
		return absint( get_option( self::plan_spend_option_key(), 0 ) );
	}

	private static function get_plan_spend_state() {
		$cap = self::get_plan_daily_token_cap();
		$used = self::get_plan_spend_used();
		$remaining = $cap > 0 ? max( 0, $cap - $used ) : null;

		return [
			'cap' => $cap,
			'used' => $used,
			'remaining' => $remaining,
			'reset_at' => gmdate( 'c', self::plan_spend_reset_at() ),
		];
	}

	/**
	 * Reserve estimated prompt tokens against the daily cap before any
	 * provider call. Cap 0 disables the limiter. Fail closed when the
	 * increment cannot land under the cap.
	 */
	private static function check_plan_spend_cap( $prompt ) {
		$cap = self::get_plan_daily_token_cap();
		if ( $cap <= 0 ) {
			return true;
		}

		$tokens = self::estimate_plan_tokens( $prompt );
		if ( ! self::reserve_plan_spend_tokens( $tokens, $cap ) ) {
			$used = self::get_plan_spend_used();
			$reset_at = self::plan_spend_reset_at();
			self::audit_log(
				'plan_spend_capped',
				[
					'cap' => $cap,
					'used' => $used,
					'tokens' => $tokens,
					'reset_at' => gmdate( 'c', $reset_at ),
				]
			);
			return new WP_Error(
				'sae_plan_spend_cap',
				'Daily plan token cap reached. Try again after reset or raise the cap in Settings.',
				[
					'status' => 429,
					'plan_spend_cap' => $cap,
					'plan_spend_used' => $used,
					'plan_spend_remaining' => 0,
					'plan_spend_reset_at' => gmdate( 'c', $reset_at ),
				]
			);
		}

		return true;
	}

	private static function reserve_plan_spend_tokens( $tokens, $cap ) {
		global $wpdb;

		$tokens = max( 1, absint( $tokens ) );
		$cap = absint( $cap );
		if ( $cap <= 0 ) {
			return true;
		}

		$key = self::plan_spend_option_key();
		add_option( $key, 0, '', 'no' );

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = option_value + %d WHERE option_name = %s AND option_value + %d <= %d",
				$tokens,
				$key,
				$tokens,
				$cap
			)
		);

		if ( $updated ) {
			wp_cache_delete( $key, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			return true;
		}

		return false;
	}

	private static function record_planner_hop( $backend, $model, $prompt_bytes, $latency_ms, $ok, $code = '' ) {
		update_option(
			self::PLANNER_LAST_HOP_OPTION,
			[
				'backend' => sanitize_key( (string) $backend ),
				'model' => sanitize_text_field( (string) $model ),
				'prompt_bytes' => absint( $prompt_bytes ),
				'latency_ms' => absint( $latency_ms ),
				'ok' => (bool) $ok,
				'code' => sanitize_key( (string) $code ),
				'at' => gmdate( 'c' ),
			],
			false
		);
	}

	private static function get_planner_last_hop() {
		$stored = get_option( self::PLANNER_LAST_HOP_OPTION, [] );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return null;
		}

		return [
			'backend' => sanitize_key( (string) ( $stored['backend'] ?? '' ) ),
			'model' => sanitize_text_field( (string) ( $stored['model'] ?? '' ) ),
			'prompt_bytes' => absint( $stored['prompt_bytes'] ?? 0 ),
			'latency_ms' => absint( $stored['latency_ms'] ?? 0 ),
			'ok' => ! empty( $stored['ok'] ),
			'code' => sanitize_key( (string) ( $stored['code'] ?? '' ) ),
			'at' => sanitize_text_field( (string) ( $stored['at'] ?? '' ) ),
		];
	}

	private static function call_ai_json_query( $prompt, array $options = [], $invalid_message = 'AI planner returned a non-JSON response.' ) {
		$backend = self::get_ai_planner_backend();
		$started = microtime( true );
		$model = self::describe_planner_model( $backend, $options );

		$prompt = (string) $prompt;
		if ( '' === trim( $prompt ) ) {
			self::record_planner_hop( $backend, $model, 0, 0, false, 'sae_planner_invalid' );
			return new WP_Error( 'sae_planner_invalid', 'AI prompt was empty.', [ 'status' => 400 ] );
		}

		$prompt = (string) apply_filters( 'struo_ai_outbound_prompt',
			$prompt
		);
		if ( '' === trim( $prompt ) ) {
			self::record_planner_hop( $backend, $model, 0, 0, false, 'sae_planner_unavailable' );
			return new WP_Error( 'sae_planner_unavailable', 'AI prompt was blocked by outbound redaction.', [ 'status' => 503 ] );
		}

		$prompt .= "\nYour reply must be a formatted JSON.";
		$prompt_bytes = strlen( $prompt );
		if ( $prompt_bytes > self::PLAN_MAX_PROMPT_LENGTH ) {
			self::record_planner_hop( $backend, $model, $prompt_bytes, 0, false, 'sae_planner_invalid' );
			return new WP_Error( 'sae_planner_invalid', 'AI prompt exceeded the outbound size limit.', [ 'status' => 400 ] );
		}

		self::note_eval_disclosure_spend( 'provider' );

		$spend = self::check_plan_spend_cap( $prompt );
		if ( is_wp_error( $spend ) ) {
			self::record_planner_hop( $backend, $model, $prompt_bytes, 0, false, $spend->get_error_code() );
			return $spend;
		}

		$result = self::dispatch_ai_json_query( $backend, $prompt, $options, $invalid_message );
		$latency_ms = (int) max( 0, round( ( microtime( true ) - $started ) * 1000 ) );
		$ok = ! is_wp_error( $result );
		self::record_planner_hop(
			$backend,
			$model,
			$prompt_bytes,
			$latency_ms,
			$ok,
			$ok ? '' : ( is_wp_error( $result ) ? (string) $result->get_error_code() : '' )
		);

		return $result;
	}

	private static function planner_provider_error( \Throwable $exception ) {
		self::audit_log(
			'planner_provider_exception',
			[
				'class' => sanitize_text_field( get_class( $exception ) ),
				'message' => sanitize_text_field( $exception->getMessage() ),
			]
		);

		return new WP_Error( 'sae_planner_error', 'AI planner failed.', [ 'status' => 502 ] );
	}

	private static function describe_planner_model( $backend, array $options = [] ) {
		$requested = sanitize_text_field( (string) ( $options['model'] ?? '' ) );
		if ( '' !== $requested ) {
			return $requested;
		}
		if ( 'openai' === $backend ) {
			return self::get_openai_model();
		}
		return '';
	}

	private static function dispatch_ai_json_query( $backend, $prompt, array $options = [], $invalid_message = 'AI planner returned a non-JSON response.' ) {
		if ( 'client' === $backend ) {
			return self::call_ai_client_json_query( $prompt, $options, $invalid_message );
		}
		if ( 'openai' === $backend ) {
			return self::call_openai_json_query( $prompt, $options, $invalid_message );
		}

		global $mwai, $mwai_core;

		if ( ! is_object( $mwai ) || ! method_exists( $mwai, 'simpleJsonQuery' ) ) {
			return new WP_Error( 'sae_planner_unavailable', 'AI planner is unavailable in this environment.', [ 'status' => 503 ] );
		}

		$use_streaming_query = self::has_plan_stream_callback()
			&& is_object( $mwai_core )
			&& method_exists( $mwai_core, 'run_query' )
			&& class_exists( 'Meow_MWAI_Query_Text' );

		try {
			if ( $use_streaming_query ) {
				$query = new Meow_MWAI_Query_Text( $prompt );
				$query->inject_params( $options );
				$query->set_response_format( 'json' );

				$default_env_id = method_exists( $mwai_core, 'get_option' ) ? (string) $mwai_core->get_option( 'ai_json_default_env' ) : '';
				$default_model = method_exists( $mwai_core, 'get_option' ) ? (string) $mwai_core->get_option( 'ai_json_default_model' ) : '';
				if ( '' !== $default_env_id && '' === (string) $query->envId ) {
					$query->set_env_id( $default_env_id );
				}
				if ( '' !== $default_model && '' === (string) $query->get_model() ) {
					$query->set_model( $default_model );
				} elseif ( '' === (string) $query->get_model() && defined( 'MWAI_FALLBACK_MODEL_JSON' ) ) {
					$query->set_model( MWAI_FALLBACK_MODEL_JSON );
				}

				$reply = $mwai_core->run_query( $query, [ __CLASS__, 'handle_ai_query_stream_event' ] );
				$result = json_decode( is_object( $reply ) ? (string) ( $reply->result ?? '' ) : '', true );
			} else {
				$result = $mwai->simpleJsonQuery( $prompt, null, null, $options );
			}
		} catch ( \Throwable $exception ) {
			return self::planner_provider_error( $exception );
		}

		if ( ! is_array( $result ) ) {
			return new WP_Error( 'sae_planner_invalid', $invalid_message, [ 'status' => 502 ] );
		}

		return $result;
	}

	private static function call_ai_client_json_query( $prompt, array $options = [], $invalid_message = 'AI planner returned a non-JSON response.' ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'sae_planner_unavailable', 'WordPress AI Client is not available in this environment.', [ 'status' => 503 ] );
		}

		$full_prompt = (string) $prompt;

		if ( self::has_plan_stream_callback() ) {
			self::emit_plan_stream_heartbeat( 'Generating plan…', 2.0 );
		}

		self::$ai_client_prompt_active = true;
		self::$ai_client_outbound_prompt = $full_prompt;

		try {
			$builder = wp_ai_client_prompt( $full_prompt );
			if ( isset( $options['temperature'] ) && is_numeric( $options['temperature'] ) ) {
				$builder = $builder->using_temperature( (float) $options['temperature'] );
			}
			$max_tokens = $options['max_tokens'] ?? ( $options['maxTokens'] ?? null );
			if ( is_numeric( $max_tokens ) ) {
				$builder = $builder->using_max_tokens( max( 1, (int) $max_tokens ) );
			}
			$builder = $builder->as_json_response();
			$text = $builder->generate_text();
		} catch ( \Throwable $exception ) {
			return self::planner_provider_error( $exception );
		} finally {
			self::$ai_client_prompt_active = false;
			self::$ai_client_outbound_prompt = '';
		}

		if ( is_wp_error( $text ) ) {
			$code = $text->get_error_code();
			$data = $text->get_error_data();
			$status = is_array( $data ) ? absint( $data['status'] ?? 0 ) : 0;
			if ( 'prompt_prevented' === $code || 429 === $status ) {
				return new WP_Error( 'sae_planner_unavailable', $text->get_error_message(), [ 'status' => 503 ] );
			}
			return new WP_Error( 'sae_planner_error', $text->get_error_message(), [ 'status' => 502 ] );
		}

		$result = json_decode( trim( (string) $text ), true );
		if ( ! is_array( $result ) ) {
			return new WP_Error( 'sae_planner_invalid', $invalid_message, [ 'status' => 502 ] );
		}

		return $result;
	}

	/**
	 * Outbound redaction hook for wp_ai_client_prompt(). Only inspects
	 * prompts Struo is currently sending so other plugins are unaffected.
	 *
	 * @param bool                           $prevent Whether to prevent the prompt.
	 * @param WP_AI_Client_Prompt_Builder    $builder Read-only clone of the builder.
	 * @return bool
	 */
	public static function maybe_prevent_ai_client_prompt( $prevent, $builder ) {
		unset( $builder );
		if ( $prevent ) {
			return true;
		}
		if ( ! self::$ai_client_prompt_active ) {
			return false;
		}

		$prompt = (string) self::$ai_client_outbound_prompt;
		if ( '' === trim( $prompt ) ) {
			return true;
		}
		if ( strlen( $prompt ) > self::PLAN_MAX_PROMPT_LENGTH ) {
			return true;
		}

		return (bool) apply_filters( 'struo_prevent_ai_prompt',
			false,
			$prompt
		);
	}

	public static function plan_block_change( WP_REST_Request $request ) {
		$origin = self::request_token_origin( $request );
		$context = self::request_token_context( $request );
		$plan_throttle = self::check_plan_rate_limit();
		if ( true !== $plan_throttle ) {
			return self::build_plan_rate_limit_response( $plan_throttle, 'plan' );
		}

		$payload = self::get_request_payload( $request );
		$url_post_id = self::request_post_id( $request );
		if ( $url_post_id > 0 && absint( $payload['post_id'] ?? 0 ) <= 0 ) {
			$payload['post_id'] = $url_post_id;
		}
		$request_text = self::sanitize_plan_request_text(
			$payload['request'] ?? ( $payload['text'] ?? ( $payload['message'] ?? '' ) )
		);
		$plan = is_array( $payload['plan'] ?? null ) ? $payload['plan'] : [];
		$explicit_post_id = self::resolve_explicit_mutation_post_id( $payload, $plan, $url_post_id );
		if ( $explicit_post_id > 0 ) {
			$plan['post_id'] = $explicit_post_id;
		}
		$planner_source = 'direct';
		$planner_warnings = [];
		$planner_meta = [];

		if ( empty( $plan ) && ! empty( $request_text ) && '{' === substr( ltrim( $request_text ), 0, 1 ) ) {
			$decoded_plan = json_decode( $request_text, true );
			if ( ! is_array( $decoded_plan ) ) {
				return new WP_Error( 'sae_plan_invalid_json', 'Request JSON could not be parsed.', [ 'status' => 400 ] );
			}
			if ( isset( $decoded_plan['plan'] ) && is_array( $decoded_plan['plan'] ) ) {
				$plan = $decoded_plan['plan'];
				$decoded_request_text = self::sanitize_plan_request_text(
					$decoded_plan['request'] ?? ( $decoded_plan['text'] ?? ( $decoded_plan['message'] ?? '' ) )
				);
				if ( '' !== $decoded_request_text ) {
					$request_text = $decoded_request_text;
				}
			} else {
				$plan = $decoded_plan;
			}
			$explicit_post_id = self::resolve_explicit_mutation_post_id( $payload, $plan, $url_post_id );
			if ( $explicit_post_id > 0 ) {
				$plan['post_id'] = $explicit_post_id;
			}
			$planner_source = 'inline_json';
		}

		if ( ! empty( $request_text ) && empty( $plan['operation'] ) ) {
			$deterministic_plan = self::infer_deterministic_plan( $request_text );
			if ( is_wp_error( $deterministic_plan ) ) {
				return $deterministic_plan;
			}
			if ( ! empty( $deterministic_plan ) ) {
				$plan = array_replace_recursive( $deterministic_plan, $plan );
				if ( $explicit_post_id > 0 ) {
					$plan['post_id'] = $explicit_post_id;
				}
				$planner_source = 'deterministic';
			}
		}

		$forced_intent = sanitize_key( (string) ( $payload['intent'] ?? '' ) );
		if ( in_array( $forced_intent, [ 'ask', 'audit' ], true ) && self::plan_has_mutation_directives( $plan ) ) {
			$forced_intent = '';
		}
		if ( in_array( $forced_intent, [ 'ask', 'audit', 'create' ], true ) ) {
			$intent = $forced_intent;
		} else {
			$intent = self::infer_request_intent( $request_text, $plan );
		}
		if ( self::bundle_request_forbids_create( $payload, $request_text, $intent ) ) {
			return new WP_Error(
				'sae_bundle_create_forbidden',
				'Page create cannot run as a multi-page bundle.',
				[ 'status' => 400 ]
			);
		}

		$target_gate = self::assert_explicit_plan_targets_disclosable( $payload, $plan, $explicit_post_id, $request_text, $intent );
		if ( is_wp_error( $target_gate ) ) {
			return $target_gate;
		}

		$forced_cross_field = self::normalize_cross_field_name( $payload['cross_field'] ?? '' );
		if ( '' !== $forced_cross_field && ! self::request_wants_bundle( $payload, $request_text, sanitize_key( (string) ( $payload['intent'] ?? '' ) ) ) ) {
			$cross_field_post_id = absint( $payload['post_id'] ?? ( $plan['post_id'] ?? 0 ) );
			if ( $cross_field_post_id <= 0 ) {
				return new WP_Error( 'sae_cross_field_no_post', 'Post ID required for cross-field generation.', [ 'status' => 400 ] );
			}

			$cross_field_response = self::prepare_cross_field_response( $cross_field_post_id, $forced_cross_field, $request_text, $payload, $origin, $context );
			if ( is_wp_error( $cross_field_response ) ) {
				return $cross_field_response;
			}

			$cross_field_response = self::attach_durable_plan_envelope( $origin, $cross_field_response );

			self::audit_log(
				'plan_cross_field',
				[
					'post_id' => absint( $cross_field_response['post']['post_id'] ?? $cross_field_post_id ),
					'field' => sanitize_key( (string) ( $cross_field_response['payload']['field'] ?? $forced_cross_field ) ),
					'source' => (string) ( $cross_field_response['planner']['source'] ?? 'ai_engine' ),
					'origin' => self::normalize_token_origin( $origin ),
				]
			);
			self::append_session_context_entry(
				(string) ( $cross_field_response['request'] ?? $request_text ),
				'do',
				(string) ( $cross_field_response['planner']['source'] ?? 'ai_engine' ),
				self::summarize_cross_field_session_entry( $cross_field_response )
			);

			return $cross_field_response;
		}

		$rag_context = self::retrieve_rag_context( $request_text );
		if ( 'audit' === (string) $intent ) {
			$audit_response = self::prepare_audit_response( $request_text, $plan, $payload, $rag_context );
			if ( is_wp_error( $audit_response ) ) {
				return $audit_response;
			}

			self::audit_log(
				'plan_audit',
				[
					'post_id' => absint( $audit_response['post']['post_id'] ?? 0 ),
					'source' => (string) ( $audit_response['planner']['source'] ?? 'ai_engine' ),
					'issue_count' => count( is_array( $audit_response['audit']['issues'] ?? null ) ? $audit_response['audit']['issues'] : [] ),
					'score' => absint( $audit_response['audit']['score'] ?? 0 ),
					'origin' => self::normalize_token_origin( $origin ),
				]
			);
			self::append_session_context_entry(
				(string) ( $audit_response['request'] ?? $request_text ),
				'audit',
				(string) ( $audit_response['planner']['source'] ?? 'ai_engine' ),
				self::summarize_audit_session_entry( $audit_response )
			);

			return $audit_response;
		}
		if ( self::should_handle_ask_intent( $intent, $plan, $request_text ) ) {
			$ask_response = self::prepare_ask_intent_response( $request_text, $plan, $payload );
			if ( is_wp_error( $ask_response ) ) {
				return $ask_response;
			}

			self::audit_log(
				'plan_ask',
				[
					'post_id' => absint( $ask_response['post']['post_id'] ?? 0 ),
					'source' => (string) ( $ask_response['source'] ?? 'deterministic' ),
					'origin' => self::normalize_token_origin( $origin ),
				]
			);
			self::append_session_context_entry(
				$request_text,
				'ask',
				(string) ( $ask_response['source'] ?? 'deterministic' ),
				self::summarize_ask_session_entry( $ask_response )
			);

			return [
				'request' => $request_text,
				'intent' => 'ask',
				'planner' => [
					'source' => (string) ( $ask_response['source'] ?? 'deterministic' ),
					'ai_used' => 'ai_engine' === (string) ( $ask_response['source'] ?? '' ),
					'warnings' => is_array( $ask_response['warnings'] ?? null ) ? $ask_response['warnings'] : [],
					'vector' => self::build_vector_meta_payload( $rag_context ),
				],
				'post' => is_array( $ask_response['post'] ?? null ) ? $ask_response['post'] : [],
				'suggestions' => is_array( $ask_response['suggestions'] ?? null ) ? $ask_response['suggestions'] : [],
				'vector_sources' => self::build_vector_sources_payload( $rag_context ),
			];
		}

		if ( 'create' === (string) $intent ) {
			$create_outline = self::prepare_create_page_outline( $request_text, $payload );
			if ( is_wp_error( $create_outline ) ) {
				return $create_outline;
			}

			self::audit_log(
				'plan_create_outline',
				[
					'source' => (string) ( $create_outline['planner']['source'] ?? 'ai_engine' ),
					'sections' => count( is_array( $create_outline['outline'] ?? null ) ? $create_outline['outline'] : [] ),
					'post_type' => sanitize_key( (string) ( $create_outline['post_type'] ?? '' ) ),
					'title' => sanitize_text_field( (string) ( $create_outline['title'] ?? '' ) ),
					'origin' => self::normalize_token_origin( $origin ),
				]
			);
			self::append_session_context_entry(
				$request_text,
				'do',
				(string) ( $create_outline['planner']['source'] ?? 'ai_engine' ),
				self::summarize_create_outline_session_entry( $create_outline )
			);

			return $create_outline;
		}

		if ( self::request_wants_bundle( $payload, $request_text, $intent ) ) {
			return self::plan_bundle( $origin, $payload, $request_text, $plan, $context );
		}

		$include_dry_run = array_key_exists( 'dry_run_preview', $payload ) ? (bool) $payload['dry_run_preview'] : true;
		$compiled = self::compile_one_mutation_target(
			[
				'request_text' => $request_text,
				'plan' => $plan,
				'payload' => $payload,
				'post_id' => $explicit_post_id,
				'origin' => $origin,
				'context' => array_merge(
					$context,
					[
						'plan_only' => 'rest' === self::normalize_token_origin( $origin ),
					]
				),
				'include_dry_run' => $include_dry_run,
				'planner_source' => $planner_source,
			]
		);
		if ( is_wp_error( $compiled ) ) {
			return $compiled;
		}

		$prepared = is_array( $compiled['prepared'] ?? null ) ? $compiled['prepared'] : [];
		$dry_run = $compiled['dry_run'] ?? null;
		$planner_source = (string) ( $compiled['planner_source'] ?? $planner_source );
		$planner_meta = is_array( $compiled['planner_meta'] ?? null ) ? $compiled['planner_meta'] : [];
		$warnings = is_array( $compiled['planner_warnings'] ?? null ) ? $compiled['planner_warnings'] : [];

		$response = [
			'request' => $request_text,
			'intent' => 'do',
			'planner' => [
				'source' => $planner_source,
				'ai_used' => in_array( $planner_source, [ 'ai_engine', 'ai_rewrite', 'client', 'openai' ], true ),
				'warnings' => $warnings,
				'rewrite' => $planner_meta,
				'vector' => self::build_vector_meta_payload( $rag_context ),
			],
			'post' => $prepared['post'],
			'operation' => $prepared['operation'],
			'endpoint' => $prepared['endpoint'],
			'payload' => $prepared['payload'],
			'dry_run' => $dry_run,
			'vector_sources' => self::build_vector_sources_payload( $rag_context ),
		];

		$response = self::attach_durable_plan_envelope( $origin, $response );

		self::audit_log(
			'plan',
			[
				'post_id' => $prepared['post']['post_id'],
				'operation' => $prepared['operation'],
				'source' => $planner_source,
				'has_dry_run' => (bool) $include_dry_run,
				'origin' => self::normalize_token_origin( $origin ),
				'agent_plan_id' => is_array( $response['agent_plan'] ?? null ) ? (string) ( $response['agent_plan']['id'] ?? '' ) : '',
				'plan_id' => (string) ( $response['plan_id'] ?? '' ),
			]
		);
		self::append_session_context_entry(
			$request_text,
			'do',
			$planner_source,
			self::summarize_do_session_entry( $prepared, $planner_meta )
		);

		return $response;
	}

	public static function validate_agent_plan_id( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{16}$/', $value );
	}

	public static function list_agent_plans() {
		return [
			'ok' => true,
			'items' => self::list_agent_plan_summaries(),
			'discovery' => self::list_discovery_plan_summaries(),
		];
	}

	public static function get_agent_plan( WP_REST_Request $request ) {
		$record = self::get_durable_plan_record_for_receipt( (string) $request['id'] );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( ! self::user_can_review_agent_plan( $record ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to review this plan.', [ 'status' => 403 ] );
		}

		$response = [
			'ok' => true,
			'plan' => self::present_agent_plan_record( $record, false ),
		];
		$journal = self::present_mutation_journal( $record );
		if ( ! empty( $journal ) ) {
			$response = array_merge( $response, $journal );
		}
		$recovery = self::present_mutation_recovery( $record );
		if ( ! empty( $recovery ) ) {
			$response = array_merge( $response, $recovery );
		}

		return $response;
	}

	/**
	 * Dual-read extras for standalone mutation_v1. Bundles and page-create omit this.
	 *
	 * @param array<string, mixed> $record
	 * @return array<string, mixed>
	 */
	private static function present_mutation_journal( array $record ) {
		if ( ! class_exists( 'Struo_Mutation_Journal' ) || ! Struo_Mutation_Journal::is_journaled_record( $record ) ) {
			return [];
		}

		$id = (string) ( $record['id'] ?? '' );
		Struo_Mutation_Journal::maybe_backfill( $record );
		$json = Struo_Mutation_Journal::stored_payload_json( $id );
		$hash = Struo_Mutation_Journal::hash_payload_json( $json );
		$events = Struo_Mutation_Journal::list_for_plan( $id );
		$projection = Struo_Mutation_Journal::project( $id, $record );

		return [
			'payload_hash' => $hash,
			'events' => $events,
			'projection' => $projection,
			'readers_match' => Struo_Mutation_Journal::readers_match( $record, $projection, $hash ),
		];
	}

	/**
	 * @param array<string, mixed> $record
	 * @return array<string, mixed>
	 */
	private static function present_mutation_recovery( array $record ) {
		if ( ! class_exists( 'Struo_Mutation_Recovery' ) || ! Struo_Mutation_Recovery::is_recoverable_record( $record ) ) {
			return [];
		}

		$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
		$recovery = null;
		if ( 'applying' === $state ) {
			$classified = Struo_Mutation_Recovery::classify_applying( $record );
			$recovery = is_array( $classified ) ? $classified : null;
		}

		return [
			'recovery' => $recovery,
			'evidence' => Struo_Mutation_Recovery::evidence( $record ),
		];
	}

	/**
	 * @param array<string, mixed> $record
	 * @param string               $required_event_type Empty = last event hash.
	 * @return string|WP_Error Payload hash on match.
	 */
	private static function assert_journaled_payload_frozen( array $record, $required_event_type = '' ) {
		$backfill = Struo_Mutation_Journal::maybe_backfill( $record );
		if ( is_wp_error( $backfill ) ) {
			return $backfill;
		}

		$id = (string) ( $record['id'] ?? '' );
		$json = Struo_Mutation_Journal::stored_payload_json( $id );
		$hash = Struo_Mutation_Journal::hash_payload_json( $json );
		$events = Struo_Mutation_Journal::list_for_plan( $id );
		$expected = '';
		$required_event_type = sanitize_key( (string) $required_event_type );
		if ( '' === $required_event_type ) {
			if ( ! empty( $events ) ) {
				$last = $events[ count( $events ) - 1 ];
				$expected = (string) ( $last['payload_hash'] ?? '' );
			}
		} else {
			foreach ( $events as $event ) {
				if ( ! is_array( $event ) ) {
					continue;
				}
				if ( sanitize_key( (string) ( $event['type'] ?? '' ) ) === $required_event_type ) {
					$expected = (string) ( $event['payload_hash'] ?? '' );
				}
			}
		}

		if ( 64 !== strlen( $hash ) || 64 !== strlen( $expected ) || ! hash_equals( $hash, $expected ) ) {
			return new WP_Error(
				'sae_mutation_payload_changed',
				'Mutation payload changed after it was queued.',
				[ 'status' => 409 ]
			);
		}

		return $hash;
	}

	/**
	 * State transition plus journal append in one InnoDB transaction.
	 *
	 * @param array<string, mixed> $body
	 * @return true|WP_Error
	 */
	private static function transition_journaled_mutation( $id, $intent, array $fields, $event_type, $payload_hash, array $body = [] ) {
		global $wpdb;

		$payload_hash = sanitize_text_field( (string) $payload_hash );
		$event_type = sanitize_key( (string) $event_type );
		$needs_event = '' !== $payload_hash && class_exists( 'Struo_Mutation_Journal' );

		if ( $needs_event ) {
			$wpdb->query( 'START TRANSACTION' );
		}

		$transitioned = self::durable_plan_move( $intent, $id, $fields );
		if ( is_wp_error( $transitioned ) ) {
			if ( $needs_event ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return $transitioned;
		}

		if ( $needs_event ) {
			$appended = Struo_Mutation_Journal::append(
				$id,
				Struo_Mutation_Journal::next_seq( $id ),
				$event_type,
				$payload_hash,
				$body
			);
			if ( is_wp_error( $appended ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $appended;
			}
			$wpdb->query( 'COMMIT' );
		}

		return true;
	}

	public static function preview_agent_plan( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$record = self::get_agent_plan_record( (string) $request['id'] );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( ! self::user_can_review_agent_plan( $record ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to review this plan.', [ 'status' => 403 ] );
		}

		if ( 'bundle_v1' === sanitize_key( (string) ( $record['payload_type'] ?? '' ) ) ) {
			$envelope = self::present_bundle_plan_envelope( $record );
			self::audit_log(
				'agent_plan_preview',
				[
					'post_id' => 0,
					'operation' => 'bundle',
					'origin' => 'rest',
					'agent_plan_id' => (string) ( $record['id'] ?? '' ),
					'plan_id' => (string) ( $record['id'] ?? '' ),
					'redeemable' => false,
				]
			);

			return $envelope;
		}

		if ( 'page_spec_v1' === sanitize_key( (string) ( $record['payload_type'] ?? '' ) ) ) {
			$envelope = self::present_page_spec_plan_envelope( $record );
			self::audit_log(
				'agent_plan_preview',
				[
					'post_id' => 0,
					'operation' => 'create',
					'origin' => 'rest',
					'agent_plan_id' => (string) ( $record['id'] ?? '' ),
					'plan_id' => (string) ( $record['id'] ?? '' ),
					'redeemable' => false,
				]
			);
			return $envelope;
		}

		$prepared = [
			'post' => is_array( $record['post'] ?? null ) ? $record['post'] : [ 'post_id' => absint( $record['post_id'] ?? 0 ) ],
			'operation' => sanitize_key( (string) ( $record['operation'] ?? '' ) ),
			'endpoint' => (string) ( $record['endpoint'] ?? '' ),
			'payload' => is_array( $record['payload'] ?? null ) ? $record['payload'] : [],
		];
		$preview = is_array( $record['preview'] ?? null ) ? $record['preview'] : null;
		if ( null === $preview ) {
			$payload = self::get_request_payload( $request );
			$response_mode = self::resolve_response_mode( is_array( $payload ) ? $payload : [] );
			$preview = self::run_plan_dry_run(
				$prepared,
				$response_mode,
				'rest',
				[
					'plan_only' => true,
					'restore_content' => (string) ( $record['base_content'] ?? '' ),
				]
			);
			if ( is_wp_error( $preview ) ) {
				return $preview;
			}
		}
		$preview = self::non_redeemable_plan_confirmation( 'rest', is_array( $preview ) ? $preview : [] );

		self::audit_log(
			'agent_plan_preview',
			[
				'post_id' => absint( $record['post_id'] ?? 0 ),
				'operation' => $prepared['operation'],
				'origin' => 'rest',
				'agent_plan_id' => (string) ( $record['id'] ?? '' ),
				'plan_id' => (string) ( $record['id'] ?? '' ),
				'redeemable' => false,
			]
		);

		return [
			'request' => (string) ( $record['request'] ?? '' ),
			'intent' => 'do',
			'planner' => [
				'source' => 'agent_plan',
				'ai_used' => false,
				'warnings' => [],
			],
			'post' => $prepared['post'],
			'operation' => $prepared['operation'],
			'endpoint' => $prepared['endpoint'],
			'payload' => $prepared['payload'],
			'dry_run' => self::strip_redeemable_fields( $preview ),
			'plan_id' => (string) ( $record['id'] ?? '' ),
			'plan_state' => sanitize_key( (string) ( $record['state'] ?? 'planned' ) ),
			'agent_plan' => [
				'id' => (string) ( $record['id'] ?? '' ),
				'queued' => true,
			],
		];
	}

	public static function dismiss_agent_plan( WP_REST_Request $request ) {
		$record = self::get_agent_plan_record( (string) $request['id'] );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( ! self::user_can_review_agent_plan( $record ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to dismiss this plan.', [ 'status' => 403 ] );
		}

		if ( 'bundle_v1' === sanitize_key( (string) ( $record['payload_type'] ?? '' ) ) ) {
			$child_ids = is_array( $record['payload']['child_ids'] ?? null ) ? $record['payload']['child_ids'] : [];
			self::cancel_durable_plan_ids( $child_ids );
		}

		$id = (string) ( $record['id'] ?? '' );
		$dismissed = self::durable_plan_move( 'cancel', $id );
		if ( is_wp_error( $dismissed ) ) {
			return $dismissed;
		}

		self::audit_log(
			'agent_plan_dismiss',
			[
				'post_id' => absint( $record['post_id'] ?? 0 ),
				'operation' => sanitize_key( (string) ( $record['operation'] ?? '' ) ),
				'origin' => 'rest',
				'agent_plan_id' => $id,
				'plan_id' => $id,
			]
		);

		return [
			'ok' => true,
			'dismissed' => true,
			'id' => $id,
		];
	}

	public static function approve_agent_plan( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$id = (string) $request['id'];
		$record = self::get_agent_plan_record( $id );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}

		if ( 'bundle_v1' === sanitize_key( (string) ( $record['payload_type'] ?? '' ) ) ) {
			return self::approve_bundle_plan( $id, $record, $request );
		}

		$bundle_child = self::ensure_bundle_child_not_directly_approved( $record );
		if ( is_wp_error( $bundle_child ) ) {
			return $bundle_child;
		}

		$mutation_hash = '';
		if ( class_exists( 'Struo_Mutation_Journal' ) && Struo_Mutation_Journal::is_journaled_record( $record ) ) {
			$frozen = self::assert_journaled_payload_frozen( $record );
			if ( is_wp_error( $frozen ) ) {
				return $frozen;
			}
			$mutation_hash = (string) $frozen;
		}

		$approved = self::transition_journaled_mutation(
			$id,
			'approve',
			[
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			],
			'approved',
			$mutation_hash,
			[ 'state' => 'approved' ]
		);
		if ( is_wp_error( $approved ) ) {
			return $approved;
		}

		self::audit_log(
			'agent_plan_approve',
			[
				'post_id' => absint( $record['post_id'] ?? 0 ),
				'operation' => sanitize_key( (string) ( $record['operation'] ?? '' ) ),
				'origin' => 'rest',
				'plan_id' => $id,
			]
		);

		$record = self::get_agent_plan_record( $id );

		return [
			'ok' => true,
			'plan_id' => $id,
			'state' => (string) ( $record['state'] ?? 'approved' ),
			'plan' => self::present_agent_plan_record( $record, false ),
		];
	}

	public static function apply_agent_plan( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return $kill_switch;
		}

		$id = (string) $request['id'];
		$record = self::get_durable_plan_record_unchecked( $id );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}

		if ( 'bundle_v1' === sanitize_key( (string) ( $record['payload_type'] ?? '' ) ) ) {
			return new WP_Error(
				'sae_bundle_apply_parent',
				'Apply a selected page, not the whole bundle.',
				[ 'status' => 409 ]
			);
		}

		if ( 'applied' === sanitize_key( (string) ( $record['state'] ?? '' ) ) ) {
			return [
				'ok' => true,
				'plan_id' => $id,
				'state' => 'applied',
				'outcome' => 'already_applied',
			];
		}

		$bundle_child = self::ensure_bundle_child_writable( $record );
		if ( is_wp_error( $bundle_child ) ) {
			return $bundle_child;
		}

		if ( 'applying' === sanitize_key( (string) ( $record['state'] ?? '' ) )
			&& class_exists( 'Struo_Mutation_Recovery' )
			&& Struo_Mutation_Recovery::is_recoverable_record( $record ) ) {
			return self::recover_journaled_apply( $id, $record, $request );
		}

		if ( 'approved' !== (string) ( $record['state'] ?? '' ) ) {
			return new WP_Error( 'sae_plan_not_approved', 'Plan must be approved before apply.', [ 'status' => 409 ] );
		}

		$post_id = absint( $record['post_id'] ?? 0 );
		$base_hash = (string) ( $record['base_content_hash'] ?? '' );
		$operation_name = sanitize_key( (string) ( $record['operation'] ?? '' ) );
		$payload_type = sanitize_key( (string) ( $record['payload_type'] ?? 'mutation_v1' ) );
		$recoverable = class_exists( 'Struo_Mutation_Recovery' ) && Struo_Mutation_Recovery::is_recoverable_record( $record );
		if ( 'page_spec_v1' === $payload_type ) {
			$serialized = (string) ( $record['payload']['serialized_content'] ?? '' );
			$content_hash = sanitize_text_field( (string) ( $record['payload']['content_hash'] ?? '' ) );
			$computed = '' === $serialized ? '' : hash( 'sha256', $serialized );
			if ( '' === $base_hash || '' === $content_hash || '' === $computed
				|| ! hash_equals( $base_hash, $content_hash )
				|| ! hash_equals( $content_hash, $computed ) ) {
				return new WP_Error( 'sae_plan_content_conflict', 'PageSpec content changed since the plan was created.', [ 'status' => 409 ] );
			}
		} elseif ( 'create' === $operation_name ) {
			$idempotency_key = sanitize_text_field( (string) ( $record['payload']['idempotency_key'] ?? '' ) );
			$plan_snapshot = self::get_create_plan_snapshot( $idempotency_key );
			if ( ! is_array( $plan_snapshot ) ) {
				return new WP_Error( 'sae_create_plan_expired', 'Create plan is missing or expired. Generate a new plan.', [ 'status' => 409 ] );
			}
			$current_hash = self::hash_create_plan_snapshot( $plan_snapshot );
			if ( '' === $base_hash || '' === $current_hash || ! hash_equals( $base_hash, $current_hash ) ) {
				return new WP_Error( 'sae_plan_content_conflict', 'Post content changed since the plan was created.', [ 'status' => 409 ] );
			}
		} elseif ( $recoverable ) {
			$current_hash = Struo_Mutation_Recovery::hash_witness( Struo_Mutation_Recovery::current_witness( $record ) );
			if ( '' === $base_hash || '' === $current_hash || ! hash_equals( $base_hash, $current_hash ) ) {
				return new WP_Error( 'sae_plan_content_conflict', 'Post content changed since the plan was created.', [ 'status' => 409 ] );
			}
		} else {
			$current_hash = self::hash_post_content( $post_id );
			if ( '' === $base_hash || '' === $current_hash || ! hash_equals( $base_hash, $current_hash ) ) {
				return new WP_Error( 'sae_plan_content_conflict', 'Post content changed since the plan was created.', [ 'status' => 409 ] );
			}
		}

		$mutation_hash = '';
		if ( class_exists( 'Struo_Mutation_Journal' ) && Struo_Mutation_Journal::is_journaled_record( $record ) ) {
			$frozen = self::assert_journaled_payload_frozen( $record, 'approved' );
			if ( is_wp_error( $frozen ) ) {
				return $frozen;
			}
			$mutation_hash = (string) $frozen;
		}

		$expected_hash = '';
		if ( $recoverable ) {
			$expected = Struo_Mutation_Recovery::expected_witness( $record );
			if ( ! is_string( $expected ) ) {
				return new WP_Error( 'sae_plan_content_conflict', 'Could not compute the expected saved bytes.', [ 'status' => 409 ] );
			}
			$expected_hash = Struo_Mutation_Recovery::hash_witness( $expected );
			if ( 64 !== strlen( $expected_hash ) ) {
				return new WP_Error( 'sae_plan_content_conflict', 'Could not compute the expected saved bytes.', [ 'status' => 409 ] );
			}
		}

		$claim_fields = [ 'applying_at' => current_time( 'mysql' ) ];
		if ( '' !== $expected_hash ) {
			$claim_fields['expected_content_hash'] = $expected_hash;
		}
		$claim = self::durable_plan_move( 'claim_apply', $id, $claim_fields );
		if ( is_wp_error( $claim ) ) {
			return $claim;
		}

		$crash = self::maybe_eval_crash_apply( 'before_write', $id );
		if ( is_wp_error( $crash ) ) {
			return $crash;
		}

		$prepared = [
			'post' => is_array( $record['post'] ?? null ) ? $record['post'] : [ 'post_id' => $post_id ],
			'operation' => sanitize_key( (string) ( $record['operation'] ?? '' ) ),
			'endpoint' => (string) ( $record['endpoint'] ?? '' ),
			'payload' => is_array( $record['payload'] ?? null ) ? $record['payload'] : [],
		];
		$payload = self::get_request_payload( $request );
		$response_mode = self::resolve_response_mode( is_array( $payload ) ? $payload : [] );
		$context = [ 'durable_plan_apply' => $id ];
		if ( 'restore' === $operation_name ) {
			$context['restore_content'] = (string) ( $record['base_content'] ?? '' );
		}
		$result = self::run_plan_dry_run( $prepared, $response_mode, 'rest', $context, false );
		if ( is_wp_error( $result ) ) {
			$failed = self::transition_journaled_mutation(
				$id,
				'fail',
				[
					'failure_code' => $result->get_error_code(),
					'failure_message' => $result->get_error_message(),
				],
				'failed',
				$mutation_hash,
				[
					'state' => 'failed',
					'code' => $result->get_error_code(),
				]
			);
			if ( is_wp_error( $failed ) ) {
				return $failed;
			}
			return $result;
		}

		$crash = self::maybe_eval_crash_apply( 'after_write', $id );
		if ( is_wp_error( $crash ) ) {
			return $crash;
		}

		if ( $recoverable ) {
			$record['expected_content_hash'] = $expected_hash;
			$read_back = Struo_Mutation_Recovery::hash_witness( Struo_Mutation_Recovery::current_witness( $record ) );
			if ( ! hash_equals( $expected_hash, $read_back ) ) {
				$conflict = new WP_Error( 'sae_plan_content_conflict', 'Saved bytes did not match the approved change.', [ 'status' => 409 ] );
				self::transition_journaled_mutation(
					$id,
					'fail',
					[
						'failure_code' => $conflict->get_error_code(),
						'failure_message' => $conflict->get_error_message(),
					],
					'failed',
					$mutation_hash,
					[
						'state' => 'failed',
						'code' => $conflict->get_error_code(),
					]
				);
				return $conflict;
			}
		}

		$receipt = is_array( $result ) ? $result : [];
		if ( $recoverable ) {
			$fresh = self::get_durable_plan_record_unchecked( $id );
			if ( is_array( $fresh ) ) {
				$fresh['expected_content_hash'] = $expected_hash;
				$fresh['state'] = 'applied';
				$receipt['evidence'] = Struo_Mutation_Recovery::evidence( $fresh );
			}
		}
		$receipt_json = wp_json_encode( $receipt );
		if ( false === $receipt_json ) {
			$receipt_json = wp_json_encode( [ 'encoding_error' => true ] );
		}

		$finished = self::transition_journaled_mutation(
			$id,
			'finish',
			[
				'applied_at' => current_time( 'mysql' ),
				'applied_by' => get_current_user_id(),
				'apply_receipt_json' => $receipt_json,
			],
			'applied',
			$mutation_hash,
			[
				'state' => 'applied',
				'receipt_hash' => hash( 'sha256', (string) $receipt_json ),
			]
		);
		if ( is_wp_error( $finished ) ) {
			return $finished;
		}

		$crash = self::maybe_eval_crash_apply( 'after_commit', $id );
		if ( is_wp_error( $crash ) ) {
			return $crash;
		}

		self::audit_log(
			'agent_plan_apply',
			[
				'post_id' => $post_id,
				'operation' => $prepared['operation'],
				'origin' => 'rest',
				'plan_id' => $id,
			]
		);

		return [
			'ok' => true,
			'plan_id' => $id,
			'state' => 'applied',
			'outcome' => 'applied',
			'result' => $receipt,
		];
	}

	public static function rollback_agent_plan( WP_REST_Request $request ) {
		$id = (string) $request['id'];
		$record = self::get_durable_plan_record_unchecked( $id );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( ! class_exists( 'Struo_Mutation_Recovery' ) ) {
			return new WP_Error( 'sae_rollback_unsupported', 'This plan cannot be rolled back.', [ 'status' => 409 ] );
		}

		$compiled = Struo_Mutation_Recovery::compile_rollback_record( $record );
		if ( is_wp_error( $compiled ) ) {
			return $compiled;
		}

		$capacity = self::assert_durable_plan_capacity( 1 );
		if ( is_wp_error( $capacity ) ) {
			return $capacity;
		}

		if ( ! self::insert_durable_plan_record( $compiled ) ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		$new_id = (string) ( $compiled['id'] ?? '' );
		if ( class_exists( 'Struo_Mutation_Journal' ) && Struo_Mutation_Journal::is_journaled_record( $compiled ) ) {
			$json = Struo_Mutation_Journal::stored_payload_json( $new_id );
			$hash = Struo_Mutation_Journal::hash_payload_json( $json );
			$queued = Struo_Mutation_Journal::append( $new_id, 1, 'queued', $hash, [ 'state' => 'planned' ] );
			if ( is_wp_error( $queued ) || '' === $json ) {
				self::delete_durable_plan_record( $new_id );
				return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
			}
		}

		$stored = self::get_durable_plan_record_unchecked( $new_id );

		return [
			'ok' => true,
			'plan_id' => $new_id,
			'state' => 'planned',
			'plan' => self::present_agent_plan_record( is_array( $stored ) ? $stored : $compiled, false ),
		];
	}

	private static function recover_journaled_apply( $id, array $record, WP_REST_Request $request ) {
		unset( $request );
		$classified = Struo_Mutation_Recovery::classify_applying( $record );
		$outcome = sanitize_key( (string) ( $classified['outcome'] ?? '' ) );
		if ( 'safe_to_retry' === $outcome ) {
			$reset = self::durable_plan_move(
				'release_claim',
				$id,
				[ 'expected_content_hash' => '' ]
			);
			if ( is_wp_error( $reset ) ) {
				return $reset;
			}

			return [
				'ok' => true,
				'plan_id' => $id,
				'state' => 'approved',
				'outcome' => 'safe_to_retry',
			];
		}
		if ( 'recovered_persisted' === $outcome ) {
			$mutation_hash = '';
			if ( class_exists( 'Struo_Mutation_Journal' ) ) {
				$frozen = self::assert_journaled_payload_frozen( $record, 'approved' );
				if ( is_wp_error( $frozen ) ) {
					return $frozen;
				}
				$mutation_hash = (string) $frozen;
			}
			$receipt = [
				'recovered' => true,
				'evidence' => Struo_Mutation_Recovery::evidence( array_merge( $record, [ 'state' => 'applied' ] ) ),
			];
			$receipt_json = wp_json_encode( $receipt );
			if ( false === $receipt_json ) {
				$receipt_json = '{}';
			}
			$finished = self::transition_journaled_mutation(
				$id,
				'finish',
				[
					'applied_at' => current_time( 'mysql' ),
					'applied_by' => get_current_user_id(),
					'apply_receipt_json' => $receipt_json,
				],
				'applied',
				$mutation_hash,
				[
					'state' => 'applied',
					'receipt_hash' => hash( 'sha256', (string) $receipt_json ),
				]
			);
			if ( is_wp_error( $finished ) ) {
				return $finished;
			}

			return [
				'ok' => true,
				'plan_id' => $id,
				'state' => 'applied',
				'outcome' => 'recovered_persisted',
				'result' => $receipt,
			];
		}

		return new WP_Error(
			'sae_outcome_unknown_conflict',
			'Apply was interrupted and the saved page does not match the approved change.',
			[ 'status' => 409 ]
		);
	}

	private static function maybe_eval_crash_apply( $stage, $plan_id ) {
		$stage = sanitize_key( (string) $stage );
		$want = apply_filters( 'struo_eval_crash_apply', '', (string) $plan_id );
		if ( $stage === sanitize_key( (string) $want ) ) {
			return new WP_Error( 'sae_eval_crash', $stage, [ 'status' => 599 ] );
		}

		return true;
	}

	public static function mutation_witness_string( array $record ) {
		$post_id = absint( $record['post_id'] ?? 0 );
		$operation = sanitize_key( (string) ( $record['operation'] ?? '' ) );
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$kind = sanitize_key( (string) ( $payload['restore_kind'] ?? '' ) );
		if ( 'cross_field' === $operation || ( 'restore' === $operation && 'cross_field' === $kind ) ) {
			$field = sanitize_key( (string) ( $payload['field'] ?? '' ) );

			return (string) self::read_post_field_value( $post_id, $field );
		}

		return (string) get_post_field( 'post_content', $post_id );
	}

	public static function mutation_expected_string( array $record ) {
		$operation = sanitize_key( (string) ( $record['operation'] ?? '' ) );
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		if ( 'restore' === $operation ) {
			return (string) ( $record['base_content'] ?? '' );
		}
		if ( 'cross_field' === $operation ) {
			return (string) self::normalize_cross_field_value(
				sanitize_key( (string) ( $payload['field'] ?? '' ) ),
				$payload['value'] ?? ''
			);
		}

		$prepared = [
			'post' => is_array( $record['post'] ?? null ) ? $record['post'] : [ 'post_id' => absint( $record['post_id'] ?? 0 ) ],
			'operation' => $operation,
			'endpoint' => (string) ( $record['endpoint'] ?? '' ),
			'payload' => $payload,
		];
		$preview = self::run_plan_dry_run( $prepared, [ 'compact' => true ], 'rest', [ 'plan_only' => true ], true );
		if ( is_wp_error( $preview ) ) {
			return null;
		}

		return is_array( $preview ) ? (string) ( $preview['serialized_content'] ?? '' ) : '';
	}

	private static function update_durable_plan_payload( $id, array $payload ) {
		global $wpdb;

		if ( ! self::plans_table_exists() ) {
			return false;
		}

		$id = (string) $id;
		if ( ! self::validate_agent_plan_id( $id ) ) {
			return false;
		}

		$encoded = wp_json_encode( self::strip_redeemable_fields( $payload ) );
		if ( false === $encoded ) {
			return false;
		}

		$table = self::get_plans_table_name();
		$updated = $wpdb->update(
			$table,
			[ 'payload_json' => $encoded ],
			[ 'plan_id' => $id ],
			[ '%s' ],
			[ '%s' ]
		);

		return false !== $updated;
	}

	private static function approve_bundle_plan( $id, array $record, WP_REST_Request $request ) {
		$innodb = self::require_innodb_for_bundle();
		if ( is_wp_error( $innodb ) ) {
			return $innodb;
		}

		$integrity = self::assert_bundle_hash( $record );
		if ( is_wp_error( $integrity ) ) {
			return $integrity;
		}

		$body = self::get_request_payload( $request );
		$expected_revision = self::required_bundle_select_revision( $body );
		if ( is_wp_error( $expected_revision ) ) {
			return $expected_revision;
		}

		global $wpdb;
		if ( ! self::plans_table_exists() ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}

		$table = self::get_plans_table_name();
		$wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT payload_json, state FROM {$table} WHERE plan_id = %s FOR UPDATE", $id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( 'planned' !== sanitize_key( (string) ( $row['state'] ?? '' ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'sae_plan_invalid_transition',
				'Bundle is not ready to approve.',
				[ 'status' => 409 ]
			);
		}

		$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}
		$current_revision = absint( $payload['select_revision'] ?? 0 );
		if ( absint( $expected_revision ) !== $current_revision ) {
			$wpdb->query( 'ROLLBACK' );
			$fresh = self::get_agent_plan_record( $id );

			return new WP_Error(
				'sae_bundle_select_conflict',
				'Selected pages changed. Refresh and try again.',
				[
					'status' => 409,
					'select_revision' => $current_revision,
					'selected_ids' => is_array( $payload['selected_ids'] ?? null ) ? array_map( 'strval', $payload['selected_ids'] ) : [],
					'plan' => $fresh ? self::present_bundle_plan_envelope( $fresh ) : null,
				]
			);
		}

		$child_ids = array_map( 'strval', is_array( $payload['child_ids'] ?? null ) ? $payload['child_ids'] : [] );
		$selected_ids = array_map( 'strval', is_array( $payload['selected_ids'] ?? null ) ? $payload['selected_ids'] : $child_ids );
		$selected_ids = array_values(
			array_filter(
				$selected_ids,
				static function ( $child_id ) use ( $child_ids ) {
					return self::validate_agent_plan_id( $child_id ) && in_array( $child_id, $child_ids, true );
				}
			)
		);
		if ( empty( $selected_ids ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'sae_bundle_empty',
				'Select at least one page before approve.',
				[ 'status' => 400 ]
			);
		}

		$integrity = self::assert_bundle_hash( [ 'payload' => $payload, 'payload_type' => 'bundle_v1' ] );
		if ( is_wp_error( $integrity ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $integrity;
		}

		$approved_ids = [];
		$failed = [];
		foreach ( $selected_ids as $child_id ) {
			$child = self::get_durable_plan_record_unchecked( $child_id );
			$child_state = sanitize_key( (string) ( $child['state'] ?? '' ) );
			$child_post_id = absint( $child['post_id'] ?? 0 );
			if ( ! $child || $child_post_id <= 0 || ! current_user_can( 'edit_post', $child_post_id ) ) {
				$failed[] = [
					'plan_id' => $child_id,
					'ok' => false,
					'code' => $child ? 'sae_insufficient_permissions' : 'sae_agent_plan_missing',
					'message' => $child ? 'Insufficient permissions to approve this page.' : 'Selected page is missing.',
				];
				continue;
			}
			if ( 'approved' === $child_state ) {
				$approved_ids[] = $child_id;
				continue;
			}
			if ( 'planned' !== $child_state ) {
				$failed[] = [
					'plan_id' => $child_id,
					'ok' => false,
					'code' => 'sae_plan_invalid_transition',
					'message' => 'Selected page is not ready to approve.',
					'state' => $child_state,
				];
				continue;
			}
			$child_approved = self::durable_plan_move(
				'approve',
				$child_id,
				[
					'approved_by' => get_current_user_id(),
					'approved_at' => current_time( 'mysql' ),
				]
			);
			if ( is_wp_error( $child_approved ) ) {
				$failed[] = [
					'plan_id' => $child_id,
					'ok' => false,
					'code' => $child_approved->get_error_code(),
					'message' => $child_approved->get_error_message(),
				];
				continue;
			}
			$approved_ids[] = $child_id;
		}

		if ( ! empty( $failed ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'sae_bundle_approve_partial',
				'Some selected pages could not be approved.',
				[
					'status' => 409,
					'approved_child_ids' => [],
					'failed' => $failed,
					'plan' => self::present_bundle_plan_envelope( self::get_agent_plan_record( $id ) ?: $record ),
				]
			);
		}

		$parent_approved = self::durable_plan_move(
			'approve',
			$id,
			[
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			]
		);
		if ( is_wp_error( $parent_approved ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $parent_approved;
		}

		$wpdb->query( 'COMMIT' );

		self::audit_log(
			'agent_plan_approve',
			[
				'post_id' => 0,
				'operation' => 'bundle',
				'origin' => 'rest',
				'plan_id' => $id,
				'approved_child_ids' => $approved_ids,
			]
		);

		$record = self::get_agent_plan_record( $id );

		return [
			'ok' => true,
			'plan_id' => $id,
			'state' => (string) ( $record['state'] ?? 'approved' ),
			'plan' => self::present_bundle_plan_envelope( $record ),
			'approved_child_ids' => $approved_ids,
		];
	}

	public static function select_bundle_children( WP_REST_Request $request ) {
		$id = (string) $request['id'];
		$record = self::get_agent_plan_record( $id );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( ! self::user_can_review_agent_plan( $record ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to review this plan.', [ 'status' => 403 ] );
		}
		if ( 'bundle_v1' !== sanitize_key( (string) ( $record['payload_type'] ?? '' ) ) ) {
			return new WP_Error( 'sae_bundle_empty', 'That plan is not a bundle.', [ 'status' => 400 ] );
		}

		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$child_ids = is_array( $payload['child_ids'] ?? null ) ? array_map( 'strval', $payload['child_ids'] ) : [];
		$body = self::get_request_payload( $request );
		$raw_selected = is_array( $body['selected_ids'] ?? null ) ? $body['selected_ids'] : [];
		$selected_ids = [];
		foreach ( $raw_selected as $child_id ) {
			$child_id = sanitize_text_field( (string) $child_id );
			if ( self::validate_agent_plan_id( $child_id ) && in_array( $child_id, $child_ids, true ) && ! in_array( $child_id, $selected_ids, true ) ) {
				$selected_ids[] = $child_id;
			}
		}

		$expected_revision = self::required_bundle_select_revision( $body );
		if ( is_wp_error( $expected_revision ) ) {
			return $expected_revision;
		}
		$replaced = self::replace_bundle_selection( $id, $selected_ids, $expected_revision );
		if ( is_wp_error( $replaced ) ) {
			return $replaced;
		}

		self::audit_log(
			'agent_plan_select',
			[
				'post_id' => 0,
				'operation' => 'bundle',
				'origin' => 'rest',
				'plan_id' => $id,
				'selected_count' => count( $replaced['selected_ids'] ),
				'select_revision' => $replaced['select_revision'],
			]
		);

		$record = self::get_agent_plan_record( $id );

		return [
			'ok' => true,
			'plan_id' => $id,
			'selected_ids' => $replaced['selected_ids'],
			'select_revision' => $replaced['select_revision'],
			'plan' => self::present_bundle_plan_envelope( $record ),
		];
	}

	private static function bundle_apply_interrupted_error( WP_Error $error, array $results ) {
		$data = $error->get_error_data();
		if ( ! is_array( $data ) ) {
			$data = [ 'status' => 403 ];
		}
		if ( empty( $data['status'] ) ) {
			$data['status'] = 403;
		}
		$data['results'] = $results;
		$data['interrupted'] = true;

		return new WP_Error( $error->get_error_code(), $error->get_error_message(), $data );
	}

	/**
	 * Explicit skip/failure for a selected child that should not be applied now.
	 * Returns null when the child is approved and should go through apply_agent_plan().
	 *
	 * @return array|null
	 */
	private static function bundle_remaining_skip_result( $child_id, array $child_ids ) {
		$child_id = (string) $child_id;
		if ( ! self::validate_agent_plan_id( $child_id ) || ! in_array( $child_id, $child_ids, true ) ) {
			return [
				'plan_id' => $child_id,
				'ok' => false,
				'outcome' => 'skipped',
				'code' => 'sae_agent_plan_missing',
				'message' => 'Selected page is missing.',
				'state' => 'missing',
			];
		}

		$child = self::get_durable_plan_record_unchecked( $child_id );
		if ( ! $child ) {
			return [
				'plan_id' => $child_id,
				'ok' => false,
				'outcome' => 'skipped',
				'code' => 'sae_agent_plan_missing',
				'message' => 'Selected page is missing.',
				'state' => 'missing',
			];
		}

		$state = sanitize_key( (string) ( $child['state'] ?? '' ) );
		if ( in_array( $state, [ 'planned', 'approved' ], true ) && absint( $child['expires_at'] ?? 0 ) <= time() ) {
			self::durable_plan_move( 'expire', $child_id );
			$state = 'expired';
		}

		$post_id = absint( $child['post_id'] ?? 0 );
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'plan_id' => $child_id,
				'ok' => false,
				'outcome' => 'skipped',
				'code' => 'sae_insufficient_permissions',
				'message' => 'Insufficient permissions to apply this page.',
				'state' => $state,
			];
		}

		if ( 'applied' === $state ) {
			return [
				'plan_id' => $child_id,
				'ok' => true,
				'outcome' => 'already_applied',
				'state' => 'applied',
			];
		}

		if ( 'approved' === $state ) {
			return null;
		}

		$code = 'cancelled' === $state
			? 'sae_plan_cancelled'
			: ( 'failed' === $state
				? 'sae_plan_failed'
				: ( 'expired' === $state ? 'sae_plan_expired' : 'sae_plan_not_approved' ) );
		$outcome = 'cancelled' === $state ? 'cancelled' : ( 'failed' === $state ? 'failed' : 'skipped' );

		return [
			'plan_id' => $child_id,
			'ok' => false,
			'outcome' => $outcome,
			'code' => $code,
			'message' => 'Selected page is not approved for apply.',
			'state' => $state,
		];
	}

	private static function planner_error_skips_remaining( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$code = $error->get_error_code();
		$data = $error->get_error_data();
		$status = absint( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );

		return 429 === $status || in_array(
			$code,
			[
				'sae_planner_unavailable',
				'sae_provider_url_refused',
				'sae_provider_key_host_mismatch',
				'sae_plan_spend_cap',
				'sae_plan_rate_limit',
			],
			true
		);
	}

	public static function apply_bundle_remaining( WP_REST_Request $request ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return self::bundle_apply_interrupted_error( $kill_switch, [] );
		}

		$innodb = self::require_innodb_for_bundle();
		if ( is_wp_error( $innodb ) ) {
			return $innodb;
		}

		$id = (string) $request['id'];
		$record = self::get_durable_plan_record_unchecked( $id );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( 'bundle_v1' !== sanitize_key( (string) ( $record['payload_type'] ?? '' ) ) ) {
			return new WP_Error( 'sae_bundle_empty', 'That plan is not a bundle.', [ 'status' => 400 ] );
		}
		$parent_state = sanitize_key( (string) ( $record['state'] ?? '' ) );
		if ( ! in_array( $parent_state, [ 'approved', 'applied' ], true ) ) {
			return new WP_Error(
				'sae_plan_not_approved',
				'Approve the selected pages before apply remaining.',
				[ 'status' => 409 ]
			);
		}

		$integrity = self::assert_bundle_hash( $record );
		if ( is_wp_error( $integrity ) ) {
			return $integrity;
		}

		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$child_ids = array_map( 'strval', is_array( $payload['child_ids'] ?? null ) ? $payload['child_ids'] : [] );
		$selected_ids = array_map( 'strval', is_array( $payload['selected_ids'] ?? null ) ? $payload['selected_ids'] : $child_ids );
		$results = [];
		foreach ( $selected_ids as $child_id ) {
			$child_id = (string) $child_id;
			$again = self::ensure_kill_switch();
			if ( is_wp_error( $again ) ) {
				return self::bundle_apply_interrupted_error( $again, $results );
			}
			$skip = self::bundle_remaining_skip_result( $child_id, $child_ids );
			if ( is_array( $skip ) ) {
				$results[] = $skip;
				continue;
			}
			$child_request = new WP_REST_Request( 'POST', '/struo/v1/console/agent-plans/' . $child_id . '/apply' );
			$child_request->set_url_params( [ 'id' => $child_id ] );
			$child_result = self::apply_agent_plan( $child_request );
			if ( is_wp_error( $child_result ) ) {
				$results[] = [
					'plan_id' => $child_id,
					'ok' => false,
					'outcome' => 'failed',
					'code' => $child_result->get_error_code(),
					'message' => $child_result->get_error_message(),
				];
				continue;
			}
			$results[] = [
				'plan_id' => $child_id,
				'ok' => true,
				'outcome' => 'newly_applied',
				'state' => sanitize_key( (string) ( $child_result['state'] ?? 'applied' ) ),
			];
		}

		$failed = array_values(
			array_filter(
				$results,
				static function ( $row ) {
					return empty( $row['ok'] );
				}
			)
		);
		$applied = count(
			array_filter(
				$results,
				static function ( $row ) {
					return 'newly_applied' === sanitize_key( (string) ( $row['outcome'] ?? '' ) );
				}
			)
		);

		self::audit_log(
			'agent_plan_apply_remaining',
			[
				'post_id' => 0,
				'operation' => 'bundle',
				'origin' => 'rest',
				'plan_id' => $id,
				'result_count' => count( $results ),
				'applied_count' => $applied,
				'failed_count' => count( $failed ),
			]
		);

		if ( empty( $failed ) ) {
			$waiting_selected = false;
			foreach ( $selected_ids as $child_id ) {
				$child = self::get_durable_plan_record_unchecked( (string) $child_id );
				$child_state = sanitize_key( (string) ( is_array( $child ) ? ( $child['state'] ?? '' ) : '' ) );
				if ( ! in_array( $child_state, [ 'applied', 'cancelled', 'failed' ], true ) ) {
					$waiting_selected = true;
					break;
				}
			}
			if ( ! $waiting_selected ) {
				self::durable_plan_move( 'finish', $id );
			}
		}
		$record_after = self::get_durable_plan_record_unchecked( $id ) ?: $record;

		return [
			'ok' => empty( $failed ),
			'plan_id' => $id,
			'results' => $results,
			'applied_count' => $applied,
			'failed_count' => count( $failed ),
			'plan' => self::present_bundle_plan_envelope( $record_after ),
		];
	}

	/**
	 * Attach durable plan metadata to a mutation response and strip redeemable tokens.
	 *
	 * @param array $merge_into Optional existing response array (batch dry-run).
	 */
	private static function attach_durable_plan_envelope( $origin, array $response, ?array $merge_into = null ) {
		$plan_id = self::create_durable_mutation_plan( $origin, $response );
		if ( is_wp_error( $plan_id ) ) {
			return $plan_id;
		}
		$output = is_array( $merge_into ) ? $merge_into : $response;

		if ( '' === $plan_id ) {
			return $output;
		}

		if ( 'mcp' === self::normalize_token_origin( $origin ) ) {
			$output['agent_plan'] = [
				'id' => $plan_id,
				'queued' => true,
			];
		} else {
			$output['plan_id'] = $plan_id;
			$output['plan_state'] = 'planned';
		}

		if ( is_array( $output['dry_run'] ?? null ) ) {
			$output['dry_run'] = self::strip_redeemable_fields( $output['dry_run'] );
		}

		return $output;
	}

	/**
	 * REST dry-runs are plan-only: no redeemable confirmation token.
	 */
	private static function rest_plan_only_context( $dry_run, $origin, array $context = [] ) {
		return array_merge(
			$context,
			[
				'plan_only' => $dry_run && 'rest' === self::normalize_token_origin( $origin ),
			]
		);
	}

	/**
	 * Wrap a REST dry-run mutation response in a durable plan envelope.
	 *
	 * @param mixed $result apply_* result.
	 */
	private static function maybe_attach_rest_mutation_plan_envelope( $origin, $dry_run, $post_id, $operation, $endpoint, array $payload, $result, $request_label = '' ) {
		if ( is_wp_error( $result ) || ! $dry_run || 'rest' !== self::normalize_token_origin( $origin ) ) {
			return $result;
		}

		$post = get_post( $post_id );
		$envelope_response = [
			'request' => $request_label,
			'intent' => 'do',
			'post' => [
				'post_id' => $post_id,
				'post_title' => $post ? sanitize_text_field( (string) $post->post_title ) : '',
				'post_slug' => $post ? sanitize_title( (string) $post->post_name ) : '',
				'post_url' => $post ? esc_url_raw( (string) get_permalink( $post ) ) : '',
			],
			'operation' => $operation,
			'endpoint' => $endpoint,
			'payload' => $payload,
			'dry_run' => is_array( $result ) ? $result : null,
		];

		return self::attach_durable_plan_envelope( 'rest', $envelope_response, is_array( $result ) ? $result : [] );
	}

	/**
	 * Unique positive ints from a request post_ids list.
	 *
	 * @param mixed $raw Request post_ids.
	 * @return int[]
	 */
	private static function unique_positive_ids( $raw ) {
		$ids = [];
		foreach ( (array) $raw as $id ) {
			$id = absint( $id );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * One-page compile target. `post_id` wins; a lone `post_ids` entry is
	 * the same page, not a bundle (bundles start at two ids).
	 */
	private static function resolve_explicit_mutation_post_id( array $payload, array $plan, $url_post_id = 0 ) {
		$id = absint( $plan['post_id'] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}
		$id = absint( $payload['post_id'] ?? $url_post_id );
		if ( $id > 0 ) {
			return $id;
		}
		$ids = self::unique_positive_ids( $payload['post_ids'] ?? [] );
		return 1 === count( $ids ) ? $ids[0] : 0;
	}

	private static function request_has_bundle_phrase( $request_text ) {
		return (bool) preg_match( '/\b(these pages|these posts|allowlisted pages)\b/i', (string) $request_text );
	}

	/**
	 * True when payload.post_ids has ≥2 ids or the request is a multi-page
	 * instruction with no explicit ids. Create intent is never a bundle.
	 */
	private static function request_wants_bundle( array $payload, $request_text, $intent ) {
		if ( 'create' === sanitize_key( (string) $intent ) ) {
			return false;
		}
		$raw = self::unique_positive_ids( $payload['post_ids'] ?? [] );
		if ( count( $raw ) >= 2 ) {
			return true;
		}
		return 0 === count( $raw ) && self::request_has_bundle_phrase( $request_text );
	}

	private static function bundle_request_forbids_create( array $payload, $request_text, $intent ) {
		$create = 'create' === sanitize_key( (string) $intent );
		$page_spec = 'page_spec_v1' === sanitize_key( (string) ( $payload['payload_type'] ?? '' ) );
		if ( ! $create && ! $page_spec ) {
			return false;
		}
		$raw = self::unique_positive_ids( $payload['post_ids'] ?? [] );
		return count( $raw ) >= 2 || self::request_has_bundle_phrase( $request_text );
	}

	/**
	 * Allowlisted ids for a bundle, plus skipped reasons.
	 *
	 * @return array{ids:int[],skipped:array}|WP_Error
	 */
	private static function resolve_bundle_post_ids( array $payload, $request_text ) {
		unset( $request_text );
		$raw = self::unique_positive_ids( $payload['post_ids'] ?? [] );
		$skipped = [];
		if ( ! empty( $raw ) ) {
			if ( count( $raw ) > self::BUNDLE_MAX_POSTS ) {
				return new WP_Error(
					'sae_bundle_too_large',
					'A bundle can include at most ' . self::BUNDLE_MAX_POSTS . ' pages.',
					[ 'status' => 400 ]
				);
			}
			$allowed = array_flip( self::get_allowed_post_ids() );
			$ids = [];
			foreach ( $raw as $id ) {
				if ( ! isset( $allowed[ $id ] ) ) {
					$skipped[] = [
						'post_id' => $id,
						'code' => 'sae_post_not_allowed',
						'message' => 'Post is not allowlisted.',
					];
					continue;
				}
				if ( ! current_user_can( 'edit_post', $id ) ) {
					$skipped[] = [
						'post_id' => $id,
						'code' => 'sae_insufficient_permissions',
						'message' => 'Insufficient permissions to edit this post.',
					];
					continue;
				}
				$ids[] = $id;
			}
			return [
				'ids' => $ids,
				'skipped' => $skipped,
			];
		}

		$ids = [];
		foreach ( self::list_allowlisted_posts() as $post ) {
			$id = absint( $post['post_id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				$skipped[] = [
					'post_id' => $id,
					'code' => 'sae_insufficient_permissions',
					'message' => 'Insufficient permissions to edit this post.',
				];
				continue;
			}
			$ids[] = $id;
			if ( count( $ids ) >= self::BUNDLE_MAX_POSTS ) {
				break;
			}
		}

		return [
			'ids' => $ids,
			'skipped' => $skipped,
		];
	}

	private static function allocate_plan_id() {
		try {
			return bin2hex( random_bytes( 8 ) );
		} catch ( Throwable $e ) {
			return '';
		}
	}

	private static function hash_bundle_children( array $child_ids ) {
		$sorted = array_values( array_filter( array_map( 'strval', $child_ids ) ) );
		sort( $sorted, SORT_STRING );
		$chunks = [];
		foreach ( $sorted as $child_id ) {
			$record = self::get_durable_plan_record_unchecked( $child_id );
			$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
			$encoded = wp_json_encode( $payload );
			$chunks[] = $child_id . ':' . hash( 'sha256', false === $encoded ? '' : $encoded );
		}
		return hash( 'sha256', implode( '|', $chunks ) );
	}

	private static function assert_bundle_hash( array $record ) {
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$stored = sanitize_text_field( (string) ( $payload['bundle_hash'] ?? '' ) );
		$child_ids = is_array( $payload['child_ids'] ?? null ) ? $payload['child_ids'] : [];
		if ( '' === $stored ) {
			return new WP_Error(
				'sae_bundle_hash_mismatch',
				'Bundle integrity hash is missing.',
				[ 'status' => 409 ]
			);
		}
		$computed = self::hash_bundle_children( $child_ids );
		if ( ! hash_equals( $stored, $computed ) ) {
			return new WP_Error(
				'sae_bundle_hash_mismatch',
				'Bundle children no longer match the stored hash.',
				[ 'status' => 409 ]
			);
		}

		return true;
	}

	private static function bundle_parent_record( array $record ) {
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$bundle_id = sanitize_text_field( (string) ( $payload['bundle_id'] ?? '' ) );
		if ( ! self::validate_agent_plan_id( $bundle_id ) ) {
			return null;
		}

		$parent = self::get_agent_plan_record( $bundle_id );
		if ( ! $parent || 'bundle_v1' !== sanitize_key( (string) ( $parent['payload_type'] ?? '' ) ) ) {
			return null;
		}

		return $parent;
	}

	private static function ensure_bundle_child_not_directly_approved( array $record ) {
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$bundle_id = sanitize_text_field( (string) ( $payload['bundle_id'] ?? '' ) );
		if ( ! self::validate_agent_plan_id( $bundle_id ) ) {
			return true;
		}

		return new WP_Error(
			'sae_bundle_approve_parent',
			'Approve selected pages from the bundle, not a single row.',
			[ 'status' => 409 ]
		);
	}

	private static function ensure_bundle_child_writable( array $record ) {
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$bundle_id = sanitize_text_field( (string) ( $payload['bundle_id'] ?? '' ) );
		if ( ! self::validate_agent_plan_id( $bundle_id ) ) {
			return true;
		}

		$parent = self::bundle_parent_record( $record );
		if ( ! $parent ) {
			return new WP_Error(
				'sae_bundle_child_not_selected',
				'This page is not part of an active bundle.',
				[ 'status' => 409 ]
			);
		}
		if ( 'approved' !== (string) ( $parent['state'] ?? '' ) ) {
			return new WP_Error(
				'sae_plan_not_approved',
				'Approve the selected pages before apply.',
				[ 'status' => 409 ]
			);
		}

		$integrity = self::assert_bundle_hash( $parent );
		if ( is_wp_error( $integrity ) ) {
			return $integrity;
		}

		$selected_ids = is_array( $parent['payload']['selected_ids'] ?? null )
			? array_map( 'strval', $parent['payload']['selected_ids'] )
			: [];
		$id = (string) ( $record['id'] ?? '' );
		if ( ! in_array( $id, $selected_ids, true ) ) {
			return new WP_Error(
				'sae_bundle_child_not_selected',
				'That page was not selected.',
				[ 'status' => 409 ]
			);
		}

		return true;
	}

	private static function required_bundle_select_revision( array $body ) {
		if ( ! array_key_exists( 'select_revision', $body ) ) {
			return new WP_Error(
				'sae_bundle_revision_required',
				'select_revision is required.',
				[ 'status' => 400 ]
			);
		}

		return absint( $body['select_revision'] );
	}

	private static function replace_bundle_selection( $id, array $selected_ids, $expected_revision ) {
		global $wpdb;

		$innodb = self::require_innodb_for_bundle();
		if ( is_wp_error( $innodb ) ) {
			return $innodb;
		}

		$id = (string) $id;
		if ( ! self::plans_table_exists() || ! self::validate_agent_plan_id( $id ) ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}

		$table = self::get_plans_table_name();
		$wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT payload_json, state FROM {$table} WHERE plan_id = %s FOR UPDATE", $id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( 'planned' !== sanitize_key( (string) ( $row['state'] ?? '' ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'sae_bundle_selection_locked',
				'Selection is locked after approve.',
				[ 'status' => 409 ]
			);
		}

		$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}
		$current_revision = absint( $payload['select_revision'] ?? 0 );
		if ( null !== $expected_revision && absint( $expected_revision ) !== $current_revision ) {
			$wpdb->query( 'ROLLBACK' );
			$record = self::get_agent_plan_record( $id );
			return new WP_Error(
				'sae_bundle_select_conflict',
				'Selected pages changed. Refresh and try again.',
				[
					'status' => 409,
					'select_revision' => $current_revision,
					'selected_ids' => is_array( $payload['selected_ids'] ?? null ) ? array_map( 'strval', $payload['selected_ids'] ) : [],
					'plan' => $record ? self::present_bundle_plan_envelope( $record ) : null,
				]
			);
		}

		$integrity = self::assert_bundle_hash( [ 'payload' => $payload, 'payload_type' => 'bundle_v1' ] );
		if ( is_wp_error( $integrity ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $integrity;
		}

		$payload['selected_ids'] = array_values( $selected_ids );
		$payload['select_revision'] = $current_revision + 1;
		$encoded = wp_json_encode( self::strip_redeemable_fields( $payload ) );
		if ( false === $encoded ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'sae_bundle_empty', 'Could not update the selected pages.', [ 'status' => 500 ] );
		}

		$updated = $wpdb->update(
			$table,
			[ 'payload_json' => $encoded ],
			[ 'plan_id' => $id ],
			[ '%s' ],
			[ '%s' ]
		);
		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'sae_bundle_empty', 'Could not update the selected pages.', [ 'status' => 500 ] );
		}

		$wpdb->query( 'COMMIT' );

		return [
			'selected_ids' => $payload['selected_ids'],
			'select_revision' => $payload['select_revision'],
		];
	}

	private static function bundle_preview_text( $value, $max = 80 ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );
			$value = false === $encoded ? '' : $encoded;
		}
		$text = trim( wp_strip_all_tags( (string) $value ) );
		$text = preg_replace( '/\s+/', ' ', $text );

		return wp_html_excerpt( (string) $text, $max, '…' );
	}

	private static function bundle_summary_field_value( array $summary, array $target, $field_key ) {
		$field_key = (string) $field_key;
		$path = self::normalize_index_path( $target['index_path'] ?? [] );
		$cursor = $summary;
		$last = count( $path ) - 1;
		foreach ( $path as $depth => $idx ) {
			$idx = absint( $idx );
			if ( ! isset( $cursor[ $idx ] ) || ! is_array( $cursor[ $idx ] ) ) {
				return '';
			}
			$entry = $cursor[ $idx ];
			if ( $depth === $last ) {
				$fields = is_array( $entry['fields'] ?? null ) ? $entry['fields'] : [];
				if ( '' !== $field_key && array_key_exists( $field_key, $fields ) ) {
					return $fields[ $field_key ];
				}
				if ( ! empty( $fields ) ) {
					$keys = array_keys( $fields );
					return $fields[ $keys[0] ];
				}

				return $entry['block_name'] ?? '';
			}
			$cursor = is_array( $entry['inner_blocks'] ?? null ) ? $entry['inner_blocks'] : [];
		}

		return '';
	}

	private static function cancel_durable_plan_ids( array $ids ) {
		if ( class_exists( 'Struo_Durable_Plans' ) ) {
			Struo_Durable_Plans::cancel_ids( $ids );
		}
	}

	private static function bundle_child_preview_fields( array $prepared, $post_id, $dry_run = null ) {
		$payload = is_array( $prepared['payload'] ?? null ) ? $prepared['payload'] : [];
		$operation = sanitize_key( (string) ( $prepared['operation'] ?? '' ) );
		$dry_run = is_array( $dry_run ) ? $dry_run : [];
		$action = sanitize_key( (string) ( $dry_run['action'] ?? $operation ) );
		$change = is_array( $dry_run['change'] ?? null ) ? $dry_run['change'] : [];
		$target = is_array( $payload['target'] ?? null ) ? $payload['target'] : [];

		if ( array_key_exists( 'old_value', $dry_run ) || array_key_exists( 'new_value', $dry_run ) ) {
			return [
				'field' => sanitize_key( (string) ( $dry_run['field'] ?? $payload['field'] ?? 'field' ) ),
				'before' => self::bundle_preview_text( $dry_run['old_value'] ?? '' ),
				'after' => self::bundle_preview_text( $dry_run['new_value'] ?? '' ),
			];
		}

		if ( 'remove' === $action ) {
			$removed = $change['removed'] ?? ( is_array( $dry_run['meta'] ?? null ) ? ( $dry_run['meta']['removed'] ?? null ) : null );

			return [
				'field' => 'block',
				'before' => self::bundle_preview_text( $removed ?: ( $change['target'] ?? 'block' ) ),
				'after' => '(removed)',
			];
		}

		if ( 'batch' === $action ) {
			return [
				'field' => 'batch',
				'before' => absint( $change['before_count'] ?? 0 ) . ' blocks',
				'after' => absint( $change['after_count'] ?? 0 ) . ' blocks',
			];
		}

		$fields = is_array( $payload['fields'] ?? null ) ? $payload['fields'] : [];
		$field_key = '';
		if ( ! empty( $fields ) ) {
			$keys = array_keys( $fields );
			$field_key = (string) $keys[0];
		}

		$before_summary = self::summarize_blocks( self::get_parsed_blocks( $post_id ) );
		$after_summary = is_array( $dry_run['blocks_after'] ?? null ) ? $dry_run['blocks_after'] : [];
		$before = self::bundle_summary_field_value( $before_summary, $target, $field_key );
		$after = self::bundle_summary_field_value( $after_summary, $target, $field_key );

		$field = sanitize_key( $field_key );
		if ( '' === $field ) {
			$field = '' !== $action ? $action : 'content';
		}

		return [
			'field' => $field,
			'before' => self::bundle_preview_text( $before ),
			'after' => self::bundle_preview_text( $after ),
		];
	}

	/**
	 * Insert parent bundle_v1 after children exist.
	 *
	 * @return string plan_id
	 */
	private static function create_durable_bundle_plan( $origin, $request_text, $plan_id, array $child_ids, array $selected_ids, array $skipped ) {
		$origin = self::normalize_token_origin( $origin );
		if ( ! self::validate_agent_plan_id( (string) $plan_id ) ) {
			return '';
		}

		$bundle_hash = self::hash_bundle_children( $child_ids );
		$now = time();
		$record = [
			'id' => (string) $plan_id,
			'payload_type' => 'bundle_v1',
			'state' => 'planned',
			'created_at' => $now,
			'expires_at' => $now + self::AGENT_PLAN_TTL,
			'user_id' => get_current_user_id(),
			'origin' => $origin,
			'post_id' => 0,
			'post' => [
				'post_id' => 0,
			],
			'operation' => 'bundle',
			'endpoint' => '',
			'request' => self::sanitize_plan_request_text( (string) $request_text ),
			'payload' => [
				'request' => self::sanitize_plan_request_text( (string) $request_text ),
				'child_ids' => array_values( $child_ids ),
				'selected_ids' => array_values( $selected_ids ),
				'skipped' => $skipped,
				'bundle_hash' => $bundle_hash,
				'select_revision' => 0,
			],
			'preview' => null,
			'base_content_hash' => $bundle_hash,
		];

		if ( ! self::insert_durable_plan_record( $record ) ) {
			return '';
		}

		return (string) $plan_id;
	}

	private static function present_bundle_plan_envelope( $record ) {
		if ( ! is_array( $record ) ) {
			return [
				'request' => '',
				'intent' => 'do',
				'operation' => 'bundle',
				'payload_type' => 'bundle_v1',
				'plan_state' => 'planned',
				'children' => [],
				'skipped' => [],
				'select_revision' => 0,
				'confirmation' => [
					'redeemable' => false,
					'origin' => 'rest',
					'message' => 'Bundle is plan-only. Approve selected rows, then apply from the console.',
				],
			];
		}

		$id = (string) ( $record['id'] ?? '' );
		$origin = self::normalize_token_origin( (string) ( $record['origin'] ?? 'rest' ) );
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$child_ids = is_array( $payload['child_ids'] ?? null ) ? $payload['child_ids'] : [];
		$selected_ids = is_array( $payload['selected_ids'] ?? null ) ? array_map( 'strval', $payload['selected_ids'] ) : array_map( 'strval', $child_ids );
		$children = [];
		foreach ( $child_ids as $child_id ) {
			$child_id = (string) $child_id;
			$child = self::get_durable_plan_record_unchecked( $child_id );
			if ( ! $child ) {
				$children[] = [
					'plan_id' => $child_id,
					'post_id' => 0,
					'post_title' => '',
					'field' => '',
					'before' => '',
					'after' => '',
					'state' => 'missing',
					'selected' => in_array( $child_id, $selected_ids, true ),
				];
				continue;
			}
			$child_payload = is_array( $child['payload'] ?? null ) ? $child['payload'] : [];
			$stored_review = is_array( $child_payload['review'] ?? null ) ? $child_payload['review'] : [];
			if ( ! empty( $stored_review['field'] ) || ! empty( $stored_review['before'] ) || ! empty( $stored_review['after'] ) ) {
				$preview_fields = [
					'field' => sanitize_key( (string) ( $stored_review['field'] ?? 'content' ) ),
					'before' => (string) ( $stored_review['before'] ?? '' ),
					'after' => (string) ( $stored_review['after'] ?? '' ),
				];
			} else {
				$prepared = [
					'operation' => sanitize_key( (string) ( $child['operation'] ?? '' ) ),
					'payload' => $child_payload,
				];
				$preview_fields = self::bundle_child_preview_fields(
					$prepared,
					absint( $child['post_id'] ?? 0 ),
					is_array( $child['preview'] ?? null ) ? $child['preview'] : []
				);
			}
			$state = sanitize_key( (string) ( $child['state'] ?? 'planned' ) );
			if ( in_array( $state, [ 'planned', 'approved' ], true ) && absint( $child['expires_at'] ?? 0 ) <= time() ) {
				$state = 'expired';
			}
			$children[] = [
				'plan_id' => $child_id,
				'post_id' => absint( $child['post_id'] ?? 0 ),
				'post_title' => sanitize_text_field( (string) ( $child['post']['post_title'] ?? '' ) ),
				'field' => $preview_fields['field'],
				'before' => $preview_fields['before'],
				'after' => $preview_fields['after'],
				'state' => $state,
				'selected' => in_array( $child_id, $selected_ids, true ),
			];
		}

		$envelope = [
			'request' => self::sanitize_plan_request_text( (string) ( $record['request'] ?? ( $payload['request'] ?? '' ) ) ),
			'intent' => 'do',
			'operation' => 'bundle',
			'payload_type' => 'bundle_v1',
			'plan_id' => $id,
			'plan_state' => sanitize_key( (string) ( $record['state'] ?? 'planned' ) ),
			'origin' => $origin,
			'children' => $children,
			'skipped' => is_array( $payload['skipped'] ?? null ) ? $payload['skipped'] : [],
			'select_revision' => absint( $payload['select_revision'] ?? 0 ),
			'confirmation' => [
				'redeemable' => false,
				'origin' => $origin,
				'message' => 'Bundle is plan-only. Approve selected rows, then apply from the console.',
			],
		];
		if ( 'mcp' === $origin ) {
			$envelope['agent_plan'] = [
				'id' => $id,
				'queued' => true,
			];
		}

		return $envelope;
	}

	/**
	 * Create-review envelope from the stored page_spec row. No regen. No serialized_content on the wire.
	 *
	 * @param array<string, mixed> $record
	 * @return array<string, mixed>
	 */
	private static function present_page_spec_plan_envelope( array $record ) {
		$id = (string) ( $record['id'] ?? '' );
		$origin = self::normalize_token_origin( (string) ( $record['origin'] ?? 'rest' ) );
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$preview = is_array( $record['preview'] ?? null ) ? self::strip_redeemable_fields( $record['preview'] ) : [];
		$safe_payload = $payload;
		if ( isset( $safe_payload['serialized_content'] ) ) {
			$safe_payload['serialized_content_bytes'] = strlen( (string) $safe_payload['serialized_content'] );
			unset( $safe_payload['serialized_content'] );
		}

		$title = sanitize_text_field(
			(string) ( $payload['title'] ?? ( $preview['title'] ?? ( $record['post']['post_title'] ?? '' ) ) )
		);
		$post_type = self::normalize_create_post_type(
			$payload['post_type'] ?? ( $preview['post_type'] ?? 'page' )
		);
		if ( '' === $post_type ) {
			$post_type = 'page';
		}

		if ( empty( $preview['confirmation'] ) || ! is_array( $preview['confirmation'] ) ) {
			$preview['confirmation'] = [
				'redeemable' => false,
				'origin' => $origin,
				'message' => 'Plan-only: approve and apply via the durable page_spec envelope.',
			];
		}

		return [
			'request' => self::sanitize_plan_request_text(
				(string) ( $record['request'] ?? ( $preview['description'] ?? '[page-create]' ) )
			),
			'intent' => 'create',
			'operation' => 'create',
			'payload_type' => 'page_spec_v1',
			'plan_id' => $id,
			'plan_state' => sanitize_key( (string) ( $record['state'] ?? 'planned' ) ),
			'origin' => $origin,
			'title' => $title,
			'post_type' => $post_type,
			'template' => sanitize_text_field( (string) ( $payload['template'] ?? ( $preview['template'] ?? '' ) ) ),
			'template_mode' => sanitize_key( (string) ( $payload['template_mode'] ?? ( $preview['template_mode'] ?? '' ) ) ),
			'template_label' => sanitize_text_field( (string) ( $payload['template_label'] ?? ( $preview['template_label'] ?? '' ) ) ),
			'template_rationale' => sanitize_text_field( (string) ( $payload['template_rationale'] ?? ( $preview['template_rationale'] ?? '' ) ) ),
			'description' => (string) ( $preview['description'] ?? '' ),
			'outline' => is_array( $preview['outline'] ?? null ) ? $preview['outline'] : [],
			'operations' => is_array( $preview['operations'] ?? null )
				? $preview['operations']
				: ( is_array( $payload['operations'] ?? null ) ? $payload['operations'] : [] ),
			'endpoint' => (string) ( $record['endpoint'] ?? '/wp-json/struo/v1/pages/create' ),
			'post' => is_array( $record['post'] ?? null )
				? $record['post']
				: [
					'post_id' => 0,
					'post_title' => $title,
					'post_slug' => sanitize_title( $title ),
					'post_url' => '',
				],
			'payload' => $safe_payload,
			'dry_run' => $preview,
			'agent_plan' => [
				'id' => $id,
				'queued' => true,
			],
		];
	}

	/**
	 * Compile one mutation target. Does not persist. Does not authorize.
	 *
	 * @param array<string, mixed> $args {
	 *   @type string               $request_text
	 *   @type array<string, mixed> $plan
	 *   @type array<string, mixed> $payload
	 *   @type int                  $post_id
	 *   @type string               $origin
	 *   @type array<string, mixed> $context
	 *   @type bool                 $include_dry_run
	 *   @type string               $planner_source
	 * }
	 * @return array{plan: array, prepared: array, dry_run: ?array, planner_source: string, planner_warnings: array, planner_meta: array}|WP_Error
	 */
	private static function compile_one_mutation_target( array $args ) {
		$request_text = self::sanitize_plan_request_text( (string) ( $args['request_text'] ?? '' ) );
		$plan = is_array( $args['plan'] ?? null ) ? $args['plan'] : [];
		$payload = is_array( $args['payload'] ?? null ) ? $args['payload'] : [];
		$post_id = absint( $args['post_id'] ?? 0 );
		$origin = $args['origin'] ?? 'rest';
		$context = is_array( $args['context'] ?? null ) ? $args['context'] : [];
		$include_dry_run = array_key_exists( 'include_dry_run', $args ) ? (bool) $args['include_dry_run'] : true;
		$planner_source = sanitize_key( (string) ( $args['planner_source'] ?? 'direct' ) );
		if ( '' === $planner_source ) {
			$planner_source = 'direct';
		}
		$planner_warnings = [];
		$planner_meta = [];
		if ( $post_id <= 0 ) {
			$post_id = self::resolve_explicit_mutation_post_id( $payload, $plan, 0 );
		}
		if ( $post_id > 0 ) {
			$plan['post_id'] = $post_id;
			$payload['post_id'] = $post_id;
		}

		if ( empty( $plan['operation'] ) && ! empty( $request_text ) ) {
			$deterministic_plan = self::infer_deterministic_plan( $request_text );
			if ( is_wp_error( $deterministic_plan ) ) {
				return $deterministic_plan;
			}
			if ( ! empty( $deterministic_plan ) ) {
				$plan = array_replace_recursive( $deterministic_plan, $plan );
				if ( $post_id > 0 ) {
					$plan['post_id'] = $post_id;
				}
				if ( 'direct' === $planner_source ) {
					$planner_source = 'deterministic';
				}
			}
		}

		$handled_resolved_rewrite = false;
		$existing_operation = sanitize_key( (string) ( $plan['operation'] ?? '' ) );
		$has_explicit_fields = ! empty( $plan['fields'] ) && is_array( $plan['fields'] );
		$can_apply_resolved_rewrite = ( '' === $existing_operation || 'update' === $existing_operation ) && ! $has_explicit_fields;
		if ( $can_apply_resolved_rewrite && self::is_tone_rewrite_request( $request_text, $plan ) ) {
			$tone_rewrite_plan = self::prepare_tone_rewrite_plan( $request_text, $plan, $payload );
			if ( is_wp_error( $tone_rewrite_plan ) ) {
				return $tone_rewrite_plan;
			}

			$plan = array_replace_recursive(
				$plan,
				is_array( $tone_rewrite_plan['plan'] ?? null ) ? $tone_rewrite_plan['plan'] : []
			);
			if ( $post_id > 0 ) {
				$plan['post_id'] = $post_id;
			}
			$planner_source = 'ai_rewrite';
			$handled_resolved_rewrite = true;
			$planner_warnings = array_merge(
				$planner_warnings,
				is_array( $tone_rewrite_plan['warnings'] ?? null ) ? $tone_rewrite_plan['warnings'] : []
			);
			if ( is_array( $tone_rewrite_plan['meta'] ?? null ) ) {
				$planner_meta = $tone_rewrite_plan['meta'];
			}
		} elseif ( $can_apply_resolved_rewrite && self::should_attempt_targeted_rewrite_plan( $request_text, $plan ) ) {
			$targeted_rewrite_plan = self::prepare_targeted_rewrite_plan( $request_text, $plan, $payload );
			if ( ! is_wp_error( $targeted_rewrite_plan ) ) {
				$plan = array_replace_recursive(
					$plan,
					is_array( $targeted_rewrite_plan['plan'] ?? null ) ? $targeted_rewrite_plan['plan'] : []
				);
				if ( $post_id > 0 ) {
					$plan['post_id'] = $post_id;
				}
				$planner_source = 'ai_rewrite';
				$handled_resolved_rewrite = true;
				$planner_warnings = array_merge(
					$planner_warnings,
					is_array( $targeted_rewrite_plan['warnings'] ?? null ) ? $targeted_rewrite_plan['warnings'] : []
				);
				if ( is_array( $targeted_rewrite_plan['meta'] ?? null ) ) {
					$planner_meta = $targeted_rewrite_plan['meta'];
				}
			}
		}

		$prefer_ai = ! empty( $payload['prefer_ai'] ) || ! empty( $payload['force_ai'] );
		$needs_ai = ! $handled_resolved_rewrite && ( $prefer_ai || empty( $plan['operation'] ) );
		if ( $needs_ai && ! empty( $request_text ) ) {
			$ai_plan = self::call_ai_planner( $request_text, $payload );
			if ( is_wp_error( $ai_plan ) ) {
				if ( ! empty( $payload['force_ai'] ) || empty( $plan['operation'] ) ) {
					return $ai_plan;
				}
				$planner_warnings[] = $ai_plan->get_error_message();
			} elseif ( is_array( $ai_plan ) ) {
				$plan = array_replace_recursive( $plan, $ai_plan );
				if ( $post_id > 0 ) {
					$plan['post_id'] = $post_id;
				}
				$planner_source = self::get_ai_planner_backend();
			}
		}

		if ( empty( $plan['operation'] ) ) {
			return new WP_Error( 'sae_plan_missing_operation', 'Missing operation. Provide request text or a plan object with operation.', [ 'status' => 400 ] );
		}

		$prepared = self::prepare_plan_operation( $plan, $request_text );
		// Missing fields/target stay 400 (one page) or skip (bundle child).
		// Retrying those with a planner hop invented insert copy and retargeted
		// heading-only pages as paragraph updates.
		$retryable_plan_errors = [
			'sae_plan_missing_block_name',
			'sae_plan_post_missing',
			'sae_plan_post_unresolved',
		];
		$ai_planner_backends = [ 'ai_engine', 'client', 'openai' ];
		if (
			is_wp_error( $prepared ) &&
			! in_array( $planner_source, $ai_planner_backends, true ) &&
			! empty( $request_text ) &&
			in_array( $prepared->get_error_code(), $retryable_plan_errors, true )
		) {
			$ai_plan = self::call_ai_planner( $request_text, $payload );
			if ( is_wp_error( $ai_plan ) ) {
				$planner_warnings[] = $ai_plan->get_error_message();
			} elseif ( is_array( $ai_plan ) ) {
				$plan = array_replace_recursive( $plan, $ai_plan );
				if ( $post_id > 0 ) {
					$plan['post_id'] = $post_id;
				}
				$planner_source = self::get_ai_planner_backend();
				$prepared = self::prepare_plan_operation( $plan, $request_text );
			}
		}
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$planner_warnings = array_values(
			array_unique(
				array_filter(
					array_merge(
						$planner_warnings,
						is_array( $prepared['warnings'] ?? null ) ? $prepared['warnings'] : []
					)
				)
			)
		);

		$dry_run = null;
		if ( $include_dry_run ) {
			$response_mode = self::resolve_response_mode( $payload );
			$dry_run = self::run_plan_dry_run( $prepared, $response_mode, $origin, $context );
			if ( is_wp_error( $dry_run ) ) {
				return $dry_run;
			}
		}

		return [
			'plan' => $plan,
			'prepared' => $prepared,
			'dry_run' => $dry_run,
			'planner_source' => $planner_source,
			'planner_warnings' => $planner_warnings,
			'planner_meta' => $planner_meta,
		];
	}

	/**
	 * One instruction × N allowlisted posts. Each child is a real mutation_v1.
	 *
	 * @return array|WP_Error
	 */
	private static function plan_bundle( $origin, array $payload, $request_text, array $plan, array $context ) {
		$origin = self::normalize_token_origin( $origin );
		$resolved = self::resolve_bundle_post_ids( $payload, $request_text );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$post_ids = is_array( $resolved['ids'] ?? null ) ? $resolved['ids'] : [];
		$skipped = is_array( $resolved['skipped'] ?? null ) ? $resolved['skipped'] : [];
		if ( count( $post_ids ) < 1 ) {
			return new WP_Error(
				'sae_bundle_empty',
				'A bundle needs at least one allowlisted page.',
				[ 'status' => 400 ]
			);
		}

		$capacity = self::assert_durable_plan_capacity( 1 + count( $post_ids ) );
		if ( is_wp_error( $capacity ) ) {
			return $capacity;
		}

		if ( empty( $plan['operation'] ) && ! empty( $request_text ) ) {
			$deterministic_plan = self::infer_deterministic_plan( $request_text );
			if ( is_wp_error( $deterministic_plan ) ) {
				return $deterministic_plan;
			}
			if ( ! empty( $deterministic_plan ) ) {
				$plan = array_replace_recursive( $deterministic_plan, $plan );
			}
		}

		$parent_id = self::allocate_plan_id();
		if ( '' === $parent_id ) {
			return new WP_Error( 'sae_bundle_empty', 'Could not allocate a bundle id.', [ 'status' => 500 ] );
		}

		$children = [];
		$child_ids = [];
		$skip_remaining_error = null;
		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( $skip_remaining_error ) {
				$skipped[] = [
					'post_id' => $post_id,
					'code' => $skip_remaining_error->get_error_code(),
					'message' => $skip_remaining_error->get_error_message(),
				];
				continue;
			}

			$compiled = self::compile_one_mutation_target(
				[
					'request_text' => $request_text,
					'plan' => $plan,
					'payload' => array_merge(
						$payload,
						[
							'verbose' => true,
						]
					),
					'post_id' => $post_id,
					'origin' => $origin,
					'context' => array_merge(
						$context,
						[
							'plan_only' => true,
						]
					),
					'include_dry_run' => true,
					'planner_source' => 'direct',
				]
			);
			if ( is_wp_error( $compiled ) ) {
				$skipped[] = [
					'post_id' => $post_id,
					'code' => $compiled->get_error_code(),
					'message' => $compiled->get_error_message(),
				];
				if ( self::planner_error_skips_remaining( $compiled ) ) {
					$skip_remaining_error = $compiled;
				}
				continue;
			}

			$prepared = is_array( $compiled['prepared'] ?? null ) ? $compiled['prepared'] : [];
			$dry_run = $compiled['dry_run'] ?? null;
			$planner_source = (string) ( $compiled['planner_source'] ?? 'direct' );

			$child_id = self::allocate_plan_id();
			if ( '' === $child_id ) {
				$skipped[] = [
					'post_id' => $post_id,
					'code' => 'sae_bundle_empty',
					'message' => 'Could not allocate a child plan id.',
				];
				continue;
			}

			$post = get_post( $post_id );
			$child_response = [
				'request' => $request_text,
				'intent' => 'do',
				'planner' => [
					'source' => $planner_source,
				],
				'post' => [
					'post_id' => $post_id,
					'post_title' => $post ? sanitize_text_field( (string) get_the_title( $post_id ) ) : '',
					'post_slug' => $post ? sanitize_title( (string) $post->post_name ) : '',
					'post_url' => $post ? esc_url_raw( (string) get_permalink( $post ) ) : '',
				],
				'operation' => $prepared['operation'],
				'endpoint' => $prepared['endpoint'],
				'payload' => $prepared['payload'],
				'dry_run' => is_array( $dry_run ) ? $dry_run : null,
			];
			$preview_fields = self::bundle_child_preview_fields( $prepared, $post_id, is_array( $dry_run ) ? $dry_run : [] );
			$stored_id = self::create_durable_mutation_plan(
				$origin,
				$child_response,
				[
					'id' => $child_id,
					'extra_payload' => [
						'bundle_id' => $parent_id,
						'review' => $preview_fields,
					],
					'skip_prune' => true,
					'skip_capacity' => true,
				]
			);
			if ( is_wp_error( $stored_id ) || '' === $stored_id ) {
				$skipped[] = [
					'post_id' => $post_id,
					'code' => is_wp_error( $stored_id ) ? $stored_id->get_error_code() : 'sae_bundle_empty',
					'message' => is_wp_error( $stored_id ) ? $stored_id->get_error_message() : 'Could not persist a child plan.',
				];
				continue;
			}

			$child_ids[] = $stored_id;
			$children[] = [
				'plan_id' => $stored_id,
				'post_id' => $post_id,
				'post_title' => $post ? sanitize_text_field( (string) get_the_title( $post_id ) ) : '',
				'field' => $preview_fields['field'],
				'before' => $preview_fields['before'],
				'after' => $preview_fields['after'],
				'state' => 'planned',
				'selected' => true,
			];
		}

		if ( count( $children ) < 1 ) {
			self::cancel_durable_plan_ids( $child_ids );
			return new WP_Error(
				'sae_bundle_empty',
				'A bundle needs at least one compiled page.',
				[
					'status' => 400,
					'skipped' => $skipped,
				]
			);
		}

		$selected_ids = $child_ids;
		$stored_parent = self::create_durable_bundle_plan(
			$origin,
			$request_text,
			$parent_id,
			$child_ids,
			$selected_ids,
			$skipped
		);
		if ( '' === $stored_parent ) {
			self::cancel_durable_plan_ids( $child_ids );
			return new WP_Error( 'sae_bundle_empty', 'Could not persist the bundle.', [ 'status' => 500 ] );
		}

		self::prune_durable_plans( array_merge( [ $parent_id ], $child_ids ) );

		self::audit_log(
			'plan_bundle',
			[
				'post_id' => 0,
				'operation' => 'bundle',
				'origin' => $origin,
				'plan_id' => $parent_id,
				'child_count' => count( $child_ids ),
				'skipped_count' => count( $skipped ),
			]
		);

		$envelope = self::present_bundle_plan_envelope( self::get_agent_plan_record( $parent_id ) );
		if ( empty( $envelope['children'] ) ) {
			$envelope['children'] = $children;
			$envelope['skipped'] = $skipped;
		}

		return $envelope;
	}

	/**
	 * Persist a mutation_v1 durable plan row.
	 *
	 * @return string|WP_Error plan_id, empty string when not stored, or 429 at capacity.
	 */
	private static function create_durable_mutation_plan( $origin, array $response, array $options = [] ) {
		$origin = self::normalize_token_origin( $origin );
		if ( ! in_array( $origin, [ 'mcp', 'rest' ], true ) ) {
			return '';
		}
		if ( 'do' !== sanitize_key( (string) ( $response['intent'] ?? 'do' ) ) ) {
			return '';
		}

		$operation = sanitize_key( (string) ( $response['operation'] ?? '' ) );
		if ( ! in_array( $operation, [ 'insert', 'update', 'remove', 'batch', 'cross_field' ], true ) ) {
			return '';
		}

		$payload = is_array( $response['payload'] ?? null ) ? $response['payload'] : [];
		$payload = self::strip_redeemable_fields( $payload );
		if ( ! empty( $options['extra_payload'] ) && is_array( $options['extra_payload'] ) ) {
			$payload = array_merge( $payload, $options['extra_payload'] );
		}
		if ( empty( $payload ) ) {
			return '';
		}

		$post = is_array( $response['post'] ?? null ) ? $response['post'] : [];
		$post_id = absint( $post['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return '';
		}

		$endpoint = self::sanitize_agent_plan_endpoint( (string) ( $response['endpoint'] ?? '' ) );
		if ( '' === $endpoint && 'batch' === $operation ) {
			$endpoint = sprintf( '/wp-json/struo/v1/posts/%d/blocks/batch', $post_id );
		}
		if ( '' === $endpoint ) {
			return '';
		}

		if ( empty( $options['skip_capacity'] ) ) {
			$capacity = self::assert_durable_plan_capacity( 1 );
			if ( is_wp_error( $capacity ) ) {
				return $capacity;
			}
		}

		try {
			$id = bin2hex( random_bytes( 8 ) );
		} catch ( Throwable $e ) {
			$id = '';
		}
		if ( ! empty( $options['id'] ) && self::validate_agent_plan_id( (string) $options['id'] ) ) {
			$id = (string) $options['id'];
		}
		if ( '' === $id ) {
			return '';
		}

		$now = time();
		$preview = is_array( $response['dry_run'] ?? null ) ? self::strip_redeemable_fields( $response['dry_run'] ) : null;
		$record = [
			'id' => $id,
			'payload_type' => 'mutation_v1',
			'state' => 'planned',
			'created_at' => $now,
			'expires_at' => $now + self::AGENT_PLAN_TTL,
			'user_id' => get_current_user_id(),
			'origin' => $origin,
			'post_id' => $post_id,
			'post' => [
				'post_id' => $post_id,
				'post_title' => sanitize_text_field( (string) ( $post['post_title'] ?? '' ) ),
				'post_slug' => sanitize_title( (string) ( $post['post_slug'] ?? '' ) ),
				'post_url' => esc_url_raw( (string) ( $post['post_url'] ?? '' ) ),
			],
			'operation' => $operation,
			'endpoint' => $endpoint,
			'request' => self::sanitize_plan_request_text( (string) ( $response['request'] ?? '' ) ),
			'payload' => $payload,
			'preview' => $preview,
			'base_content_hash' => self::hash_post_content( $post_id ),
			'base_content' => (string) get_post_field( 'post_content', $post_id ),
		];
		$witness_record = [
			'payload_type' => 'mutation_v1',
			'post_id' => $post_id,
			'operation' => $operation,
			'payload' => $payload,
		];
		if ( class_exists( 'Struo_Mutation_Recovery' ) && Struo_Mutation_Recovery::is_recoverable_record( $witness_record ) ) {
			$witness = Struo_Mutation_Recovery::current_witness( $witness_record );
			$record['base_content'] = $witness;
			$record['base_content_hash'] = Struo_Mutation_Recovery::hash_witness( $witness );
		}

		if ( ! self::insert_durable_plan_record( $record ) ) {
			return '';
		}

		if ( class_exists( 'Struo_Mutation_Journal' ) && Struo_Mutation_Journal::is_journaled_record( $record ) ) {
			global $wpdb;
			Struo_Mutation_Journal::ensure_table();
			$json = Struo_Mutation_Journal::stored_payload_json( $id );
			$hash = Struo_Mutation_Journal::hash_payload_json( $json );
			$queued = Struo_Mutation_Journal::append( $id, 1, 'queued', $hash, [ 'state' => 'planned' ] );
			if ( is_wp_error( $queued ) || '' === $json ) {
				self::delete_durable_plan_record( $id );
				return '';
			}
		}

		if ( empty( $options['skip_prune'] ) ) {
			self::prune_durable_plans( [ $id ] );
		}

		return $id;
	}

	/**
	 * Attach durable page_spec_v1 metadata and strip redeemable tokens.
	 */
	private static function attach_durable_page_spec_envelope( $origin, array $response, ?array $merge_into = null ) {
		$plan_id = self::create_durable_page_spec_plan( $origin, $response );
		if ( is_wp_error( $plan_id ) ) {
			return $plan_id;
		}
		$output = is_array( $merge_into ) ? $merge_into : $response;

		if ( '' === $plan_id ) {
			return $output;
		}

		$output['plan_id'] = $plan_id;
		$output['plan_state'] = 'planned';
		$output['payload_type'] = 'page_spec_v1';

		if ( is_array( $output['dry_run'] ?? null ) ) {
			$output['dry_run'] = self::strip_redeemable_fields( $output['dry_run'] );
		}

		return $output;
	}

	/**
	 * Persist a page_spec_v1 durable plan row (exact serialized content compiled at plan time).
	 *
	 * @return string|WP_Error plan_id, empty string when not stored, or 429 at capacity.
	 */
	private static function create_durable_page_spec_plan( $origin, array $response ) {
		$origin = self::normalize_token_origin( $origin );
		if ( 'rest' !== $origin ) {
			return '';
		}
		if ( 'create' !== sanitize_key( (string) ( $response['intent'] ?? '' ) ) ) {
			return '';
		}
		if ( 'create' !== sanitize_key( (string) ( $response['operation'] ?? '' ) ) ) {
			return '';
		}

		$page_spec = self::normalize_page_spec_payload(
			is_array( $response['payload'] ?? null ) ? $response['payload'] : []
		);
		if ( is_wp_error( $page_spec ) ) {
			return '';
		}

		$endpoint = self::sanitize_agent_plan_endpoint( (string) ( $response['endpoint'] ?? '' ) );
		if ( '' === $endpoint ) {
			return '';
		}

		$capacity = self::assert_durable_plan_capacity( 1 );
		if ( is_wp_error( $capacity ) ) {
			return $capacity;
		}

		try {
			$id = bin2hex( random_bytes( 8 ) );
		} catch ( Throwable $e ) {
			return '';
		}

		$now = time();
		$post = is_array( $response['post'] ?? null ) ? $response['post'] : [];
		$preview = is_array( $response['dry_run'] ?? null ) ? self::strip_redeemable_fields( $response['dry_run'] ) : null;
		$record = [
			'id' => $id,
			'payload_type' => 'page_spec_v1',
			'state' => 'planned',
			'created_at' => $now,
			'expires_at' => $now + self::AGENT_PLAN_TTL,
			'user_id' => get_current_user_id(),
			'origin' => $origin,
			'post_id' => 0,
			'post' => [
				'post_id' => 0,
				'post_title' => sanitize_text_field( (string) ( $post['post_title'] ?? $page_spec['title'] ) ),
				'post_slug' => sanitize_title( (string) ( $post['post_slug'] ?? $page_spec['title'] ) ),
				'post_url' => '',
			],
			'operation' => 'create',
			'endpoint' => $endpoint,
			'request' => self::sanitize_plan_request_text( (string) ( $response['request'] ?? '' ) ),
			'payload' => $page_spec,
			'preview' => $preview,
			'base_content_hash' => (string) $page_spec['content_hash'],
		];

		if ( ! self::insert_durable_plan_record( $record ) ) {
			return '';
		}

		self::prune_durable_plans( [ $id ] );

		return $id;
	}

	/**
	 * @deprecated Use attach_durable_page_spec_envelope / create_durable_page_spec_plan.
	 */
	private static function attach_durable_create_plan_envelope( $origin, array $response, ?array $merge_into = null ) {
		return self::attach_durable_page_spec_envelope( $origin, $response, $merge_into );
	}

	/**
	 * @deprecated Use create_durable_page_spec_plan.
	 */
	private static function create_durable_create_plan( $origin, array $response ) {
		return self::create_durable_page_spec_plan( $origin, $response );
	}

	/**
	 * Queue an MCP/Abilities plan for Mission Brief review. Never stores a
	 * confirmation token. REST/console plans are not queued — the operator
	 * already has the review panel.
	 *
	 * @return string Queued plan id, or empty string when not queued.
	 */
	private static function persist_mcp_agent_plan( $origin, array $response ) {
		if ( 'mcp' !== self::normalize_token_origin( $origin ) ) {
			return '';
		}

		return self::create_durable_mutation_plan( $origin, $response );
	}

	private static function sanitize_agent_plan_endpoint( $endpoint ) {
		$endpoint = (string) $endpoint;
		if ( preg_match( '#^/wp-json/struo/v1/posts/[0-9]+/(?:blocks|fields)/(?:insert|update|remove|batch)/?$#', $endpoint ) ) {
			return $endpoint;
		}
		if ( '/wp-json/struo/v1/pages/create' === $endpoint ) {
			return $endpoint;
		}

		return '';
	}

	private static function strip_redeemable_fields( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		unset( $value['confirmation_token'] );
		if ( isset( $value['confirmation'] ) && is_array( $value['confirmation'] ) ) {
			$origin = self::normalize_token_origin( $value['confirmation']['origin'] ?? 'rest' );
			$message = sanitize_text_field( (string) ( $value['confirmation']['message'] ?? '' ) );
			if ( '' === $message ) {
				$message = 'mcp' === $origin
					? 'Dry-run only: MCP-originated plans do not receive a redeemable confirmation token. Apply from the console.'
					: 'Plan-only: approve and apply via the durable plan envelope.';
			}
			$value['confirmation'] = [
				'redeemable' => false,
				'origin' => $origin,
				'message' => $message,
			];
		}

		foreach ( $value as $key => $child ) {
			if ( 'confirmation' === $key || ! is_array( $child ) ) {
				continue;
			}
			$value[ $key ] = self::strip_redeemable_fields( $child );
		}

		return $value;
	}

	private static function non_redeemable_plan_confirmation( $origin, array $existing = [] ) {
		$origin = self::normalize_token_origin( $origin );
		if ( isset( $existing['confirmation'] ) && is_array( $existing['confirmation'] ) ) {
			$existing['confirmation']['origin'] = $origin;
			return self::strip_redeemable_fields( $existing );
		}

		$existing['confirmation'] = [
			'redeemable' => false,
			'origin' => $origin,
			'message' => 'mcp' === $origin
				? 'Dry-run only: MCP-originated plans do not receive a redeemable confirmation token. Apply from the console.'
				: 'Plan-only: approve and apply via the durable plan envelope.',
		];

		return $existing;
	}

	private static function get_agent_plan_record( $id ) {
		$id = is_string( $id ) ? $id : '';
		if ( ! self::validate_agent_plan_id( $id ) ) {
			return null;
		}

		return self::get_durable_plan_record( $id );
	}

	private static function user_can_review_agent_plan( array $record ) {
		$post_id = absint( $record['post_id'] ?? 0 );
		$payload_type = sanitize_key( (string) ( $record['payload_type'] ?? '' ) );
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}

		if ( 'bundle_v1' === $payload_type ) {
			return self::user_can_edit_every_bundle_child( $record );
		}

		// page_spec / create plans mint before a post exists — authorize on create_posts.
		$operation    = sanitize_key( (string) ( $record['operation'] ?? '' ) );
		if ( 'page_spec_v1' !== $payload_type && 'create' !== $operation ) {
			return false;
		}

		$post_type = self::normalize_create_post_type(
			(string) ( $record['payload']['post_type'] ?? ( $record['post']['post_type'] ?? 'page' ) )
		);
		if ( '' === $post_type ) {
			$post_type = 'page';
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! is_object( $post_type_object ) ) {
			return false;
		}

		$create_cap = ! empty( $post_type_object->cap->create_posts )
			? (string) $post_type_object->cap->create_posts
			: 'edit_posts';

		return current_user_can( $create_cap );
	}

	private static function user_can_edit_every_bundle_child( array $record ) {
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$child_ids = is_array( $payload['child_ids'] ?? null ) ? $payload['child_ids'] : [];
		if ( empty( $child_ids ) ) {
			return false;
		}

		foreach ( $child_ids as $child_id ) {
			$child_id = (string) $child_id;
			if ( ! self::validate_agent_plan_id( $child_id ) ) {
				return false;
			}
			$child = self::get_durable_plan_record_unchecked( $child_id );
			$post_id = absint( $child['post_id'] ?? 0 );
			if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Waiting bundle_v1 parents and page_spec_v1 the operator may review.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function list_discovery_plan_summaries() {
		$records = self::list_durable_plan_records(
			[
				'origins' => [ 'rest', 'mcp' ],
				'states' => [ 'planned', 'approved' ],
				'include_expired' => false,
			]
		);
		$items = [];
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$payload_type = sanitize_key( (string) ( $record['payload_type'] ?? '' ) );
			if ( 'bundle_v1' !== $payload_type && 'page_spec_v1' !== $payload_type ) {
				continue;
			}
			$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
			if ( '' !== sanitize_text_field( (string) ( $payload['bundle_id'] ?? '' ) ) ) {
				continue;
			}
			$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
			if ( in_array( $state, [ 'planned', 'approved' ], true ) && absint( $record['expires_at'] ?? 0 ) <= time() ) {
				continue;
			}
			if ( ! self::user_can_review_agent_plan( $record ) ) {
				continue;
			}
			$items[] = self::present_discovery_item( $record );
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( (string) ( $right['created_at'] ?? '' ), (string) ( $left['created_at'] ?? '' ) );
			}
		);

		return $items;
	}

	/**
	 * Summary for the second list. No queue.persisted. No public HTTP.
	 *
	 * @return array<string, mixed>
	 */
	private static function present_discovery_item( array $record ) {
		$presented = self::present_agent_plan_record( $record, true );
		unset( $presented['queue'] );
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$child_ids = is_array( $payload['child_ids'] ?? null ) ? $payload['child_ids'] : [];
		$presented['child_count'] = count( $child_ids );

		return $presented;
	}

	private static function list_agent_plan_summaries() {
		// Work Queue is standalone recoverable mutation_v1 only.
		// bundle_v1 and page_spec_v1 wait in discovery (S4.5), not on these cards.
		$records = self::list_durable_plan_records(
			[
				'origins' => [ 'rest', 'mcp' ],
				'states' => [ 'planned', 'approved', 'applying', 'applied', 'failed' ],
				'include_expired' => true,
			]
		);
		$items = [];
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
			if ( in_array( $state, [ 'planned', 'approved' ], true ) && absint( $record['expires_at'] ?? 0 ) <= time() ) {
				continue;
			}
			if ( ! class_exists( 'Struo_Mutation_Recovery' ) || ! Struo_Mutation_Recovery::is_recoverable_record( $record ) ) {
				continue;
			}
			if ( ! self::user_can_review_agent_plan( $record ) ) {
				continue;
			}
			$items[] = self::present_work_queue_item( $record );
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return strcmp( (string) ( $right['created_at'] ?? '' ), (string) ( $left['created_at'] ?? '' ) );
			}
		);

		return $items;
	}

	private static function present_work_queue_item( array $record ) {
		$presented = self::present_agent_plan_record( $record, true );
		$hash = '';
		$match = false;
		if ( class_exists( 'Struo_Mutation_Recovery' ) && Struo_Mutation_Recovery::is_recoverable_record( $record ) ) {
			$hash = Struo_Mutation_Recovery::hash_witness( Struo_Mutation_Recovery::current_witness( $record ) );
			$expected = sanitize_text_field( (string) ( $record['expected_content_hash'] ?? '' ) );
			$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
			if ( 'applied' === $state && 64 === strlen( $expected ) ) {
				$match = hash_equals( $expected, $hash );
			} elseif ( 'applied' === $state ) {
				$match = true;
			}
		}
		$presented['queue'] = [
			'persisted' => [
				'match' => $match,
				'hash' => $hash,
			],
		];

		return $presented;
	}

	private static function present_agent_plan_record( array $record, $summary_only ) {
		$request = (string) ( $record['request'] ?? '' );
		$excerpt = $request;
		if ( strlen( $excerpt ) > 160 ) {
			$excerpt = rtrim( substr( $excerpt, 0, 157 ) ) . '...';
		}

		$presented = [
			'id' => (string) ( $record['id'] ?? '' ),
			'plan_id' => (string) ( $record['id'] ?? '' ),
			'payload_type' => sanitize_key( (string) ( $record['payload_type'] ?? 'mutation_v1' ) ),
			'state' => sanitize_key( (string) ( $record['state'] ?? 'planned' ) ),
			'created_at' => gmdate( 'c', absint( $record['created_at'] ?? time() ) ),
			'expires_at' => gmdate( 'c', absint( $record['expires_at'] ?? ( time() + self::AGENT_PLAN_TTL ) ) ),
			'origin' => sanitize_key( (string) ( $record['origin'] ?? 'mcp' ) ),
			'post_id' => absint( $record['post_id'] ?? 0 ),
			'post_title' => sanitize_text_field( (string) ( $record['post']['post_title'] ?? '' ) ),
			'operation' => sanitize_key( (string) ( $record['operation'] ?? '' ) ),
			'request' => $summary_only ? $excerpt : $request,
		];

		if ( $summary_only ) {
			return $presented;
		}

		$presented['post'] = is_array( $record['post'] ?? null ) ? $record['post'] : [ 'post_id' => $presented['post_id'] ];
		$presented['endpoint'] = (string) ( $record['endpoint'] ?? '' );
		$payload = self::strip_redeemable_fields(
			is_array( $record['payload'] ?? null ) ? $record['payload'] : []
		);
		if ( isset( $payload['serialized_content'] ) ) {
			$payload['serialized_content_bytes'] = strlen( (string) $payload['serialized_content'] );
			unset( $payload['serialized_content'] );
		}
		$presented['payload'] = $payload;
		if ( is_array( $record['preview'] ?? null ) ) {
			$presented['preview'] = self::strip_redeemable_fields( $record['preview'] );
		}
		if ( 'bundle_v1' === $presented['payload_type'] ) {
			$envelope = self::present_bundle_plan_envelope( $record );
			$presented['children'] = $envelope['children'];
			$presented['skipped'] = $envelope['skipped'];
			$presented['select_revision'] = $envelope['select_revision'];
			$presented['plan_state'] = $envelope['plan_state'];
		}

		return $presented;
	}

	public static function get_console_status() {
		$options = self::get_options();
		$branding = self::get_console_branding_config();
		$persona = self::get_ai_persona_config();
		$manifest = self::get_block_manifest();
		$allowed_posts = self::list_allowlisted_posts();
		$allowed_post_ids = array_values( array_map( 'absint', wp_list_pluck( $allowed_posts, 'post_id' ) ) );
		$allowed_blocks = self::get_allowed_block_types();
		$health = self::build_console_health_snapshot();
		$rate_limit = self::get_rate_limit_state();

		return [
			'ok' => true,
			'plugin' => [
				'name' => 'struo',
				'version' => self::VERSION,
			],
			'ai_provider' => [
				'name' => self::get_ai_provider(),
				'backend' => self::get_ai_planner_backend(),
				'last' => self::get_planner_last_hop(),
			],
			'platform' => [
				'abilities' => function_exists( 'wp_register_ability' ),
				'ai_client' => function_exists( 'wp_ai_client_prompt' ),
				'connectors' => self::wp_connectors_available(),
				'text_generation' => self::wp_ai_client_has_text_generation(),
			],
			'safety' => [
				'kill_switch' => ! empty( $options['kill_switch'] ),
				'confirmation_ttl_seconds' => self::CONFIRM_TTL,
				'idempotency_ttl_seconds' => self::IDEMPOTENCY_TTL,
				'batch_max_operations' => self::BATCH_MAX_OPERATIONS,
				'max_blocks' => self::MAX_BLOCKS,
			],
			'permissions' => [
				'can_manage_settings' => current_user_can( self::CAP_STRUO_MANAGE_SETTINGS ),
				'struo_plan' => current_user_can( self::CAP_STRUO_PLAN ),
				'struo_approve' => current_user_can( self::CAP_STRUO_APPROVE ),
				'struo_apply' => current_user_can( self::CAP_STRUO_APPLY ),
				'struo_manage_registry' => current_user_can( self::CAP_STRUO_MANAGE_REGISTRY ),
				'struo_manage_settings' => current_user_can( self::CAP_STRUO_MANAGE_SETTINGS ),
			],
			'branding' => [
				'display_name' => $branding['display_name'],
				'site_name' => $branding['site_name'],
				'colors' => [
					'primary' => $branding['colors']['primary'],
					'accent' => $branding['colors']['accent'],
				],
			],
			'rate_limit' => [
				'window_seconds' => self::RATE_LIMIT_WINDOW,
				'max' => self::RATE_LIMIT_MAX,
				'used' => $rate_limit['used'],
				'remaining' => $rate_limit['remaining'],
				'reset_at' => gmdate( 'c', $rate_limit['reset_at'] ),
			],
			'plan_spend' => self::get_plan_spend_state(),
			'persona' => $persona,
			'allowlist' => [
				'post_ids' => $allowed_post_ids,
				'posts' => $allowed_posts,
				'content_types' => self::get_content_type_registry( $options ),
				'blocks' => $allowed_blocks,
				'allowed_post_count' => count( $allowed_post_ids ),
				'allowed_block_count' => count( $allowed_blocks ),
				'manifest_coverage_count' => count( $manifest ),
			],
			'health' => $health,
			'agent_plans' => self::list_agent_plan_summaries(),
			'discovery_plans' => self::list_discovery_plan_summaries(),
			'generated_at' => gmdate( 'c' ),
		];
	}

	public static function get_console_audit( WP_REST_Request $request ) {
		nocache_headers();
		$page = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = absint( $request->get_param( 'per_page' ) );
		if ( $per_page <= 0 ) {
			$per_page = 25;
		}
		$per_page = min( $per_page, 100 );
		$filters = self::parse_console_audit_filters( $request );

		$result = self::query_audit_entries(
			[
				'page' => $page,
				'per_page' => $per_page,
				'action' => $filters['action'],
				'post_id' => $filters['post_id'],
				'since' => $filters['since'],
				'exclude_actions' => $filters['exclude_actions'],
			]
		);

		return [
			'ok' => true,
			'source' => $result['source'],
			'items' => $result['items'],
			'pagination' => [
				'page' => $page,
				'per_page' => $per_page,
				'total' => $result['total'],
			],
		];
	}

	public static function get_console_audit_export( WP_REST_Request $request ) {
		nocache_headers();
		$filters = self::parse_console_audit_filters( $request );
		$format = sanitize_key( (string) $request->get_param( 'format' ) );
		if ( ! in_array( $format, [ 'csv', 'json' ], true ) ) {
			$format = 'json';
		}

		$max_rows = absint( $request->get_param( 'max_rows' ) );
		if ( $max_rows <= 0 ) {
			$max_rows = 1000;
		}
		$max_rows = min( $max_rows, 5000 );

		$page = 1;
		$per_page = 100;
		$items = [];
		$source = 'table';

		while ( count( $items ) < $max_rows ) {
			$result = self::query_audit_entries(
				[
					'page' => $page,
					'per_page' => $per_page,
					'action' => $filters['action'],
					'post_id' => $filters['post_id'],
					'since' => $filters['since'],
					'exclude_actions' => $filters['exclude_actions'],
				]
			);

			if ( 1 === $page ) {
				$source = sanitize_text_field( (string) ( $result['source'] ?? 'table' ) );
			}
			$chunk = is_array( $result['items'] ?? null ) ? $result['items'] : [];
			if ( empty( $chunk ) ) {
				break;
			}

			$remaining = $max_rows - count( $items );
			if ( count( $chunk ) > $remaining ) {
				$chunk = array_slice( $chunk, 0, $remaining );
			}
			$items = array_merge( $items, $chunk );

			if ( count( $chunk ) < $per_page ) {
				break;
			}
			$page++;
		}

		$timestamp = gmdate( 'Ymd-His' );
		if ( 'csv' === $format ) {
			return [
				'ok' => true,
				'format' => 'csv',
				'source' => $source,
				'count' => count( $items ),
				'filename' => "sae-history-{$timestamp}.csv",
				'content' => self::build_console_audit_csv( $items ),
			];
		}

		return [
			'ok' => true,
			'format' => 'json',
			'source' => $source,
			'count' => count( $items ),
			'filename' => "sae-history-{$timestamp}.json",
			'items' => $items,
		];
	}

	private static function parse_console_audit_filters( WP_REST_Request $request ) {
		$action_filter = sanitize_text_field( (string) $request->get_param( 'action' ) );
		$post_id_filter = absint( $request->get_param( 'post_id' ) );
		$since_filter = sanitize_text_field( (string) $request->get_param( 'since' ) );
		$exclude_actions_param = $request->get_param( 'exclude_actions' );
		$exclude_actions = [];
		if ( is_array( $exclude_actions_param ) ) {
			$exclude_actions = array_map( 'sanitize_key', $exclude_actions_param );
		} elseif ( is_string( $exclude_actions_param ) ) {
			$exclude_actions = array_map( 'sanitize_key', array_filter( array_map( 'trim', explode( ',', $exclude_actions_param ) ) ) );
		}
		return [
			'action' => $action_filter,
			'post_id' => $post_id_filter,
			'since' => $since_filter,
			'exclude_actions' => array_values( array_filter( $exclude_actions ) ),
		];
	}

	private static function build_console_audit_csv( array $items ) {
		$rows = [ 'time,action,post_id,user_id,user_label,details' ];
		foreach ( $items as $item ) {
			$rows[] = implode(
				',',
				[
					self::escape_console_csv_cell( $item['created_at'] ?? '' ),
					self::escape_console_csv_cell( $item['action'] ?? '' ),
					self::escape_console_csv_cell( absint( $item['post_id'] ?? 0 ) ),
					self::escape_console_csv_cell( absint( $item['user_id'] ?? 0 ) ),
					self::escape_console_csv_cell( $item['user_label'] ?? '' ),
					self::escape_console_csv_cell( wp_json_encode( $item['details'] ?? [] ) ),
				]
			);
		}
		return implode( "\n", $rows );
	}

	private static function escape_console_csv_cell( $value ) {
		$text = (string) $value;
		if ( preg_match( '/^\s*[=+\-@]/', $text ) || preg_match( '/^[\t\r]/', $text ) ) {
			$text = "'" . $text;
		}
		$text = str_replace( '"', '""', $text );
		return '"' . $text . '"';
	}

	public static function set_console_kill_switch( WP_REST_Request $request ) {
		$payload = self::get_request_payload( $request );
		$enabled = ! empty( $payload['enabled'] ) ? 1 : 0;
		$options = self::get_options();
		$options['kill_switch'] = $enabled;

		update_option( self::OPTION_KEY, $options, false );
		self::audit_log(
			'console_kill_switch',
			[
				'enabled' => (bool) $enabled,
				'toggled_by' => get_current_user_id(),
			]
		);

		return [
			'ok' => true,
			'kill_switch' => (bool) $enabled,
			'updated_at' => gmdate( 'c' ),
		];
	}

	private static function sanitize_plan_request_text( $value ) {
		$text = wp_strip_all_tags( (string) $value );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( strlen( $text ) > self::PLAN_MAX_REQUEST_LENGTH ) {
			$text = substr( $text, 0, self::PLAN_MAX_REQUEST_LENGTH );
		}
		return $text;
	}

	private static function plan_has_mutation_directives( array $plan ) {
		$keys = [ 'operation', 'block_name', 'fields', 'target', 'position', 'parent_path', 'remove_all' ];
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $plan ) ) {
				continue;
			}
			$value = $plan[ $key ];
			if ( is_array( $value ) && ! empty( $value ) ) {
				return true;
			}
			if ( ! is_array( $value ) && '' !== (string) $value && null !== $value ) {
				return true;
			}
		}

		return false;
	}

	private static function extract_requested_tone_phrase( $request_text ) {
		$request_text = (string) $request_text;
		if ( '' === trim( $request_text ) ) {
			return '';
		}

		if ( preg_match( '/\bin\s+((?:an?\s+|the\s+)?[a-z0-9][a-z0-9 ,&\/-]{1,80}\s+tone)\b/i', $request_text, $matches ) ) {
			return sanitize_text_field( trim( $matches[1] ) );
		}

		if ( preg_match( '/\btone\s*(?:to|as|is)\s+([a-z0-9][a-z0-9 ,&\/-]{1,80})\b/i', $request_text, $matches ) ) {
			return sanitize_text_field( trim( $matches[1] ) . ' tone' );
		}

		return '';
	}

	private static function is_tone_rewrite_request( $request_text, array $plan = [] ) {
		$raw_text = (string) $request_text;
		$normalized_text = self::normalize_match_phrase( $raw_text );
		if ( '' === $normalized_text ) {
			return false;
		}

		if ( '' === self::extract_requested_tone_phrase( $raw_text ) ) {
			return false;
		}

			$rewrite_signal = preg_match( '/\b(rewrite|rephrase|polish|refresh|refine|adapt|adjust|shift)\b/i', $raw_text );
		$edit_signal = preg_match( '/\b(update|change|edit|replace)\b/i', $raw_text );
		if ( ! $rewrite_signal && ! $edit_signal ) {
			return false;
		}

		if ( ! empty( $plan['operation'] ) ) {
			return true;
		}

		return ! preg_match( '/\b(ideas?|suggest(?:ion|ions)?|recommend(?:ation|ations)?|brainstorm|examples?)\b/i', $normalized_text );
	}

	private static function infer_request_intent( $request_text, array $plan = [] ) {
		if ( self::plan_has_mutation_directives( $plan ) ) {
			return 'do';
		}

		$raw_text = (string) $request_text;
		$text = self::normalize_match_phrase( $raw_text );
		if ( empty( $text ) ) {
			return 'do';
		}

		if ( self::is_tone_rewrite_request( $raw_text, $plan ) ) {
			return 'do';
		}

		if ( self::is_create_intent_request( $raw_text ) ) {
			return 'create';
		}

		if ( self::is_audit_intent_request( $raw_text ) ) {
			return 'audit';
		}

		$do_signals = [
			'/\bto\s*["\x{201C}]([^"\x{201D}]+)["\x{201D}]/ui',
			"/\bto\s*['\x{2018}\x{2019}]([^'\x{2018}\x{2019}]+)['\x{2018}\x{2019}]/ui",
			'/^\s*(?:please\s+)?(?:rewrite|rephrase|shorten|simplify|clarify|tighten|polish|refresh|refine|strengthen|soften|adapt|adjust)\b/i',
			'/^\s*(?:please\s+)?improve\b.+\b(headline|heading|title|subheading|subtitle|subhead|description|body|copy|content|paragraph|cta|button)\b/i',
			'/\b(set|change|update|replace|modify|rename|remove|delete|insert|add|append)\b/i',
			'/\b(improve|rewrite|polish|refresh)\b.+\b(to|with|as)\b/i',
			'/\b(rewrite|rephrase|polish|refresh|adapt|adjust|change|update)\b.+\bin\s+(?:an?\s+|the\s+)?[a-z0-9 ,&\/-]{2,80}\s+tone\b/i',
		];
		foreach ( $do_signals as $pattern ) {
			if ( preg_match( $pattern, $raw_text ) ) {
				return 'do';
			}
		}

		$ask_patterns = [
			'/\b(give|share|show)\s+me\b/',
			'/\bideas?\b/',
			'/\bsuggest(?:ion|ions)?\b/',
			'/\brecommend(?:ation|ations)?\b/',
			'/\bhow\s+(?:can|should)\b/',
			'/\bwhat\s+(?:should|can)\b/',
			'/\bbrainstorm\b/',
			'/\bimprove(?:ments?)?\b/',
			'/\bhelp\s+me\b/',
		];

		foreach ( $ask_patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return 'ask';
			}
		}

		if ( preg_match( '/\?\s*$/', $raw_text ) ) {
			return 'ask';
		}

		return 'do';
	}

	private static function is_create_intent_request( $request_text ) {
		$request_text = (string) $request_text;
		if ( '' === trim( $request_text ) ) {
			return false;
		}

		// Allow brief adjectives between the verb and the content type so
		// phrases like "Create a short blog post…" still count as create.
		return (bool) preg_match(
			'/\b(create|build|make|generate|scaffold)\b[\s\S]{0,40}?\b(?:new\s+)?(?:landing\s*page|blog\s*post|page|post|article)\b/i',
			$request_text
		);
	}

	private static function is_audit_intent_request( $request_text ) {
		$request_text = (string) $request_text;
		if ( '' === trim( $request_text ) ) {
			return false;
		}

		$normalized_text = self::normalize_match_phrase( $request_text );
		if ( '' === $normalized_text ) {
			return false;
		}

		$audit_signal = preg_match( '/\b(audit|analy[sz]e|assess|evaluate|critique|score)\b/i', $request_text )
			|| preg_match( '/\breview\b(?:\s+(?:this|the|my))?\s+(?:page|post|blog|article|content|copy|homepage|landing(?:\s+page)?)\b/i', $normalized_text )
			|| preg_match( '/\bwhat(?:\'s| is)\s+wrong\s+with\b/i', $normalized_text )
			|| preg_match( '/\bhow\s+(?:good|strong|clear|effective)\s+is\b/i', $normalized_text );
		if ( ! $audit_signal ) {
			return false;
		}

		if ( preg_match( '/\b(update|change|rewrite|rephrase|replace|set|remove|delete|insert|add|append|shorten|simplify|translate|create|build|make|generate)\b/i', $request_text ) ) {
			return false;
		}

		return (bool) preg_match(
			'/\b(this\s+(?:page|post|blog|article)|page|post|blog|article|homepage|landing(?:\s+page)?|content|copy)\b/i',
			$normalized_text
		);
	}

	private static function should_handle_ask_intent( $intent, array $plan, $request_text ) {
		if ( 'ask' !== (string) $intent ) {
			return false;
		}
		if ( empty( self::sanitize_plan_request_text( $request_text ) ) ) {
			return false;
		}
		if ( ! empty( $plan['operation'] ) ) {
			return false;
		}

		return ! self::plan_has_mutation_directives( $plan );
	}

	private static function should_attempt_targeted_rewrite_plan( $request_text, array $plan = [] ) {
		$raw_text = (string) $request_text;
		$normalized_text = self::normalize_match_phrase( $raw_text );
		if ( '' === $normalized_text ) {
			return false;
		}

		$operation = sanitize_key( (string) ( $plan['operation'] ?? '' ) );
		if ( '' !== $operation && 'update' !== $operation ) {
			return false;
		}

		$plan_fields = is_array( $plan['fields'] ?? null ) ? $plan['fields'] : [];
		if ( ! empty( $plan_fields ) ) {
			return false;
		}

		if ( '' !== self::extract_value_from_text( $raw_text ) ) {
			return false;
		}

		if ( self::is_create_intent_request( $raw_text ) ) {
			return false;
		}

		if ( preg_match( '/\b(ideas?|suggest(?:ion|ions)?|recommend(?:ation|ations)?|brainstorm|examples?)\b/i', $normalized_text ) ) {
			return false;
		}

		if ( self::is_tone_rewrite_request( $raw_text, $plan ) ) {
			return false;
		}

		return (bool) preg_match(
			'/^\s*(?:please\s+)?(?:(?:rewrite|rephrase|shorten|simplify|clarify|tighten|polish|refresh|refine|strengthen|soften|adapt|adjust)\b|improve\b.+\b(headline|heading|title|subheading|subtitle|subhead|description|body|copy|content|paragraph|cta|button)\b)/i',
			$raw_text
		);
	}

	private static function infer_deterministic_plan( $request_text ) {
		$request_text = self::sanitize_plan_request_text( $request_text );
		if ( empty( $request_text ) ) {
			return [];
		}

		if ( self::is_unsupported_mixed_update_insert_request( $request_text ) ) {
			return new WP_Error(
				'sae_plan_mixed_operation_unsupported',
				'I can update a headline or add a new subheading, but not both in one step yet. Try one change at a time.',
				[ 'status' => 400 ]
			);
		}

		$plan = [];
		$operation = self::infer_operation_from_text( $request_text );
		if ( empty( $operation ) ) {
			return [];
		}
		$plan['operation'] = $operation;

		$post_id = self::extract_post_id_from_text( $request_text );
		if ( $post_id > 0 ) {
			$plan['post_id'] = $post_id;
		}

		$post_url = self::extract_first_url_from_text( $request_text );
		if ( ! empty( $post_url ) ) {
			$plan['post_url'] = $post_url;
		}

		$target = self::extract_target_from_text( $request_text );
		if ( ! empty( $target ) ) {
			$plan['target'] = $target;
		}

		if ( in_array( $operation, [ 'insert', 'update', 'remove' ], true ) ) {
			$block_name = self::infer_block_name_from_text( $request_text );
			if ( ! empty( $block_name ) ) {
				$plan['block_name'] = $block_name;
			}
			if ( in_array( $operation, [ 'insert', 'update' ], true ) && ! empty( $block_name ) ) {
				$resolved_value = self::extract_value_from_text( $request_text );
				if ( '' !== $resolved_value ) {
					$primary_field = self::get_primary_field_for_block( $block_name );
					if ( '' !== $primary_field ) {
						$plan['fields'] = [
							$primary_field => $resolved_value,
						];
					}
				}
			}
		}

		if ( 'remove' === $operation ) {
			$plan['remove_all'] = (bool) preg_match( '/\b(remove|delete)\s+all\b/i', $request_text );
		}

		return $plan;
	}

	private static function is_unsupported_mixed_update_insert_request( $request_text ) {
		$normalized = self::normalize_match_phrase( $request_text );
		if ( '' === $normalized ) {
			return false;
		}

		$has_update_signal = preg_match(
			'/\b(update|change|edit|set|replace|rewrite|rephrase|shorten|simplify|clarify|tighten|polish|refresh|refine|strengthen|soften|adapt|adjust)\b/',
			$normalized
		) || preg_match(
			'/^\s*(?:please\s+)?improve\b.+\b(headline|heading|title|subheading|subtitle|subhead|description|body|copy|content|paragraph|cta|button)\b/i',
			(string) $request_text
		);
		if ( ! $has_update_signal ) {
			return false;
		}

		$has_insert_signal = preg_match( '/\b(add|insert|append)\b/', $normalized );
		if ( ! $has_insert_signal ) {
			return false;
		}

		return (bool) preg_match(
			'/\b(before|after|below|above|under|beneath|directly below|directly above|new|another|subhead|subheading|subtitle|paragraph|section|block)\b/',
			$normalized
		);
	}

	private static function infer_operation_from_text( $request_text ) {
		$text = strtolower( (string) $request_text );
		if ( preg_match( '/\b(remove|delete|erase|drop)\b/', $text ) ) {
			return 'remove';
		}
		if ( preg_match( '/\b(add|insert|append|create)\b/', $text ) ) {
			return 'insert';
		}
		if ( preg_match( '/^\s*(?:please\s+)?(?:(?:rewrite|rephrase|shorten|simplify|clarify|tighten|polish|refresh|refine|strengthen|soften|adapt|adjust)\b|improve\b.+\b(headline|heading|title|subheading|subtitle|subhead|description|body|copy|content|paragraph|cta|button)\b)/i', (string) $request_text ) ) {
			return 'update';
		}
		if ( preg_match( '/\b(update|change|edit|set|replace)\b/', $text ) ) {
			return 'update';
		}
		return '';
	}

	private static function extract_post_id_from_text( $request_text ) {
		$patterns = [
			'/\bpost\s+(\d+)\b/i',
			'/\bpage\s+(\d+)\b/i',
			'/[?&](?:p|page_id|post)=([0-9]+)/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, (string) $request_text, $matches ) ) {
				return absint( $matches[1] );
			}
		}

		return 0;
	}

	private static function extract_first_url_from_text( $request_text ) {
		if ( preg_match( '/https?:\/\/\S+/i', (string) $request_text, $matches ) ) {
			return rtrim( esc_url_raw( $matches[0] ), '),.;' );
		}
		return '';
	}

	private static function extract_target_from_text( $request_text ) {
		$target = [];
		$anchor = self::extract_anchor_from_text( $request_text );
		if ( ! empty( $anchor ) ) {
			$target['anchor'] = $anchor;
		}

		$block_id = self::extract_block_id_from_text( $request_text );
		if ( ! empty( $block_id ) ) {
			$target['block_id'] = $block_id;
		}

		$index_path = self::extract_index_path_from_text( $request_text );
		if ( ! empty( $index_path ) ) {
			$target['index_path'] = $index_path;
		}

		return $target;
	}

	private static function infer_insert_position_from_text( $request_text ) {
		$request_text = (string) $request_text;
		$normalized = self::normalize_match_phrase( $request_text );

		if ( preg_match( '/\b(before|after)\s+(?:block|index(?:_path)?|index path)\s*(\[[^\]]+\]|[0-9]+(?:\s*,\s*[0-9]+)*)/i', $request_text, $matches ) ) {
			$target_path = self::normalize_index_path( $matches[2] );
			if ( ! empty( $target_path ) ) {
				return [
					'type' => sanitize_key( $matches[1] ),
					'target' => [
						'index_path' => $target_path,
					],
				];
			}
		}

		if ( preg_match( '/\b(prepend|top|start|beginning)\b/', $normalized ) ) {
			return [ 'type' => 'prepend' ];
		}

		if ( preg_match( '/\b(append|bottom|end)\b/', $normalized ) ) {
			return [ 'type' => 'append' ];
		}

		return [];
	}

	private static function prepare_ask_intent_response( $request_text, array $plan, array $payload ) {
		$warnings = [];
		$post = [];
		$has_explicit_post_reference = absint( $plan['post_id'] ?? 0 ) > 0
			|| ! empty( $plan['post_url'] ?? '' )
			|| ! empty( $plan['post_slug'] ?? '' )
			|| ! empty( $plan['post_title'] ?? '' );

		$resolved_post = self::resolve_post_from_plan( $plan, $request_text );
		if ( is_wp_error( $resolved_post ) ) {
			$error_code = $resolved_post->get_error_code();
			if ( $has_explicit_post_reference || 'sae_plan_post_not_allowed' === $error_code ) {
				return $resolved_post;
			}
		} else {
			$post = $resolved_post;
		}

		$source = 'deterministic';
		$suggestions = [];
		$ai_result = self::call_ai_ideation_planner( $request_text, $payload, $post );
		if ( is_wp_error( $ai_result ) ) {
			$warnings[] = $ai_result->get_error_message();
			$suggestions = self::build_fallback_ask_suggestions( $request_text, $post );
		} elseif ( is_array( $ai_result ) ) {
			$source = 'ai_engine';
			$suggestions = array_slice( $ai_result['suggestions'] ?? [], 0, self::ASK_MAX_SUGGESTIONS );
		}

		if ( empty( $suggestions ) ) {
			$suggestions = self::build_fallback_ask_suggestions( $request_text, $post );
		}
		$suggestions = self::apply_variant_metadata_to_suggestions( $suggestions, $request_text );

		return [
			'source' => $source,
			'warnings' => $warnings,
			'post' => $post,
			'suggestions' => $suggestions,
		];
	}


	private static function prepare_audit_response( $request_text, array $plan, array $payload = [], $rag_context = null ) {
		$request_text = self::sanitize_plan_request_text( $request_text );
		if ( '' === $request_text ) {
			$request_text = 'Audit this page for clarity, CTA strength, readability, SEO, and accessibility.';
		}

		$resolved_post = self::resolve_post_from_plan( $plan, $request_text );
		if ( is_wp_error( $resolved_post ) ) {
			return $resolved_post;
		}

		$post_id = absint( $resolved_post['post_id'] ?? 0 );
		$read_permission = self::ensure_read_allowed( $post_id );
		if ( is_wp_error( $read_permission ) ) {
			return $read_permission;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'sae_audit_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}

		if ( ! is_array( $rag_context ) ) {
			$rag_context = self::retrieve_rag_context( trim( $request_text . ' ' . $post->post_title ) );
		}

		$block_summary = implode( "\n", self::summarize_blocks_for_audit( self::get_parsed_blocks( $post_id ) ) );
		$audit_result = self::call_ai_audit_planner( $request_text, $resolved_post, $block_summary, $payload, $rag_context );
		if ( is_wp_error( $audit_result ) ) {
			return $audit_result;
		}

		return [
			'request' => $request_text,
			'intent' => 'audit',
			'planner' => [
				'source' => 'ai_engine',
				'ai_used' => true,
				'warnings' => [],
				'vector' => self::build_vector_meta_payload( $rag_context ),
			],
			'post' => $resolved_post,
			'audit' => $audit_result,
			'vector_sources' => self::build_vector_sources_payload( $rag_context ),
		];
	}

	private static function summarize_blocks_for_audit( array $blocks, array $path = [], &$used_chars = 0, $max_chars = 7000 ) {
		$lines = [];
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$current_path = array_merge( $path, [ $index ] );
			$block_name = self::normalize_block_name( $block['blockName'] ?? '' );
			if ( '' === $block_name ) {
				if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					$child_lines = self::summarize_blocks_for_audit( $block['innerBlocks'], $current_path, $used_chars, $max_chars );
					if ( ! empty( $child_lines ) ) {
						$lines = array_merge( $lines, $child_lines );
					}
				}
				continue;
			}

			$pairs = self::build_block_prompt_field_pairs( $block, $block_name );
			$rendered_text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( render_block( $block ) ) ) );
			$excerpt = self::normalize_prompt_excerpt( $rendered_text, 180 );
			$line_parts = [ sprintf( '[%s] %s', implode( '.', $current_path ), $block_name ) ];
			if ( ! empty( $pairs ) ) {
				$line_parts[] = 'fields: ' . implode( ', ', $pairs );
			}
			if ( '' !== $excerpt ) {
				$line_parts[] = 'excerpt: "' . $excerpt . '"';
			}
			$line = implode( ' — ', $line_parts );
			$line_length = function_exists( 'mb_strlen' ) ? mb_strlen( $line ) : strlen( $line );
			if ( ( $used_chars + $line_length + 1 ) > $max_chars ) {
				break;
			}
			$lines[] = $line;
			$used_chars += $line_length + 1;

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$child_lines = self::summarize_blocks_for_audit( $block['innerBlocks'], $current_path, $used_chars, $max_chars );
				if ( ! empty( $child_lines ) ) {
					$lines = array_merge( $lines, $child_lines );
				}
			}
		}

		return $lines;
	}

	private static function prepare_resolved_rewrite_plan( $request_text, array $plan, array $payload = [], array $options = [] ) {
		$resolved_post = self::resolve_post_from_plan( $plan, $request_text );
		if ( is_wp_error( $resolved_post ) ) {
			return $resolved_post;
		}

		$post_id = absint( $resolved_post['post_id'] ?? 0 );
		$warnings = [];
		$tone_phrase = sanitize_text_field( (string) ( $options['tone_phrase'] ?? '' ) );
		$missing_target_message = ! empty( $options['missing_target_message'] )
			? (string) $options['missing_target_message']
			: 'Rewrite requests require a target block. Mention the section, headline, button, or block you want to change.';
		$empty_field_message = ! empty( $options['empty_field_message'] )
			? (string) $options['empty_field_message']
			: 'The selected field is empty. Provide an explicit replacement value instead of a rewrite request.';
		$noop_message = ! empty( $options['noop_message'] )
			? (string) $options['noop_message']
			: 'Rewrite output matched the existing content. Add more direction or target a different field.';
		$field_inferred_template = ! empty( $options['field_inferred_warning'] )
			? (string) $options['field_inferred_warning']
			: 'Rewrite inferred field "%s". If you meant a different field, include it in your request.';
		$rewrite_context = is_array( $options['rewrite_context'] ?? null ) ? $options['rewrite_context'] : [];

		$target = self::resolve_plan_target( $plan, $request_text, $post_id, 'update', $warnings );
		if ( empty( $target ) ) {
			return new WP_Error(
				'sae_plan_missing_target',
				$missing_target_message,
				[ 'status' => 400 ]
			);
		}

		$blocks = self::get_parsed_blocks( $post_id );
		$target_result = self::find_target_block( $blocks, $target );
		if ( is_wp_error( $target_result ) ) {
			return $target_result;
		}

		$block = $target_result['parent'][ $target_result['index'] ] ?? [];
		if ( ! is_array( $block ) ) {
			return new WP_Error( 'sae_target_not_found', 'Target block not found for rewrite.', [ 'status' => 404 ] );
		}

		$block_name = (string) ( $block['blockName'] ?? '' );
		$schema = self::get_block_schema( $block_name );
		if ( empty( $schema ) ) {
			return new WP_Error( 'sae_block_schema_missing', 'No schema found for the target block.', [ 'status' => 400 ] );
		}

		$current_fields = self::extract_block_fields_for_rewrite( $block );
		$field_name = self::infer_rewrite_field_name( $request_text, $plan, $schema, $current_fields );
		if ( '' === $field_name ) {
			return new WP_Error(
				'sae_plan_missing_fields',
				'Unable to determine which field to rewrite. Mention a field like headline, content, or button text.',
				[ 'status' => 400 ]
			);
		}

		$plan_fields = is_array( $plan['fields'] ?? null ) ? $plan['fields'] : [];
		$request_normalized = self::normalize_match_phrase( $request_text );
		$field_inferred = empty( $plan_fields ) && ! preg_match(
			'/\b(field|cta|button|headline|heading|title|subheading|subtitle|subhead|description|body|copy|quote|citation|html|markup)\b/',
			$request_normalized
		);
		$field_label = trim( str_replace( '_', ' ', (string) $field_name ) );
		if ( $field_inferred ) {
			$warnings[] = sprintf(
				$field_inferred_template,
				$field_label ? $field_label : $field_name
			);
		}

		$current_value = self::normalize_rewrite_field_value( $current_fields[ $field_name ] ?? '' );
		if ( '' === $current_value ) {
			return new WP_Error(
				'sae_plan_missing_fields',
				$empty_field_message,
				[ 'status' => 400 ]
			);
		}

		$max_rewrite_chars = self::PLAN_MAX_REQUEST_LENGTH;
		$current_value_length = function_exists( 'mb_strlen' ) ? mb_strlen( $current_value ) : strlen( $current_value );
		if ( $current_value_length > $max_rewrite_chars ) {
			return new WP_Error(
				'sae_rewrite_value_too_long',
				sprintf(
					'Selected field content is too long to rewrite safely (%d characters). Target a shorter field (headline, subheading, button text), or provide an explicit replacement value.',
					absint( $current_value_length )
				),
				[
					'status' => 413,
					'max_chars' => $max_rewrite_chars,
				]
			);
		}

		$rewrite_result = self::call_ai_rewrite_planner(
			$request_text,
			array_merge(
				[
					'post' => $resolved_post,
					'target' => $target,
					'target_path' => is_array( $target_result['path'] ?? null ) ? $target_result['path'] : [],
					'block_name' => $block_name,
					'field_name' => $field_name,
					'current_value' => $current_value,
					'tone_phrase' => $tone_phrase,
				],
				$rewrite_context
			),
			$payload
		);
		if ( is_wp_error( $rewrite_result ) ) {
			return $rewrite_result;
		}

		$rewritten_value = self::normalize_rewrite_field_value( $rewrite_result['rewritten_value'] ?? '' );
		if ( '' === $rewritten_value ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI rewrite planner returned empty rewritten_value.', [ 'status' => 502 ] );
		}
		if ( $rewritten_value === $current_value ) {
			return new WP_Error(
				'sae_rewrite_noop',
				$noop_message,
				[ 'status' => 422 ]
			);
		}

		return [
			'plan' => [
				'operation' => 'update',
				'post_id' => $post_id,
				'target' => $target,
				'fields' => [
					$field_name => $rewritten_value,
				],
			],
			'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
			'meta' => [
				'field_name' => $field_name,
				'field_label' => $field_label,
				'field_inferred' => (bool) $field_inferred,
				'tone_phrase' => $tone_phrase,
			],
		];
	}

	private static function prepare_tone_rewrite_plan( $request_text, array $plan, array $payload = [] ) {
		return self::prepare_resolved_rewrite_plan(
			$request_text,
			$plan,
			$payload,
			[
				'tone_phrase' => self::extract_requested_tone_phrase( $request_text ),
				'missing_target_message' => 'Tone rewrite requests require a target block. Mention an anchor, block id, or block type.',
				'empty_field_message' => 'The selected field is empty. Provide an explicit replacement value instead of a tone rewrite request.',
				'noop_message' => 'Rewrite output matched the existing content. Try adding more direction (e.g. "more urgent") or choose a different tone.',
				'field_inferred_warning' => 'Tone rewrite inferred field "%s". If you meant a different field, include it in your request (e.g. "rewrite the headline in a casual tone").',
			]
		);
	}

	private static function prepare_targeted_rewrite_plan( $request_text, array $plan, array $payload = [] ) {
		return self::prepare_resolved_rewrite_plan(
			$request_text,
			$plan,
			$payload,
			[
				'missing_target_message' => 'Rewrite requests require a target block. Mention the headline, paragraph, button, or section you want to update.',
				'empty_field_message' => 'The selected field is empty. Provide an explicit replacement value instead of a rewrite request.',
				'noop_message' => 'Rewrite output matched the existing content. Add more direction or target a different field.',
				'field_inferred_warning' => 'Rewrite inferred field "%s". If you meant a different field, include it explicitly in your request.',
			]
		);
	}

	private static function extract_block_fields_for_rewrite( array $block ) {
		$block_name = (string) ( $block['blockName'] ?? '' );
		if ( self::is_core_block( $block_name ) ) {
			return self::extract_core_block_fields( $block );
		}

		$data = is_array( $block['attrs']['data'] ?? null ) ? $block['attrs']['data'] : [];
		$fields = [];
		foreach ( $data as $field_name => $value ) {
			$normalized_name = sanitize_key( (string) $field_name );
			if ( '' === $normalized_name || 0 === strpos( $normalized_name, '_' ) ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}
			$fields[ $normalized_name ] = (string) $value;
		}

		return $fields;
	}

	private static function normalize_rewrite_field_value( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	private static function is_textual_schema_field( array $schema_field ) {
		$type = sanitize_key( (string) ( $schema_field['type'] ?? '' ) );
		return in_array( $type, [ 'text', 'textarea', 'wysiwyg', 'email', 'url' ], true );
	}

	private static function find_first_existing_schema_field( array $schema, array $preferred_names ) {
		foreach ( $preferred_names as $preferred_name ) {
			$candidate = sanitize_key( (string) $preferred_name );
			if ( '' !== $candidate && isset( $schema[ $candidate ] ) ) {
				return $candidate;
			}
		}

		return '';
	}

	private static function infer_rewrite_field_name( $request_text, array $plan, array $schema, array $current_fields ) {
		$plan_fields = is_array( $plan['fields'] ?? null ) ? $plan['fields'] : [];
		foreach ( array_keys( $plan_fields ) as $field_name ) {
			$candidate = sanitize_key( (string) $field_name );
			if ( '' !== $candidate && isset( $schema[ $candidate ] ) ) {
				return $candidate;
			}
		}

		$schema_index = self::build_schema_match_index( $schema );
		if ( preg_match( '/\bfield\s+([a-z0-9 _-]{2,80})\b/i', (string) $request_text, $field_match ) ) {
			$matched_field = self::find_best_matching_field_name( $field_match[1], $schema_index );
			if ( '' !== $matched_field ) {
				return $matched_field;
			}
		}

		$request_normalized = self::normalize_match_phrase( $request_text );
		$pattern_preferences = [
			'/\b(cta|button)\b/' => [ 'button_text', 'cta_text', 'button_label', 'label', 'text', 'content' ],
			'/\b(headline|heading|title)\b/' => [ 'headline', 'heading', 'title', 'content', 'text' ],
			'/\b(subheading|subtitle|subhead)\b/' => [ 'subheading', 'subtitle', 'subhead', 'content', 'text' ],
			'/\b(description|body|copy)\b/' => [ 'description', 'copy', 'content', 'body', 'text' ],
			'/\b(quote|citation)\b/' => [ 'content', 'citation', 'quote' ],
			'/\b(html|markup)\b/' => [ 'html', 'content' ],
		];

		foreach ( $pattern_preferences as $pattern => $preferred_names ) {
			if ( ! preg_match( $pattern, $request_normalized ) ) {
				continue;
			}
			$matched_field = self::find_first_existing_schema_field( $schema, $preferred_names );
			if ( '' !== $matched_field ) {
				return $matched_field;
			}
		}

		foreach ( $current_fields as $field_name => $value ) {
			if ( ! isset( $schema[ $field_name ] ) ) {
				continue;
			}
			if ( ! self::is_textual_schema_field( $schema[ $field_name ] ) ) {
				continue;
			}
			if ( '' === self::normalize_rewrite_field_value( $value ) ) {
				continue;
			}
			return $field_name;
		}

		foreach ( $schema as $field_name => $schema_field ) {
			if ( self::is_textual_schema_field( $schema_field ) ) {
				return sanitize_key( (string) $field_name );
			}
		}

		return '';
	}

	private static function extract_anchor_from_text( $request_text ) {
		if ( preg_match( '/\banchor\s+([a-z0-9][a-z0-9_-]*)\b/i', (string) $request_text, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}
		if ( preg_match( '/#([a-z0-9][a-z0-9_-]*)\b/i', (string) $request_text, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}
		return '';
	}

	private static function extract_block_id_from_text( $request_text ) {
		if ( preg_match( '/\bblock[_\s-]?id\s*[:=]?\s*([a-z0-9][a-z0-9_-]*)\b/i', (string) $request_text, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}
		return '';
	}

	private static function extract_index_path_from_text( $request_text ) {
		if ( preg_match( '/\bindex(?:_path)?\s*[:=]?\s*(\[[^\]]+\]|[0-9]+(?:\s*,\s*[0-9]+)*)/i', (string) $request_text, $matches ) ) {
			return self::normalize_index_path( $matches[1] );
		}
		return [];
	}

	private static function normalize_index_path( $value ) {
		if ( is_array( $value ) ) {
			return array_values(
				array_filter(
					array_map(
						static function ( $item ) {
							return is_numeric( $item ) ? absint( $item ) : null;
						},
						$value
					),
					static function ( $item ) {
						return null !== $item;
					}
				)
			);
		}

		if ( ! is_string( $value ) ) {
			return [];
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return [];
		}

		if ( '[' === substr( $trimmed, 0, 1 ) ) {
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				return self::normalize_index_path( $decoded );
			}
		}

		$normalized = preg_replace( '/[^0-9,.-]/', '', $trimmed );
		$parts = preg_split( '/[,.]+/', (string) $normalized );
		$path = [];
		foreach ( $parts as $part ) {
			if ( '' === $part || ! is_numeric( $part ) ) {
				continue;
			}
			$path[] = absint( $part );
		}
		return $path;
	}

	private static function list_allowlisted_posts() {
		$posts = [];
		foreach ( self::get_allowed_post_ids() as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$post_status = sanitize_key( (string) $post->post_status );
			if ( in_array( $post_status, [ 'auto-draft', 'trash', 'inherit' ], true ) ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$post_content = (string) $post->post_content;
			$posts[] = [
				'post_id' => $post_id,
				'post_title' => get_the_title( $post_id ),
				'post_slug' => (string) $post->post_name,
				'post_url' => get_permalink( $post_id ),
				'post_type' => sanitize_key( (string) $post->post_type ),
				'post_status' => $post_status,
				'post_modified_gmt' => (string) get_post_field( 'post_modified_gmt', $post_id ),
				'word_count' => absint( str_word_count( wp_strip_all_tags( $post_content ) ) ),
			];
		}
		return $posts;
	}

	private static function normalize_match_phrase( $value ) {
		$normalized = strtolower( wp_strip_all_tags( (string) $value ) );
		$normalized = preg_replace( '/[^a-z0-9]+/', ' ', $normalized );
		return trim( preg_replace( '/\s+/', ' ', $normalized ) );
	}

	private static function find_allowlisted_post_by_slug( $slug, array $allowlisted_posts ) {
		$slug = self::normalize_match_phrase( $slug );
		if ( empty( $slug ) ) {
			return null;
		}

		foreach ( $allowlisted_posts as $post ) {
			$candidate = self::normalize_match_phrase( $post['post_slug'] ?? '' );
			if ( ! empty( $candidate ) && $slug === $candidate ) {
				return $post;
			}
		}

		return null;
	}

	private static function find_allowlisted_post_by_title( $title, array $allowlisted_posts ) {
		$title = self::normalize_match_phrase( $title );
		if ( empty( $title ) ) {
			return null;
		}

		foreach ( $allowlisted_posts as $post ) {
			$candidate = self::normalize_match_phrase( $post['post_title'] ?? '' );
			if ( empty( $candidate ) ) {
				continue;
			}
			if ( $candidate === $title || false !== strpos( $candidate, $title ) || false !== strpos( $title, $candidate ) ) {
				return $post;
			}
		}

		return null;
	}

	private static function extract_match_tokens( $value ) {
		$phrase = self::normalize_match_phrase( $value );
		if ( empty( $phrase ) ) {
			return [];
		}

		$stop_words = [
			'a',
			'an',
			'and',
			'at',
			'for',
			'from',
			'in',
			'into',
			'of',
			'on',
			'or',
			'that',
			'the',
			'this',
			'to',
			'with',
			'page',
			'section',
			'block',
			'update',
			'change',
			'set',
			'make',
			'add',
			'insert',
			'remove',
			'delete',
			'please',
		];

		$tokens = array_values( array_filter( explode( ' ', $phrase ) ) );
		$tokens = array_filter(
			$tokens,
			static function ( $token ) use ( $stop_words ) {
				if ( strlen( $token ) < 3 || is_numeric( $token ) ) {
					return false;
				}
				return ! in_array( $token, $stop_words, true );
			}
		);

		return array_values( array_unique( $tokens ) );
	}

	private static function score_allowlisted_post_from_request( $request_normalized, array $post ) {
		$request_normalized = self::normalize_match_phrase( $request_normalized );
		if ( empty( $request_normalized ) ) {
			return 0;
		}

		$title = self::normalize_match_phrase( $post['post_title'] ?? '' );
		$slug = self::normalize_match_phrase( str_replace( '-', ' ', (string) ( $post['post_slug'] ?? '' ) ) );
		if ( empty( $title ) && empty( $slug ) ) {
			return 0;
		}

		$score = 0;
		if ( ! empty( $title ) && false !== strpos( $request_normalized, $title ) ) {
			$score += 8;
		}
		if ( ! empty( $slug ) && false !== strpos( $request_normalized, $slug ) ) {
			$score += 7;
		}

		$request_tokens = self::extract_match_tokens( $request_normalized );
		$candidate_tokens = array_values(
			array_unique(
				array_merge(
					self::extract_match_tokens( $title ),
					self::extract_match_tokens( $slug )
				)
			)
		);
		if ( ! empty( $request_tokens ) && ! empty( $candidate_tokens ) ) {
			$shared_tokens = array_intersect( $request_tokens, $candidate_tokens );
			$score += count( $shared_tokens ) * 2;
		}

		if ( preg_match( '/\b(page|section)\b/', $request_normalized ) ) {
			$score += 1;
		}

		return $score;
	}

	private static function find_allowlisted_post_by_request_context( $request_text, array $allowlisted_posts ) {
		$request_text = (string) $request_text;
		$request_normalized = self::normalize_match_phrase( $request_text );
		if ( empty( $request_normalized ) || empty( $allowlisted_posts ) ) {
			return null;
		}

		$context_phrases = [];
		$context_patterns = [
			'/\b([a-z0-9][a-z0-9 -]{1,80})\s+(?:page|section)\b/i',
			'/\b(?:page|section)\s+(?:called|named)?\s*([a-z0-9][a-z0-9 -]{1,80})\b/i',
		];
		foreach ( $context_patterns as $pattern ) {
			if ( preg_match_all( $pattern, $request_text, $matches ) && ! empty( $matches[1] ) ) {
				foreach ( $matches[1] as $phrase ) {
					$normalized_phrase = self::normalize_match_phrase( $phrase );
					if ( ! empty( $normalized_phrase ) ) {
						$context_phrases[] = $normalized_phrase;
					}
				}
			}
		}

		$context_phrases = array_values( array_unique( $context_phrases ) );
		foreach ( $context_phrases as $phrase ) {
			$matched = self::find_allowlisted_post_by_slug( $phrase, $allowlisted_posts );
			if ( is_array( $matched ) ) {
				return $matched;
			}
			$matched = self::find_allowlisted_post_by_title( $phrase, $allowlisted_posts );
			if ( is_array( $matched ) ) {
				return $matched;
			}
		}

		$best_match = null;
		$best_score = 0;
		$has_tie = false;
		foreach ( $allowlisted_posts as $post ) {
			$score = self::score_allowlisted_post_from_request( $request_normalized, $post );
			if ( $score <= 0 ) {
				continue;
			}
			if ( $score > $best_score ) {
				$best_match = $post;
				$best_score = $score;
				$has_tie = false;
				continue;
			}
			if ( $score === $best_score ) {
				$has_tie = true;
			}
		}

		if ( $has_tie || $best_score < 3 ) {
			return null;
		}

		return is_array( $best_match ) ? $best_match : null;
	}

	private static function find_allowlisted_post_by_url( $url, array $allowlisted_posts ) {
		$url = trim( esc_url_raw( (string) $url ) );
		if ( empty( $url ) ) {
			return null;
		}

		$needle = untrailingslashit( strtolower( $url ) );
		foreach ( $allowlisted_posts as $post ) {
			$candidate = untrailingslashit( strtolower( (string) ( $post['post_url'] ?? '' ) ) );
			if ( ! empty( $candidate ) && $needle === $candidate ) {
				return $post;
			}
		}

		$path = wp_parse_url( $needle, PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path, '/' ) : '';
		if ( empty( $path ) ) {
			return null;
		}

		$parts = array_values( array_filter( explode( '/', $path ) ) );
		$slug = end( $parts );
		return self::find_allowlisted_post_by_slug( $slug, $allowlisted_posts );
	}

	private static function resolve_post_from_plan( array $plan, $request_text ) {
		$allowlisted_posts = self::list_allowlisted_posts();
		$allowlisted_post_ids = array_values( array_map( 'absint', wp_list_pluck( $allowlisted_posts, 'post_id' ) ) );

		$post_id = absint( $plan['post_id'] ?? 0 );
		if ( ! $post_id ) {
			$post_id = self::extract_post_id_from_text( $request_text );
		}
		if ( $post_id > 0 ) {
			if ( ! in_array( $post_id, $allowlisted_post_ids, true ) ) {
				return new WP_Error( 'sae_plan_post_not_allowed', 'Resolved post_id is not allowlisted.', [ 'status' => 403, 'post_id' => $post_id ] );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to plan this post.', [ 'status' => 403, 'post_id' => $post_id ] );
			}
			$post = get_post( $post_id );
			return [
				'post_id' => $post_id,
				'post_title' => $post ? get_the_title( $post_id ) : '',
				'post_slug' => $post ? (string) $post->post_name : '',
				'post_url' => $post ? get_permalink( $post_id ) : '',
				'resolved_by' => 'post_id',
			];
		}

		$post_url = trim( esc_url_raw( (string) ( $plan['post_url'] ?? '' ) ) );
		if ( empty( $post_url ) ) {
			$post_url = self::extract_first_url_from_text( $request_text );
		}
		if ( ! empty( $post_url ) ) {
			$matched = self::find_allowlisted_post_by_url( $post_url, $allowlisted_posts );
			if ( ! is_array( $matched ) ) {
				return new WP_Error( 'sae_plan_post_unresolved', 'Could not resolve post_url to an allowlisted post.', [ 'status' => 400 ] );
			}
			$matched['resolved_by'] = 'post_url';
			return $matched;
		}

		$post_slug = sanitize_title( (string) ( $plan['post_slug'] ?? '' ) );
		if ( empty( $post_slug ) && preg_match( '/\b(?:slug|page)\s+([a-z0-9][a-z0-9-]*)\b/i', (string) $request_text, $matches ) ) {
			$post_slug = sanitize_title( $matches[1] );
		}
		if ( ! empty( $post_slug ) ) {
			$matched = self::find_allowlisted_post_by_slug( $post_slug, $allowlisted_posts );
			if ( ! is_array( $matched ) ) {
				return new WP_Error( 'sae_plan_post_unresolved', 'Could not resolve post_slug to an allowlisted post.', [ 'status' => 400 ] );
			}
			$matched['resolved_by'] = 'post_slug';
			return $matched;
		}

		$post_title = trim( sanitize_text_field( (string) ( $plan['post_title'] ?? '' ) ) );
		if ( ! empty( $post_title ) ) {
			$matched = self::find_allowlisted_post_by_title( $post_title, $allowlisted_posts );
			if ( ! is_array( $matched ) ) {
				return new WP_Error( 'sae_plan_post_unresolved', 'Could not resolve post_title to an allowlisted post.', [ 'status' => 400 ] );
			}
			$matched['resolved_by'] = 'post_title';
			return $matched;
		}

		$matched = self::find_allowlisted_post_by_request_context( $request_text, $allowlisted_posts );
		if ( is_array( $matched ) ) {
			$matched['resolved_by'] = 'request_context';
			return $matched;
		}

		$matched = self::find_allowlisted_post_by_title( $request_text, $allowlisted_posts );
		if ( is_array( $matched ) ) {
			$matched['resolved_by'] = 'request_text';
			return $matched;
		}

		$hints = array_values(
			array_filter(
				array_map(
					static function ( $item ) {
						return trim( (string) ( $item['post_title'] ?? '' ) );
					},
					$allowlisted_posts
				)
			)
		);
		return new WP_Error(
			'sae_plan_post_missing',
			'Missing post reference. Include post_id, URL, slug, or page title.',
			[
				'status' => 400,
				'hints' => $hints,
			]
		);
	}

	private static function infer_block_name_from_text( $request_text ) {
		$raw_text = (string) $request_text;
		if ( preg_match( '/\b((?:acf|core)\/[a-z0-9_-]+)\b/i', $raw_text, $matches ) ) {
			$candidate = self::normalize_block_name( $matches[1] );
			if ( in_array( $candidate, self::get_allowed_block_types(), true ) ) {
				return $candidate;
			}
		}

		$request_text = self::normalize_match_phrase( $raw_text );
		if ( empty( $request_text ) ) {
			return '';
		}

		$best_match = '';
		$best_score = 0;
		foreach ( self::get_allowed_block_types() as $block_name ) {
			$slug = preg_replace( '#^(acf|core)/#', '', $block_name );
			$phrase = self::normalize_match_phrase( str_replace( [ '-', '_' ], ' ', $slug ) );
			$tokens = array_values( array_filter( explode( ' ', $phrase ) ) );
			$score = 0;
			foreach ( $tokens as $token ) {
				if ( in_array( $token, [ 'acf', 'core', 'v2', 'v3', 'block' ], true ) ) {
					continue;
				}
				if ( false !== strpos( $request_text, $token ) ) {
					$score++;
				}
			}

			if ( ! empty( $phrase ) && false !== strpos( $request_text, $phrase ) ) {
				$score += 2;
			}

			if ( $score > $best_score ) {
				$best_score = $score;
				$best_match = $block_name;
			}
		}

		return $best_score > 0 ? $best_match : '';
	}

	private static function infer_explicit_core_block_name_from_text( $request_text ) {
		$text = self::normalize_match_phrase( $request_text );
		if ( empty( $text ) ) {
			return '';
		}

		$alias_map = [
			'core/paragraph' => [ '/\bparagraphs?\b/' ],
			'core/heading' => [ '/\b(headings?|headline|headlines|title|titles)\b/' ],
			'core/list' => [ '/\b(lists?|bullets?|bullet points?|numbered lists?)\b/' ],
			'core/image' => [ '/\b(images?|photos?|figures?|illustrations?|screenshots?)\b/' ],
			'core/buttons' => [ '/\b(button groups?|button rows?|button sets?|buttons)\b/' ],
			'core/button' => [ '/\b(cta|cta button|button text|button label|button)\b/' ],
			'core/columns' => [ '/\b(columns?|two column|three column|multi column)\b/' ],
			'core/quote' => [ '/\b(quotes?|blockquote|blockquotes)\b/' ],
			'core/html' => [ '/\b(html|markup)\b/' ],
		];

		$allowed_blocks = self::get_allowed_block_types();
		foreach ( $alias_map as $block_name => $patterns ) {
			if ( ! in_array( $block_name, $allowed_blocks, true ) ) {
				continue;
			}

			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $text ) ) {
					return $block_name;
				}
			}
		}

		return '';
	}

	private static function extract_quoted_values( $request_text ) {
		$values = [];
		if ( preg_match_all( '/["“]([^"”]+)["”]/u', (string) $request_text, $matches ) && ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $value ) {
				$value = trim( (string) $value );
				if ( '' !== $value ) {
					$values[] = $value;
				}
			}
		}

		if ( preg_match_all( "/'([^']+)'/u", (string) $request_text, $matches_single ) && ! empty( $matches_single[1] ) ) {
			foreach ( $matches_single[1] as $value ) {
				$value = trim( (string) $value );
				if ( '' !== $value ) {
					$values[] = $value;
				}
			}
		}

		return array_values( array_unique( $values ) );
	}

	private static function extract_value_from_text( $request_text ) {
		$request_text = (string) $request_text;
		if ( '' === trim( $request_text ) ) {
			return '';
		}

		$patterns = [
			'/\b(?:to|with|as)\s+["“]([^"”]+)["”]/u',
			"/\b(?:to|with|as)\s+'([^']+)'/u",
		];
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $request_text, $matches ) ) {
				$value = trim( sanitize_text_field( (string) ( $matches[1] ?? '' ) ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		$tail_patterns = [
			'/\b(?:set|change|update|replace)\b[\w\s,-]*\b(?:to\s+say|to\s+read|so\s+it\s+says)\s+["“]?([^"”\r\n]+?)["”]?\s*[.!?]*$/u',
		];
		foreach ( $tail_patterns as $pattern ) {
			if ( preg_match( $pattern, $request_text, $matches ) ) {
				$value = trim( sanitize_text_field( (string) ( $matches[1] ?? '' ) ) );
				if ( '' !== $value && self::is_tail_value_candidate_usable( $value ) ) {
					return $value;
				}
			}
		}

		$quoted_values = self::extract_quoted_values( $request_text );
		if ( ! empty( $quoted_values ) ) {
			return trim( sanitize_text_field( (string) $quoted_values[0] ) );
		}

		return '';
	}

	private static function is_tail_value_candidate_usable( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === trim( $value ) ) {
			return false;
		}

		$normalized = self::normalize_match_phrase( $value );
		if ( '' === $normalized ) {
			return false;
		}

		if ( preg_match( '/^(?:more|less)\s+[a-z]+(?:ly|er|est)?$/i', $normalized ) ) {
			return false;
		}

		if ( preg_match( '/^(?:shorter|longer|simpler|clearer|stronger|softer|friendlier|more\s+\w+ly)$/i', $normalized ) ) {
			return false;
		}

		$word_count = preg_match_all( '/[\p{L}\p{N}]+/u', $normalized, $matches );
		if ( $word_count >= 2 ) {
			return true;
		}

		return (bool) preg_match( '/[,:;\/&-]/', $value );
	}

	private static function get_primary_field_for_block( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );
		if ( '' === $block_name ) {
			return '';
		}

		$schema = self::get_block_schema( $block_name );
		if ( empty( $schema ) || ! is_array( $schema ) ) {
			return '';
		}

		$preferred_fields_by_block = [
			'core/heading' => [ 'content', 'headline', 'heading', 'title', 'text' ],
			'core/paragraph' => [ 'content', 'text' ],
			'core/image' => [ 'caption', 'alt', 'url' ],
			'core/button' => [ 'text', 'label', 'content' ],
			'core/quote' => [ 'content', 'text', 'citation' ],
			'core/html' => [ 'html', 'content' ],
		];
		$preferred_fields = isset( $preferred_fields_by_block[ $block_name ] )
			? $preferred_fields_by_block[ $block_name ]
			: [ 'content', 'text', 'headline', 'heading', 'title', 'description', 'copy', 'body', 'html' ];

		foreach ( $preferred_fields as $preferred_field ) {
			if ( isset( $schema[ $preferred_field ] ) ) {
				return $preferred_field;
			}
		}

		foreach ( $schema as $field_name => $field_schema ) {
			$type = sanitize_key( (string) ( $field_schema['type'] ?? '' ) );
			if ( in_array( $type, [ 'text', 'textarea', 'wysiwyg', 'html' ], true ) ) {
				return sanitize_key( (string) $field_name );
			}
		}

		$schema_keys = array_keys( $schema );
		if ( ! empty( $schema_keys ) ) {
			return sanitize_key( (string) $schema_keys[0] );
		}

		return '';
	}

	private static function build_schema_match_index( array $schema ) {
		$index = [];
		foreach ( $schema as $field_name => $field ) {
			$canonical = sanitize_key( (string) $field_name );
			if ( empty( $canonical ) ) {
				continue;
			}

			$name_key = self::normalize_match_phrase( $canonical );
			if ( ! empty( $name_key ) ) {
				$index[ $name_key ] = $canonical;
			}

			$label_key = self::normalize_match_phrase( $field['label'] ?? '' );
			if ( ! empty( $label_key ) ) {
				$index[ $label_key ] = $canonical;
			}
		}

		return $index;
	}

	private static function find_best_matching_field_name( $phrase, array $index ) {
		$phrase = self::normalize_match_phrase( $phrase );
		if ( empty( $phrase ) || empty( $index ) ) {
			return '';
		}

		if ( isset( $index[ $phrase ] ) ) {
			return $index[ $phrase ];
		}

		$best_match = '';
		$best_length = 0;
		foreach ( $index as $candidate_phrase => $canonical_name ) {
			if ( false === strpos( $phrase, $candidate_phrase ) && false === strpos( $candidate_phrase, $phrase ) ) {
				continue;
			}
			$current_length = strlen( $candidate_phrase );
			if ( $current_length > $best_length ) {
				$best_length = $current_length;
				$best_match = $canonical_name;
			}
		}

		return $best_match;
	}

	private static function map_common_field_hint_to_schema( $field_hint, array $schema ) {
		$field_hint = sanitize_key( (string) $field_hint );
		if ( '' === $field_hint || empty( $schema ) ) {
			return '';
		}

		$hint_map = [
			'/\b(headline|heading|title)\b/' => [ 'headline', 'heading', 'title', 'content', 'text' ],
			'/\b(subheading|subtitle|subhead)\b/' => [ 'subheading', 'subtitle', 'content', 'text' ],
			'/\b(cta|button|label)\b/' => [ 'button_text', 'cta_text', 'button_label', 'label', 'text', 'content' ],
			'/\b(description|copy|body|paragraph|text)\b/' => [ 'description', 'copy', 'content', 'body', 'text' ],
			'/\b(html|markup)\b/' => [ 'html', 'content' ],
		];

		foreach ( $hint_map as $pattern => $candidates ) {
			if ( ! preg_match( $pattern, $field_hint ) ) {
				continue;
			}
			foreach ( $candidates as $candidate ) {
				if ( isset( $schema[ $candidate ] ) ) {
					return $candidate;
				}
			}
		}

		return '';
	}

	private static function normalize_plan_fields_for_schema( array $fields, array $schema, $request_text, array &$warnings = [] ) {
		if ( empty( $fields ) || empty( $schema ) ) {
			return $fields;
		}

		$schema_index = self::build_schema_match_index( $schema );
		$normalized_fields = [];
		$mapping_count = 0;
		$dropped_count = 0;

		foreach ( $fields as $raw_field_name => $value ) {
			$field_name = sanitize_key( (string) $raw_field_name );
			if ( '' === $field_name ) {
				$dropped_count++;
				continue;
			}

			$resolved_field = '';
			if ( isset( $schema[ $field_name ] ) ) {
				$resolved_field = $field_name;
			} else {
				$resolved_field = self::find_best_matching_field_name( $field_name, $schema_index );
				if ( '' === $resolved_field ) {
					$resolved_field = self::map_common_field_hint_to_schema( $field_name, $schema );
				}
			}

			if ( '' === $resolved_field || ! isset( $schema[ $resolved_field ] ) ) {
				$dropped_count++;
				continue;
			}

			if ( ! array_key_exists( $resolved_field, $normalized_fields ) ) {
				$normalized_fields[ $resolved_field ] = $value;
			}
			if ( $resolved_field !== $field_name ) {
				$mapping_count++;
			}
		}

		if ( ! empty( $normalized_fields ) ) {
			if ( $mapping_count > 0 ) {
				$warnings[] = 'Mapped request fields to editable schema fields.';
			}
			if ( $dropped_count > 0 ) {
				$warnings[] = 'Ignored unsupported field names from planner output.';
			}
			return $normalized_fields;
		}

		$inferred_fields = self::infer_update_fields_from_text( $request_text, $schema );
		if ( ! empty( $inferred_fields ) ) {
			$warnings[] = 'Fields auto-inferred from request text.';
			return $inferred_fields;
		}

		return $fields;
	}

	private static function find_first_block_path_by_name( array $blocks, $block_name, array $path = [] ) {
		foreach ( $blocks as $index => $block ) {
			$current_path = array_merge( $path, [ $index ] );
			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				return $current_path;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$inner_path = self::find_first_block_path_by_name( $block['innerBlocks'], $block_name, $current_path );
				if ( ! empty( $inner_path ) ) {
					return $inner_path;
				}
			}
		}

		return [];
	}

	private static function detect_block_name_from_target( $post_id, array $target ) {
		$blocks = self::get_parsed_blocks( $post_id );
		$target_result = self::find_target_block( $blocks, $target );
		if ( is_wp_error( $target_result ) ) {
			return '';
		}
		return (string) ( $target_result['parent'][ $target_result['index'] ]['blockName'] ?? '' );
	}

	private static function infer_update_fields_from_text( $request_text, array $schema ) {
		$resolved_value = '';
		$quoted_values = self::extract_quoted_values( $request_text );

		$field_phrase = '';
		if ( preg_match( '/\b(?:set|update|change|replace)\s+([a-z0-9 _-]{2,120}?)\s+(?:to|with|as)\s+["“]([^"”]+)["”]/i', (string) $request_text, $matches ) ) {
			$field_phrase = sanitize_text_field( $matches[1] );
			$resolved_value = trim( sanitize_text_field( (string) $matches[2] ) );
		} elseif ( preg_match( "/\b(?:set|update|change|replace)\s+([a-z0-9 _-]{2,120}?)\s+(?:to|with|as)\s+'([^']+)'/i", (string) $request_text, $matches_single ) ) {
			$field_phrase = sanitize_text_field( $matches_single[1] );
			$resolved_value = trim( sanitize_text_field( (string) $matches_single[2] ) );
		} elseif ( preg_match( '/\b(?:set|update|change|replace)\s+([a-z0-9 _-]{2,120}?)\s+(?:to|with|as)\s+(.+)$/i', (string) $request_text, $matches_unquoted ) ) {
			$field_phrase = sanitize_text_field( $matches_unquoted[1] );
			$resolved_value = trim( sanitize_text_field( (string) $matches_unquoted[2] ) );
			$resolved_value = trim( $resolved_value, " \t\n\r\0\x0B.,;:!?" );
		}

		$schema_index = self::build_schema_match_index( $schema );
		$field_name = '';
		if ( ! empty( $field_phrase ) ) {
			$field_phrase = preg_replace( '/\b(?:on|for)\s+(?:post|page)\b.*$/i', '', (string) $field_phrase );
			$field_phrase = preg_replace( '/\b(?:on|in)\s+(?:block|section)\b.*$/i', '', (string) $field_phrase );
			$field_phrase = preg_replace( '/^(?:the|a|an)\s+/i', '', (string) $field_phrase );
			$field_phrase = trim( (string) $field_phrase );
			$field_name = self::find_best_matching_field_name( $field_phrase, $schema_index );
		}

		$request_normalized = self::normalize_match_phrase( $request_text );
		if ( empty( $field_name ) && preg_match( '/\b(headline|heading|title)\b/', $request_normalized ) ) {
			foreach ( [ 'headline', 'heading', 'title' ] as $preferred_name ) {
				if ( isset( $schema[ $preferred_name ] ) ) {
					$field_name = $preferred_name;
					break;
				}
			}
			if ( empty( $field_name ) && isset( $schema['content'] ) ) {
				$field_name = 'content';
			}
		}

		if ( empty( $field_name ) && preg_match( '/\b(subheading|subtitle)\b/', $request_normalized ) ) {
			foreach ( [ 'subheading', 'subtitle' ] as $preferred_name ) {
				if ( isset( $schema[ $preferred_name ] ) ) {
					$field_name = $preferred_name;
					break;
				}
			}
		}

		if ( empty( $field_name ) && preg_match( '/\b(cta|button)\b/', $request_normalized ) ) {
			foreach ( [ 'button_text', 'cta_text', 'button_label', 'label', 'text', 'content' ] as $preferred_name ) {
				if ( isset( $schema[ $preferred_name ] ) ) {
					$field_name = $preferred_name;
					break;
				}
			}
		}

		if ( empty( $field_name ) && preg_match( '/\b(copy|description|body)\b/', $request_normalized ) ) {
			foreach ( [ 'description', 'copy', 'content', 'body' ] as $preferred_name ) {
				if ( isset( $schema[ $preferred_name ] ) ) {
					$field_name = $preferred_name;
					break;
				}
			}
		}

		if ( empty( $field_name ) && preg_match( '/\b(html|markup)\b/', $request_normalized ) && isset( $schema['html'] ) ) {
			$field_name = 'html';
		}

		if ( empty( $field_name ) && isset( $schema['content'] ) && ! empty( $quoted_values ) ) {
			$field_name = 'content';
		}
		if ( empty( $field_name ) && isset( $schema['html'] ) && ! empty( $quoted_values ) ) {
			$field_name = 'html';
		}

		if ( empty( $field_name ) || ! isset( $schema[ $field_name ] ) ) {
			return [];
		}

		if ( ! empty( $resolved_value ) ) {
			$quoted_values = array_merge( [ $resolved_value ], $quoted_values );
		}
		if ( empty( $quoted_values ) ) {
			return [];
		}

		return [ $field_name => $quoted_values[0] ];
	}

	private static function infer_multi_field_update_from_text( $request_text, array $schema ) {
		if ( empty( $schema ) || self::is_unsupported_mixed_update_insert_request( $request_text ) ) {
			return [];
		}

		$assignments = self::extract_explicit_update_assignments( $request_text );
		if ( empty( $assignments ) ) {
			return [];
		}

		$fields = [];
		foreach ( $assignments as $assignment ) {
			$field_hint = sanitize_text_field( (string) ( $assignment['field_hint'] ?? '' ) );
			$value = trim( sanitize_text_field( (string) ( $assignment['value'] ?? '' ) ) );
			if ( '' === $field_hint || '' === $value ) {
				continue;
			}

			$resolved_field = self::resolve_field_hint_for_schema( $field_hint, $schema );
			if ( '' === $resolved_field || ! isset( $schema[ $resolved_field ] ) ) {
				continue;
			}

			if ( isset( $fields[ $resolved_field ] ) ) {
				return [];
			}

			$fields[ $resolved_field ] = $value;
		}

		return count( $fields ) >= 2 ? $fields : [];
	}

	private static function extract_explicit_update_assignments( $request_text ) {
		if ( self::is_unsupported_mixed_update_insert_request( $request_text ) ) {
			return [];
		}

		$matches = [];
		$pattern = '/\b(headline|heading|title|subheading|subtitle|subhead|description|body|copy|cta(?:\s+text)?|button(?:\s+text|\s+label)?|label|text|content|paragraph|html|markup)\b(?:[^"\'“”\r\n]{0,40}?)?(?:(?:to|with|as)\b\s*[:=-]?\s*|:\s*)(?:"([^"]+)"|\'([^\']+)\'|“([^”]+)”)/iu';
		if ( ! preg_match_all( $pattern, (string) $request_text, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$assignments = [];
		foreach ( $matches as $match ) {
			$field_hint = sanitize_text_field( (string) ( $match[1] ?? '' ) );
			$value = '';
			foreach ( [ 2, 3, 4 ] as $group_index ) {
				if ( ! empty( $match[ $group_index ] ) ) {
					$value = trim( sanitize_text_field( (string) $match[ $group_index ] ) );
					break;
				}
			}

			if ( '' === $field_hint || '' === $value ) {
				continue;
			}

			$assignments[] = [
				'field_hint' => $field_hint,
				'value' => $value,
			];
		}

		return count( $assignments ) >= 2 ? $assignments : [];
	}

	private static function infer_field_hints_from_text( $request_text ) {
		$raw_text = (string) $request_text;
		$normalized = self::normalize_match_phrase( $raw_text );
		if ( '' === $normalized ) {
			return [];
		}

		$field_hints = [];
		$hint_patterns = [
			'headline' => '/\b(headline|heading|title)\b/',
			'subheading' => '/\b(subheading|subtitle|subhead)\b/',
			'button_text' => '/\b(cta|button)\b/',
			'description' => '/\b(description|copy|body)\b/',
			'content' => '/\b(content|paragraph|text)\b/',
			'html' => '/\b(html|markup)\b/',
		];

		foreach ( $hint_patterns as $field_hint => $pattern ) {
			if ( preg_match( $pattern, $normalized ) ) {
				$field_hints[] = $field_hint;
			}
		}

		if ( empty( $field_hints ) && ! empty( self::extract_quoted_values( $raw_text ) ) ) {
			$field_hints[] = 'content';
		}

		return array_values( array_unique( array_filter( $field_hints ) ) );
	}

	private static function resolve_field_hint_for_schema( $field_hint, array $schema ) {
		$field_hint = sanitize_key( (string) $field_hint );
		if ( '' === $field_hint || empty( $schema ) ) {
			return '';
		}

		if ( isset( $schema[ $field_hint ] ) ) {
			return $field_hint;
		}

		$schema_index = self::build_schema_match_index( $schema );
		$resolved_field = self::find_best_matching_field_name( $field_hint, $schema_index );
		if ( '' !== $resolved_field && isset( $schema[ $resolved_field ] ) ) {
			return $resolved_field;
		}

		$resolved_field = self::map_common_field_hint_to_schema( $field_hint, $schema );
		if ( '' !== $resolved_field && isset( $schema[ $resolved_field ] ) ) {
			return $resolved_field;
		}

		return '';
	}

	private static function get_field_hint_aliases( $field_hint ) {
		$field_hint = sanitize_key( (string) $field_hint );
		$aliases = [
			'headline' => [ 'headline', 'heading', 'title' ],
			'subheading' => [ 'subheading', 'subtitle', 'subhead' ],
			'button_text' => [ 'button_text', 'cta_text', 'button_label', 'label' ],
			'description' => [ 'description', 'copy', 'body' ],
			'content' => [ 'content', 'text', 'paragraph' ],
			'html' => [ 'html', 'markup' ],
		];

		return $aliases[ $field_hint ] ?? [ $field_hint ];
	}

	private static function get_preferred_block_names_for_field_hint( $field_hint ) {
		$field_hint = sanitize_key( (string) $field_hint );
		$preferred = [
			'headline' => [ 'core/heading' ],
			'subheading' => [ 'core/paragraph' ],
			'button_text' => [ 'core/button' ],
			'description' => [ 'core/paragraph', 'core/quote' ],
			'content' => [ 'core/paragraph', 'core/quote', 'core/list' ],
			'html' => [ 'core/html' ],
		];

		return $preferred[ $field_hint ] ?? [];
	}

	private static function score_target_candidate_for_field_hint( $field_hint, array $candidate ) {
		$field_hint = sanitize_key( (string) $field_hint );
		$field_name = sanitize_key( (string) ( $candidate['field_name'] ?? '' ) );
		$block_name = self::normalize_block_name( $candidate['block_name'] ?? '' );
		$score = 0;
		$field_aliases = self::get_field_hint_aliases( $field_hint );
		$preferred_blocks = self::get_preferred_block_names_for_field_hint( $field_hint );

		if ( in_array( $field_name, $field_aliases, true ) ) {
			$score += 4;
		} elseif ( in_array( $field_name, [ 'content', 'text', 'html' ], true ) ) {
			$score += 1;
		}

		if ( ! empty( $preferred_blocks ) && in_array( $block_name, $preferred_blocks, true ) ) {
			$score += 3;
		}

		return $score;
	}

	private static function compare_rank_parts( array $left, array $right ) {
		$max = max( count( $left ), count( $right ) );
		for ( $index = 0; $index < $max; $index++ ) {
			$left_value = $left[ $index ] ?? 0;
			$right_value = $right[ $index ] ?? 0;
			if ( $left_value < $right_value ) {
				return -1;
			}
			if ( $left_value > $right_value ) {
				return 1;
			}
		}

		return 0;
	}

	private static function select_candidate_near_preferred_target( array $candidates, array $preferred_target = [], $field_hint = '' ) {
		$preferred_path = self::normalize_index_path( $preferred_target['index_path'] ?? [] );
		if ( empty( $preferred_path ) || empty( $candidates ) ) {
			return [];
		}

		$preferred_parent = $preferred_path;
		array_pop( $preferred_parent );
		$preferred_blocks = self::get_preferred_block_names_for_field_hint( $field_hint );

		$ranked = [];
		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$candidate_path = self::normalize_index_path( $candidate['target']['index_path'] ?? [] );
			if ( empty( $candidate_path ) ) {
				continue;
			}

			$candidate_parent = $candidate_path;
			array_pop( $candidate_parent );
			$shared_parent = ( $candidate_parent === $preferred_parent ) ? 0 : 1;
			$shared_root = ( isset( $candidate_path[0], $preferred_path[0] ) && $candidate_path[0] === $preferred_path[0] ) ? 0 : 1;
			$distance = abs( end( $candidate_path ) - end( $preferred_path ) );
			$depth_delta = abs( count( $candidate_path ) - count( $preferred_path ) );
			$is_preferred_block = ( ! empty( $preferred_blocks ) && in_array( self::normalize_block_name( $candidate['block_name'] ?? '' ), $preferred_blocks, true ) ) ? 0 : 1;

			$ranked[] = [
				'candidate' => $candidate,
				'rank' => [ $is_preferred_block, $shared_parent, $shared_root, $depth_delta, $distance ],
			];
		}

		if ( empty( $ranked ) ) {
			return [];
		}

		usort(
			$ranked,
			static function ( $left, $right ) {
				return self::compare_rank_parts( $left['rank'], $right['rank'] );
			}
		);

		if ( 1 === count( $ranked ) || self::compare_rank_parts( $ranked[0]['rank'], $ranked[1]['rank'] ) < 0 ) {
			return $ranked[0]['candidate'];
		}

		return [];
	}

	private static function collect_target_candidates_by_field_hint( array $blocks, $field_hint, $requested_block_name = '', array $path = [] ) {
		$candidates = [];
		$field_hint = sanitize_key( (string) $field_hint );
		$requested_block_name = self::normalize_block_name( $requested_block_name );

		if ( '' === $field_hint ) {
			return $candidates;
		}

		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$current_path = array_merge( $path, [ $index ] );
			$block_name = self::normalize_block_name( $block['blockName'] ?? '' );

			if ( '' !== $block_name ) {
				$schema = self::get_block_schema( $block_name );
				if ( ! empty( $schema ) ) {
					$resolved_field = self::resolve_field_hint_for_schema( $field_hint, $schema );
					if ( '' !== $resolved_field && isset( $schema[ $resolved_field ] ) && self::is_textual_schema_field( $schema[ $resolved_field ] ) ) {
						$matches_requested_block = '' === $requested_block_name || $requested_block_name === $block_name;
						if ( $matches_requested_block ) {
							$candidates[] = [
								'target' => [ 'index_path' => $current_path ],
								'block_name' => $block_name,
								'field_name' => $resolved_field,
								'path_depth' => count( $current_path ),
								'match_score' => self::score_target_candidate_for_field_hint( $field_hint, [
									'block_name' => $block_name,
									'field_name' => $resolved_field,
								] ),
							];
						}
					}
				}
			}

			$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			if ( ! empty( $inner_blocks ) ) {
				$candidates = array_merge(
					$candidates,
					self::collect_target_candidates_by_field_hint( $inner_blocks, $field_hint, $requested_block_name, $current_path )
				);
			}
		}

		return $candidates;
	}

	private static function infer_requested_block_name_for_update_resolution( array $plan, $request_text ) {
		$requested_block_name = self::normalize_block_name( $plan['block_name'] ?? '' );
		if ( '' === $requested_block_name ) {
			$requested_block_name = self::infer_explicit_core_block_name_from_text( $request_text );
		}
		if ( '' === $requested_block_name ) {
			$requested_block_name = self::infer_block_name_from_text( $request_text );
		}

		return $requested_block_name;
	}

	private static function resolve_update_candidate_for_field_hint( array $blocks, $field_hint, $requested_block_name = '', array $preferred_target = [] ) {
		$candidates = self::collect_target_candidates_by_field_hint( $blocks, $field_hint, $requested_block_name );
		if ( 1 === count( $candidates ) ) {
			return $candidates[0];
		}

		if ( count( $candidates ) > 1 ) {
			$top_match_score = max(
				array_map(
					static function ( $candidate ) {
						return (int) ( $candidate['match_score'] ?? 0 );
					},
					$candidates
				)
			);
			$top_match_candidates = array_values(
				array_filter(
					$candidates,
					static function ( $candidate ) use ( $top_match_score ) {
						return (int) ( $candidate['match_score'] ?? 0 ) === $top_match_score;
					}
				)
			);
			if ( 1 === count( $top_match_candidates ) ) {
				return $top_match_candidates[0];
			}
			if ( ! empty( $preferred_target ) ) {
				$preferred_candidate = self::select_candidate_near_preferred_target( $top_match_candidates, $preferred_target, $field_hint );
				if ( ! empty( $preferred_candidate ) ) {
					return $preferred_candidate;
				}
			}
			$candidates = $top_match_candidates;

			$top_level_candidates = array_values(
				array_filter(
					$candidates,
					static function ( $candidate ) {
						$index_path = is_array( $candidate['target']['index_path'] ?? null ) ? $candidate['target']['index_path'] : [];
						return 1 === count( $index_path );
					}
				)
			);
			if ( 1 === count( $top_level_candidates ) ) {
				return $top_level_candidates[0];
			}

			return new WP_Error(
				'sae_target_ambiguous',
				sprintf( 'More than one editable block matches the requested %s field.', $field_hint ),
				[
					'status' => 400,
					'field_hint' => $field_hint,
					'requested_block_name' => $requested_block_name,
					'candidates' => self::summarize_target_candidates_for_error( $candidates ),
				]
			);
		}

		return [];
	}

	private static function summarize_target_candidates_for_error( array $candidates ) {
		$summaries = [];
		foreach ( array_slice( $candidates, 0, 5 ) as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$summaries[] = [
				'block_name' => sanitize_text_field( (string) ( $candidate['block_name'] ?? '' ) ),
				'field_name' => sanitize_key( (string) ( $candidate['field_name'] ?? '' ) ),
				'index_path' => self::normalize_index_path( $candidate['target']['index_path'] ?? [] ),
			];
		}

		return $summaries;
	}

	private static function resolve_update_target_from_field_hints( array $plan, $request_text, $post_id, array &$warnings = [] ) {
		$blocks = self::get_parsed_blocks( $post_id );
		if ( empty( $blocks ) ) {
			return [];
		}

		$field_hints = [];
		$plan_fields = is_array( $plan['fields'] ?? null ) ? $plan['fields'] : [];
		if ( ! empty( $plan_fields ) ) {
			$field_hints = array_values(
				array_filter(
					array_map(
						static function ( $field_name ) {
							return sanitize_key( (string) $field_name );
						},
						array_keys( $plan_fields )
					)
				)
			);
		}
		if ( empty( $field_hints ) ) {
			$field_hints = self::infer_field_hints_from_text( $request_text );
		}
		if ( empty( $field_hints ) ) {
			return [];
		}

		$requested_block_name = self::infer_requested_block_name_for_update_resolution( $plan, $request_text );

		foreach ( $field_hints as $field_hint ) {
			$candidate = self::resolve_update_candidate_for_field_hint( $blocks, $field_hint, $requested_block_name );
			if ( is_wp_error( $candidate ) ) {
				return $candidate;
			}
			if ( ! empty( $candidate ) ) {
				$warnings[] = sprintf(
					'Target auto-resolved to %s by %s field match.',
					(string) ( $candidate['block_name'] ?? 'matched block' ),
					$field_hint
				);
				return is_array( $candidate['target'] ?? null ) ? $candidate['target'] : [];
			}
		}

		return [];
	}

	private static function prepare_multi_target_update_bundle( array $plan, $request_text, $post_id, array &$warnings = [] ) {
		$assignments = self::extract_explicit_update_assignments( $request_text );
		if ( empty( $assignments ) ) {
			return [];
		}

		$blocks = self::get_parsed_blocks( $post_id );
		if ( empty( $blocks ) ) {
			return [];
		}

		$requested_block_name = self::infer_requested_block_name_for_update_resolution( $plan, $request_text );
		$preferred_target = [];
		$operations = [];
		$resolved_assignments = 0;

		foreach ( $assignments as $assignment ) {
			$field_hint = sanitize_key( (string) ( $assignment['field_hint'] ?? '' ) );
			$value = trim( sanitize_text_field( (string) ( $assignment['value'] ?? '' ) ) );
			if ( '' === $field_hint || '' === $value ) {
				continue;
			}

			$candidate_block_name = $requested_block_name;
			$preferred_blocks = self::get_preferred_block_names_for_field_hint( $field_hint );
			if ( '' !== $candidate_block_name && ! empty( $preferred_blocks ) && ! in_array( $candidate_block_name, $preferred_blocks, true ) ) {
				$candidate_block_name = '';
			}

			$candidate = self::resolve_update_candidate_for_field_hint( $blocks, $field_hint, $candidate_block_name, $preferred_target );
			if ( is_wp_error( $candidate ) ) {
				return $candidate;
			}
			if ( empty( $candidate ) ) {
				continue;
			}

			$block_name = self::normalize_block_name( $candidate['block_name'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}

			$schema = self::get_block_schema( $block_name );
			if ( empty( $schema ) ) {
				continue;
			}

			$field_name = self::resolve_field_hint_for_schema( $field_hint, $schema );
			if ( '' === $field_name || ! isset( $schema[ $field_name ] ) ) {
				continue;
			}

			$target = is_array( $candidate['target'] ?? null ) ? $candidate['target'] : [];
			if ( empty( $target ) ) {
				continue;
			}

			$target_key = wp_json_encode( $target );
			if ( ! isset( $operations[ $target_key ] ) ) {
				$operations[ $target_key ] = [
					'action' => 'update',
					'target' => $target,
					'fields' => [],
				];
			}

			if ( isset( $operations[ $target_key ]['fields'][ $field_name ] ) ) {
				return [];
			}

			$operations[ $target_key ]['fields'][ $field_name ] = $value;
			$resolved_assignments++;
			if ( empty( $preferred_target ) ) {
				$preferred_target = $target;
			}
		}

		if ( $resolved_assignments < count( $assignments ) ) {
			return new WP_Error(
				'sae_plan_multi_target_unresolved',
				'I found multiple field updates, but they do not map cleanly to editable blocks on this page yet. Try them one at a time.',
				[ 'status' => 400 ]
			);
		}

		$grouped_operations = array_values( $operations );
		if ( count( $grouped_operations ) <= 1 ) {
			return [];
		}

		$warnings[] = 'Multiple target blocks auto-resolved from explicit field updates.';

		return [
			'bundle_name' => 'Coordinated field updates',
			'operations' => $grouped_operations,
		];
	}

	private static function resolve_plan_target( array $plan, $request_text, $post_id, $operation, &$warnings ) {
		$warnings = is_array( $warnings ) ? $warnings : [];
		$target = is_array( $plan['target'] ?? null ) ? $plan['target'] : [];

		if ( ! empty( $target['index_path'] ) ) {
			$target['index_path'] = self::normalize_index_path( $target['index_path'] );
		}
		if ( ! empty( $target['anchor'] ) ) {
			$target['anchor'] = sanitize_text_field( (string) $target['anchor'] );
		}
		if ( ! empty( $target['block_id'] ) ) {
			$target['block_id'] = sanitize_text_field( (string) $target['block_id'] );
		}

		$target = array_filter(
			$target,
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return ! empty( $value );
				}
				return '' !== (string) $value;
			}
		);

		$target_from_text = self::extract_target_from_text( $request_text );
		if ( ! empty( $target_from_text ) ) {
			return $target_from_text;
		}

		$explicit_core_block = self::infer_explicit_core_block_name_from_text( $request_text );
		if ( ! empty( $target ) ) {
			if ( in_array( $operation, [ 'update', 'remove' ], true ) && ! empty( $explicit_core_block ) ) {
				$blocks = self::get_parsed_blocks( $post_id );
				$target_match = self::find_target_block( $blocks, $target );
				if (
					! is_wp_error( $target_match ) &&
					( (string) ( $target_match['parent'][ $target_match['index'] ]['blockName'] ?? '' ) ) !== $explicit_core_block
				) {
					$path = self::find_first_block_path_by_name( $blocks, $explicit_core_block );
					if ( ! empty( $path ) ) {
						$warnings[] = sprintf( 'Target adjusted to first "%s" block from request text.', $explicit_core_block );
						return [ 'index_path' => $path ];
					}
				}
			}

			return $target;
		}

		$block_name = ! empty( $explicit_core_block )
			? $explicit_core_block
			: self::normalize_block_name( $plan['block_name'] ?? '' );
		if ( empty( $block_name ) && in_array( $operation, [ 'update', 'remove' ], true ) ) {
			$block_name = self::infer_block_name_from_text( $request_text );
		}
		if ( in_array( $operation, [ 'update', 'remove' ], true ) && ! empty( $block_name ) ) {
			$blocks = self::get_parsed_blocks( $post_id );
			$path = self::find_first_block_path_by_name( $blocks, $block_name );
			if ( ! empty( $path ) ) {
				$warnings[] = sprintf( 'Target auto-resolved to first "%s" block.', $block_name );
				return [ 'index_path' => $path ];
			}
		}

		return [];
	}

	private static function prepare_plan_operation( array $plan, $request_text ) {
		$resolved_post = self::resolve_post_from_plan( $plan, $request_text );
		if ( is_wp_error( $resolved_post ) ) {
			return $resolved_post;
		}

		$operation = sanitize_key( (string) ( $plan['operation'] ?? '' ) );
		if ( ! in_array( $operation, [ 'insert', 'update', 'remove' ], true ) ) {
			return new WP_Error( 'sae_plan_invalid_operation', 'operation must be insert, update, or remove.', [ 'status' => 400 ] );
		}

		$post_id = absint( $resolved_post['post_id'] ?? 0 );
		$warnings = [];
		$endpoint = '';
		$payload = [];

		if ( 'insert' === $operation ) {
			$block_name = self::normalize_block_name( $plan['block_name'] ?? '' );
			if ( empty( $block_name ) ) {
				$block_name = self::infer_block_name_from_text( $request_text );
			}
			if ( empty( $block_name ) ) {
				return new WP_Error( 'sae_plan_missing_block_name', 'block_name is required for insert operations.', [ 'status' => 400 ] );
			}
			$allowed_blocks = self::get_allowed_block_types();
			if ( ! in_array( $block_name, $allowed_blocks, true ) ) {
				return new WP_Error( 'sae_plan_block_not_allowed', 'Resolved block_name is not allowlisted.', [ 'status' => 403, 'block_name' => $block_name ] );
			}

			$fields = is_array( $plan['fields'] ?? null ) ? $plan['fields'] : [];
			$schema = self::get_block_schema( $block_name );
			if ( ! empty( $fields ) && ! empty( $schema ) ) {
				$fields = self::normalize_plan_fields_for_schema( $fields, $schema, $request_text, $warnings );
			}
			if ( empty( $fields ) && ! empty( $schema ) ) {
				$fields = self::infer_update_fields_from_text( $request_text, $schema );
				if ( ! empty( $fields ) ) {
					$warnings[] = 'Fields auto-inferred from request text.';
				}
			}
			if ( empty( $fields ) ) {
				return new WP_Error(
					'sae_plan_missing_fields',
					'fields are required for insert operations. Include quoted content, for example: add paragraph "Your text here".',
					[ 'status' => 400 ]
				);
			}
			$position = is_array( $plan['position'] ?? null ) ? $plan['position'] : [];
			if ( empty( $position ) ) {
				$position = self::infer_insert_position_from_text( $request_text );
			}
			$parent_path = self::normalize_index_path( $plan['parent_path'] ?? [] );
			$endpoint = sprintf( '/wp-json/struo/v1/posts/%d/blocks/insert', $post_id );
			$payload = [
				'block_name' => $block_name,
				'fields' => $fields,
				'position' => $position,
				'parent_path' => $parent_path,
			];
		}

		if ( 'update' === $operation ) {
			$multi_target_bundle = self::prepare_multi_target_update_bundle( $plan, $request_text, $post_id, $warnings );
			if ( is_wp_error( $multi_target_bundle ) ) {
				return $multi_target_bundle;
			}
			if ( ! empty( $multi_target_bundle['operations'] ) && is_array( $multi_target_bundle['operations'] ) ) {
				return [
					'post' => $resolved_post,
					'operation' => 'batch',
					'endpoint' => sprintf( '/wp-json/struo/v1/posts/%d/blocks/batch', $post_id ),
					'payload' => [
						'bundle_name' => self::sanitize_bundle_name( $multi_target_bundle['bundle_name'] ?? '' ),
						'operations' => $multi_target_bundle['operations'],
					],
					'warnings' => $warnings,
				];
			}

			$target = self::resolve_plan_target( $plan, $request_text, $post_id, $operation, $warnings );
			if ( empty( $target ) ) {
				$target = self::resolve_update_target_from_field_hints( $plan, $request_text, $post_id, $warnings );
				if ( is_wp_error( $target ) ) {
					return $target;
				}
			}
			if ( empty( $target ) ) {
				return new WP_Error( 'sae_plan_missing_target', 'target is required for update operations.', [ 'status' => 400 ] );
			}

			$fields = is_array( $plan['fields'] ?? null ) ? $plan['fields'] : [];
			$block_name_for_schema = self::detect_block_name_from_target( $post_id, $target );
			$schema = [];
			if ( ! empty( $block_name_for_schema ) ) {
				$schema = self::get_block_schema( $block_name_for_schema );
			}
			if ( ! empty( $schema ) ) {
				$multi_fields = self::infer_multi_field_update_from_text( $request_text, $schema );
				if ( ! empty( $multi_fields ) && ( empty( $fields ) || count( $fields ) <= 1 ) ) {
					$fields = $multi_fields;
					$warnings[] = 'Multiple fields auto-inferred from request text.';
				}
			}
			if ( ! empty( $fields ) && ! empty( $schema ) ) {
				$fields = self::normalize_plan_fields_for_schema( $fields, $schema, $request_text, $warnings );
			}
			if ( empty( $fields ) && ! empty( $schema ) ) {
				$fields = self::infer_update_fields_from_text( $request_text, $schema );
				if ( ! empty( $fields ) ) {
					$warnings[] = 'Fields auto-inferred from request text.';
				}
			}

			if ( empty( $fields ) ) {
				return new WP_Error( 'sae_plan_missing_fields', 'fields are required for update operations.', [ 'status' => 400 ] );
			}

			$endpoint = sprintf( '/wp-json/struo/v1/posts/%d/blocks/update', $post_id );
			$payload = [
				'target' => $target,
				'fields' => $fields,
			];
		}

		if ( 'remove' === $operation ) {
			$remove_all = ! empty( $plan['remove_all'] );
			$block_name = self::normalize_block_name( $plan['block_name'] ?? '' );
			if ( $remove_all && empty( $block_name ) ) {
				$block_name = self::infer_block_name_from_text( $request_text );
			}

			$target = [];
			if ( ! $remove_all ) {
				$target = self::resolve_plan_target( $plan, $request_text, $post_id, $operation, $warnings );
			}

			if ( ! $remove_all && empty( $target ) ) {
				return new WP_Error(
					'sae_plan_missing_target',
					'target is required for remove operations unless remove_all=true.',
					[ 'status' => 400 ]
				);
			}

			if ( $remove_all && empty( $block_name ) ) {
				return new WP_Error(
					'sae_plan_missing_block_name',
					'block_name is required when remove_all is true.',
					[ 'status' => 400 ]
				);
			}

			if ( ! empty( $block_name ) && ! in_array( $block_name, self::get_allowed_block_types(), true ) ) {
				return new WP_Error( 'sae_plan_block_not_allowed', 'Resolved block_name is not allowlisted.', [ 'status' => 403, 'block_name' => $block_name ] );
			}

			$endpoint = sprintf( '/wp-json/struo/v1/posts/%d/blocks/remove', $post_id );
			$payload = [
				'target' => $target,
				'block_name' => $block_name,
				'remove_all' => $remove_all,
			];
		}

		return [
			'post' => $resolved_post,
			'operation' => $operation,
			'endpoint' => $endpoint,
			'payload' => $payload,
			'warnings' => $warnings,
		];
	}

	private static function run_plan_dry_run( array $prepared, array $response_mode, $origin, array $context = [], $dry_run = true ) {
		$post_id = absint( $prepared['post']['post_id'] ?? 0 );
		$operation = sanitize_key( (string) ( $prepared['operation'] ?? '' ) );
		$payload = is_array( $prepared['payload'] ?? null ) ? $prepared['payload'] : [];
		$origin = self::normalize_token_origin( $origin );

		if ( 'insert' === $operation ) {
			return self::apply_insert(
				$post_id,
				$payload['block_name'] ?? '',
				is_array( $payload['fields'] ?? null ) ? $payload['fields'] : [],
				is_array( $payload['position'] ?? null ) ? $payload['position'] : [],
				is_array( $payload['parent_path'] ?? null ) ? $payload['parent_path'] : [],
				$dry_run,
				'',
				'',
				$response_mode,
				$origin,
				$context
			);
		}

		if ( 'update' === $operation ) {
			return self::apply_update(
				$post_id,
				is_array( $payload['target'] ?? null ) ? $payload['target'] : [],
				is_array( $payload['fields'] ?? null ) ? $payload['fields'] : [],
				$dry_run,
				'',
				'',
				$response_mode,
				$origin,
				$context
			);
		}

		if ( 'remove' === $operation ) {
			return self::apply_remove(
				$post_id,
				is_array( $payload['target'] ?? null ) ? $payload['target'] : [],
				$payload['block_name'] ?? '',
				! empty( $payload['remove_all'] ),
				$dry_run,
				'',
				'',
				$response_mode,
				$origin,
				$context
			);
		}

		if ( 'batch' === $operation ) {
			return self::apply_batch(
				$post_id,
				is_array( $payload['operations'] ?? null ) ? $payload['operations'] : [],
				$dry_run,
				'',
				'',
				$response_mode,
				$payload['bundle_name'] ?? '',
				$origin,
				$context
			);
		}

		if ( 'cross_field' === $operation ) {
			return self::apply_cross_field_update(
				$post_id,
				sanitize_key( (string) ( $payload['field'] ?? '' ) ),
				self::normalize_cross_field_value(
					sanitize_key( (string) ( $payload['field'] ?? '' ) ),
					$payload['value'] ?? ''
				),
				$dry_run,
				'',
				'',
				$origin,
				$context
			);
		}

		if ( 'restore' === $operation ) {
			$content = (string) ( $context['restore_content'] ?? '' );
			$plan_id = sanitize_text_field( (string) ( $context['durable_plan_apply'] ?? '' ) );
			if ( '' === $content && self::validate_agent_plan_id( $plan_id ) ) {
				$stored = self::get_durable_plan_record_unchecked( $plan_id );
				$content = is_array( $stored ) ? (string) ( $stored['base_content'] ?? '' ) : '';
			}

			return self::apply_restore( $post_id, $payload, $dry_run, $content );
		}

		if ( 'create' === $operation ) {
			if ( ! empty( $payload['serialized_content'] ) ) {
				$page_spec = self::normalize_page_spec_payload( $payload );
				if ( is_wp_error( $page_spec ) ) {
					return $page_spec;
				}
				if ( $dry_run ) {
					return [
						'ok' => true,
						'dry_run' => true,
						'payload_type' => 'page_spec_v1',
						'title' => $page_spec['title'],
						'post_type' => $page_spec['post_type'],
						'template' => $page_spec['template'],
						'blocks_created' => absint( $page_spec['block_count'] ?? 0 ),
						'content_hash' => $page_spec['content_hash'],
						'operations' => is_array( $page_spec['operations'] ?? null ) ? $page_spec['operations'] : [],
						'confirmation' => [
							'redeemable' => false,
							'origin' => self::normalize_token_origin( $origin ),
							'message' => 'Plan-only: approve and apply via the durable page_spec envelope.',
						],
					];
				}
				return self::execute_page_spec_apply( $page_spec, $context, (string) ( $page_spec['idempotency_key'] ?? '' ) );
			}

			$idempotency_key = sanitize_text_field( (string) ( $payload['idempotency_key'] ?? '' ) );
			if ( '' === $idempotency_key ) {
				return new WP_Error( 'sae_invalid_idempotency_key', 'idempotency_key is required.', [ 'status' => 400 ] );
			}

			return self::execute_create_page_apply( $idempotency_key, '', $context );
		}

		return new WP_Error( 'sae_plan_invalid_operation', 'Unsupported operation for dry run.', [ 'status' => 400 ] );
	}

	/**
	 * Write stored before-bytes for a restore plan. Does not rewrite payload_json.
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|WP_Error
	 */
	private static function apply_restore( $post_id, array $payload, $dry_run, $content ) {
		$post_id = absint( $post_id );
		$content = (string) $content;
		$kind = sanitize_key( (string) ( $payload['restore_kind'] ?? 'post_content' ) );
		if ( $post_id <= 0 || '' === $content ) {
			return new WP_Error( 'sae_rollback_unsupported', 'This plan has no stored before-bytes.', [ 'status' => 409 ] );
		}

		$result = [
			'ok' => true,
			'post_id' => $post_id,
			'action' => 'restore',
			'dry_run' => (bool) $dry_run,
		];
		if ( $dry_run ) {
			return $result;
		}

		$permission = self::ensure_write_allowed( $post_id, true );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( 'cross_field' === $kind ) {
			$written = self::write_post_field_value(
				$post_id,
				sanitize_key( (string) ( $payload['field'] ?? '' ) ),
				$content
			);
			if ( is_wp_error( $written ) ) {
				return $written;
			}

			return $result;
		}

		$update = wp_update_post(
			[
				'ID' => $post_id,
				'post_content' => wp_slash( $content ),
			],
			true
		);
		if ( is_wp_error( $update ) ) {
			return $update;
		}

		self::invalidate_parsed_blocks_cache( $post_id );

		return $result;
	}

	private static function prepare_create_page_outline( $request_text, array $payload = [] ) {
		$request_text = self::sanitize_plan_request_text( $request_text );
		if ( '' === $request_text ) {
			return new WP_Error( 'sae_create_request_required', 'A create request is required.', [ 'status' => 400 ] );
		}

		unset( $payload['post_id'] );
		if ( isset( $payload['plan'] ) && is_array( $payload['plan'] ) ) {
			unset( $payload['plan']['post_id'] );
			if ( empty( $payload['plan'] ) ) {
				unset( $payload['plan'] );
			}
		}

		$payload_post_type = self::normalize_create_post_type( $payload['post_type'] ?? '' );
		$post_type         = '' !== $payload_post_type
			? $payload_post_type
			: self::infer_create_post_type_from_request( $request_text );
		$payload['post_type'] = $post_type;

		$rag_context = self::retrieve_rag_context( $request_text );
		$site_plan = self::call_ai_site_planner( $request_text, $post_type, $payload, $rag_context );
		if ( is_wp_error( $site_plan ) ) {
			return $site_plan;
		}

		$template_meta = self::build_create_outline_template_meta(
			is_array( $site_plan['outline'] ?? null ) ? $site_plan['outline'] : [],
			$post_type,
			(string) ( $site_plan['title'] ?? '' )
		);

		return [
			'request' => $request_text,
			'intent' => 'create',
			'title' => sanitize_text_field( (string) ( $site_plan['title'] ?? '' ) ),
			'slug' => sanitize_title( (string) ( $site_plan['slug'] ?? '' ) ),
			'post_type' => $post_type,
			'outline' => is_array( $site_plan['outline'] ?? null ) ? $site_plan['outline'] : [],
			'template' => sanitize_key( (string) ( $template_meta['template'] ?? '' ) ),
			'template_mode' => sanitize_key( (string) ( $template_meta['template_mode'] ?? '' ) ),
			'template_label' => sanitize_text_field( (string) ( $template_meta['template_label'] ?? '' ) ),
			'template_rationale' => sanitize_text_field( (string) ( $template_meta['template_rationale'] ?? '' ) ),
			'planner' => [
				'source' => 'ai_engine',
				'ai_used' => true,
				'warnings' => [],
				'vector' => self::build_vector_meta_payload( $rag_context ),
			],
			'vector_sources' => self::build_vector_sources_payload( $rag_context ),
		];
	}

	private static function planner_policy_options( $scope_label, array $payload = [] ) {
		if ( array_key_exists( 'planner_options', $payload ) ) {
			self::audit_log(
				'planner_options_ignored',
				[ 'scope' => sanitize_text_field( (string) $scope_label ) ]
			);
		}

		return [
			'scope' => self::get_ai_scope_label( $scope_label ),
		];
	}

	private static function call_ai_site_planner( $request_text, $post_type = 'page', array $payload = [], $rag_context = null ) {
		$options = self::planner_policy_options( 'Page Creator', $payload );

		$prompt = self::build_site_planner_prompt( $request_text, $post_type, $payload, $rag_context );
		$result = self::call_ai_json_query( $prompt, $options, 'AI site planner returned a non-JSON response.' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::validate_site_planner_result( $result, $post_type );
	}

	private static function build_site_planner_prompt( $request_text, $post_type = 'page', array $payload = [], $rag_context = null ) {
		$normalized_post_type = self::normalize_create_post_type( $post_type );
		if ( '' === $normalized_post_type ) {
			$normalized_post_type = 'page';
		}

		$allowed_blocks = self::get_site_planner_allowed_blocks( $normalized_post_type );
		$catalog_lines = self::build_site_planner_catalog_lines( $allowed_blocks );
		$template_lines = self::build_site_planner_template_lines( $normalized_post_type );
		$persona_lines = self::get_ai_persona_prompt_lines();

		$lines = [];
		$lines[] = sprintf( 'You are a WordPress page architect for %s.', self::get_site_context_label() );
		$lines[] = 'Design an outline for a net-new page using only the approved block catalog.';
		$lines[] = sprintf( 'Requested content type: %s.', 'post' === $normalized_post_type ? 'blog post' : 'page' );
		$lines[] = 'Return valid JSON only. No markdown.';
		$lines[] = 'Use ONLY block_name values listed in the catalog below.';
		$lines[] = 'Allowed block_name values (exact string only): ' . implode( ', ', $allowed_blocks ) . '.';
		$lines[] = 'Maximum sections: 12.';
		$lines[] = 'Block catalog summary:';
		$lines = array_merge( $lines, $catalog_lines );
		if ( ! empty( $template_lines ) ) {
			$lines = array_merge( $lines, $template_lines );
		}
		if ( ! empty( $persona_lines ) ) {
			$lines = array_merge( $lines, $persona_lines );
		}

		$planner_post_id = absint( $payload['post_id'] ?? ( is_array( $payload['plan'] ?? null ) ? ( $payload['plan']['post_id'] ?? 0 ) : 0 ) );
		$content_type_lines = self::get_content_type_prompt_context( $planner_post_id );
		if ( ! empty( $content_type_lines ) ) {
			$lines = array_merge( $lines, $content_type_lines );
		}
		if ( is_array( $rag_context ) && ! empty( $rag_context['lines'] ) && is_array( $rag_context['lines'] ) ) {
			$lines = array_merge( $lines, $rag_context['lines'] );
		}

		$lines[] = 'JSON schema (shape):';
		$lines[] = wp_json_encode(
			[
				'title' => 'string',
				'slug' => 'string',
				'outline' => [
					[
						'section' => 'string',
						'purpose' => 'string',
						'block_name' => 'string',
						'reasoning' => 'string',
					],
				],
			]
		);
		$lines[] = 'User request: ' . $request_text;

		return implode( "\n", $lines );
	}

	private static function get_site_planner_allowed_blocks( $post_type = 'page' ) {
		$normalized_post_type = self::normalize_create_post_type( $post_type );
		if ( '' === $normalized_post_type ) {
			$normalized_post_type = 'page';
		}

		$allowed_lookup = array_fill_keys( self::get_allowed_block_types(), true );
		$blocks = [];

		foreach ( self::get_page_templates() as $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}

			$template_post_type = self::normalize_create_post_type( $template['post_type'] ?? 'page' );
			if ( '' !== $template_post_type && $template_post_type !== $normalized_post_type ) {
				continue;
			}

			$template_blocks = is_array( $template['blocks'] ?? null ) ? $template['blocks'] : [];
			foreach ( $template_blocks as $block ) {
				$block_name = self::normalize_block_name( is_array( $block ) ? ( $block['block_name'] ?? '' ) : '' );
				if ( '' === $block_name || empty( $allowed_lookup[ $block_name ] ) ) {
					continue;
				}
				$blocks[ $block_name ] = $block_name;
			}
		}

		if ( empty( $blocks ) ) {
			return self::get_allowed_block_types();
		}

		return array_values( $blocks );
	}

	private static function build_site_planner_catalog_lines( array $allowed_blocks ) {
		if ( empty( $allowed_blocks ) ) {
			return [ '- (none)' ];
		}

		$lines = [];
		foreach ( $allowed_blocks as $block_name ) {
			$normalized_block = self::normalize_block_name( $block_name );
			if ( '' === $normalized_block ) {
				continue;
			}
			$schema = self::get_block_schema( $normalized_block );
			$field_parts = [];
			if ( is_array( $schema ) ) {
				foreach ( $schema as $field_name => $field ) {
					$canonical = sanitize_key( (string) $field_name );
					if ( '' === $canonical ) {
						continue;
					}
					$type = sanitize_key( (string) ( is_array( $field ) ? ( $field['type'] ?? '' ) : '' ) );
					$field_parts[] = sprintf( '%s (%s)', $canonical, '' !== $type ? $type : 'value' );
					if ( count( $field_parts ) >= 5 ) {
						break;
					}
				}
			}
			if ( empty( $field_parts ) ) {
				$field_parts[] = 'none';
			}
			$lines[] = sprintf(
				'- %s — %s. Fields: %s.',
				$normalized_block,
				sanitize_text_field( self::get_create_block_label( $normalized_block ) ),
				implode( ', ', $field_parts )
			);
		}

		return empty( $lines ) ? [ '- (none)' ] : $lines;
	}

	private static function build_site_planner_template_lines( $post_type = 'page' ) {
		$normalized_post_type = self::normalize_create_post_type( $post_type );
		if ( '' === $normalized_post_type ) {
			$normalized_post_type = 'page';
		}

		$templates = [];
		foreach ( self::get_page_templates() as $template_key => $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}
			$template_post_type = self::normalize_create_post_type( $template['post_type'] ?? 'page' );
			if ( '' !== $template_post_type && $template_post_type !== $normalized_post_type ) {
				continue;
			}
			$templates[ $template_key ] = $template;
		}

		if ( empty( $templates ) ) {
			return [];
		}

		$template_limit = 8;
		$template_overflow_count = max( 0, count( $templates ) - $template_limit );
		if ( $template_overflow_count > 0 ) {
			$templates = array_slice( $templates, 0, $template_limit, true );
		}

		$lines = [ 'Available template patterns:' ];
		foreach ( $templates as $template_key => $template ) {
			$label = sanitize_text_field( (string) ( $template['label'] ?? $template_key ) );
			$template_blocks = is_array( $template['blocks'] ?? null ) ? $template['blocks'] : [];
			$metadata = is_array( $template['metadata'] ?? null ) ? $template['metadata'] : [];
			$fragments = [];

			$family  = sanitize_text_field( (string) ( $metadata['family'] ?? '' ) );
			$variant = sanitize_text_field( (string) ( $metadata['variant'] ?? '' ) );
			if ( '' !== $family && '' !== $variant ) {
				$fragments[] = sprintf( 'Pattern: %s / %s', $family, $variant );
			} elseif ( '' !== $family ) {
				$fragments[] = sprintf( 'Pattern: %s', $family );
			}

			$context = sanitize_text_field( (string) ( $metadata['context'] ?? '' ) );
			if ( '' !== $context ) {
				$fragments[] = sprintf( 'Context: %s', $context );
			}

			$summary = self::normalize_prompt_excerpt(
				sanitize_text_field(
					(string) (
						$metadata['purpose']
							?? ( $metadata['what_it_is'] ?? ( $template['description'] ?? '' ) )
					)
				),
				200
			);
			if ( '' !== $summary ) {
				$fragments[] = $summary;
			}

			$recipe = self::normalize_template_section_recipe( $template['section_recipe'] ?? null, $template_blocks );
			if ( ! empty( $recipe ) ) {
				$recipe_labels = array_map( [ __CLASS__, 'humanize_template_recipe_item' ], array_slice( $recipe, 0, 6 ) );
				$recipe_labels = array_values( array_filter( $recipe_labels ) );
				if ( ! empty( $recipe_labels ) ) {
					$fragments[] = 'Sections: ' . implode( ' → ', $recipe_labels );
				}
			}

			$module_descriptors = self::get_seeded_template_module_descriptors(
				self::normalize_template_acf_modules( $metadata['acf_modules'] ?? null, $template_blocks )
			);
			if ( ! empty( $module_descriptors ) ) {
				$fragments[] = 'Known DS patterns: ' . implode( ', ', array_slice( $module_descriptors, 0, 3 ) );
			}

			$layout_line = ! empty( $fragments ) ? implode( ' | ', $fragments ) : 'layout unavailable';
			$lines[] = sprintf( '- %s (%s): %s', $label, sanitize_key( (string) $template_key ), $layout_line );
		}
		if ( $template_overflow_count > 0 ) {
			$lines[] = sprintf(
				'- %d more approved template%s are available in the registry.',
				$template_overflow_count,
				1 === $template_overflow_count ? '' : 's'
			);
		}

		return count( $lines ) > 1 ? $lines : [];
	}

	private static function validate_site_planner_result( array $result, $post_type = 'page' ) {
		$title = self::normalize_create_title( $result['title'] ?? '' );
		if ( '' === $title ) {
			return new WP_Error( 'sae_create_outline_invalid', 'AI site planner returned an empty title.', [ 'status' => 502 ] );
		}

		$slug = sanitize_title( (string) ( $result['slug'] ?? '' ) );
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}
		if ( '' === $slug ) {
			return new WP_Error( 'sae_create_outline_invalid', 'AI site planner returned an invalid slug.', [ 'status' => 502 ] );
		}

		$outline_raw = null;
		if ( is_array( $result['outline'] ?? null ) ) {
			$outline_raw = $result['outline'];
		} elseif ( is_array( $result['sections'] ?? null ) ) {
			$outline_raw = $result['sections'];
		}
		if ( ! is_array( $outline_raw ) ) {
			return new WP_Error( 'sae_create_outline_invalid', 'AI site planner must return an outline array.', [ 'status' => 502 ] );
		}

		$outline_count = count( $outline_raw );
		if ( $outline_count < 1 || $outline_count > 12 ) {
			return new WP_Error( 'sae_create_outline_invalid', 'AI site planner outline must contain between 1 and 12 sections.', [ 'status' => 502 ] );
		}

		$allowed_blocks = self::get_site_planner_allowed_blocks( $post_type );
		$outline = [];
		foreach ( $outline_raw as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				return new WP_Error(
					'sae_create_outline_invalid',
					sprintf( 'AI site planner outline entry %d is invalid.', $index ),
					[ 'status' => 502 ]
				);
			}

			$section = self::normalize_prompt_excerpt( sanitize_text_field( (string) ( $entry['section'] ?? ( $entry['title'] ?? '' ) ) ), 90 );
			$purpose = self::normalize_prompt_excerpt( sanitize_text_field( (string) ( $entry['purpose'] ?? ( $entry['description'] ?? '' ) ) ), 240 );
			$block_name = self::normalize_block_name( $entry['block_name'] ?? ( $entry['block'] ?? '' ) );
			$reasoning = self::normalize_prompt_excerpt( sanitize_text_field( (string) ( $entry['reasoning'] ?? '' ) ), 240 );

			if ( '' === $section || '' === $purpose || '' === $block_name ) {
				return new WP_Error(
					'sae_create_outline_invalid',
					sprintf( 'AI site planner outline entry %d is missing required values.', $index ),
					[ 'status' => 502 ]
				);
			}
			if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
				return new WP_Error(
					'sae_create_template_block_not_allowed',
					sprintf( 'AI site planner resolved block_name "%s" that is not allowlisted.', $block_name ),
					[ 'status' => 403, 'block_name' => $block_name ]
				);
			}

			$outline[] = [
				'section' => $section,
				'purpose' => $purpose,
				'block_name' => $block_name,
				'reasoning' => $reasoning,
			];
		}

		return [
			'title' => $title,
			'slug' => $slug,
			'outline' => $outline,
		];
	}

	private static function infer_create_post_type_from_request( $request_text ) {
		if ( preg_match( '/\b(?:blog\s*post|article)\b/i', (string) $request_text ) ) {
			return 'post';
		}
		if ( preg_match( '/\b(?:new\s+)?posts?\b/i', (string) $request_text ) && ! preg_match( '/\b(?:page|landing\s*page)\b/i', (string) $request_text ) ) {
			return 'post';
		}
		return 'page';
	}

	private static function summarize_create_outline_session_entry( array $response ) {
		$title = sanitize_text_field( (string) ( $response['title'] ?? '' ) );
		if ( '' === $title ) {
			$title = 'new page';
		}
		$section_count = count( is_array( $response['outline'] ?? null ) ? $response['outline'] : [] );
		return sprintf( 'Prepared a create outline for %s with %d section%s.', $title, $section_count, 1 === $section_count ? '' : 's' );
	}

	private static function call_ai_planner( $request_text, array $payload = [] ) {
		$options = self::planner_policy_options( 'Planner', $payload );

		$prompt = self::build_ai_planner_prompt( $request_text, $payload );
		$result = self::call_ai_json_query( $prompt, $options, 'AI planner returned a non-JSON response.' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::validate_ai_planner_result( $result );
	}

	private static function call_ai_ideation_planner( $request_text, array $payload = [], array $post = [] ) {
		$options = self::planner_policy_options( 'Ideation', $payload );

		$prompt = self::build_ai_ideation_prompt( $request_text, $post );
		$result = self::call_ai_json_query( $prompt, $options, 'AI ideation planner returned a non-JSON response.' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::validate_ai_ideation_result( $result );
	}

	private static function call_ai_audit_planner( $request_text, array $post = [], $block_summary = '', array $payload = [], $rag_context = null ) {
		$options = self::planner_policy_options( 'Audit', $payload );

		$prompt = self::build_ai_audit_prompt( $request_text, $post, (string) $block_summary, $rag_context );
		$result = self::call_ai_json_query( $prompt, $options, 'AI audit planner returned a non-JSON response.' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::validate_ai_audit_result( $result );
	}

	private static function call_ai_rewrite_planner( $request_text, array $context = [], array $payload = [] ) {
		$options = self::planner_policy_options( 'Rewrite', $payload );

		$prompt = self::build_ai_rewrite_prompt( $request_text, $context );
		$result = self::call_ai_json_query( $prompt, $options, 'AI rewrite planner returned a non-JSON response.' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::validate_ai_rewrite_result( $result );
	}

	private static function call_ai_cross_field_planner( $request_text, array $context = [], array $payload = [] ) {
		$options = self::planner_policy_options( 'Cross Field', $payload );

		$prompt = self::build_ai_cross_field_prompt( $request_text, $context );
		$result = self::call_ai_json_query( $prompt, $options, 'AI cross-field planner returned a non-JSON response.' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::validate_ai_cross_field_result( $result );
	}

	private static function get_ai_plan_target_schema() {
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => [
				'index_path' => [
					'type' => 'array',
					'items' => [
						'type' => 'integer',
						'minimum' => 0,
					],
				],
				'anchor' => [
					'type' => 'string',
				],
				'block_id' => [
					'type' => 'string',
				],
			],
		];
	}

	private static function get_ai_ideation_schema() {
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => [
				'suggestions' => [
					'type' => 'array',
					'minItems' => 1,
					'maxItems' => self::ASK_MAX_SUGGESTIONS,
					'items' => [
						'type' => 'object',
						'additionalProperties' => false,
						'properties' => [
							'title' => [
								'type' => 'string',
							],
							'rationale' => [
								'type' => 'string',
							],
							'command' => [
								'type' => 'string',
							],
							'candidate_value' => [
								'type' => 'string',
							],
							'persuasion_label' => [
								'type' => 'string',
							],
							'target_hint' => [
								'type' => 'string',
							],
						],
						'required' => [ 'title', 'command' ],
					],
				],
			],
			'required' => [ 'suggestions' ],
		];
	}

	private static function get_ai_audit_schema() {
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => [
				'score' => [
					'type' => 'integer',
					'minimum' => 1,
					'maximum' => 10,
				],
				'summary' => [
					'type' => 'string',
				],
				'issues' => [
					'type' => 'array',
					'maxItems' => 8,
					'items' => [
						'type' => 'object',
						'additionalProperties' => false,
						'properties' => [
							'title' => [ 'type' => 'string' ],
							'severity' => [ 'type' => 'string', 'enum' => [ 'high', 'medium', 'low' ] ],
							'section_label' => [ 'type' => 'string' ],
							'category' => [ 'type' => 'string' ],
							'rationale' => [ 'type' => 'string' ],
							'fix_command' => [ 'type' => 'string' ],
						],
						'required' => [ 'title', 'severity', 'rationale', 'fix_command' ],
					],
				],
			],
			'required' => [ 'score', 'summary', 'issues' ],
		];
	}

	private static function get_ai_rewrite_schema() {
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => [
				'rewritten_value' => [
					'type' => 'string',
				],
				'rationale' => [
					'type' => 'string',
				],
			],
			'required' => [ 'rewritten_value' ],
		];
	}

	private static function get_ai_cross_field_schema() {
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => [
				'proposed_value' => [
					'type' => 'string',
				],
				'rationale' => [
					'type' => 'string',
				],
			],
			'required' => [ 'proposed_value' ],
		];
	}

	private static function get_ai_plan_schema() {
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => [
				'operation' => [
					'type' => 'string',
					'enum' => [ 'insert', 'update', 'remove' ],
				],
				'post_id' => [
					'type' => 'integer',
					'minimum' => 1,
				],
				'post_url' => [
					'type' => 'string',
				],
				'post_slug' => [
					'type' => 'string',
				],
				'post_title' => [
					'type' => 'string',
				],
				'block_name' => [
					'type' => 'string',
				],
				'target' => self::get_ai_plan_target_schema(),
				'fields' => [
					'type' => 'object',
					'additionalProperties' => true,
				],
				'position' => [
					'type' => 'object',
					'additionalProperties' => false,
					'properties' => [
						'type' => [
							'type' => 'string',
							'enum' => [ 'append', 'prepend', 'before', 'after' ],
						],
						'target' => self::get_ai_plan_target_schema(),
					],
				],
				'parent_path' => [
					'type' => 'array',
					'items' => [
						'type' => 'integer',
						'minimum' => 0,
					],
				],
				'remove_all' => [
					'type' => 'boolean',
				],
			],
		];
	}

	private static function validate_ai_planner_result( array $result ) {
		$schema = self::get_ai_plan_schema();

		if ( function_exists( 'rest_validate_value_from_schema' ) ) {
			$validation = rest_validate_value_from_schema( $result, $schema, 'ai_plan' );
			if ( is_wp_error( $validation ) ) {
				return new WP_Error(
					'sae_planner_schema_invalid',
					'AI planner returned invalid JSON shape: ' . $validation->get_error_message(),
					[ 'status' => 502 ]
				);
			}
		}

		$sanitized = $result;
		if ( function_exists( 'rest_sanitize_value_from_schema' ) ) {
			$sanitized = rest_sanitize_value_from_schema( $result, $schema );
		}
		if ( ! is_array( $sanitized ) ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI planner result must decode to an object.', [ 'status' => 502 ] );
		}

		if ( isset( $sanitized['operation'] ) ) {
			$sanitized['operation'] = sanitize_key( (string) $sanitized['operation'] );
		}

		if ( isset( $sanitized['post_id'] ) ) {
			$sanitized['post_id'] = absint( $sanitized['post_id'] );
		}

		foreach ( [ 'post_slug', 'post_title', 'post_url' ] as $key ) {
			if ( isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $sanitized[ $key ] );
			}
		}

		if ( isset( $sanitized['block_name'] ) ) {
			$sanitized['block_name'] = self::normalize_block_name( $sanitized['block_name'] );
		}

		if ( array_key_exists( 'target', $sanitized ) ) {
			$target = self::normalize_target_selector( $sanitized['target'] );
			if ( ! empty( $sanitized['target'] ) && empty( $target ) ) {
				return new WP_Error( 'sae_planner_schema_invalid', 'AI planner target selector is invalid.', [ 'status' => 502 ] );
			}
			$sanitized['target'] = $target;
		}

		if ( array_key_exists( 'fields', $sanitized ) ) {
			if ( ! is_array( $sanitized['fields'] ) ) {
				return new WP_Error( 'sae_planner_schema_invalid', 'AI planner fields must be an object.', [ 'status' => 502 ] );
			}
			if ( ! empty( $sanitized['fields'] ) && ! self::is_assoc_array( $sanitized['fields'] ) ) {
				return new WP_Error( 'sae_planner_schema_invalid', 'AI planner fields must be a key/value object.', [ 'status' => 502 ] );
			}
		}

		if ( array_key_exists( 'position', $sanitized ) ) {
			if ( ! is_array( $sanitized['position'] ) ) {
				return new WP_Error( 'sae_planner_schema_invalid', 'AI planner position must be an object.', [ 'status' => 502 ] );
			}
			$position_type = sanitize_key( (string) ( $sanitized['position']['type'] ?? '' ) );
			if ( ! in_array( $position_type, [ 'append', 'prepend', 'before', 'after' ], true ) ) {
				return new WP_Error( 'sae_planner_schema_invalid', 'AI planner position.type is invalid.', [ 'status' => 502 ] );
			}

			$normalized_position = [ 'type' => $position_type ];
			$position_target = self::normalize_target_selector( $sanitized['position']['target'] ?? [] );
			if ( in_array( $position_type, [ 'before', 'after' ], true ) && empty( $position_target ) ) {
				return new WP_Error(
					'sae_planner_schema_invalid',
					'AI planner position.target is required for before/after.',
					[ 'status' => 502 ]
				);
			}
			if ( ! empty( $position_target ) ) {
				$normalized_position['target'] = $position_target;
			}
			$sanitized['position'] = $normalized_position;
		}

		if ( array_key_exists( 'parent_path', $sanitized ) ) {
			$parent_path = self::normalize_index_path( $sanitized['parent_path'] );
			if ( ! empty( $sanitized['parent_path'] ) && empty( $parent_path ) ) {
				return new WP_Error( 'sae_planner_schema_invalid', 'AI planner parent_path is invalid.', [ 'status' => 502 ] );
			}
			$sanitized['parent_path'] = $parent_path;
		}

		if ( array_key_exists( 'remove_all', $sanitized ) ) {
			$sanitized['remove_all'] = (bool) $sanitized['remove_all'];
		}

		return $sanitized;
	}

	private static function get_allowed_persuasion_labels() {
		return [
			'benefit',
			'clarity',
			'curiosity',
			'emotion',
			'urgency',
			'social-proof',
			'authority',
			'risk-reversal',
			'value',
		];
	}

	private static function normalize_persuasion_label( $value ) {
		$label = strtolower( trim( (string) $value ) );
		if ( '' === $label ) {
			return '';
		}
		$label = str_replace( '_', '-', $label );
		$label = preg_replace( '/\s+/', '-', $label );
		$label = sanitize_key( $label );
		if ( in_array( $label, self::get_allowed_persuasion_labels(), true ) ) {
			return $label;
		}

		return '';
	}

	private static function validate_ai_rewrite_result( array $result ) {
		$schema = self::get_ai_rewrite_schema();

		if ( function_exists( 'rest_validate_value_from_schema' ) ) {
			$validation = rest_validate_value_from_schema( $result, $schema, 'ai_rewrite' );
			if ( is_wp_error( $validation ) ) {
				return new WP_Error(
					'sae_planner_schema_invalid',
					'AI rewrite response shape is invalid: ' . $validation->get_error_message(),
					[ 'status' => 502 ]
				);
			}
		}

		$sanitized = $result;
		if ( function_exists( 'rest_sanitize_value_from_schema' ) ) {
			$sanitized = rest_sanitize_value_from_schema( $result, $schema );
		}
		if ( ! is_array( $sanitized ) ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI rewrite response must decode to an object.', [ 'status' => 502 ] );
		}

		$rewritten_value = self::normalize_rewrite_field_value( wp_kses_post( (string) ( $sanitized['rewritten_value'] ?? '' ) ) );
		if ( '' === $rewritten_value ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI rewrite planner returned empty rewritten_value.', [ 'status' => 502 ] );
		}

		return [
			'rewritten_value' => $rewritten_value,
			'rationale' => sanitize_text_field( (string) ( $sanitized['rationale'] ?? '' ) ),
		];
	}

	private static function validate_ai_cross_field_result( array $result ) {
		$schema = self::get_ai_cross_field_schema();

		if ( function_exists( 'rest_validate_value_from_schema' ) ) {
			$validation = rest_validate_value_from_schema( $result, $schema, 'ai_cross_field' );
			if ( is_wp_error( $validation ) ) {
				return new WP_Error(
					'sae_planner_schema_invalid',
					'AI cross-field response shape is invalid: ' . $validation->get_error_message(),
					[ 'status' => 502 ]
				);
			}
		}

		$sanitized = $result;
		if ( function_exists( 'rest_sanitize_value_from_schema' ) ) {
			$sanitized = rest_sanitize_value_from_schema( $result, $schema );
		}
		if ( ! is_array( $sanitized ) ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI cross-field response must decode to an object.', [ 'status' => 502 ] );
		}

		$proposed_value = sanitize_text_field( (string) ( $sanitized['proposed_value'] ?? '' ) );
		if ( '' === $proposed_value ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI cross-field planner returned empty proposed_value.', [ 'status' => 502 ] );
		}

		return [
			'proposed_value' => $proposed_value,
			'rationale' => sanitize_text_field( (string) ( $sanitized['rationale'] ?? '' ) ),
		];
	}

	private static function normalize_ai_candidate_value( $value ) {
		if ( is_string( $value ) || is_numeric( $value ) || is_bool( $value ) ) {
			return sanitize_text_field( (string) $value );
		}

		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$title = sanitize_text_field( (string) ( $value['title'] ?? '' ) );
		$description = sanitize_text_field( (string) ( $value['description'] ?? '' ) );
		if ( '' !== $title && '' !== $description ) {
			return trim( $title . ' — ' . $description );
		}
		if ( '' !== $title ) {
			return $title;
		}
		if ( '' !== $description ) {
			return $description;
		}

		$parts = [];
		foreach ( array_values( $value ) as $entry ) {
			if ( is_scalar( $entry ) ) {
				$part = sanitize_text_field( (string) $entry );
				if ( '' !== $part ) {
					$parts[] = $part;
				}
			}
			if ( count( $parts ) >= 2 ) {
				break;
			}
		}

		return ! empty( $parts ) ? implode( ' — ', $parts ) : '';
	}

	private static function normalize_ai_ideation_result_shape( array $result ) {
		$suggestions = is_array( $result['suggestions'] ?? null ) ? $result['suggestions'] : [];
		$normalized_suggestions = [];
		foreach ( $suggestions as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( array_key_exists( 'candidate_value', $item ) ) {
				$item['candidate_value'] = self::normalize_ai_candidate_value( $item['candidate_value'] );
			}
			$normalized_suggestions[] = $item;
		}
		$result['suggestions'] = $normalized_suggestions;
		return $result;
	}

	private static function validate_ai_ideation_result( array $result ) {
		$result = self::normalize_ai_ideation_result_shape( $result );
		$schema = self::get_ai_ideation_schema();

		if ( function_exists( 'rest_validate_value_from_schema' ) ) {
			$validation = rest_validate_value_from_schema( $result, $schema, 'ai_ask' );
			if ( is_wp_error( $validation ) ) {
				return new WP_Error(
					'sae_planner_schema_invalid',
					'AI ideation response shape is invalid: ' . $validation->get_error_message(),
					[ 'status' => 502 ]
				);
			}
		}

		$sanitized = $result;
		if ( function_exists( 'rest_sanitize_value_from_schema' ) ) {
			$sanitized = rest_sanitize_value_from_schema( $result, $schema );
		}
		if ( ! is_array( $sanitized ) ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI ideation response must decode to an object.', [ 'status' => 502 ] );
		}

		$suggestions = is_array( $sanitized['suggestions'] ?? null ) ? $sanitized['suggestions'] : [];
		$normalized = [];
		foreach ( $suggestions as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$command = sanitize_text_field( (string) ( $item['command'] ?? '' ) );
			$rationale = sanitize_text_field( (string) ( $item['rationale'] ?? '' ) );
			$candidate_value = sanitize_text_field( (string) ( $item['candidate_value'] ?? '' ) );
			$persuasion_label = self::normalize_persuasion_label( $item['persuasion_label'] ?? '' );
			$target_hint = sanitize_text_field( (string) ( $item['target_hint'] ?? '' ) );
			if ( '' === $title || '' === $command ) {
				continue;
			}
			$normalized_item = [
				'title' => $title,
				'rationale' => $rationale,
				'command' => $command,
			];
			if ( '' !== $candidate_value ) {
				$normalized_item['candidate_value'] = $candidate_value;
			}
			if ( '' !== $persuasion_label ) {
				$normalized_item['persuasion_label'] = $persuasion_label;
			}
			if ( '' !== $target_hint ) {
				$normalized_item['target_hint'] = $target_hint;
			}
			$normalized[] = $normalized_item;
		}

		if ( empty( $normalized ) ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI ideation planner returned empty suggestions.', [ 'status' => 502 ] );
		}

		return [
			'suggestions' => array_slice( $normalized, 0, self::ASK_MAX_SUGGESTIONS ),
		];
	}

	private static function normalize_audit_issue_severity( $value ) {
		$normalized = sanitize_key( (string) $value );
		$aliases = [
			'critical' => 'high',
			'severe' => 'high',
			'high' => 'high',
			'warning' => 'medium',
			'moderate' => 'medium',
			'medium' => 'medium',
			'info' => 'low',
			'minor' => 'low',
			'low' => 'low',
		];
		return $aliases[ $normalized ] ?? '';
	}

	private static function validate_ai_audit_result( array $result ) {
		$schema = self::get_ai_audit_schema();

		if ( function_exists( 'rest_validate_value_from_schema' ) ) {
			$validation = rest_validate_value_from_schema( $result, $schema, 'ai_audit' );
			if ( is_wp_error( $validation ) ) {
				return new WP_Error(
					'sae_planner_schema_invalid',
					'AI audit response shape is invalid: ' . $validation->get_error_message(),
					[ 'status' => 502 ]
				);
			}
		}

		$sanitized = $result;
		if ( function_exists( 'rest_sanitize_value_from_schema' ) ) {
			$sanitized = rest_sanitize_value_from_schema( $result, $schema );
		}
		if ( ! is_array( $sanitized ) ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI audit response must decode to an object.', [ 'status' => 502 ] );
		}

		$score = absint( $sanitized['score'] ?? 0 );
		if ( $score < 1 || $score > 10 ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI audit response must include a score between 1 and 10.', [ 'status' => 502 ] );
		}

		$summary = sanitize_text_field( (string) ( $sanitized['summary'] ?? '' ) );
		$issues = is_array( $sanitized['issues'] ?? null ) ? $sanitized['issues'] : [];
		$had_issue_candidates = ! empty( $issues );
		$normalized_issues = [];
		foreach ( $issues as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$severity = self::normalize_audit_issue_severity( $item['severity'] ?? '' );
			$rationale = sanitize_text_field( (string) ( $item['rationale'] ?? '' ) );
			$fix_command = sanitize_text_field( (string) ( $item['fix_command'] ?? '' ) );
			$section_label = sanitize_text_field( (string) ( $item['section_label'] ?? '' ) );
			$category = sanitize_text_field( (string) ( $item['category'] ?? '' ) );
			if ( '' === $title || '' === $severity || '' === $rationale || '' === $fix_command ) {
				continue;
			}
			$normalized_issue = [
				'title' => $title,
				'severity' => $severity,
				'rationale' => $rationale,
				'fix_command' => $fix_command,
			];
			if ( '' !== $section_label ) {
				$normalized_issue['section_label'] = $section_label;
			}
			if ( '' !== $category ) {
				$normalized_issue['category'] = $category;
			}
			$normalized_issues[] = $normalized_issue;
		}

		if ( $had_issue_candidates && empty( $normalized_issues ) ) {
			return new WP_Error( 'sae_planner_schema_invalid', 'AI audit planner returned issues without usable fix commands.', [ 'status' => 502 ] );
		}

		if ( '' === $summary ) {
			$summary = empty( $normalized_issues )
				? 'No major issues were identified.'
				: sprintf( '%d issue%s found.', count( $normalized_issues ), 1 === count( $normalized_issues ) ? '' : 's' );
		}

		return [
			'score' => $score,
			'summary' => $summary,
			'issues' => array_slice( $normalized_issues, 0, 8 ),
		];
	}

	private static function is_variant_ideation_request( $request_text ) {
		$raw_text = (string) $request_text;
		$normalized = self::normalize_match_phrase( $raw_text );
		if ( '' === $normalized ) {
			return false;
		}

		if ( preg_match( '/\ba\s*\/\s*b\b/i', $raw_text ) ) {
			return true;
		}

		return (bool) preg_match(
			'/\b((?:a\s*b|ab)(?:\s+test(?:ing)?)?|split\s+test(?:ing)?|variant|variants|alternatives?|copy\s+options?|headline\s+options?)\b/',
			$normalized
		);
	}

	private static function normalize_variant_target_hint( $focus ) {
		$focus = self::normalize_match_phrase( $focus );
		if ( 'button text' === $focus ) {
			return 'button_text';
		}
		if ( 'subheading' === $focus ) {
			return 'subheading';
		}
		if ( 'description' === $focus ) {
			return 'description';
		}
		return 'headline';
	}

		private static function apply_variant_metadata_to_suggestions( array $suggestions, $request_text, $focus = '' ) {
			if ( ! self::is_variant_ideation_request( $request_text ) ) {
				return $suggestions;
			}

			$target_hint = self::normalize_variant_target_hint( $focus );
			$normalized = [];
			foreach ( array_values( $suggestions ) as $index => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

			$candidate_value = sanitize_text_field( (string) ( $item['candidate_value'] ?? '' ) );
			if ( '' === $candidate_value ) {
				$quoted = self::extract_quoted_values( (string) ( $item['command'] ?? '' ) );
				if ( ! empty( $quoted ) ) {
					$candidate_value = sanitize_text_field( (string) $quoted[0] );
				}
			}
			if ( '' === $candidate_value ) {
				$candidate_value = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
				}

				$item['candidate_value'] = $candidate_value;
				$normalized_label = self::normalize_persuasion_label( $item['persuasion_label'] ?? '' );
				if ( '' !== $normalized_label ) {
					$item['persuasion_label'] = $normalized_label;
				} else {
					unset( $item['persuasion_label'] );
				}
				if ( '' === sanitize_text_field( (string) ( $item['target_hint'] ?? '' ) ) ) {
					$item['target_hint'] = $target_hint;
				}
			$normalized[] = $item;
		}

		return $normalized;
	}

	private static function build_fallback_ask_suggestions( $request_text, array $post = [] ) {
		$scope = trim( (string) ( $post['post_title'] ?? '' ) );
		if ( '' === $scope ) {
			$scope = 'the selected page';
		}

		$normalized = self::normalize_match_phrase( $request_text );
		$focus = 'headline';
		if ( preg_match( '/\b(cta|button)\b/', $normalized ) ) {
			$focus = 'button text';
		} elseif ( preg_match( '/\b(subheading|subtitle)\b/', $normalized ) ) {
			$focus = 'subheading';
		} elseif ( preg_match( '/\b(description|copy|body)\b/', $normalized ) ) {
			$focus = 'description';
		}

		if ( 'button text' === $focus ) {
			$suggestions = [
				[
					'title' => 'Start Free',
					'rationale' => 'Short, clear CTA focused on immediate action.',
					'command' => sprintf( 'Update the button text on %s to "Start Free".', $scope ),
				],
				[
					'title' => 'Try Interactive Demo',
					'rationale' => 'Low-friction CTA for users evaluating first.',
					'command' => sprintf( 'Update the button text on %s to "Try Interactive Demo".', $scope ),
				],
				[
					'title' => 'Book a Demo',
					'rationale' => 'High-intent CTA for buyers ready to talk.',
					'command' => sprintf( 'Update the button text on %s to "Book a Demo".', $scope ),
				],
			];
			return self::apply_variant_metadata_to_suggestions( $suggestions, $request_text, $focus );
		}

		if ( 'description' === $focus ) {
			$suggestions = [
				[
					'title' => 'Lead with the business outcome',
					'rationale' => 'Outcome-first copy improves clarity and conversion.',
					'command' => sprintf( 'Update the description on %s to "Trusted AI answers from governed, live business data with no pipelines required.".', $scope ),
				],
				[
					'title' => 'Highlight speed + trust together',
					'rationale' => 'Combining speed and trust messaging fits purchase criteria.',
					'command' => sprintf( 'Update the description on %s to "Deliver trusted insights in seconds with governed access and clear review before publish.".', $scope ),
				],
				[
					'title' => 'Emphasize cost efficiency',
					'rationale' => 'Cost framing helps justify adoption internally.',
					'command' => sprintf( 'Update the description on %s to "Cut publishing time while keeping every change reviewable before it goes live.".', $scope ),
				],
			];
			return self::apply_variant_metadata_to_suggestions( $suggestions, $request_text, $focus );
		}

		$suggestions = [
			[
				'title' => 'Trusted AI for every team',
				'rationale' => 'Balances governance and usability for a broad audience.',
				'command' => sprintf( 'Update the %s on %s to "Trusted AI for every team".', $focus, $scope ),
			],
			[
				'title' => 'Cut cost, ship insights faster',
				'rationale' => 'Communicates clear value in a short statement.',
				'command' => sprintf( 'Update the %s on %s to "Cut cost, ship insights faster".', $focus, $scope ),
			],
			[
				'title' => 'Governed data. Confident decisions.',
				'rationale' => 'Connects data governance directly to business confidence.',
				'command' => sprintf( 'Update the %s on %s to "Governed data. Confident decisions.".', $focus, $scope ),
			],
		];

		return self::apply_variant_metadata_to_suggestions( $suggestions, $request_text, $focus );
	}

	private static function get_session_context_key() {
		$user_id = absint( get_current_user_id() );
		$session_token = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
		$token_hash = $session_token ? substr( hash( 'sha256', $session_token ), 0, 20 ) : 'anon';
		return sprintf( '%s%d_%s', self::SESSION_CONTEXT_KEY_PREFIX, $user_id, $token_hash );
	}

	private static function get_session_context_entries() {
		$stored = get_transient( self::get_session_context_key() );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$entries = [];
		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$request = self::sanitize_plan_request_text( $entry['request'] ?? '' );
			$summary = sanitize_text_field( (string) ( $entry['summary'] ?? '' ) );
			if ( '' === $request || '' === $summary ) {
				continue;
			}
			$entries[] = [
				'request' => $request,
				'intent' => in_array( sanitize_key( (string) ( $entry['intent'] ?? '' ) ), [ 'ask', 'do', 'audit' ], true ) ? sanitize_key( (string) $entry['intent'] ) : 'do',
				'source' => sanitize_key( (string) ( $entry['source'] ?? '' ) ),
				'summary' => $summary,
			];
		}

		if ( count( $entries ) > self::SESSION_CONTEXT_MAX_ENTRIES ) {
			$entries = array_slice( $entries, -1 * self::SESSION_CONTEXT_MAX_ENTRIES );
		}

		return $entries;
	}

	private static function append_session_context_entry( $request_text, $intent, $source, $summary ) {
		$request = self::sanitize_plan_request_text( $request_text );
		$summary = sanitize_text_field( (string) $summary );
		if ( '' === $request || '' === $summary ) {
			return;
		}

		$normalized_intent = sanitize_key( (string) $intent );
		if ( ! in_array( $normalized_intent, [ 'ask', 'do', 'audit' ], true ) ) {
			$normalized_intent = 'do';
		}

		$entries = self::get_session_context_entries();
		$entries[] = [
			'request' => $request,
			'intent' => $normalized_intent,
			'source' => sanitize_key( (string) $source ),
			'summary' => $summary,
		];
		if ( count( $entries ) > self::SESSION_CONTEXT_MAX_ENTRIES ) {
			$entries = array_slice( $entries, -1 * self::SESSION_CONTEXT_MAX_ENTRIES );
		}

		set_transient( self::get_session_context_key(), $entries, self::SESSION_CONTEXT_TTL );
	}

	private static function get_session_context_prompt_lines( $max_chars = self::SESSION_CONTEXT_MAX_CHARS ) {
		$entries = self::get_session_context_entries();
		if ( empty( $entries ) ) {
			return [];
		}

		$lines = array_map(
			static function ( $entry ) {
				return sprintf(
					'User: %s → Result: %s',
					(string) ( $entry['request'] ?? '' ),
					(string) ( $entry['summary'] ?? '' )
				);
			},
			$entries
		);
		$max_chars = max( 120, absint( $max_chars ) );
		while ( ! empty( $lines ) ) {
			$joined = implode( "\n", $lines );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $joined ) : strlen( $joined );
			if ( $length <= $max_chars ) {
				break;
			}
			array_shift( $lines );
		}

		if ( empty( $lines ) ) {
			return [];
		}

		array_unshift( $lines, 'Previous conversation:' );
		return $lines;
	}

	private static function get_ai_persona_config() {
		$options = self::get_options();
		$tone = self::normalize_prompt_excerpt( (string) ( $options['ai_persona_tone'] ?? '' ), 80 );
		$reading_level = self::sanitize_persona_reading_level( $options['ai_persona_reading_level'] ?? '' );
		$forbidden = is_array( $options['ai_persona_forbidden_phrases'] ?? null ) ? $options['ai_persona_forbidden_phrases'] : [];
		$forbidden = array_map(
			static function ( $phrase ) {
				return self::normalize_prompt_excerpt( $phrase, 80 );
			},
			$forbidden
		);
		$forbidden = array_values( array_filter( array_unique( $forbidden ) ) );
		if ( count( $forbidden ) > 12 ) {
			$forbidden = array_slice( $forbidden, 0, 12 );
		}
		return [
			'tone' => $tone,
			'reading_level' => $reading_level,
			'forbidden_phrases' => $forbidden,
		];
	}

	private static function get_ai_persona_prompt_lines() {
		$persona = self::get_ai_persona_config();
		$tone = (string) ( $persona['tone'] ?? '' );
		$reading_level = (string) ( $persona['reading_level'] ?? '' );
		$forbidden = is_array( $persona['forbidden_phrases'] ?? null ) ? $persona['forbidden_phrases'] : [];
		if ( '' === $tone && '' === $reading_level && empty( $forbidden ) ) {
			return [];
		}

		$reading_labels = [
			'grade_6' => 'Grade 6',
			'grade_8' => 'Grade 8',
			'grade_10' => 'Grade 10',
			'college' => 'College',
			'executive' => 'Executive',
		];

		$lines = [ 'Editorial AI persona rules:' ];
		if ( '' !== $tone ) {
			$lines[] = 'Preferred tone: ' . $tone;
		}
		if ( '' !== $reading_level && isset( $reading_labels[ $reading_level ] ) ) {
			$lines[] = 'Reading level target: ' . $reading_labels[ $reading_level ] . '.';
		}
		if ( ! empty( $forbidden ) ) {
			$lines[] = 'Avoid these phrases: ' . implode( ' | ', $forbidden );
		}

		return $lines;
	}

	private static function get_content_type_prompt_context( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! self::user_can_disclose_post( $post_id ) ) {
			return [];
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$post_type = sanitize_key( (string) $post->post_type );
		$lines = [];
		$lines[] = sprintf( 'Content type: %s.', $post_type );

		switch ( $post_type ) {
			case 'post':
				$lines[] = 'This is a blog post. Follow blog conventions:';
				$lines[] = '- Intro paragraph should hook the reader in 1-2 sentences.';
				$lines[] = '- Use clear subheadings for scannability.';
				$lines[] = '- End with a call-to-action, not a summary.';
				$lines[] = '- Headlines should be specific and benefit-driven.';
				$categories = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );
				if ( is_array( $categories ) && ! empty( $categories ) ) {
					$lines[] = 'Categories: ' . implode( ', ', array_slice( $categories, 0, 5 ) ) . '.';
				}
				$tags = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
				if ( is_array( $tags ) && ! empty( $tags ) ) {
					$lines[] = 'Tags: ' . implode( ', ', array_slice( $tags, 0, 10 ) ) . '.';
				}
				break;

			case 'page':
				$lines[] = 'This is a marketing/product page. Follow page conventions:';
				$lines[] = '- Headlines should emphasize value propositions.';
				$lines[] = '- Keep copy scannable with short paragraphs.';
				$lines[] = '- CTAs should be action-oriented and specific.';
				break;

			default:
				$discoverable_types = self::get_discoverable_content_types();
				$type_label = isset( $discoverable_types[ $post_type ]['label'] ) ? (string) $discoverable_types[ $post_type ]['label'] : $post_type;
				$lines[] = sprintf( 'This is a %s. Follow its conventional content structure.', $type_label );
				break;
		}

		return $lines;
	}

	private static function summarize_ask_session_entry( array $ask_response ) {
		$suggestions = is_array( $ask_response['suggestions'] ?? null ) ? $ask_response['suggestions'] : [];
		$count = count( $suggestions );
		$post_label = sanitize_text_field( (string) ( $ask_response['post']['post_title'] ?? '' ) );
		if ( '' === $post_label ) {
			$post_label = sanitize_text_field( (string) ( $ask_response['post']['post_slug'] ?? '' ) );
		}
		$scope = '' !== $post_label ? sprintf( ' for %s', $post_label ) : '';
		return sprintf( 'Suggested %d option%s%s.', $count, 1 === $count ? '' : 's', $scope );
	}


	private static function summarize_audit_session_entry( array $audit_response ) {
		$post_label = sanitize_text_field( (string) ( $audit_response['post']['post_title'] ?? '' ) );
		if ( '' === $post_label ) {
			$post_label = sanitize_text_field( (string) ( $audit_response['post']['post_slug'] ?? '' ) );
		}
		if ( '' === $post_label ) {
			$post_label = 'the selected page';
		}
		$score = absint( $audit_response['audit']['score'] ?? 0 );
		$issue_count = count( is_array( $audit_response['audit']['issues'] ?? null ) ? $audit_response['audit']['issues'] : [] );
		return sprintf(
			'Audited %s: score %d/10 with %d issue%s.',
			$post_label,
			$score,
			$issue_count,
			1 === $issue_count ? '' : 's'
		);
	}

	private static function summarize_do_session_entry( array $prepared, array $planner_meta = [] ) {
		$operation = sanitize_key( (string) ( $prepared['operation'] ?? 'update' ) );
		$post_label = sanitize_text_field( (string) ( $prepared['post']['post_title'] ?? '' ) );
		if ( '' === $post_label ) {
			$post_label = sanitize_text_field( (string) ( $prepared['post']['post_slug'] ?? '' ) );
		}
		if ( '' === $post_label ) {
			$post_label = 'the selected page';
		}

		$rewrite_field = sanitize_text_field( (string) ( $planner_meta['field_label'] ?? $planner_meta['field_name'] ?? '' ) );
		if ( '' !== $rewrite_field ) {
			return sprintf( 'Prepared rewrite plan for %s on %s.', $rewrite_field, $post_label );
		}

		return sprintf( 'Prepared %s plan on %s.', $operation ? $operation : 'update', $post_label );
	}

	private static function normalize_prompt_excerpt( $value, $max_chars = 120 ) {
		$text = wp_strip_all_tags( (string) $value );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( (string) $text );
		$max_chars = max( 40, absint( $max_chars ) );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $length > $max_chars ) {
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_chars ) : substr( $text, 0, $max_chars );
			$text = rtrim( $text ) . '…';
		}
		return str_replace( '"', "'", $text );
	}

	private static function build_block_prompt_field_pairs( array $block, $block_name ) {
		$fields = self::extract_block_fields_for_rewrite( $block );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return [];
		}

		$pairs = [];
		$schema = self::get_block_schema( $block_name );
		if ( ! empty( $schema ) ) {
			foreach ( $schema as $field_name => $schema_field ) {
				$canonical = sanitize_key( (string) $field_name );
				if ( '' === $canonical || ! isset( $fields[ $canonical ] ) ) {
					continue;
				}
				if ( ! is_array( $schema_field ) || ! self::is_textual_schema_field( $schema_field ) ) {
					continue;
				}
				$value = self::normalize_prompt_excerpt( $fields[ $canonical ] );
				if ( '' === $value ) {
					continue;
				}
				$pairs[] = sprintf( '%s="%s"', $canonical, $value );
				if ( count( $pairs ) >= 3 ) {
					break;
				}
			}
		}

		if ( ! empty( $pairs ) ) {
			return $pairs;
		}

		foreach ( $fields as $field_name => $value ) {
			$canonical = sanitize_key( (string) $field_name );
			if ( '' === $canonical ) {
				continue;
			}
			$normalized = self::normalize_prompt_excerpt( $value );
			if ( '' === $normalized ) {
				continue;
			}
			$pairs[] = sprintf( '%s="%s"', $canonical, $normalized );
			if ( count( $pairs ) >= 3 ) {
				break;
			}
		}

		return $pairs;
	}

	private static function build_planner_page_context_lines( array $payload = [] ) {
		$plan = is_array( $payload['plan'] ?? null ) ? $payload['plan'] : [];
		$post_id = absint( $plan['post_id'] ?? ( $payload['post_id'] ?? 0 ) );
		if ( $post_id <= 0 || ! self::user_can_disclose_post( $post_id ) ) {
			return [];
		}

		$blocks = self::get_parsed_blocks( $post_id );
		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return [];
		}

		$lines = [ 'Current page content (top blocks):' ];
		$max_chars = self::PLANNER_PAGE_CONTEXT_MAX_CHARS;
		$used_chars = function_exists( 'mb_strlen' ) ? mb_strlen( $lines[0] ) : strlen( $lines[0] );
		foreach ( array_slice( $blocks, 0, 5 ) as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$block_name = self::normalize_block_name( $block['blockName'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}
			$pairs = self::build_block_prompt_field_pairs( $block, $block_name );
			if ( empty( $pairs ) ) {
				continue;
			}
			$line = sprintf( '%s: %s', $block_name, implode( ', ', $pairs ) );
			$line_length = function_exists( 'mb_strlen' ) ? mb_strlen( $line ) : strlen( $line );
			if ( ( $used_chars + $line_length + 1 ) > $max_chars ) {
				break;
			}
			$lines[] = $line;
			$used_chars += $line_length + 1;
		}

		return count( $lines ) > 1 ? $lines : [];
	}

	private static function retrieve_rag_context( $search_text ) {
		self::note_eval_disclosure_spend( 'rag' );
		$request_text = self::sanitize_plan_request_text( $search_text );
		if ( '' === $request_text || self::RAG_CONTEXT_MAX_CHARS <= 0 ) {
			return null;
		}
		$rag_disabled = self::get_config_constant( 'SAE_RAG_DISABLED', 'STRUO_RAG_DISABLED' );
		if ( null !== $rag_disabled && $rag_disabled ) {
			return null;
		}

		global $mwai_core;
		if ( ! is_object( $mwai_core ) || ! method_exists( $mwai_core, 'retrieve_context' ) || ! method_exists( $mwai_core, 'get_option' ) ) {
			return null;
		}
		if ( ! class_exists( 'Meow_MWAI_Query_Text' ) ) {
			return null;
		}

		$embeddings_env_id = sanitize_text_field( (string) $mwai_core->get_option( 'embeddings_default_env' ) );
		if ( '' === $embeddings_env_id ) {
			$embeddings_env_id = sanitize_text_field( (string) $mwai_core->get_option( 'ai_embeddings_default_env' ) );
		}
		if ( '' === $embeddings_env_id ) {
			return null;
		}
		if ( method_exists( $mwai_core, 'get_embeddings_env' ) ) {
			$env = $mwai_core->get_embeddings_env( $embeddings_env_id );
			if ( empty( $env ) || ! is_array( $env ) ) {
				return null;
			}
		}

		$cache_key = md5( strtolower( $embeddings_env_id . '|' . $request_text ) );
		if ( self::$rag_context_cache_key === $cache_key && is_array( self::$rag_context_cache ) ) {
			return self::$rag_context_cache;
		}

		try {
			$query = new \Meow_MWAI_Query_Text( $request_text );
			$context = $mwai_core->retrieve_context(
				[
					'embeddingsEnvId' => $embeddings_env_id,
					'contextMaxLength' => self::RAG_CONTEXT_MAX_CHARS,
				],
				$query
			);
		} catch ( \Throwable $exception ) {
			$context = null;
		}

		$content = is_array( $context ) ? (string) ( $context['content'] ?? '' ) : '';
		$content = self::normalize_prompt_excerpt( $content, self::RAG_CONTEXT_MAX_CHARS );
		$embeddings = [];
		$unscoped_hit = false;
		if ( is_array( $context ) && ! empty( $context['embeddings'] ) && is_array( $context['embeddings'] ) ) {
			foreach ( array_slice( $context['embeddings'], 0, 6 ) as $embedding ) {
				if ( ! is_array( $embedding ) ) {
					continue;
				}
				$ref_post_id = absint( $embedding['postId'] ?? ( $embedding['post_id'] ?? 0 ) );
				$ref = sanitize_text_field( (string) ( $embedding['ref'] ?? '' ) );
				if ( $ref_post_id <= 0 && ctype_digit( $ref ) ) {
					$ref_post_id = absint( $ref );
				}
				if ( $ref_post_id <= 0 ) {
					$unscoped_hit = true;
					continue;
				}
				if ( ! self::user_can_disclose_post( $ref_post_id ) ) {
					continue;
				}
				$title = sanitize_text_field( (string) ( $embedding['title'] ?? '' ) );
				if ( '' === $title ) {
					$title = $ref;
				}
				if ( '' === $title ) {
					continue;
				}
				$item = [ 'title' => $title ];
				if ( isset( $embedding['score'] ) && is_numeric( $embedding['score'] ) ) {
					$item['score'] = round( (float) $embedding['score'], 4 );
				}
				$embeddings[] = $item;
			}
		}
		if ( $unscoped_hit || empty( $embeddings ) ) {
			$content = '';
		}

		if ( '' === $content && empty( $embeddings ) ) {
			self::$rag_context_cache_key = $cache_key;
			self::$rag_context_cache = [
				'lines' => [],
				'meta' => [
					'enriched' => false,
					'match_count' => 0,
				],
				'embeddings' => [],
			];
			return self::$rag_context_cache;
		}

		$source_line_parts = [];
		foreach ( array_slice( $embeddings, 0, 4 ) as $item ) {
			$line = $item['title'];
			if ( isset( $item['score'] ) && is_numeric( $item['score'] ) ) {
				$line .= sprintf( ' (score %.2f)', (float) $item['score'] );
			}
			$source_line_parts[] = $line;
		}

		$lines = [ 'Related content from the knowledge base:' ];
		if ( ! empty( $source_line_parts ) ) {
			$lines[] = 'Matched sources: ' . implode( ' | ', $source_line_parts );
		}
		if ( '' !== $content ) {
			$lines[] = 'Relevant excerpts: ' . $content;
		}

		self::$rag_context_cache_key = $cache_key;
		self::$rag_context_cache = [
			'lines' => $lines,
			'meta' => [
				'enriched' => true,
				'match_count' => count( $embeddings ),
			],
			'embeddings' => $embeddings,
		];
		return self::$rag_context_cache;
	}

	private static function build_vector_meta_payload( $rag_context ) {
		if ( ! is_array( $rag_context ) || ! is_array( $rag_context['meta'] ?? null ) ) {
			return [
				'enriched' => false,
				'match_count' => 0,
			];
		}
		return [
			'enriched' => ! empty( $rag_context['meta']['enriched'] ),
			'match_count' => absint( $rag_context['meta']['match_count'] ?? 0 ),
		];
	}

	private static function build_vector_sources_payload( $rag_context ) {
		if ( ! is_array( $rag_context ) || empty( $rag_context['embeddings'] ) || ! is_array( $rag_context['embeddings'] ) ) {
			return [];
		}

		$sources = [];
		foreach ( array_slice( $rag_context['embeddings'], 0, 6 ) as $embedding ) {
			if ( ! is_array( $embedding ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $embedding['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$item = [ 'title' => $title ];
			if ( isset( $embedding['score'] ) && is_numeric( $embedding['score'] ) ) {
				$item['score'] = round( (float) $embedding['score'], 4 );
			}
			$sources[] = $item;
		}

		return $sources;
	}

	private static function build_ai_planner_prompt( $request_text, array $payload = [] ) {
		$allowlisted_posts = self::list_allowlisted_posts();
		$post_hints = array_map(
			static function ( $post ) {
				$title = trim( (string) ( $post['post_title'] ?? '' ) );
				$slug = trim( (string) ( $post['post_slug'] ?? '' ) );
				return sprintf(
					'%d:%s%s',
					absint( $post['post_id'] ?? 0 ),
					$title ? $title : 'untitled',
					$slug ? ' (' . $slug . ')' : ''
				);
			},
			$allowlisted_posts
		);

		$allowed_blocks = self::get_allowed_block_types();
		$session_context_lines = self::get_session_context_prompt_lines();
		$persona_lines = self::get_ai_persona_prompt_lines();
		$page_context_lines = self::build_planner_page_context_lines( $payload );

		$lines = [];
		$lines[] = 'Convert the request into a JSON plan for a WordPress block edit API.';
		$lines[] = 'Return valid JSON only. No markdown.';
		$lines[] = 'Supported operations: insert, update, remove.';
		$lines[] = 'Use explicit field names when possible.';
		$lines[] = 'Only use allowlisted post IDs and block names.';
		$lines[] = 'Allowed posts: ' . implode( ' | ', $post_hints );
		$lines[] = 'Allowed block names: ' . implode( ', ', $allowed_blocks );
		if ( ! empty( $session_context_lines ) ) {
			$lines = array_merge( $lines, $session_context_lines );
		}
		if ( ! empty( $persona_lines ) ) {
			$lines = array_merge( $lines, $persona_lines );
		}
		$planner_post_id = absint( $payload['post_id'] ?? ( is_array( $payload['plan'] ?? null ) ? ( $payload['plan']['post_id'] ?? 0 ) : 0 ) );
		$content_type_lines = self::get_content_type_prompt_context( $planner_post_id );
		if ( ! empty( $content_type_lines ) ) {
			$lines = array_merge( $lines, $content_type_lines );
		}
		if ( ! empty( $page_context_lines ) ) {
			$lines = array_merge( $lines, $page_context_lines );
		}
		$rag = self::retrieve_rag_context( $request_text );
		if ( ! empty( $rag['lines'] ) && is_array( $rag['lines'] ) ) {
			$lines = array_merge( $lines, $rag['lines'] );
		}
		$lines[] = 'JSON schema (shape):';
		$lines[] = wp_json_encode(
			[
				'operation' => 'insert|update|remove',
				'post_id' => 0,
				'post_url' => '',
				'post_slug' => '',
				'post_title' => '',
				'block_name' => '',
				'target' => [
					'index_path' => [ 0 ],
					'anchor' => '',
					'block_id' => '',
				],
				'fields' => new stdClass(),
				'position' => [
					'type' => 'append|prepend|before|after',
					'target' => [
						'index_path' => [ 0 ],
						'anchor' => '',
						'block_id' => '',
					],
				],
				'parent_path' => [ 0 ],
				'remove_all' => false,
			]
		);
		$lines[] = 'User request: ' . $request_text;

		return implode( "\n", $lines );
	}

	private static function build_ai_ideation_prompt( $request_text, array $post = [] ) {
		$is_variant_request = self::is_variant_ideation_request( $request_text );
		$allowlisted_posts = self::list_allowlisted_posts();
		$post_hints = array_map(
			static function ( $item ) {
				$title = trim( (string) ( $item['post_title'] ?? '' ) );
				$slug = trim( (string) ( $item['post_slug'] ?? '' ) );
				return sprintf(
					'%d:%s%s',
					absint( $item['post_id'] ?? 0 ),
					$title ? $title : 'untitled',
					$slug ? ' (' . $slug . ')' : ''
				);
			},
			$allowlisted_posts
		);

		$post_context = '';
		if ( ! empty( $post['post_id'] ) ) {
			$post_context = sprintf(
				'Focus context: post_id=%d, title="%s".',
				absint( $post['post_id'] ),
				sanitize_text_field( (string) ( $post['post_title'] ?? '' ) )
			);
		}

		$lines = [];
		$lines[] = 'Generate concise content-edit suggestions for a WordPress editor request.';
		$lines[] = 'Return valid JSON only. No markdown.';
		$lines[] = 'Return 3 to 5 suggestions.';
		$lines[] = 'Each suggestion must include: title, rationale, command.';
		$lines[] = 'command must be a natural-language edit command that can be sent back to the planner.';
		$lines[] = 'command must begin with an action verb: Update, Change, Set, Replace, Add, Remove, or Insert.';
		if ( $is_variant_request ) {
			$lines[] = 'This request is asking for A/B or variant options.';
			$lines[] = 'For each suggestion include candidate_value (exact variant copy) and persuasion_label.';
			$lines[] = 'persuasion_label must be one of: ' . implode( ', ', self::get_allowed_persuasion_labels() ) . '.';
		} else {
			$lines[] = 'candidate_value, persuasion_label, and target_hint are optional unless the request is A/B ideation.';
		}
		$lines[] = 'Keep each title under 70 characters.';
		$lines[] = 'Allowed posts: ' . implode( ' | ', $post_hints );
		if ( '' !== $post_context ) {
			$lines[] = $post_context;
		}
		$session_context_lines = self::get_session_context_prompt_lines();
		if ( ! empty( $session_context_lines ) ) {
			$lines = array_merge( $lines, $session_context_lines );
		}
		$persona_lines = self::get_ai_persona_prompt_lines();
		if ( ! empty( $persona_lines ) ) {
			$lines = array_merge( $lines, $persona_lines );
		}
		$ideation_post_id = absint( $post['post_id'] ?? 0 );
		$content_type_lines = self::get_content_type_prompt_context( $ideation_post_id );
		if ( ! empty( $content_type_lines ) ) {
			$lines = array_merge( $lines, $content_type_lines );
		}
		$rag = self::retrieve_rag_context( $request_text );
		if ( ! empty( $rag['lines'] ) && is_array( $rag['lines'] ) ) {
			$lines = array_merge( $lines, $rag['lines'] );
		}
		$lines[] = 'JSON schema (shape):';
		$lines[] = wp_json_encode(
			[
				'suggestions' => [
					[
						'title' => '',
						'rationale' => '',
						'command' => '',
						'candidate_value' => '',
						'persuasion_label' => '',
						'target_hint' => '',
					],
				],
			]
		);
		$lines[] = 'User request: ' . $request_text;

		return implode( "\n", $lines );
	}

	private static function build_ai_audit_prompt( $request_text, array $post = [], $block_summary = '', $rag_context = null ) {
		$post_id = absint( $post['post_id'] ?? 0 );
		$post_title = sanitize_text_field( (string) ( $post['post_title'] ?? '' ) );
		$post_slug = sanitize_text_field( (string) ( $post['post_slug'] ?? '' ) );
		$post_url = esc_url_raw( (string) ( $post['post_url'] ?? '' ) );
		$post_obj = $post_id > 0 ? get_post( $post_id ) : null;
		$post_type = $post_obj ? sanitize_key( (string) $post_obj->post_type ) : '';
		$post_status = $post_obj ? sanitize_key( (string) $post_obj->post_status ) : '';

		$lines = [];
		$lines[] = 'Audit a WordPress marketing page and return a structured content quality report.';
		$lines[] = 'Return valid JSON only. No markdown.';
		$lines[] = 'Focus on clarity, value proposition strength, CTA quality, readability, scannability, SEO basics, and accessibility/copy issues.';
		$lines[] = 'Prioritize the highest-impact issues first.';
			$lines[] = 'Return 0 to 8 issues. If the page is already strong, return fewer issues but still include a summary.';
			$lines[] = 'Each issue must include a fix_command that can be sent back to a WordPress edit planner.';
			$lines[] = 'fix_command must begin with an action verb such as Update, Change, Set, Replace, Add, Remove, Rewrite, Shorten, Simplify, or Improve.';
			$lines[] = 'Prefer fix_command values that reference a specific section or editable field rather than vague page-wide advice.';
			$lines[] = 'Each fix_command must describe exactly one editor action. Do not chain multiple actions with "and".';
			$lines[] = 'Use natural language only. Do not include raw block addresses, index_path values, anchors, block ids, schema keys, or internal block names.';
			$lines[] = 'Prefer editor-facing targets such as headline, subheading, button text, paragraph text, CTA label, or section copy.';
		$lines[] = 'severity must be one of: high, medium, low.';
		$lines[] = 'Score the page from 1 to 10.';
		if ( $post_title || $post_slug || $post_type ) {
			$lines[] = sprintf(
				'Page context: title="%s", slug="%s", type=%s, status=%s, url=%s.',
				$post_title,
				$post_slug,
				$post_type ? $post_type : 'unknown',
				$post_status ? $post_status : 'unknown',
				$post_url ? $post_url : 'n/a'
			);
		}
		$session_context_lines = self::get_session_context_prompt_lines();
		if ( ! empty( $session_context_lines ) ) {
			$lines = array_merge( $lines, $session_context_lines );
		}
		$persona_lines = self::get_ai_persona_prompt_lines();
		if ( ! empty( $persona_lines ) ) {
			$lines = array_merge( $lines, $persona_lines );
		}
		$content_type_lines = self::get_content_type_prompt_context( $post_id );
		if ( ! empty( $content_type_lines ) ) {
			$lines = array_merge( $lines, $content_type_lines );
		}
		if ( is_array( $rag_context ) && ! empty( $rag_context['lines'] ) && is_array( $rag_context['lines'] ) ) {
			$lines = array_merge( $lines, $rag_context['lines'] );
		}
		$lines[] = 'Page blocks and excerpts:';
		$lines[] = '' !== trim( (string) $block_summary ) ? (string) $block_summary : '- No block summary available.';
		$lines[] = 'JSON schema (shape):';
		$lines[] = wp_json_encode(
			[
				'score' => 7,
				'summary' => 'string',
				'issues' => [
					[
						'title' => 'string',
						'severity' => 'high|medium|low',
						'section_label' => 'string',
						'category' => 'string',
						'rationale' => 'string',
						'fix_command' => 'string',
					],
				],
			]
		);
		$lines[] = 'Audit request: ' . $request_text;

		return implode( "
", $lines );
	}

	private static function build_ai_rewrite_prompt( $request_text, array $context = [] ) {
		$post = is_array( $context['post'] ?? null ) ? $context['post'] : [];
		$target = is_array( $context['target'] ?? null ) ? $context['target'] : [];
			$target_path = is_array( $context['target_path'] ?? null ) ? $context['target_path'] : [];
			$block_name = sanitize_text_field( (string) ( $context['block_name'] ?? '' ) );
			$field_name = sanitize_key( (string) ( $context['field_name'] ?? '' ) );
			$current_value = (string) ( $context['current_value'] ?? '' );
			$tone_phrase = sanitize_text_field( (string) ( $context['tone_phrase'] ?? '' ) );
			$max_value_length = absint( self::PLAN_MAX_REQUEST_LENGTH );
			if ( $max_value_length > 0 ) {
				if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
					if ( mb_strlen( $current_value ) > $max_value_length ) {
						$current_value = mb_substr( $current_value, 0, $max_value_length );
					}
				} elseif ( strlen( $current_value ) > $max_value_length ) {
					$current_value = substr( $current_value, 0, $max_value_length );
				}
			}

			$lines = [];
		$lines[] = 'Rewrite the provided block field value for WordPress.';
		$lines[] = 'Return valid JSON only. No markdown.';
		$lines[] = 'Adjust tone and phrasing while preserving facts, claims, product names, and intent.';
		$lines[] = 'Do not add new factual claims that are not already present.';
		$lines[] = 'If HTML tags are present, preserve equivalent semantic tags.';
		if ( '' !== $tone_phrase ) {
			$lines[] = 'Requested tone: ' . $tone_phrase;
		}
		$session_context_lines = self::get_session_context_prompt_lines();
		if ( ! empty( $session_context_lines ) ) {
			$lines = array_merge( $lines, $session_context_lines );
		}
		$persona_lines = self::get_ai_persona_prompt_lines();
		if ( ! empty( $persona_lines ) ) {
			$lines = array_merge( $lines, $persona_lines );
		}
		$rewrite_post_id = absint( $post['post_id'] ?? 0 );
		$content_type_lines = self::get_content_type_prompt_context( $rewrite_post_id );
		if ( ! empty( $content_type_lines ) ) {
			$lines = array_merge( $lines, $content_type_lines );
		}
		$rag = self::retrieve_rag_context( $request_text );
		if ( ! empty( $rag['lines'] ) && is_array( $rag['lines'] ) ) {
			$lines = array_merge( $lines, $rag['lines'] );
		}
		$lines[] = 'Context: ' . wp_json_encode(
			[
				'post_id' => absint( $post['post_id'] ?? 0 ),
				'post_title' => sanitize_text_field( (string) ( $post['post_title'] ?? '' ) ),
				'block_name' => $block_name,
				'field_name' => $field_name,
				'target' => $target,
				'target_path' => $target_path,
			]
		);
		$lines[] = 'Current field value:';
		$lines[] = $current_value;
		$lines[] = 'JSON schema (shape):';
		$lines[] = wp_json_encode(
			[
				'rewritten_value' => '',
				'rationale' => '',
			]
		);
		$lines[] = 'User request: ' . $request_text;

		return implode( "\n", $lines );
	}

	private static function build_ai_cross_field_prompt( $request_text, array $context = [] ) {
		$post_id = absint( $context['post_id'] ?? 0 );
		$post_title = sanitize_text_field( (string) ( $context['post_title'] ?? '' ) );
		$post_type = sanitize_key( (string) ( $context['post_type'] ?? '' ) );
		$field = self::normalize_cross_field_name( $context['field'] ?? '' );
		$current_value = self::normalize_cross_field_value( $field, $context['current_value'] ?? '' );
		$source_content = wp_strip_all_tags( (string) ( $context['source_content'] ?? '' ) );
		$source_content = preg_replace( '/\s+/', ' ', $source_content );
		$source_content = trim( (string) $source_content );
		$source_content = self::normalize_prompt_excerpt( $source_content, self::CROSS_FIELD_SOURCE_MAX_CHARS );

		$definitions = self::get_cross_field_definitions();
		$field_label = isset( $definitions[ $field ]['label'] ) ? (string) $definitions[ $field ]['label'] : $field;

		$lines = [];
		$lines[] = 'Generate a post-level field value from WordPress page content.';
		$lines[] = 'Return valid JSON only. No markdown.';
		$lines[] = 'Keep outputs concise, editor-safe, and publication-ready.';
		$lines[] = 'Field target: ' . $field . ' (' . $field_label . ').';

		if ( 'excerpt' === $field ) {
			$lines[] = 'Write a compelling excerpt in 2 to 3 sentences (<= 300 characters).';
			$lines[] = 'Focus on outcomes and reader motivation.';
		} elseif ( 'meta_description' === $field ) {
			$lines[] = 'Write an SEO meta description between 120 and 160 characters.';
			$lines[] = 'Use active voice and include the core benefit naturally.';
		} elseif ( 'featured_image_alt' === $field ) {
			$lines[] = 'Write descriptive featured-image alt text (<= 125 characters).';
			$lines[] = 'Avoid prefixes like "Image of" or "Photo of".';
		}

		$lines[] = 'Post context: ' . wp_json_encode(
			[
				'post_id' => $post_id,
				'post_title' => $post_title,
				'post_type' => $post_type,
			]
		);
		if ( '' !== $current_value ) {
			$lines[] = 'Current field value: ' . $current_value;
			$lines[] = 'Improve it while preserving factual consistency.';
		} else {
			$lines[] = 'Current field value is empty. Generate a new one.';
		}

		$session_context_lines = self::get_session_context_prompt_lines();
		if ( ! empty( $session_context_lines ) ) {
			$lines = array_merge( $lines, $session_context_lines );
		}
		$persona_lines = self::get_ai_persona_prompt_lines();
		if ( ! empty( $persona_lines ) ) {
			$lines = array_merge( $lines, $persona_lines );
		}
		$content_type_lines = self::get_content_type_prompt_context( $post_id );
		if ( ! empty( $content_type_lines ) ) {
			$lines = array_merge( $lines, $content_type_lines );
		}
		$rag = self::retrieve_rag_context( $request_text );
		if ( ! empty( $rag['lines'] ) && is_array( $rag['lines'] ) ) {
			$lines = array_merge( $lines, $rag['lines'] );
		}

		$lines[] = 'Source content:';
		$lines[] = $source_content;
		$lines[] = 'JSON schema (shape):';
		$lines[] = wp_json_encode(
			[
				'proposed_value' => '',
				'rationale' => '',
			]
		);
		$lines[] = 'User request: ' . $request_text;

		return implode( "\n", $lines );
	}

	private static function normalize_batch_operations( array $operations ) {
		$normalized = [];
		foreach ( $operations as $index => $operation ) {
			if ( ! is_array( $operation ) ) {
				return new WP_Error(
					'sae_batch_invalid_operation',
					sprintf( 'Operation %d must be an object.', $index ),
					[ 'status' => 400, 'operation_index' => $index ]
				);
			}

			$action = sanitize_key( (string) ( $operation['action'] ?? ( $operation['operation'] ?? '' ) ) );
			if ( ! in_array( $action, [ 'insert', 'update', 'remove' ], true ) ) {
				return new WP_Error(
					'sae_batch_invalid_action',
					sprintf( 'Operation %d has an invalid action.', $index ),
					[ 'status' => 400, 'operation_index' => $index ]
				);
			}

			$item = [ 'action' => $action ];
			if ( 'insert' === $action ) {
				$block_name = self::normalize_block_name( $operation['block_name'] ?? '' );
				if ( empty( $block_name ) ) {
					return new WP_Error(
						'sae_batch_missing_block_name',
						sprintf( 'Operation %d requires block_name.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}
				$fields = $operation['fields'] ?? [];
				if ( ! is_array( $fields ) ) {
					return new WP_Error(
						'sae_batch_invalid_fields',
						sprintf( 'Operation %d fields must be an object.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}

				$item['block_name'] = $block_name;
				$item['fields'] = $fields;
				$position = is_array( $operation['position'] ?? null ) ? $operation['position'] : [];
				$position_type = sanitize_key( (string) ( $position['type'] ?? 'append' ) );
				if ( ! in_array( $position_type, [ 'append', 'prepend', 'before', 'after' ], true ) ) {
					return new WP_Error(
						'sae_batch_invalid_position',
						sprintf( 'Operation %d has an invalid position.type.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}
				$normalized_position = [ 'type' => $position_type ];
				$position_target = self::normalize_target_selector( $position['target'] ?? [] );
				if ( in_array( $position_type, [ 'before', 'after' ], true ) && empty( $position_target ) ) {
					return new WP_Error(
						'sae_batch_missing_target',
						sprintf( 'Operation %d requires position.target for before/after.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}
				if ( ! empty( $position_target ) ) {
					$normalized_position['target'] = $position_target;
				}
				$item['position'] = $normalized_position;
				$item['parent_path'] = self::normalize_index_path( $operation['parent_path'] ?? [] );
			}

			if ( 'update' === $action ) {
				$target = self::normalize_target_selector( $operation['target'] ?? [] );
				if ( empty( $target ) ) {
					return new WP_Error(
						'sae_batch_missing_target',
						sprintf( 'Operation %d requires target.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}
				$fields = $operation['fields'] ?? [];
				if ( ! is_array( $fields ) || empty( $fields ) ) {
					return new WP_Error(
						'sae_batch_missing_fields',
						sprintf( 'Operation %d requires non-empty fields.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}
				$item['target'] = $target;
				$item['fields'] = $fields;
			}

			if ( 'remove' === $action ) {
				$remove_all = ! empty( $operation['remove_all'] );
				$target = self::normalize_target_selector( $operation['target'] ?? [] );
				$block_name = self::normalize_block_name( $operation['block_name'] ?? '' );
				if ( $remove_all && empty( $block_name ) ) {
					return new WP_Error(
						'sae_batch_missing_block_name',
						sprintf( 'Operation %d requires block_name when remove_all is true.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}
				if ( ! $remove_all && empty( $target ) ) {
					return new WP_Error(
						'sae_batch_missing_target',
						sprintf( 'Operation %d requires target unless remove_all is true.', $index ),
						[ 'status' => 400, 'operation_index' => $index ]
					);
				}

				$item['target'] = $target;
				$item['block_name'] = $block_name;
				$item['remove_all'] = $remove_all;
			}

			$normalized[] = $item;
		}

		return $normalized;
	}

	private static function normalize_target_selector( $target ) {
		if ( ! is_array( $target ) ) {
			return [];
		}

		$normalized = [];
		$index_path = self::normalize_index_path( $target['index_path'] ?? [] );
		if ( ! empty( $index_path ) ) {
			$normalized['index_path'] = $index_path;
		}

		$anchor = sanitize_text_field( (string) ( $target['anchor'] ?? '' ) );
		if ( '' !== $anchor ) {
			$normalized['anchor'] = $anchor;
		}

		$block_id = sanitize_text_field( (string) ( $target['block_id'] ?? '' ) );
		if ( '' !== $block_id ) {
			$normalized['block_id'] = $block_id;
		}

		return $normalized;
	}

	private static function apply_batch( $post_id, array $operations, $dry_run, $confirmation_token = '', $idempotency_key = '', array $response_mode = [], $bundle_name = '', $origin = 'rest', array $context = [] ) {
		$permission = self::ensure_write_allowed( $post_id, ! $dry_run );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'sae_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}

		$normalized_operations = self::normalize_batch_operations( $operations );
		if ( is_wp_error( $normalized_operations ) ) {
			return $normalized_operations;
		}
		if ( empty( $normalized_operations ) ) {
			return new WP_Error( 'sae_batch_missing_operations', 'operations must be a non-empty array.', [ 'status' => 400 ] );
		}
		if ( count( $normalized_operations ) > self::BATCH_MAX_OPERATIONS ) {
			return new WP_Error(
				'sae_batch_too_many_operations',
				sprintf( 'operations cannot exceed %d in a single batch.', self::BATCH_MAX_OPERATIONS ),
				[ 'status' => 400, 'max_operations' => self::BATCH_MAX_OPERATIONS ]
			);
		}

		$operation = self::build_operation_payload(
			'batch',
			$post_id,
			[
				'bundle_name' => self::sanitize_bundle_name( $bundle_name ),
				'operations' => $normalized_operations,
			]
		);

		$confirmation = [];
		if ( $dry_run ) {
			$confirmation = self::issue_confirmation_token( $operation, $post_id, $origin, $context );
		} else {
			$confirmed = self::ensure_write_confirmation( $confirmation_token, $operation, $post_id, $context );
			if ( is_wp_error( $confirmed ) ) {
				return $confirmed;
			}
		}

		$blocks = self::get_parsed_blocks( $post_id );
		$before_summary = self::summarize_blocks( $blocks );
		$results = [];

		foreach ( $normalized_operations as $index => $item ) {
			$item_result = self::apply_batch_operation( $post_id, $blocks, $item, $index );
			if ( is_wp_error( $item_result ) ) {
				return $item_result;
			}
			$results[] = $item_result;
		}

		return self::finalize_blocks_update(
			$post_id,
			$blocks,
			$dry_run,
			'batch',
			[
				'bundle_name' => self::sanitize_bundle_name( $bundle_name ),
				'operations_count' => count( $normalized_operations ),
				'operations' => $results,
			],
			$before_summary,
			$confirmation,
			$response_mode,
			$operation,
			$idempotency_key
		);
	}

	private static function apply_batch_operation( $post_id, array &$blocks, array $operation, $index ) {
		$action = $operation['action'] ?? '';

		if ( 'insert' === $action ) {
			$result = self::apply_batch_insert_operation( $blocks, $operation );
		} elseif ( 'update' === $action ) {
			$result = self::apply_batch_update_operation( $blocks, $operation );
		} elseif ( 'remove' === $action ) {
			$result = self::apply_batch_remove_operation( $blocks, $operation );
		} else {
			$result = new WP_Error( 'sae_batch_invalid_action', 'Unsupported batch action.', [ 'status' => 400 ] );
		}

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( ! is_array( $data ) ) {
				$data = [];
			}
			$data['operation_index'] = $index;
			$data['post_id'] = $post_id;
			$data['operation'] = $operation;
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), $data );
		}

		$result['index'] = $index;
		return $result;
	}

	private static function apply_batch_insert_operation( array &$blocks, array $operation ) {
		$block_name = self::normalize_block_name( $operation['block_name'] ?? '' );
		$fields = is_array( $operation['fields'] ?? null ) ? $operation['fields'] : [];
		$position = is_array( $operation['position'] ?? null ) ? $operation['position'] : [];
		$parent_path = self::normalize_index_path( $operation['parent_path'] ?? [] );
		$allowed_blocks = self::get_allowed_block_types();

		if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
			return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
		}

		$policy = self::get_manifest_policy( $block_name );
		if ( ! self::policy_allows_operation( $policy, 'insert' ) ) {
			return new WP_Error( 'sae_policy_op_not_allowed', 'Insert is disabled for this block type.', [ 'status' => 403 ] );
		}
		if ( is_array( $policy ) && ! empty( $policy['safe_defaults'] ) ) {
			$fields = array_replace_recursive( $policy['safe_defaults'], $fields );
		}
		$fields = self::apply_manifest_aliases( $fields, $policy );

		$schema = self::get_block_schema( $block_name );
		if ( empty( $schema ) ) {
			return new WP_Error( 'sae_block_schema_missing', 'No schema found for this block.', [ 'status' => 400 ] );
		}

		$max_per_page = is_array( $policy ) ? ( $policy['max_per_page'] ?? null ) : null;
		if ( is_int( $max_per_page ) && $max_per_page > 0 ) {
			$current_count = self::count_blocks_by_name( $blocks, $block_name );
			if ( $current_count >= $max_per_page ) {
				return new WP_Error(
					'sae_policy_max_per_page',
					sprintf( 'Block type "%s" reached max_per_page (%d).', $block_name, $max_per_page ),
					[
						'status' => 400,
						'block_name' => $block_name,
						'max_per_page' => $max_per_page,
						'current_count' => $current_count,
					]
				);
			}
		}

		$parent_block_name = self::get_parent_block_name( $blocks, $parent_path );
		if ( is_wp_error( $parent_block_name ) ) {
			return $parent_block_name;
		}
		if ( ! self::policy_allows_parent( $policy, $parent_block_name ) ) {
			return new WP_Error(
				'sae_policy_parent_not_allowed',
				sprintf( 'Insert parent "%s" is not allowed for block type "%s".', $parent_block_name, $block_name ),
				[
					'status' => 400,
					'block_name' => $block_name,
					'parent' => $parent_block_name,
				]
			);
		}

		$contract_errors = [];
		$contract_valid = self::validate_manifest_field_contracts( $fields, $policy, true, $contract_errors );
		if ( ! $contract_valid ) {
			return new WP_Error( 'sae_policy_required_fields', 'Field contract validation failed.', [ 'status' => 400, 'errors' => $contract_errors ] );
		}

		$errors = [];
		$validated = self::validate_fields( $schema, $fields, $errors, true );
		if ( ! $validated ) {
			return new WP_Error( 'sae_invalid_fields', 'Field validation failed.', [ 'status' => 400, 'errors' => $errors ] );
		}

		if ( self::is_core_block( $block_name ) ) {
			$new_block = self::build_core_block( $block_name, $fields );
		} else {
			$acf_data = self::build_acf_data( $schema, $fields );
			$new_block = self::build_block( $block_name, $acf_data );
		}
		if ( is_wp_error( $new_block ) ) {
			return $new_block;
		}
		$type = isset( $position['type'] ) ? sanitize_text_field( (string) $position['type'] ) : 'append';
		$target = is_array( $position['target'] ?? null ) ? $position['target'] : [];

		$target_parent =& $blocks;
		if ( ! empty( $parent_path ) ) {
			$parent_result = self::get_parent_by_path( $blocks, $parent_path );
			if ( is_wp_error( $parent_result ) ) {
				return $parent_result;
			}
			$target_parent =& $parent_result['parent'];
		}

		if ( ! isset( $target_parent ) || ! is_array( $target_parent ) ) {
			return new WP_Error( 'sae_invalid_parent', 'Parent block path is invalid.', [ 'status' => 400 ] );
		}

		$target_path = null;
		if ( in_array( $type, [ 'before', 'after' ], true ) ) {
			$target_result = self::find_target_block( $blocks, $target );
			if ( is_wp_error( $target_result ) ) {
				return $target_result;
			}
			$target_parent =& $target_result['parent'];
			$target_index = $target_result['index'];
			$insert_at = ( 'before' === $type ) ? $target_index : $target_index + 1;
			array_splice( $target_parent, $insert_at, 0, [ $new_block ] );
			$target_path = $target_result['path'];
		} elseif ( 'prepend' === $type ) {
			array_unshift( $target_parent, $new_block );
		} else {
			$target_parent[] = $new_block;
			$type = 'append';
		}

		return [
			'action' => 'insert',
			'block_name' => $block_name,
			'position' => $type,
			'parent_path' => $parent_path,
			'target' => $target_path,
		];
	}

	private static function apply_batch_update_operation( array &$blocks, array $operation ) {
		$target = is_array( $operation['target'] ?? null ) ? $operation['target'] : [];
		$fields = is_array( $operation['fields'] ?? null ) ? $operation['fields'] : [];
		$target_result = self::find_target_block( $blocks, $target );
		if ( is_wp_error( $target_result ) ) {
			return $target_result;
		}

		$block =& $target_result['parent'][ $target_result['index'] ];
		$block_name = $block['blockName'] ?? '';
		$allowed_blocks = self::get_allowed_block_types();
		if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
			return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
		}

		$schema = self::get_block_schema( $block_name );
		if ( empty( $schema ) ) {
			return new WP_Error( 'sae_block_schema_missing', 'No schema found for this block.', [ 'status' => 400 ] );
		}

		$policy = self::get_manifest_policy( $block_name );
		if ( ! self::policy_allows_operation( $policy, 'update' ) ) {
			return new WP_Error( 'sae_policy_op_not_allowed', 'Update is disabled for this block type.', [ 'status' => 403 ] );
		}
		$fields = self::apply_manifest_aliases( $fields, $policy );
		$field_lock_errors = [];
		$field_lock_valid = self::validate_locked_fields( $fields, $policy, $field_lock_errors );
		if ( ! $field_lock_valid ) {
			return new WP_Error( 'sae_field_locked', 'Field locking policy violated.', [ 'status' => 403, 'errors' => $field_lock_errors ] );
		}

		$contract_errors = [];
		$contract_valid = self::validate_manifest_field_contracts( $fields, $policy, false, $contract_errors );
		if ( ! $contract_valid ) {
			return new WP_Error( 'sae_policy_required_fields', 'Field contract validation failed.', [ 'status' => 400, 'errors' => $contract_errors ] );
		}

		$errors = [];
		$validated = self::validate_fields( $schema, $fields, $errors, false );
		if ( ! $validated ) {
			return new WP_Error( 'sae_invalid_fields', 'Field validation failed.', [ 'status' => 400, 'errors' => $errors ] );
		}

		if ( self::is_core_block( $block_name ) ) {
			$core_update = self::apply_core_block_update( $block, $fields );
			if ( is_wp_error( $core_update ) ) {
				return $core_update;
			}
		} else {
			$existing_data = isset( $block['attrs']['data'] ) && is_array( $block['attrs']['data'] ) ? $block['attrs']['data'] : [];
			$updated_data = array_merge( $existing_data, self::build_acf_data( $schema, $fields ) );
			$block['attrs']['data'] = $updated_data;
		}

		return [
			'action' => 'update',
			'block_name' => $block_name,
			'target' => $target_result['path'],
			'targeting_warning' => ( ! empty( $target['index_path'] ) && is_array( $target['index_path'] ) )
				? 'index_path may shift after inserts/removes; prefer anchor or block_id.'
				: null,
		];
	}

	private static function apply_batch_remove_operation( array &$blocks, array $operation ) {
		$target = is_array( $operation['target'] ?? null ) ? $operation['target'] : [];
		$remove_all = ! empty( $operation['remove_all'] );
		$block_name = self::normalize_block_name( $operation['block_name'] ?? '' );
		$removed = 0;
		$target_path = null;
		$allowed_blocks = self::get_allowed_block_types();

		if ( $remove_all ) {
			if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
				return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
			}
			$policy = self::get_manifest_policy( $block_name );
			if ( ! self::policy_allows_operation( $policy, 'remove' ) ) {
				return new WP_Error( 'sae_policy_op_not_allowed', 'Remove is disabled for this block type.', [ 'status' => 403 ] );
			}
			$blocks = self::remove_blocks_by_name( $blocks, $block_name, $removed );
		} else {
			$target_result = self::find_target_block( $blocks, $target );
			if ( is_wp_error( $target_result ) ) {
				return $target_result;
			}
			$target_block_name = $target_result['parent'][ $target_result['index'] ]['blockName'] ?? '';
			if ( empty( $allowed_blocks ) || ! in_array( $target_block_name, $allowed_blocks, true ) ) {
				return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
			}
			$policy = self::get_manifest_policy( $target_block_name );
			if ( ! self::policy_allows_operation( $policy, 'remove' ) ) {
				return new WP_Error( 'sae_policy_op_not_allowed', 'Remove is disabled for this block type.', [ 'status' => 403 ] );
			}
			array_splice( $target_result['parent'], $target_result['index'], 1 );
			$block_name = $target_block_name;
			$removed = 1;
			$target_path = $target_result['path'];
		}

		return [
			'action' => 'remove',
			'block_name' => $block_name,
			'removed' => $removed,
			'target' => $target_path,
			'targeting_warning' => ( ! $remove_all && ! empty( $target['index_path'] ) && is_array( $target['index_path'] ) )
				? 'index_path may shift after inserts/removes; prefer anchor or block_id.'
				: null,
		];
	}

	private static function apply_insert( $post_id, $block_name, array $fields, array $position, array $parent_path, $dry_run, $confirmation_token = '', $idempotency_key = '', array $response_mode = [], $origin = 'rest', array $context = [] ) {
		$permission = self::ensure_write_allowed( $post_id, ! $dry_run );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'sae_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}

		$block_name = self::normalize_block_name( $block_name );
		$policy = self::get_manifest_policy( $block_name );
		if ( ! self::policy_allows_operation( $policy, 'insert' ) ) {
			return new WP_Error( 'sae_policy_op_not_allowed', 'Insert is disabled for this block type.', [ 'status' => 403 ] );
		}
		if ( is_array( $policy ) && ! empty( $policy['safe_defaults'] ) ) {
			$fields = array_replace_recursive( $policy['safe_defaults'], $fields );
		}
		$fields = self::apply_manifest_aliases( $fields, $policy );

		$operation = self::build_operation_payload(
			'insert',
			$post_id,
			[
				'block_name' => $block_name,
				'fields' => $fields,
				'position' => $position,
				'parent_path' => $parent_path,
			]
		);
		$confirmation = [];
		if ( $dry_run ) {
			$confirmation = self::issue_confirmation_token( $operation, $post_id, $origin, $context );
		} else {
			$confirmed = self::ensure_write_confirmation( $confirmation_token, $operation, $post_id, $context );
			if ( is_wp_error( $confirmed ) ) {
				return $confirmed;
			}
		}

		$blocks = self::get_parsed_blocks( $post_id );
		$before_summary = self::summarize_blocks( $blocks );
		$allowed_blocks = self::get_allowed_block_types();

		if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
			return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
		}

		$schema = self::get_block_schema( $block_name );
		if ( empty( $schema ) ) {
			return new WP_Error( 'sae_block_schema_missing', 'No schema found for this block.', [ 'status' => 400 ] );
		}

		$max_per_page = is_array( $policy ) ? ( $policy['max_per_page'] ?? null ) : null;
		if ( is_int( $max_per_page ) && $max_per_page > 0 ) {
			$current_count = self::count_blocks_by_name( $blocks, $block_name );
			if ( $current_count >= $max_per_page ) {
				return new WP_Error(
					'sae_policy_max_per_page',
					sprintf( 'Block type "%s" reached max_per_page (%d).', $block_name, $max_per_page ),
					[
						'status' => 400,
						'block_name' => $block_name,
						'max_per_page' => $max_per_page,
						'current_count' => $current_count,
					]
				);
			}
		}

		$parent_block_name = self::get_parent_block_name( $blocks, $parent_path );
		if ( is_wp_error( $parent_block_name ) ) {
			return $parent_block_name;
		}
		if ( ! self::policy_allows_parent( $policy, $parent_block_name ) ) {
			return new WP_Error(
				'sae_policy_parent_not_allowed',
				sprintf( 'Insert parent "%s" is not allowed for block type "%s".', $parent_block_name, $block_name ),
				[
					'status' => 400,
					'block_name' => $block_name,
					'parent' => $parent_block_name,
				]
			);
		}

		$contract_errors = [];
		$contract_valid = self::validate_manifest_field_contracts( $fields, $policy, true, $contract_errors );
		if ( ! $contract_valid ) {
			return new WP_Error( 'sae_policy_required_fields', 'Field contract validation failed.', [ 'status' => 400, 'errors' => $contract_errors ] );
		}

		$errors = [];
		$validated = self::validate_fields( $schema, $fields, $errors, true );
		if ( ! $validated ) {
			return new WP_Error( 'sae_invalid_fields', 'Field validation failed.', [ 'status' => 400, 'errors' => $errors ] );
		}

		if ( self::is_core_block( $block_name ) ) {
			$new_block = self::build_core_block( $block_name, $fields );
		} else {
			$acf_data = self::build_acf_data( $schema, $fields );
			$new_block = self::build_block( $block_name, $acf_data );
		}
		if ( is_wp_error( $new_block ) ) {
			return $new_block;
		}

		$target_parent =& $blocks;
		if ( ! empty( $parent_path ) ) {
			$parent_result = self::get_parent_by_path( $blocks, $parent_path );
			if ( is_wp_error( $parent_result ) ) {
				return $parent_result;
			}
			$target_parent =& $parent_result['parent'];
		}

		if ( ! isset( $target_parent ) || ! is_array( $target_parent ) ) {
			return new WP_Error( 'sae_invalid_parent', 'Parent block path is invalid.', [ 'status' => 400 ] );
		}

		$type = isset( $position['type'] ) ? sanitize_text_field( $position['type'] ) : 'append';
		$target = is_array( $position['target'] ?? null ) ? $position['target'] : [];

		if ( in_array( $type, [ 'before', 'after' ], true ) ) {
			$target_result = self::find_target_block( $blocks, $target );
			if ( is_wp_error( $target_result ) ) {
				return $target_result;
			}
			$target_parent =& $target_result['parent'];
			$target_index = $target_result['index'];
			$insert_at = ( 'before' === $type ) ? $target_index : $target_index + 1;
			array_splice( $target_parent, $insert_at, 0, [ $new_block ] );
		} elseif ( 'prepend' === $type ) {
			array_unshift( $target_parent, $new_block );
		} else {
			$target_parent[] = $new_block;
		}

		return self::finalize_blocks_update(
			$post_id,
			$blocks,
			$dry_run,
			'insert',
			[
				'block_name' => $block_name,
				'position' => $type,
			],
			$before_summary,
			$confirmation,
			$response_mode,
			$operation,
			$idempotency_key
		);
	}

	private static function apply_update( $post_id, array $target, array $fields, $dry_run, $confirmation_token = '', $idempotency_key = '', array $response_mode = [], $origin = 'rest', array $context = [] ) {
		$permission = self::ensure_write_allowed( $post_id, ! $dry_run );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'sae_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}

		$operation = self::build_operation_payload(
			'update',
			$post_id,
			[
				'target' => $target,
				'fields' => $fields,
			]
		);
		$confirmation = [];
		if ( $dry_run ) {
			$confirmation = self::issue_confirmation_token( $operation, $post_id, $origin, $context );
		} else {
			$confirmed = self::ensure_write_confirmation( $confirmation_token, $operation, $post_id, $context );
			if ( is_wp_error( $confirmed ) ) {
				return $confirmed;
			}
		}

		$blocks = self::get_parsed_blocks( $post_id );
		$before_summary = self::summarize_blocks( $blocks );
		$target_result = self::find_target_block( $blocks, $target );
		if ( is_wp_error( $target_result ) ) {
			return $target_result;
		}

		$block =& $target_result['parent'][ $target_result['index'] ];
		$block_name = $block['blockName'] ?? '';

		$allowed_blocks = self::get_allowed_block_types();
		if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
			return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
		}

		$schema = self::get_block_schema( $block_name );
		if ( empty( $schema ) ) {
			return new WP_Error( 'sae_block_schema_missing', 'No schema found for this block.', [ 'status' => 400 ] );
		}

		$policy = self::get_manifest_policy( $block_name );
		if ( ! self::policy_allows_operation( $policy, 'update' ) ) {
			return new WP_Error( 'sae_policy_op_not_allowed', 'Update is disabled for this block type.', [ 'status' => 403 ] );
		}
		$fields = self::apply_manifest_aliases( $fields, $policy );
		$field_lock_errors = [];
		$field_lock_valid = self::validate_locked_fields( $fields, $policy, $field_lock_errors );
		if ( ! $field_lock_valid ) {
			return new WP_Error( 'sae_field_locked', 'Field locking policy violated.', [ 'status' => 403, 'errors' => $field_lock_errors ] );
		}

		$contract_errors = [];
		$contract_valid = self::validate_manifest_field_contracts( $fields, $policy, false, $contract_errors );
		if ( ! $contract_valid ) {
			return new WP_Error( 'sae_policy_required_fields', 'Field contract validation failed.', [ 'status' => 400, 'errors' => $contract_errors ] );
		}

		$errors = [];
		$validated = self::validate_fields( $schema, $fields, $errors, false );
		if ( ! $validated ) {
			return new WP_Error( 'sae_invalid_fields', 'Field validation failed.', [ 'status' => 400, 'errors' => $errors ] );
		}

		if ( self::is_core_block( $block_name ) ) {
			$core_update = self::apply_core_block_update( $block, $fields );
			if ( is_wp_error( $core_update ) ) {
				return $core_update;
			}
		} else {
			$existing_data = isset( $block['attrs']['data'] ) && is_array( $block['attrs']['data'] ) ? $block['attrs']['data'] : [];
			$updated_data = array_merge( $existing_data, self::build_acf_data( $schema, $fields ) );
			$block['attrs']['data'] = $updated_data;
		}

		return self::finalize_blocks_update(
			$post_id,
			$blocks,
			$dry_run,
			'update',
			[
				'block_name' => $block_name,
				'target' => $target_result['path'],
				'targeting_warning' => ( ! empty( $target['index_path'] ) && is_array( $target['index_path'] ) )
					? 'index_path may shift after inserts/removes; prefer anchor or block_id.'
					: null,
			],
			$before_summary,
			$confirmation,
			$response_mode,
			$operation,
			$idempotency_key
		);
	}

	private static function apply_remove( $post_id, array $target, $block_name, $remove_all, $dry_run, $confirmation_token = '', $idempotency_key = '', array $response_mode = [], $origin = 'rest', array $context = [] ) {
		$permission = self::ensure_write_allowed( $post_id, ! $dry_run );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'sae_post_missing', 'Post not found.', [ 'status' => 404 ] );
		}

		$block_name = self::normalize_block_name( $block_name );
		$operation = self::build_operation_payload(
			'remove',
			$post_id,
			[
				'target' => $target,
				'block_name' => $block_name,
				'remove_all' => (bool) $remove_all,
			]
		);
		$confirmation = [];
		if ( $dry_run ) {
			$confirmation = self::issue_confirmation_token( $operation, $post_id, $origin, $context );
		} else {
			$confirmed = self::ensure_write_confirmation( $confirmation_token, $operation, $post_id, $context );
			if ( is_wp_error( $confirmed ) ) {
				return $confirmed;
			}
		}

		$blocks = self::get_parsed_blocks( $post_id );
		$before_summary = self::summarize_blocks( $blocks );
		$removed = 0;
		$target_path = null;
		$allowed_blocks = self::get_allowed_block_types();

		if ( $remove_all ) {
			if ( empty( $allowed_blocks ) || ! in_array( $block_name, $allowed_blocks, true ) ) {
				return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
			}
			$policy = self::get_manifest_policy( $block_name );
			if ( ! self::policy_allows_operation( $policy, 'remove' ) ) {
				return new WP_Error( 'sae_policy_op_not_allowed', 'Remove is disabled for this block type.', [ 'status' => 403 ] );
			}

			$blocks = self::remove_blocks_by_name( $blocks, $block_name, $removed );
		} else {
			$target_result = self::find_target_block( $blocks, $target );
			if ( is_wp_error( $target_result ) ) {
				return $target_result;
			}
			$target_block_name = $target_result['parent'][ $target_result['index'] ]['blockName'] ?? '';
			if ( empty( $allowed_blocks ) || ! in_array( $target_block_name, $allowed_blocks, true ) ) {
				return new WP_Error( 'sae_block_not_allowed', 'Block type is not allowlisted.', [ 'status' => 403 ] );
			}
			$policy = self::get_manifest_policy( $target_block_name );
			if ( ! self::policy_allows_operation( $policy, 'remove' ) ) {
				return new WP_Error( 'sae_policy_op_not_allowed', 'Remove is disabled for this block type.', [ 'status' => 403 ] );
			}
			array_splice( $target_result['parent'], $target_result['index'], 1 );
			$block_name = $target_block_name;
			$removed = 1;
			$target_path = $target_result['path'];
		}

		return self::finalize_blocks_update(
			$post_id,
			$blocks,
			$dry_run,
			'remove',
			[
				'block_name' => $block_name,
				'removed' => $removed,
				'target' => $target_path,
				'targeting_warning' => ( ! $remove_all && ! empty( $target['index_path'] ) && is_array( $target['index_path'] ) )
					? 'index_path may shift after inserts/removes; prefer anchor or block_id.'
					: null,
			],
			$before_summary,
			$confirmation,
			$response_mode,
			$operation,
			$idempotency_key
		);
	}

	private static function finalize_blocks_update( $post_id, array $blocks, $dry_run, $action, array $meta, array $before_summary = [], array $confirmation = [], array $response_mode = [], array $operation = [], $idempotency_key = '' ) {
		$before_content = (string) get_post_field( 'post_content', $post_id );
		$serialized = serialize_blocks( $blocks );
		$after_summary = self::summarize_blocks( $blocks );
		$before_count = count( $before_summary );
		$after_count = count( $after_summary );
		$before_total_count = self::count_summary_blocks_recursive( $before_summary );
		$after_total_count = self::count_blocks_recursive( $blocks );
		if ( $after_total_count > self::MAX_BLOCKS ) {
			return new WP_Error(
				'sae_block_limit',
				sprintf( 'Block limit exceeded (%d).', self::MAX_BLOCKS ),
				[
					'status' => 400,
					'max_blocks' => self::MAX_BLOCKS,
					'block_count' => $after_total_count,
					'top_level_block_count' => $after_count,
				]
			);
		}
		$compact = array_key_exists( 'compact', $response_mode ) ? (bool) $response_mode['compact'] : true;
		$verbose = ! empty( $response_mode['verbose'] );
		$include_blocks_after = ! $compact || $verbose;

		$result = [
			'post_id' => $post_id,
			'action' => $action,
			'dry_run' => (bool) $dry_run,
			'block_count' => $after_count,
			'total_block_count' => $after_total_count,
			'change' => [
				'before_count' => $before_count,
				'after_count' => $after_count,
				'delta' => $after_count - $before_count,
				'before_total_count' => $before_total_count,
				'after_total_count' => $after_total_count,
				'total_delta' => $after_total_count - $before_total_count,
				'target' => $meta['target'] ?? null,
				'removed' => $meta['removed'] ?? null,
			],
			'meta' => $meta,
		];
		if ( $include_blocks_after ) {
			$result['blocks_after'] = $after_summary;
		}
		if ( $dry_run && ! empty( $confirmation ) ) {
			$result['confirmation'] = $confirmation;
		}

		if ( $dry_run ) {
			$result['serialized_content'] = $serialized;
			self::audit_log( 'dry_run_' . $action, array_merge( [ 'post_id' => $post_id ], $meta ) );
			return $result;
		}

		$idempotent_result = self::get_idempotent_response( $idempotency_key, $operation );
		if ( is_wp_error( $idempotent_result ) ) {
			return $idempotent_result;
		}
		if ( is_array( $idempotent_result ) ) {
			self::audit_log( 'idempotent_replay_' . $action, array_merge( [ 'post_id' => $post_id ], $meta, [ 'idempotency_key' => $idempotency_key ] ) );
			return $idempotent_result;
		}

		$update = wp_update_post(
			[
				'ID' => $post_id,
				'post_content' => wp_slash( $serialized ),
			],
			true
		);

		if ( is_wp_error( $update ) ) {
			return $update;
		}

		self::invalidate_parsed_blocks_cache( $post_id );

		$result = self::with_idempotency_meta( $result, $idempotency_key, false );
		self::store_idempotent_response( $idempotency_key, $operation, $result );

		self::audit_log(
			$action,
			array_merge(
				[ 'post_id' => $post_id ],
				$meta,
				[ 'idempotency_key' => $idempotency_key ],
				[
					'before_excerpt' => self::audit_excerpt( $before_content ),
					'after_excerpt' => self::audit_excerpt( $serialized ),
				]
			)
		);
		return $result;
	}

	private static function resolve_response_mode( array $payload ) {
		$compact = array_key_exists( 'compact', $payload ) ? (bool) $payload['compact'] : true;
		$verbose = ! empty( $payload['verbose'] );
		if ( $verbose ) {
			$compact = false;
		}

		return [
			'compact' => $compact,
			'verbose' => $verbose,
		];
	}

	private static function get_block_schema( $block_name ) {
		$block_name = self::normalize_block_name( $block_name );
		if ( self::is_core_block( $block_name ) ) {
			return self::get_core_block_schema( $block_name );
		}

		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		$field_groups = acf_get_field_groups( [ 'block' => $block_name ] );
		$schema = [];

		foreach ( $field_groups as $group ) {
			$group_fields = acf_get_fields( $group['key'] );
			if ( is_array( $group_fields ) ) {
				foreach ( $group_fields as $field ) {
					if ( ! empty( $field['name'] ) ) {
						$schema[ $field['name'] ] = $field;
					}
				}
			}
		}

		return $schema;
	}

	private static function parse_single_block_markup( $markup, $expected_block_name = '' ) {
		$markup = (string) $markup;
		if ( '' === trim( $markup ) ) {
			return new WP_Error( 'sae_block_schema_missing', 'Generated block markup is empty.', [ 'status' => 400 ] );
		}

		$parsed = parse_blocks( $markup );
		if ( ! is_array( $parsed ) || 1 !== count( $parsed ) ) {
			return new WP_Error( 'sae_block_schema_missing', 'Generated block markup did not parse into a single block.', [ 'status' => 400 ] );
		}

		$block = $parsed[0];
		if ( ! empty( $expected_block_name ) && (string) ( $block['blockName'] ?? '' ) !== $expected_block_name ) {
			return new WP_Error( 'sae_block_schema_missing', 'Generated block markup parsed into the wrong block type.', [ 'status' => 400 ] );
		}

		return $block;
	}

	private static function build_core_comment_attrs( array $attrs ) {
		if ( empty( $attrs ) ) {
			return '';
		}

		$encoded = wp_json_encode( $attrs );
		return false !== $encoded ? ' ' . $encoded : '';
	}

	private static function extract_acf_block_fields( array $block ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
		$data = isset( $attrs['data'] ) && is_array( $attrs['data'] ) ? $attrs['data'] : [];
		if ( empty( $data ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $data as $name => $value ) {
			$name = sanitize_key( (string) $name );
			if ( '' === $name || 0 === strpos( $name, '_' ) ) {
				continue;
			}
			$normalized[ $name ] = $value;
		}

		return $normalized;
	}

	private static function extract_nested_block_definitions( array $blocks ) {
		$definitions = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_name = self::normalize_block_name( $block['blockName'] ?? '' );
			if ( '' === $block_name || ! in_array( $block_name, self::get_allowed_block_types(), true ) ) {
				continue;
			}

			$fields = self::is_core_block( $block_name )
				? self::extract_core_block_fields( $block )
				: self::extract_acf_block_fields( $block );

			$definitions[] = [
				'block_name' => $block_name,
				'fields' => $fields,
			];
		}

		return $definitions;
	}

	private static function nested_blocks_are_fully_allowlisted( array $blocks ) {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				return false;
			}

			$block_name = self::normalize_block_name( $block['blockName'] ?? '' );
			if ( '' === $block_name || ! in_array( $block_name, self::get_allowed_block_types(), true ) ) {
				return false;
			}

			$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			if ( ! empty( $inner_blocks ) && ! self::nested_blocks_are_fully_allowlisted( $inner_blocks ) ) {
				return false;
			}
		}

		return true;
	}

	private static function build_nested_blocks_from_definitions( array $definitions ) {
		$blocks = [];

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$block_name = self::normalize_block_name( $definition['block_name'] ?? '' );
			if ( '' === $block_name ) {
				continue;
			}

			$fields = is_array( $definition['fields'] ?? null ) ? $definition['fields'] : [];

			if ( self::is_core_block( $block_name ) ) {
				$block = self::build_core_block( $block_name, $fields );
			} else {
				$schema = self::get_block_schema( $block_name );
				if ( empty( $schema ) ) {
					return new WP_Error( 'sae_block_schema_missing', 'No schema found for nested block.', [ 'status' => 400, 'block_name' => $block_name ] );
				}
				$acf_data = self::build_acf_data( $schema, $fields );
				$block = self::build_block( $block_name, $acf_data );
			}

			if ( is_wp_error( $block ) ) {
				return $block;
			}

			$blocks[] = $block;
		}

		return $blocks;
	}

	private static function extract_core_block_fields( array $block ) {
		$block_name = (string) ( $block['blockName'] ?? '' );
		$inner_html = (string) ( $block['innerHTML'] ?? '' );
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];

		if ( 'core/paragraph' === $block_name ) {
			if ( preg_match( '#<p[^>]*>(.*?)</p>#is', $inner_html, $matches ) ) {
				return [ 'content' => trim( (string) $matches[1] ) ];
			}
			return [ 'content' => trim( wp_kses_post( $inner_html ) ) ];
		}

		if ( 'core/heading' === $block_name ) {
			$level = absint( $attrs['level'] ?? 2 );
			if ( $level < 1 || $level > 6 ) {
				$level = 2;
			}
			if ( preg_match( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $inner_html, $matches ) ) {
				return [
					'content' => trim( (string) $matches[1] ),
					'level' => $level,
				];
			}
			return [
				'content' => trim( wp_kses_post( $inner_html ) ),
				'level' => $level,
			];
		}

		if ( 'core/list' === $block_name ) {
			$items = [];
			if ( preg_match_all( '#<li[^>]*>(.*?)</li>#is', $inner_html, $matches ) && ! empty( $matches[1] ) ) {
				foreach ( $matches[1] as $item_html ) {
					$items[] = [
						'content' => trim( (string) $item_html ),
					];
				}
			}
			return [
				'items' => $items,
				'ordered' => ! empty( $attrs['ordered'] ),
			];
		}

		if ( 'core/image' === $block_name ) {
			$image_id = absint( $attrs['id'] ?? 0 );
			$url = '';
			$alt = sanitize_text_field( (string) ( $attrs['alt'] ?? '' ) );
			$caption = '';
			$size_slug = sanitize_key( (string) ( $attrs['sizeSlug'] ?? '' ) );

			if ( preg_match( '#<img[^>]*src=["\']([^"\']+)["\'][^>]*>#is', $inner_html, $src_matches ) ) {
				$url = esc_url_raw( html_entity_decode( (string) $src_matches[1], ENT_QUOTES, 'UTF-8' ) );
			}
			if ( '' === $alt && preg_match( '#<img[^>]*alt=["\']([^"\']*)["\'][^>]*>#is', $inner_html, $alt_matches ) ) {
				$alt = sanitize_text_field( html_entity_decode( (string) $alt_matches[1], ENT_QUOTES, 'UTF-8' ) );
			}
			if ( preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#is', $inner_html, $caption_matches ) ) {
				$caption = trim( (string) $caption_matches[1] );
			}
			if ( 0 === $image_id && preg_match( '#wp-image-(\d+)#i', $inner_html, $id_matches ) ) {
				$image_id = absint( $id_matches[1] );
			}

			return [
				'id' => $image_id,
				'url' => $url,
				'alt' => $alt,
				'caption' => $caption,
				'size_slug' => $size_slug,
			];
		}

		if ( 'core/quote' === $block_name ) {
			$content = '';
			$citation = '';
			if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', $inner_html, $content_matches ) && ! empty( $content_matches[1] ) ) {
				$paragraphs = array_values(
					array_filter(
						array_map(
							static function ( $paragraph ) {
								return trim( (string) $paragraph );
							},
							$content_matches[1]
						),
						static function ( $paragraph ) {
							return '' !== $paragraph;
						}
					)
				);
				$content = implode( "\n\n", $paragraphs );
			}
			if ( preg_match( '#<cite[^>]*>(.*)</cite>#is', $inner_html, $cite_matches ) ) {
				$citation = trim( (string) $cite_matches[1] );
			}
			return [
				'content' => $content,
				'citation' => $citation,
			];
		}

		if ( 'core/button' === $block_name ) {
			$text = '';
			$url = '';
			if ( preg_match( '#<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)</a>#is', $inner_html, $matches ) ) {
				$url = esc_url_raw( html_entity_decode( (string) $matches[1], ENT_QUOTES, 'UTF-8' ) );
				$text = trim( wp_strip_all_tags( (string) $matches[2] ) );
			} else {
				$text = trim( wp_strip_all_tags( $inner_html ) );
			}
			if ( '' === $text ) {
				$text = sanitize_text_field( (string) ( $attrs['text'] ?? '' ) );
			}
			if ( '' === $url ) {
				$url = esc_url_raw( (string) ( $attrs['url'] ?? '' ) );
			}
			return [
				'text' => $text,
				'url' => $url,
			];
		}

		if ( 'core/buttons' === $block_name ) {
			$buttons = [];
			$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			foreach ( $inner_blocks as $child_block ) {
				if ( ! is_array( $child_block ) || 'core/button' !== (string) ( $child_block['blockName'] ?? '' ) ) {
					continue;
				}
				$child_fields = self::extract_core_block_fields( $child_block );
				if ( ! empty( $child_fields ) ) {
					$buttons[] = [
						'text' => sanitize_text_field( (string) ( $child_fields['text'] ?? '' ) ),
						'url' => esc_url_raw( (string) ( $child_fields['url'] ?? '' ) ),
					];
				}
			}
			return [
				'buttons' => $buttons,
			];
		}

		if ( 'core/group' === $block_name || 'core/column' === $block_name ) {
			return [
				'inner_blocks' => self::extract_nested_block_definitions(
					isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : []
				),
			];
		}

		if ( 'core/columns' === $block_name ) {
			$columns = [];
			$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			foreach ( $inner_blocks as $child_block ) {
				if ( ! is_array( $child_block ) || 'core/column' !== (string) ( $child_block['blockName'] ?? '' ) ) {
					continue;
				}
				$columns[] = [
					'inner_blocks' => self::extract_nested_block_definitions(
						isset( $child_block['innerBlocks'] ) && is_array( $child_block['innerBlocks'] ) ? $child_block['innerBlocks'] : []
					),
				];
			}
			return [
				'columns' => $columns,
			];
		}

		if ( 'core/html' === $block_name ) {
			return [ 'html' => $inner_html ];
		}

		return [];
	}

	private static function extract_classes_from_html( $html, $tag_pattern ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return [];
		}

		$pattern = '#<' . $tag_pattern . '[^>]*\sclass=["\']([^"\']+)["\']#i';
		if ( ! preg_match( $pattern, $html, $matches ) ) {
			return [];
		}

		$classes = preg_split( '/\s+/', trim( (string) $matches[1] ) );
		return array_values(
			array_filter(
				array_map( 'sanitize_html_class', is_array( $classes ) ? $classes : [] ),
				static function ( $class_name ) {
					return '' !== $class_name;
				}
			)
		);
	}

	private static function build_core_css_classes( array $attrs, array $existing_classes = [], array $base_classes = [] ) {
		$classes = array_values(
			array_filter(
				array_map(
					'sanitize_html_class',
					array_merge( $base_classes, $existing_classes )
				),
				static function ( $class_name ) {
					return '' !== $class_name;
				}
			)
		);

		$class_name = trim( (string) ( $attrs['className'] ?? '' ) );
		if ( '' !== $class_name ) {
			$classes = array_merge(
				$classes,
				array_values(
					array_filter(
						array_map(
							'sanitize_html_class',
							preg_split( '/\s+/', $class_name )
						),
						static function ( $token ) {
							return '' !== $token;
						}
					)
				)
			);
		}

		$text_align = sanitize_html_class( (string) ( $attrs['textAlign'] ?? '' ) );
		if ( '' !== $text_align ) {
			$classes[] = 'has-text-align-' . $text_align;
		}

		$font_size = sanitize_html_class( (string) ( $attrs['fontSize'] ?? '' ) );
		if ( '' !== $font_size ) {
			$classes[] = 'has-' . $font_size . '-font-size';
		}

		$text_color = sanitize_html_class( (string) ( $attrs['textColor'] ?? '' ) );
		if ( '' !== $text_color ) {
			$classes[] = 'has-' . $text_color . '-color';
		}

		$background_color = sanitize_html_class( (string) ( $attrs['backgroundColor'] ?? '' ) );
		if ( '' !== $background_color ) {
			$classes[] = 'has-' . $background_color . '-background-color';
		}

		return array_values( array_unique( $classes ) );
	}

	private static function render_html_class_attr( array $classes ) {
		if ( empty( $classes ) ) {
			return '';
		}
		return ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
	}

	private static function build_core_block( $block_name, array $fields, array $existing_attrs = [], $existing_inner_html = '' ) {
		$block_name = self::normalize_block_name( $block_name );
		$attrs = is_array( $existing_attrs ) ? $existing_attrs : [];

		if ( 'core/paragraph' === $block_name ) {
			$content = wp_kses_post( (string) ( $fields['content'] ?? '' ) );
			$classes = self::build_core_css_classes(
				$attrs,
				self::extract_classes_from_html( (string) $existing_inner_html, 'p' )
			);
			$html = sprintf( '<p%1$s>%2$s</p>', self::render_html_class_attr( $classes ), $content );
			return [
				'blockName' => $block_name,
				'attrs' => $attrs,
				'innerBlocks' => [],
				'innerHTML' => $html,
				'innerContent' => [ $html ],
			];
		}

		if ( 'core/heading' === $block_name ) {
			$level = absint( $fields['level'] ?? ( $attrs['level'] ?? 2 ) );
			if ( $level < 1 || $level > 6 ) {
				$level = 2;
			}
			if ( 2 === $level ) {
				unset( $attrs['level'] );
			} else {
				$attrs['level'] = $level;
			}
			$content = wp_kses_post( (string) ( $fields['content'] ?? '' ) );
			$tag = 'h' . $level;
			$classes = self::build_core_css_classes(
				$attrs,
				self::extract_classes_from_html( (string) $existing_inner_html, 'h[1-6]' )
			);
			$html = sprintf( '<%1$s%2$s>%3$s</%1$s>', $tag, self::render_html_class_attr( $classes ), $content );
			return [
				'blockName' => $block_name,
				'attrs' => $attrs,
				'innerBlocks' => [],
				'innerHTML' => $html,
				'innerContent' => [ $html ],
			];
		}

		if ( 'core/list' === $block_name ) {
			$items = isset( $fields['items'] ) && is_array( $fields['items'] ) ? $fields['items'] : [];
			$ordered = ! empty( $fields['ordered'] );
			$list_tag = $ordered ? 'ol' : 'ul';
			$list_items_html = implode(
				'',
				array_map(
					static function ( $item ) {
						$content = is_array( $item ) ? wp_kses_post( (string) ( $item['content'] ?? '' ) ) : '';
						return '<li>' . $content . '</li>';
					},
					$items
				)
			);
			$classes = self::build_core_css_classes(
				$attrs,
				self::extract_classes_from_html( (string) $existing_inner_html, $list_tag )
			);
			$html = sprintf( '<%1$s%2$s>%3$s</%1$s>', $list_tag, self::render_html_class_attr( $classes ), $list_items_html );
			if ( $ordered ) {
				$attrs['ordered'] = true;
			} else {
				unset( $attrs['ordered'] );
			}

			return self::parse_single_block_markup(
				sprintf(
					'<!-- wp:list%1$s -->%2$s<!-- /wp:list -->',
					self::build_core_comment_attrs( $attrs ),
					$html
				),
				'core/list'
			);
		}

		if ( 'core/image' === $block_name ) {
			$image_id = absint( $fields['id'] ?? ( $attrs['id'] ?? 0 ) );
			$size_slug = sanitize_key( (string) ( $fields['size_slug'] ?? ( $attrs['sizeSlug'] ?? 'full' ) ) );
			if ( '' === $size_slug ) {
				$size_slug = 'full';
			}

			$url = esc_url_raw( (string) ( $fields['url'] ?? '' ) );
			if ( '' === $url && $image_id > 0 ) {
				$resolved_url = wp_get_attachment_image_url( $image_id, $size_slug );
				if ( $resolved_url ) {
					$url = esc_url_raw( $resolved_url );
				}
			}
			if ( '' === $url ) {
				return new WP_Error( 'sae_invalid_fields', 'Image block requires either an attachment ID or image URL.', [ 'status' => 400 ] );
			}

			$alt = sanitize_text_field( (string) ( $fields['alt'] ?? ( $attrs['alt'] ?? '' ) ) );
			$caption = wp_kses_post( (string) ( $fields['caption'] ?? '' ) );
			$classes = self::build_core_css_classes(
				$attrs,
				self::extract_classes_from_html( (string) $existing_inner_html, 'figure' ),
				[ 'wp-block-image', 'size-' . $size_slug ]
			);
			$image_classes = [];
			if ( $image_id > 0 ) {
				$image_classes[] = 'wp-image-' . $image_id;
				$attrs['id'] = $image_id;
			} else {
				unset( $attrs['id'] );
			}
			$attrs['sizeSlug'] = $size_slug;
			if ( '' !== $alt ) {
				$attrs['alt'] = $alt;
			} else {
				unset( $attrs['alt'] );
			}

			$html = '<figure' . self::render_html_class_attr( $classes ) . '><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"';
			if ( ! empty( $image_classes ) ) {
				$html .= ' class="' . esc_attr( implode( ' ', $image_classes ) ) . '"';
			}
			$html .= ' />';
			if ( '' !== trim( $caption ) ) {
				$html .= '<figcaption>' . $caption . '</figcaption>';
			}
			$html .= '</figure>';

			return self::parse_single_block_markup(
				sprintf(
					'<!-- wp:image%1$s -->%2$s<!-- /wp:image -->',
					self::build_core_comment_attrs( $attrs ),
					$html
				),
				'core/image'
			);
		}

		if ( 'core/quote' === $block_name ) {
			$content = wp_kses_post( (string) ( $fields['content'] ?? '' ) );
			$citation = wp_kses_post( (string) ( $fields['citation'] ?? '' ) );
			$paragraph_html = '';
			if ( preg_match( '#<p[\s>].*</p>#is', $content ) ) {
				$paragraph_html = $content;
			} else {
				$parts = array_values(
					array_filter(
						array_map(
							static function ( $part ) {
								return trim( (string) $part );
							},
							preg_split( '/\R{2,}/u', $content )
						),
						static function ( $part ) {
							return '' !== $part;
						}
					)
				);
				if ( empty( $parts ) ) {
					$parts = [ '' ];
				}
				$paragraph_html = implode(
					'',
					array_map(
						static function ( $part ) {
							return '<p>' . $part . '</p>';
						},
						$parts
					)
				);
			}

			$classes = self::build_core_css_classes(
				$attrs,
				self::extract_classes_from_html( (string) $existing_inner_html, 'blockquote' ),
				[ 'wp-block-quote' ]
			);
			$html = '<blockquote' . self::render_html_class_attr( $classes ) . '>' . $paragraph_html;
			if ( '' !== trim( $citation ) ) {
				$html .= '<cite>' . $citation . '</cite>';
			}
			$html .= '</blockquote>';
			return [
				'blockName' => $block_name,
				'attrs' => $attrs,
				'innerBlocks' => [],
				'innerHTML' => $html,
				'innerContent' => [ $html ],
			];
		}

		if ( 'core/button' === $block_name ) {
			$text = sanitize_text_field( (string) ( $fields['text'] ?? $fields['content'] ?? '' ) );
			if ( '' === trim( $text ) ) {
				$text = 'Learn more';
			}
			$url = esc_url_raw( (string) ( $fields['url'] ?? '#' ) );
			if ( '' === $url ) {
				$url = '#';
			}

			$button_wrapper_classes = self::build_core_css_classes(
				$attrs,
				self::extract_classes_from_html( (string) $existing_inner_html, 'div' ),
				[ 'wp-block-button' ]
			);

			$existing_link_classes = self::extract_classes_from_html( (string) $existing_inner_html, 'a' );
			$link_classes = array_values(
				array_unique(
					array_filter(
						array_merge( [ 'wp-block-button__link' ], $existing_link_classes )
					)
				)
			);

			$attrs['url'] = $url;
			$html = sprintf(
				'<div%1$s><a class="%2$s" href="%3$s">%4$s</a></div>',
				self::render_html_class_attr( $button_wrapper_classes ),
				esc_attr( implode( ' ', $link_classes ) ),
				esc_url( $url ),
				esc_html( $text )
			);
			return [
				'blockName' => $block_name,
				'attrs' => $attrs,
				'innerBlocks' => [],
				'innerHTML' => $html,
				'innerContent' => [ $html ],
			];
		}

		if ( 'core/buttons' === $block_name ) {
			$button_rows = isset( $fields['buttons'] ) && is_array( $fields['buttons'] ) ? $fields['buttons'] : [];
			$child_blocks = [];
			foreach ( $button_rows as $button_row ) {
				$button_fields = is_array( $button_row ) ? $button_row : [];
				$child_block = self::build_core_block( 'core/button', $button_fields );
				if ( is_wp_error( $child_block ) ) {
					return $child_block;
				}
				$child_blocks[] = $child_block;
			}
			$children_markup = ! empty( $child_blocks ) ? serialize_blocks( $child_blocks ) : '';
			$classes = self::build_core_css_classes(
				$attrs,
				self::extract_classes_from_html( (string) $existing_inner_html, 'div' ),
				[ 'wp-block-buttons' ]
			);
			$html = '<div' . self::render_html_class_attr( $classes ) . '>' . $children_markup . '</div>';

			return self::parse_single_block_markup(
				sprintf(
					'<!-- wp:buttons%1$s -->%2$s<!-- /wp:buttons -->',
					self::build_core_comment_attrs( $attrs ),
					$html
				),
				'core/buttons'
			);
		}

		if ( 'core/group' === $block_name || 'core/column' === $block_name ) {
			$inner_definitions = isset( $fields['inner_blocks'] ) && is_array( $fields['inner_blocks'] ) ? $fields['inner_blocks'] : [];
			$child_blocks = self::build_nested_blocks_from_definitions( $inner_definitions );
			if ( is_wp_error( $child_blocks ) ) {
				return $child_blocks;
			}
			$children_markup = ! empty( $child_blocks ) ? serialize_blocks( $child_blocks ) : '';
			$base_classes = 'core/group' === $block_name ? [ 'wp-block-group' ] : [ 'wp-block-column' ];
			$html = '<div' . self::render_html_class_attr(
				self::build_core_css_classes(
					$attrs,
					self::extract_classes_from_html( (string) $existing_inner_html, 'div' ),
					$base_classes
				)
			) . '>' . $children_markup . '</div>';

			return self::parse_single_block_markup(
				sprintf(
					'<!-- wp:%1$s%2$s -->%3$s<!-- /wp:%1$s -->',
					'core/group' === $block_name ? 'group' : 'column',
					self::build_core_comment_attrs( $attrs ),
					$html
				),
				$block_name
			);
		}

		if ( 'core/columns' === $block_name ) {
			$column_rows = isset( $fields['columns'] ) && is_array( $fields['columns'] ) ? $fields['columns'] : [];
			$column_blocks = [];
			foreach ( $column_rows as $column_row ) {
				$column_fields = is_array( $column_row ) ? $column_row : [];
				$column_block = self::build_core_block( 'core/column', $column_fields );
				if ( is_wp_error( $column_block ) ) {
					return $column_block;
				}
				$column_blocks[] = $column_block;
			}
			$children_markup = ! empty( $column_blocks ) ? serialize_blocks( $column_blocks ) : '';
			$html = '<div' . self::render_html_class_attr(
				self::build_core_css_classes(
					$attrs,
					self::extract_classes_from_html( (string) $existing_inner_html, 'div' ),
					[ 'wp-block-columns' ]
				)
			) . '>' . $children_markup . '</div>';

			return self::parse_single_block_markup(
				sprintf(
					'<!-- wp:columns%1$s -->%2$s<!-- /wp:columns -->',
					self::build_core_comment_attrs( $attrs ),
					$html
				),
				'core/columns'
			);
		}

		if ( 'core/html' === $block_name ) {
			$html = wp_kses_post( (string) ( $fields['html'] ?? '' ) );
			return [
				'blockName' => $block_name,
				'attrs' => $attrs,
				'innerBlocks' => [],
				'innerHTML' => $html,
				'innerContent' => [ $html ],
			];
		}

		return new WP_Error( 'sae_block_schema_missing', 'No schema available for this Gutenberg block.', [ 'status' => 400 ] );
	}

	private static function apply_core_block_update( array &$block, array $fields ) {
		$block_name = (string) ( $block['blockName'] ?? '' );
		if ( in_array( $block_name, [ 'core/group', 'core/column', 'core/columns' ], true ) ) {
			$existing_inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			if ( ! self::nested_blocks_are_fully_allowlisted( $existing_inner_blocks ) ) {
				return new WP_Error(
					'sae_nested_block_not_allowed',
					'Container block contains nested blocks outside the current AI allowlist.',
					[
						'status' => 400,
						'block_name' => $block_name,
					]
				);
			}
		}

		$current_fields = self::extract_core_block_fields( $block );
		$merged_fields = array_merge( $current_fields, $fields );
		$rebuilt_block = self::build_core_block(
			$block_name,
			$merged_fields,
			isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [],
			(string) ( $block['innerHTML'] ?? '' )
		);

		if ( is_wp_error( $rebuilt_block ) ) {
			return $rebuilt_block;
		}

		$block = $rebuilt_block;
		return true;
	}

	private static function validate_fields( array $schema, array $fields, array &$errors, $enforce_required = false ) {
		$valid = true;

		if ( $enforce_required ) {
			foreach ( $schema as $name => $field ) {
				if ( ! empty( $field['required'] ) && ! array_key_exists( $name, $fields ) ) {
					$errors[] = sprintf( 'Required field "%s" is missing.', $name );
					$valid = false;
				}
			}
		}

		foreach ( $fields as $name => $value ) {
			if ( ! isset( $schema[ $name ] ) ) {
				$errors[] = sprintf( 'Field "%s" is not allowlisted.', $name );
				$valid = false;
				continue;
			}

			$field = $schema[ $name ];
			$type = $field['type'] ?? '';
			$valid = self::validate_field_type( $type, $value, $field, $errors, $name, $enforce_required ) && $valid;
		}

		return $valid;
	}

	private static function validate_field_type( $type, $value, array $field, array &$errors, $name, $enforce_required = false ) {
		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'url':
			case 'email':
			case 'password':
			case 'color_picker':
			case 'wysiwyg':
				if ( ! is_string( $value ) ) {
					$errors[] = sprintf( 'Field "%s" expects a string.', $name );
					return false;
				}
				return true;
			case 'number':
			case 'range':
				if ( ! is_numeric( $value ) ) {
					$errors[] = sprintf( 'Field "%s" expects a number.', $name );
					return false;
				}
				return true;
			case 'true_false':
				if ( ! is_bool( $value ) && ! in_array( $value, [ 0, 1, '0', '1' ], true ) ) {
					$errors[] = sprintf( 'Field "%s" expects a boolean.', $name );
					return false;
				}
				return true;
			case 'select':
			case 'radio':
			case 'button_group':
			case 'checkbox':
				$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? array_keys( $field['choices'] ) : [];
				$multiple = ! empty( $field['multiple'] );
				$values = $multiple ? (array) $value : [ $value ];
				foreach ( $values as $choice ) {
					if ( ! empty( $choices ) && ! in_array( $choice, $choices, true ) ) {
						$errors[] = sprintf( 'Field "%s" has invalid choice "%s".', $name, $choice );
						return false;
					}
				}
				return true;
			case 'image':
			case 'file':
			case 'gallery':
				if ( ! is_int( $value ) && ! is_array( $value ) ) {
					$errors[] = sprintf( 'Field "%s" expects an attachment ID or object.', $name );
					return false;
				}
				return true;
			case 'link':
				if ( ! is_array( $value ) && ! is_string( $value ) ) {
					$errors[] = sprintf( 'Field "%s" expects a link array or string.', $name );
					return false;
				}
				return true;
			case 'repeater':
				if ( ! is_array( $value ) ) {
					$errors[] = sprintf( 'Field "%s" expects an array of rows.', $name );
					return false;
				}
				$sub_schema = self::index_sub_fields( $field );
				foreach ( $value as $row_index => $row ) {
					if ( ! is_array( $row ) ) {
						$errors[] = sprintf( 'Field "%s" row %d expects an object.', $name, $row_index );
						return false;
					}
					$row_valid = self::validate_fields( $sub_schema, $row, $errors, $enforce_required );
					if ( ! $row_valid ) {
						return false;
					}
				}
				return true;
			case 'group':
				if ( ! is_array( $value ) ) {
					$errors[] = sprintf( 'Field "%s" expects an object.', $name );
					return false;
				}
				$sub_schema = self::index_sub_fields( $field );
				return self::validate_fields( $sub_schema, $value, $errors, $enforce_required );
			case 'blocks':
				if ( ! is_array( $value ) ) {
					$errors[] = sprintf( 'Field "%s" expects an array of block definitions.', $name );
					return false;
				}
				foreach ( $value as $index => $definition ) {
					if ( ! is_array( $definition ) ) {
						$errors[] = sprintf( 'Field "%s" block %d expects an object.', $name, $index );
						return false;
					}
					$block_name = self::normalize_block_name( $definition['block_name'] ?? '' );
					if ( '' === $block_name ) {
						$errors[] = sprintf( 'Field "%s" block %d is missing block_name.', $name, $index );
						return false;
					}
					if ( ! in_array( $block_name, self::get_allowed_block_types(), true ) ) {
						$errors[] = sprintf( 'Field "%s" block %d uses non-allowlisted block "%s".', $name, $index, $block_name );
						return false;
					}
					$block_fields = is_array( $definition['fields'] ?? null ) ? $definition['fields'] : [];
					$block_schema = self::get_block_schema( $block_name );
					if ( empty( $block_schema ) ) {
						$errors[] = sprintf( 'Field "%s" block %d has no schema for "%s".', $name, $index, $block_name );
						return false;
					}
					if ( ! self::validate_fields( $block_schema, $block_fields, $errors, $enforce_required ) ) {
						return false;
					}
				}
				return true;
			default:
				return true;
		}
	}

	private static function build_acf_data( array $schema, array $values ) {
		$data = [];
		foreach ( $values as $name => $value ) {
			if ( ! isset( $schema[ $name ] ) ) {
				continue;
			}
			$field = $schema[ $name ];
			$type = $field['type'] ?? '';
			$data[ $name ] = self::normalize_value( $type, $field, $value );
			$data[ '_' . $name ] = $field['key'] ?? '';

			if ( 'repeater' === $type ) {
				$rows = [];
				$sub_schema = self::index_sub_fields( $field );
				foreach ( $value as $row ) {
					$rows[] = self::build_acf_data( $sub_schema, $row );
				}
				$data[ $name ] = $rows;
			}

			if ( 'group' === $type ) {
				$sub_schema = self::index_sub_fields( $field );
				$data[ $name ] = self::build_acf_data( $sub_schema, $value );
			}
		}
		return $data;
	}

	private static function normalize_value( $type, array $field, $value ) {
		switch ( $type ) {
			case 'number':
			case 'range':
				return is_numeric( $value ) ? 0 + $value : $value;
			case 'true_false':
				return (bool) $value;
			default:
				return $value;
		}
	}

	private static function index_sub_fields( array $field ) {
		$schema = [];
		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub ) {
				if ( ! empty( $sub['name'] ) ) {
					$schema[ $sub['name'] ] = $sub;
				}
			}
		}
		return $schema;
	}

	private static function build_block( $block_name, array $data ) {
		return [
			'blockName' => $block_name,
			'attrs' => [
				'id' => 'block_' . wp_generate_uuid4(),
				'name' => $block_name,
				'data' => $data,
				'mode' => 'preview',
			],
			'innerBlocks' => [],
			'innerHTML' => '',
			'innerContent' => [],
		];
	}

	private static function get_parent_by_path( array &$blocks, array $path ) {
		$current =& $blocks;
		foreach ( $path as $depth => $index ) {
			if ( ! isset( $current[ $index ] ) ) {
				return new WP_Error( 'sae_invalid_parent', 'Parent block path is invalid.', [ 'status' => 400 ] );
			}
			if ( ! isset( $current[ $index ]['innerBlocks'] ) || ! is_array( $current[ $index ]['innerBlocks'] ) ) {
				$current[ $index ]['innerBlocks'] = [];
			}
			$current =& $current[ $index ]['innerBlocks'];
		}
		return [
			'parent' => &$current,
			'path' => $path,
		];
	}

	private static function find_target_block( array &$blocks, array $target ) {
		if ( ! empty( $target['index_path'] ) && is_array( $target['index_path'] ) ) {
			return self::get_parent_and_index_by_path( $blocks, $target['index_path'] );
		}

		$needle_anchor = isset( $target['anchor'] ) ? sanitize_text_field( $target['anchor'] ) : '';
		$needle_id = isset( $target['block_id'] ) ? sanitize_text_field( $target['block_id'] ) : '';

		if ( empty( $needle_anchor ) && empty( $needle_id ) ) {
			return new WP_Error( 'sae_invalid_target', 'Target must include index_path, anchor, or block_id.', [ 'status' => 400 ] );
		}

		return self::find_block_by_attr( $blocks, $needle_anchor, $needle_id );
	}

	private static function get_parent_and_index_by_path( array &$blocks, array $path ) {
		$current =& $blocks;
		$depth = count( $path );
		for ( $i = 0; $i < $depth; $i++ ) {
			$index = $path[ $i ];
			if ( ! isset( $current[ $index ] ) ) {
				return new WP_Error( 'sae_invalid_target', 'index_path does not exist.', [ 'status' => 400 ] );
			}
			if ( $i === $depth - 1 ) {
				return [
					'parent' => &$current,
					'index' => $index,
					'path' => $path,
				];
			}
			if ( ! isset( $current[ $index ]['innerBlocks'] ) || ! is_array( $current[ $index ]['innerBlocks'] ) ) {
				return new WP_Error( 'sae_invalid_target', 'index_path does not resolve to a block.', [ 'status' => 400 ] );
			}
			$current =& $current[ $index ]['innerBlocks'];
		}

		return new WP_Error( 'sae_invalid_target', 'Invalid index_path.', [ 'status' => 400 ] );
	}

	private static function get_block_by_path( array $blocks, array $path ) {
		$current = $blocks;
		$block = null;
		$depth = count( $path );

		for ( $i = 0; $i < $depth; $i++ ) {
			$index = $path[ $i ];
			if ( ! isset( $current[ $index ] ) ) {
				return new WP_Error( 'sae_invalid_parent', 'Parent block path is invalid.', [ 'status' => 400 ] );
			}

			$block = $current[ $index ];
			$current = ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) )
				? $block['innerBlocks']
				: [];
		}

		return $block;
	}

	private static function find_block_by_attr( array &$blocks, $anchor, $block_id, array $path = [] ) {
		foreach ( $blocks as $index => &$block ) {
			$current_path = array_merge( $path, [ $index ] );
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			$block_anchor = $attrs['anchor'] ?? '';
			$block_uid = $attrs['id'] ?? '';

			if ( ( $anchor && $block_anchor === $anchor ) || ( $block_id && $block_uid === $block_id ) ) {
				return [
					'parent' => &$blocks,
					'index' => $index,
					'path' => $current_path,
				];
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$result = self::find_block_by_attr( $block['innerBlocks'], $anchor, $block_id, $current_path );
				if ( ! is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		return new WP_Error( 'sae_target_not_found', 'Target block not found.', [ 'status' => 404 ] );
	}

	private static function remove_blocks_by_name( array $blocks, $block_name, &$removed ) {
		$filtered = [];
		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && $block['blockName'] === $block_name ) {
				$removed++;
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::remove_blocks_by_name( $block['innerBlocks'], $block_name, $removed );
			}
			$filtered[] = $block;
		}
		return $filtered;
	}

	private static function count_blocks_recursive( array $blocks ) {
		$count = 0;
		foreach ( $blocks as $block ) {
			$count++;
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$count += self::count_blocks_recursive( $block['innerBlocks'] );
			}
		}
		return $count;
	}

	private static function count_summary_blocks_recursive( array $summary ) {
		$count = 0;
		foreach ( $summary as $item ) {
			$count++;
			if ( ! empty( $item['inner_blocks'] ) && is_array( $item['inner_blocks'] ) ) {
				$count += self::count_summary_blocks_recursive( $item['inner_blocks'] );
			}
		}
		return $count;
	}

	private static function build_operation_payload( $action, $post_id, array $payload ) {
		return [
			'action' => sanitize_text_field( (string) $action ),
			'post_id' => absint( $post_id ),
			'payload' => $payload,
		];
	}

	/**
	 * Origin-aware token minting (hotfix): origins are 'rest' | 'mcp',
	 * passed EXPLICITLY down the call chain — no global switch any caller
	 * can flip. MCP-originated dry-runs must never mint a redeemable
	 * confirmation token: the plan response reaches an autonomous agent,
	 * and a redeemable token would let it close the human-approval loop.
	 */
	private static function normalize_token_origin( $origin ) {
		return 'mcp' === $origin ? 'mcp' : 'rest';
	}

	/**
	 * Origin transport: dispatch_internal stamps the request it constructs;
	 * REST-dispatched requests carry no attribute and default to rest.
	 */
	private static function request_token_origin( WP_REST_Request $request ) {
		$attributes = (array) $request->get_attributes();
		return self::normalize_token_origin( $attributes['struo_origin'] ?? 'rest' );
	}

	private static function request_token_context( WP_REST_Request $request ) {
		$attributes = (array) $request->get_attributes();
		$context = $attributes['struo_origin_context'] ?? [];
		return is_array( $context ) ? $context : [];
	}

	private static function issue_confirmation_token( array $operation, $post_id, $origin = 'rest', array $context = [] ) {
		unset( $post_id );
		$origin = self::normalize_token_origin( $origin );

		if ( 'mcp' === $origin ) {
			self::audit_log(
				'mcp_dry_run_token_suppressed',
				[
					'action' => sanitize_key( (string) ( $operation['action'] ?? '' ) ),
					'post_id' => absint( $operation['post_id'] ?? 0 ),
					'tool' => sanitize_key( (string) ( $context['tool'] ?? '' ) ),
					'request_id' => sanitize_text_field( (string) ( $context['request_id'] ?? '' ) ),
				]
			);
			return [
				'redeemable' => false,
				'origin' => $origin,
				'message' => 'Dry-run only: MCP-originated plans do not receive a redeemable confirmation token. Apply from the console.',
			];
		}

		return [
			'redeemable' => false,
			'origin' => $origin,
			'message' => 'Plan-only: approve and apply via the durable plan envelope.',
		];
	}

	private static function ensure_write_confirmation( $confirmation_token, array $operation, $post_id, array $context = [] ) {
		unset( $confirmation_token );
		$plan_id = sanitize_text_field( (string) ( $context['durable_plan_apply'] ?? '' ) );
		if ( '' !== $plan_id ) {
			return self::ensure_durable_plan_apply_claim( $plan_id, $operation, $post_id );
		}

		return new WP_Error(
			'sae_plan_apply_required',
			'Direct writes require an approved durable plan. Use plan_id → approve → apply.',
			[ 'status' => 403 ]
		);
	}

	private static function ensure_durable_plan_apply_claim( $plan_id, array $operation, $post_id ) {
		global $wpdb;

		if ( ! self::plans_table_exists() ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		$record = self::get_durable_plan_record( $plan_id );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}

		if ( 'applying' !== (string) ( $record['state'] ?? '' ) ) {
			return new WP_Error( 'sae_plan_not_applying', 'Plan is not in applying state.', [ 'status' => 409 ] );
		}

		if ( absint( $record['post_id'] ?? 0 ) !== absint( $post_id ) ) {
			return new WP_Error( 'sae_plan_post_mismatch', 'Plan post mismatch.', [ 'status' => 400 ] );
		}

		if ( sanitize_text_field( $record['operation'] ?? '' ) !== sanitize_text_field( $operation['action'] ?? '' ) ) {
			return new WP_Error( 'sae_plan_operation_mismatch', 'Plan operation mismatch.', [ 'status' => 400 ] );
		}

		$expected_hash = (string) ( $record['base_content_hash'] ?? '' );
		if ( class_exists( 'Struo_Mutation_Recovery' ) && Struo_Mutation_Recovery::is_recoverable_record( $record ) ) {
			$current_hash = Struo_Mutation_Recovery::hash_witness( Struo_Mutation_Recovery::current_witness( $record ) );
		} else {
			$current_hash = self::hash_post_content( $post_id );
		}
		if ( '' === $expected_hash || '' === $current_hash || ! hash_equals( $expected_hash, $current_hash ) ) {
			return new WP_Error( 'sae_plan_content_conflict', 'Post content changed since the plan was created.', [ 'status' => 409 ] );
		}

		return true;
	}

	private static function ensure_create_write_confirmation( $confirmation_token, array $operation, array $plan_snapshot, array $context = [] ) {
		unset( $confirmation_token );
		$plan_id = sanitize_text_field( (string) ( $context['durable_plan_apply'] ?? '' ) );
		if ( '' !== $plan_id ) {
			return self::ensure_durable_create_plan_apply_claim( $plan_id, $operation, $plan_snapshot );
		}

		return new WP_Error(
			'sae_plan_apply_required',
			'Direct writes require an approved durable plan. Use plan_id → approve → apply.',
			[ 'status' => 403 ]
		);
	}

	private static function ensure_durable_create_plan_apply_claim( $plan_id, array $operation, array $plan_snapshot ) {
		if ( ! self::plans_table_exists() ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		$record = self::get_durable_plan_record( $plan_id );
		if ( ! $record ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}

		if ( 'applying' !== (string) ( $record['state'] ?? '' ) ) {
			return new WP_Error( 'sae_plan_not_applying', 'Plan is not in applying state.', [ 'status' => 409 ] );
		}

		if ( 'create' !== sanitize_text_field( $record['operation'] ?? '' ) ) {
			return new WP_Error( 'sae_plan_operation_mismatch', 'Plan operation mismatch.', [ 'status' => 400 ] );
		}

		if ( 'create_page_v2' !== sanitize_text_field( $operation['action'] ?? '' ) ) {
			return new WP_Error( 'sae_plan_operation_mismatch', 'Plan operation mismatch.', [ 'status' => 400 ] );
		}

		$expected_hash = (string) ( $record['base_content_hash'] ?? '' );
		$current_hash = self::hash_create_plan_snapshot( $plan_snapshot );
		if ( '' === $expected_hash || '' === $current_hash || ! hash_equals( $expected_hash, $current_hash ) ) {
			return new WP_Error( 'sae_plan_content_conflict', 'Create plan changed since the plan was created.', [ 'status' => 409 ] );
		}

		return true;
	}

	private static function issue_create_confirmation_token( array $operation, array $plan_snapshot, $origin = 'rest', array $context = [] ) {
		unset( $plan_snapshot );
		$origin = self::normalize_token_origin( $origin );

		if ( 'mcp' === $origin ) {
			self::audit_log(
				'mcp_dry_run_token_suppressed',
				[
					'action' => sanitize_key( (string) ( $operation['action'] ?? '' ) ),
					'post_id' => 0,
					'tool' => sanitize_key( (string) ( $context['tool'] ?? '' ) ),
					'request_id' => sanitize_text_field( (string) ( $context['request_id'] ?? '' ) ),
				]
			);
			return [
				'redeemable' => false,
				'origin' => $origin,
				'message' => 'Dry-run only: MCP-originated plans do not receive a redeemable confirmation token. Apply from the console.',
			];
		}

		return [
			'redeemable' => false,
			'origin' => $origin,
			'message' => 'Plan-only: approve and apply via the durable plan envelope.',
		];
	}

	private static function normalize_idempotency_key( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return new WP_Error( 'sae_invalid_idempotency_key', 'idempotency_key must be a string.', [ 'status' => 400 ] );
		}

		$key = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $key ) {
			return '';
		}

		if ( strlen( $key ) > self::IDEMPOTENCY_KEY_MAX_LENGTH ) {
			return new WP_Error(
				'sae_invalid_idempotency_key',
				sprintf( 'idempotency_key must be at most %d characters.', self::IDEMPOTENCY_KEY_MAX_LENGTH ),
				[ 'status' => 400 ]
			);
		}

		return $key;
	}

	private static function get_idempotency_cache_key( $idempotency_key ) {
		return self::IDEMPOTENCY_KEY_PREFIX . md5( (string) $idempotency_key );
	}

	private static function with_idempotency_meta( array $result, $idempotency_key, $replayed ) {
		if ( empty( $idempotency_key ) ) {
			return $result;
		}

		$result['idempotency'] = [
			'key' => (string) $idempotency_key,
			'replayed' => (bool) $replayed,
			'ttl_seconds' => self::IDEMPOTENCY_TTL,
		];

		return $result;
	}

	private static function get_idempotent_response( $idempotency_key, array $operation ) {
		if ( empty( $idempotency_key ) ) {
			return null;
		}

		if ( empty( $operation ) ) {
			return new WP_Error( 'sae_idempotency_missing_operation', 'idempotency_key requires an operation payload.', [ 'status' => 500 ] );
		}

		$cache_key = self::get_idempotency_cache_key( $idempotency_key );
		$record = get_transient( $cache_key );
		if ( ! is_array( $record ) ) {
			return null;
		}

		$current_user_id = get_current_user_id();
		$stored_user_id = absint( $record['user_id'] ?? 0 );
		if ( $stored_user_id !== $current_user_id ) {
			return new WP_Error( 'sae_idempotency_user_mismatch', 'idempotency_key belongs to a different user.', [ 'status' => 403 ] );
		}

		$expected_hash = self::hash_operation_payload( $operation );
		$stored_hash = (string) ( $record['operation_hash'] ?? '' );
		if ( empty( $stored_hash ) || ! hash_equals( $stored_hash, $expected_hash ) ) {
			return new WP_Error( 'sae_idempotency_conflict', 'idempotency_key was already used for a different operation.', [ 'status' => 409 ] );
		}

		$stored_result = $record['result'] ?? null;
		if ( ! is_array( $stored_result ) ) {
			return new WP_Error( 'sae_idempotency_invalid_cache', 'idempotency cache entry is invalid.', [ 'status' => 409 ] );
		}

		return self::with_idempotency_meta( $stored_result, $idempotency_key, true );
	}

	private static function store_idempotent_response( $idempotency_key, array $operation, array $result ) {
		if ( empty( $idempotency_key ) || empty( $operation ) ) {
			return;
		}

		$cache_key = self::get_idempotency_cache_key( $idempotency_key );
		$record = [
			'user_id' => get_current_user_id(),
			'operation_hash' => self::hash_operation_payload( $operation ),
			'result' => $result,
			'created_at' => time(),
		];

		set_transient( $cache_key, $record, self::IDEMPOTENCY_TTL );
	}

	private static function hash_post_content( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return '';
		}
		return hash( 'sha256', (string) get_post_field( 'post_content', $post_id ) );
	}

	private static function hash_operation_payload( array $operation ) {
		$canonical = self::canonicalize_value( $operation );
		$json = wp_json_encode( $canonical );
		$salt = wp_salt( 'auth' );
		return hash_hmac( 'sha256', (string) $json, (string) $salt );
	}

	private static function canonicalize_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$normalized = [];
		foreach ( $value as $key => $item ) {
			$normalized[ $key ] = self::canonicalize_value( $item );
		}

		if ( self::is_assoc_array( $normalized ) ) {
			ksort( $normalized );
		}

		return $normalized;
	}

	private static function is_assoc_array( array $array ) {
		if ( [] === $array ) {
			return false;
		}
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	private static function get_plan_rate_limit_window() {
		$window = self::PLAN_RATE_LIMIT_WINDOW;
		$window_constant = self::get_config_constant( 'SAE_PLAN_RATE_LIMIT_WINDOW', 'STRUO_PLAN_RATE_LIMIT_WINDOW' );
		if ( null !== $window_constant ) {
			$window = absint( $window_constant );
		}
		$window = (int) apply_filters( 'struo_plan_rate_limit_window', $window );
		// Floored: filters/constants cannot self-disable the limiter.
		return max( self::PLAN_RATE_LIMIT_MIN_WINDOW, $window );
	}

	private static function get_plan_rate_limit_for_user() {
		$limit = 0;
		$quotas = self::get_options()['plan_rate_role_quotas'] ?? [];
		$quotas = is_array( $quotas ) ? $quotas : [];
		$user = wp_get_current_user();

		foreach ( (array) $user->roles as $role ) {
			$role_key = sanitize_key( (string) $role );
			if ( '' === $role_key || ! isset( $quotas[ $role_key ] ) ) {
				continue;
			}
			$limit = max( $limit, absint( $quotas[ $role_key ] ) );
		}

		if ( $limit <= 0 ) {
			$limit = self::PLAN_RATE_LIMIT_MAX;
		}

		$max_constant = self::get_config_constant( 'SAE_PLAN_RATE_LIMIT_MAX', 'STRUO_PLAN_RATE_LIMIT_MAX' );
		if ( null !== $max_constant ) {
			$limit = absint( $max_constant );
		}

		$limit = (int) apply_filters( 'struo_plan_rate_limit_max', $limit, $user );
		// Floored: filters/constants cannot self-disable the limiter.
		return max( self::PLAN_RATE_LIMIT_MIN_MAX, $limit );
	}

	private static function check_plan_rate_limit() {
		if ( self::$plan_rate_limit_consumed ) {
			return true;
		}

		// Window and max are floored (see PLAN_RATE_LIMIT_MIN_WINDOW/MAX),
		// so neither can be zeroed out via constants or filters. The cap is
		// enforced atomically (CAS / cache incr), so it stays hard under
		// concurrent requests.
		$window = self::get_plan_rate_limit_window();
		$max = self::get_plan_rate_limit_for_user();

		$result = Struo_Rate_Limit_Cas::consume( 'plan_rate_u' . get_current_user_id(), $max, $window );

		if ( ! $result['allowed'] ) {
			return [
				'max' => $max,
				'reset_at' => $result['window_end'],
			];
		}

		self::$plan_rate_limit_consumed = true;
		return true;
	}

	private static function build_plan_rate_limit_response( array $throttle, $route ) {
		$retry_after = max( 1, absint( $throttle['reset_at'] ) - time() );
		self::audit_log(
			'plan_rate_limited',
			[
				'route' => sanitize_key( (string) $route ),
				'user_id' => get_current_user_id(),
				'limit' => absint( $throttle['max'] ),
				'reset_at' => gmdate( 'c', absint( $throttle['reset_at'] ) ),
				'retry_after' => $retry_after,
			]
		);

		$response = rest_ensure_response(
			[
				'code' => 'sae_plan_rate_limit',
				'message' => 'Plan rate limit exceeded. Try again later.',
				'data' => [
					'status' => 429,
					'rate_limit_max' => absint( $throttle['max'] ),
					'rate_limit_reset_at' => gmdate( 'c', absint( $throttle['reset_at'] ) ),
					'retry_after' => $retry_after,
				],
			]
		);
		$response->set_status( 429 );
		$response->header( 'Retry-After', (string) $retry_after );
		return $response;
	}

	private static function get_rate_limit_state() {
		$window = self::RATE_LIMIT_WINDOW;
		$window_start = Struo_Rate_Limit_Cas::window_start( $window );
		$count = self::read_rate_counter( 'rate_u' . get_current_user_id(), $window_start );
		$used = min( self::RATE_LIMIT_MAX, max( 0, $count ) );
		$remaining = max( 0, self::RATE_LIMIT_MAX - $used );

		return [
			'used' => $used,
			'remaining' => $remaining,
			'reset_at' => $window_start + $window,
		];
	}

	private static function read_rate_counter( $name, $window_start ) {
		$key = Struo_Rate_Limit_Cas::counter_name( $name, $window_start );
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return absint( wp_cache_get( $key, Struo_Rate_Limit_Cas::CACHE_GROUP ) );
		}
		return absint( get_option( $key, 0 ) );
	}

	private static function check_rate_limit() {
		// Atomic fixed-window consumption: the cap is hard under
		// concurrency (CAS conditional increment / cache incr).
		$result = Struo_Rate_Limit_Cas::consume( 'rate_u' . get_current_user_id(), self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW );

		if ( ! $result['allowed'] ) {
			$data = [
				'status' => 429,
				'rate_limit_max' => self::RATE_LIMIT_MAX,
				'rate_limit_remaining' => 0,
				'rate_limit_reset_at' => gmdate( 'c', $result['window_end'] ),
			];
			return new WP_Error( 'sae_rate_limit', 'Rate limit exceeded.', $data );
		}

		return [
			'remaining' => max( 0, self::RATE_LIMIT_MAX - $result['count'] ),
			'reset_at' => $result['window_end'],
		];
	}

	private static function attach_rate_limit_meta( WP_Error $error ) {
		$data = $error->get_error_data();
		if ( ! is_array( $data ) ) {
			$data = [];
		}
		if ( isset( $data['rate_limit_max'] ) ) {
			return $error;
		}

		$state = self::get_rate_limit_state();
		$data['rate_limit_max'] = self::RATE_LIMIT_MAX;
		$data['rate_limit_remaining'] = $state['remaining'];
		$data['rate_limit_reset_at'] = gmdate( 'c', $state['reset_at'] );
		$error->add_data( $data );
		return $error;
	}

	private static function ensure_kill_switch() {
		$options = self::get_options();
		if ( ! empty( $options['kill_switch'] ) ) {
			return new WP_Error( 'sae_kill_switch', 'Write endpoints are disabled.', [ 'status' => 403 ] );
		}
		return true;
	}

	private static function ensure_write_allowed( $post_id, $consume_rate_limit = true ) {
		// Defense in depth: allowlist evaluation must never run for
		// anonymous callers (REST permission callbacks already gate this).
		if ( ! is_user_logged_in() ) {
			return self::attach_rate_limit_meta( new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', [ 'status' => rest_authorization_required_code() ] ) );
		}

		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return self::attach_rate_limit_meta( $kill_switch );
		}

		$allowed_posts = self::get_allowed_post_ids();
		if ( empty( $allowed_posts ) || ! in_array( $post_id, $allowed_posts, true ) ) {
			return self::attach_rate_limit_meta( new WP_Error( 'sae_post_not_allowed', 'Post ID is not allowlisted.', [ 'status' => 403 ] ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return self::attach_rate_limit_meta( new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to edit this post.', [ 'status' => 403 ] ) );
		}

		if ( $consume_rate_limit ) {
			$rate_check = self::check_rate_limit();
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		return true;
	}

	private static function ensure_create_allowed( $post_type, $consume_rate_limit = true ) {
		$kill_switch = self::ensure_kill_switch();
		if ( is_wp_error( $kill_switch ) ) {
			return self::attach_rate_limit_meta( $kill_switch );
		}

		$post_type = self::normalize_create_post_type( $post_type );
		if ( '' === $post_type ) {
			return self::attach_rate_limit_meta( new WP_Error( 'sae_create_post_type_invalid', 'Post type is invalid for create operations.', [ 'status' => 400 ] ) );
		}

		$permission = self::can_user_create_post_type( $post_type );
		if ( is_wp_error( $permission ) ) {
			return self::attach_rate_limit_meta( $permission );
		}

		if ( $consume_rate_limit ) {
			$rate_check = self::check_rate_limit();
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		return true;
	}

	private static function ensure_read_allowed( $post_id ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', [ 'status' => rest_authorization_required_code() ] );
		}

		if ( ! $post_id ) {
			return new WP_Error( 'sae_missing_post', 'Post ID is required.', [ 'status' => 400 ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions to read this post.', [ 'status' => 403 ] );
		}

		$allowed_posts = self::get_allowed_post_ids();
		if ( empty( $allowed_posts ) || ! in_array( $post_id, $allowed_posts, true ) ) {
			return new WP_Error( 'sae_post_not_allowed', 'Post ID is not allowlisted.', [ 'status' => 403 ] );
		}

		return true;
	}


	/**
	 * Post id from a REST request: route `id`, else body `post_id` / `id`.
	 *
	 * REST routes bind `id` in the URL. MCP/Abilities send `post_id` in the
	 * body. One reader so permission checks and handlers cannot disagree.
	 */
	private static function request_post_id( WP_REST_Request $request ) {
		$route_id = absint( $request['id'] ?? 0 );
		if ( $route_id > 0 ) {
			return $route_id;
		}
		$payload = self::get_request_payload( $request );
		return absint( $payload['post_id'] ?? ( $payload['id'] ?? 0 ) );
	}

	private static function get_request_payload( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( empty( $payload ) ) {
			$payload = $request->get_body_params();
		}
		return is_array( $payload ) ? $payload : [];
	}

	public static function register_mcp_tools( $tools ) {
		$tools[] = [
			'name' => 'struo_get_block_catalog',
			'description' => 'List allowlisted ACF blocks and their field schemas.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [],
			],
		];
		$tools[] = [
			'name' => 'struo_list_post_blocks',
			'description' => 'List parsed block tree for a post.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
				],
				'required' => [ 'post_id' ],
			],
		];
		$tools[] = [
			'name' => 'struo_plan_block_change',
			'description' => 'Plan a block change from request text or a structured plan and optionally run a dry-run preview.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'request' => [ 'type' => 'string' ],
					'plan' => [ 'type' => 'object' ],
					'dry_run_preview' => [ 'type' => 'boolean' ],
					'prefer_ai' => [ 'type' => 'boolean' ],
					'force_ai' => [ 'type' => 'boolean' ],
					'compact' => [ 'type' => 'boolean' ],
					'verbose' => [ 'type' => 'boolean' ],
					'post_ids' => [
						'type' => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
			],
		];
		$tools[] = [
			'name' => 'struo_insert_block',
			'description' => 'Insert an allowlisted block with fields.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'block_name' => [ 'type' => 'string' ],
					'fields' => [ 'type' => 'object' ],
					'position' => [ 'type' => 'object' ],
					'parent_path' => [
						'type' => 'array',
						'items' => [ 'type' => 'integer' ],
					],
					'dry_run' => [ 'type' => 'boolean' ],
					'compact' => [ 'type' => 'boolean' ],
					'verbose' => [ 'type' => 'boolean' ],
					'confirmation_token' => [ 'type' => 'string' ],
					'idempotency_key' => [ 'type' => 'string' ],
				],
				'required' => [ 'post_id', 'block_name', 'fields' ],
			],
		];
		$tools[] = [
			'name' => 'struo_update_block',
			'description' => 'Update an allowlisted block by target reference.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'target' => [ 'type' => 'object' ],
					'fields' => [ 'type' => 'object' ],
					'dry_run' => [ 'type' => 'boolean' ],
					'compact' => [ 'type' => 'boolean' ],
					'verbose' => [ 'type' => 'boolean' ],
					'confirmation_token' => [ 'type' => 'string' ],
					'idempotency_key' => [ 'type' => 'string' ],
				],
				'required' => [ 'post_id', 'target', 'fields' ],
			],
		];
		$tools[] = [
			'name' => 'struo_remove_block',
			'description' => 'Remove a block by target or remove all by block_name.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'target' => [ 'type' => 'object' ],
					'block_name' => [ 'type' => 'string' ],
					'remove_all' => [ 'type' => 'boolean' ],
					'dry_run' => [ 'type' => 'boolean' ],
					'compact' => [ 'type' => 'boolean' ],
					'verbose' => [ 'type' => 'boolean' ],
					'confirmation_token' => [ 'type' => 'string' ],
					'idempotency_key' => [ 'type' => 'string' ],
				],
				'required' => [ 'post_id' ],
			],
		];
		$tools[] = [
			'name' => 'struo_apply_batch',
			'description' => 'Apply multiple insert/update/remove operations in one dry-run + confirmation cycle.',
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'operations' => [ 'type' => 'array' ],
					'dry_run' => [ 'type' => 'boolean' ],
					'compact' => [ 'type' => 'boolean' ],
					'verbose' => [ 'type' => 'boolean' ],
					'confirmation_token' => [ 'type' => 'string' ],
					'idempotency_key' => [ 'type' => 'string' ],
				],
				'required' => [ 'post_id', 'operations' ],
			],
		];

		return $tools;
	}

	/**
	 * MCP tool name → internal operation.
	 *
	 * Canonical names are struo_*. Previous plugin prefixes are derived so
	 * already-connected agents keep working without a second catalog.
	 */
	private static function mcp_operation_map() {
		$canonical = [
			'struo_get_block_catalog' => 'get-block-catalog',
			'struo_list_post_blocks' => 'get-post-blocks',
			'struo_plan_block_change' => 'plan-block-change',
			'struo_insert_block' => 'preview-insert',
			'struo_update_block' => 'preview-update',
			'struo_remove_block' => 'preview-remove',
			'struo_apply_batch' => 'preview-batch',
		];
		$aliases = [
			'insert-block' => 'preview-insert',
			'update-block' => 'preview-update',
			'remove-block' => 'preview-remove',
			'apply-batch' => 'preview-batch',
		];
		return $canonical + $aliases;
	}

	/**
	 * Shared dispatcher for MCP tools and WP 6.9 Abilities.
	 *
	 * Always forces dry_run, strips confirmation tokens, and stamps
	 * struo_origin=mcp so minting cannot close the apply loop. Apply stays
	 * REST/console. The $origin argument is accepted for caller compatibility
	 * and ignored.
	 *
	 * @param string $operation Operation key (ability or MCP alias).
	 * @param array  $input     Tool/ability input.
	 * @param string $origin    Ignored; this dispatcher always stamps mcp.
	 * @param array  $context   Origin context (tool, request_id).
	 * @return mixed
	 */
	public static function dispatch_internal( $operation, array $input, $origin = 'mcp', array $context = [] ) {
		unset( $origin ); // Callers pass origin; this path is always mcp.
		$aliases = self::mcp_operation_map();
		$operation = (string) $operation;
		if ( isset( $aliases[ $operation ] ) ) {
			$operation = $aliases[ $operation ];
		}

		$is_write = in_array( $operation, [ 'preview-insert', 'preview-update', 'preview-remove', 'preview-batch' ], true );
		$request = new WP_REST_Request( $is_write || 'plan-block-change' === $operation ? 'POST' : 'GET' );
		$post_id = absint( $input['post_id'] ?? ( $input['id'] ?? 0 ) );
		$dispatch_args = $input;
		if ( $post_id > 0 ) {
			// URL `id` is how REST routes bind the post. set_param() would
			// land in POST and then vanish when set_body_params() replaces
			// the body (MCP sends post_id, not id).
			$request->set_url_params( [ 'id' => $post_id ] );
			$dispatch_args['id'] = $post_id;
			$dispatch_args['post_id'] = $post_id;
		}
		// This dispatcher is preview-only (MCP + Abilities). Apply stays
		// REST/console. Never mint a redeemable token here, even if a
		// caller passes origin=rest.
		$dispatch_args['dry_run'] = true;
		unset( $dispatch_args['confirmation_token'] );
		$request->set_body_params( $dispatch_args );

		$dispatch_ops = [
			'get-block-catalog',
			'plan-block-change',
			'get-post-blocks',
			'preview-insert',
			'preview-update',
			'preview-remove',
			'preview-batch',
		];
		if ( ! in_array( $operation, $dispatch_ops, true ) ) {
			return new WP_Error( 'sae_unknown_operation', 'Unknown Struo operation.', [ 'status' => 404 ] );
		}

		$permission = Struo_Authority::authorize(
			$operation,
			[
				'post_id' => $post_id,
				'request' => $request,
			],
			'mcp'
		);

		if ( true !== $permission ) {
			return is_wp_error( $permission )
				? $permission
				: new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions for this tool.', [ 'status' => 403 ] );
		}

		// Origin-aware minting (hotfix): the mcp origin + tool context are
		// stamped on the request this method constructs, so every dry-run
		// confirmation issued during the dispatch is non-redeemable and
		// audited with its tool/request id.
		// WP core only has the plural set_attributes( array ).
		$request->set_attributes(
			array_merge(
				(array) $request->get_attributes(),
				[
					'struo_origin' => 'mcp',
					'struo_origin_context' => [
						'tool' => sanitize_key( (string) ( $context['tool'] ?? $operation ) ),
						'request_id' => (string) ( $context['request_id'] ?? '' ),
					],
				]
			)
		);

		self::audit_log(
			'mcp_tool_call',
			[
				'tool' => sanitize_key( (string) ( $context['tool'] ?? $operation ) ),
				'post_id' => $post_id,
				'dry_run_forced' => true,
			]
		);

		switch ( $operation ) {
			case 'get-block-catalog':
				$result = self::get_block_catalog();
				break;
			case 'get-post-blocks':
				$result = self::get_post_blocks( $request );
				break;
			case 'plan-block-change':
				$result = self::plan_block_change( $request );
				break;
			case 'preview-insert':
				$result = self::insert_block( $request );
				break;
			case 'preview-update':
				$result = self::update_block( $request );
				break;
			case 'preview-remove':
				$result = self::remove_block( $request );
				break;
			case 'preview-batch':
				$result = self::batch_blocks( $request );
				break;
			default:
				return new WP_Error( 'sae_unknown_operation', 'Unknown Struo operation.', [ 'status' => 404 ] );
		}

		if ( $result instanceof WP_REST_Response ) {
			return $result->get_data();
		}

		return $result;
	}

	public static function handle_mcp_call( $result, $tool, $args, $id ) {
		$args = is_array( $args ) ? $args : [];
		$tool_map = self::mcp_operation_map();
		if ( ! isset( $tool_map[ (string) $tool ] ) ) {
			return $result;
		}

		return self::dispatch_internal(
			$tool_map[ (string) $tool ],
			$args,
			'mcp',
			[
				'tool' => sanitize_key( (string) $tool ),
				'request_id' => (string) $id,
			]
		);
	}

	private static function build_console_health_snapshot() {
		$allowlisted_posts = self::list_allowlisted_posts();
		$post_health = [
			'route' => '/wp-json/struo/v1/posts/<id>/blocks',
			'ok' => false,
			'status' => 'warning',
			'message' => 'No allowlisted posts configured.',
		];

		if ( ! empty( $allowlisted_posts ) ) {
			$probe_post_id = absint( $allowlisted_posts[0]['post_id'] ?? 0 );
			if ( $probe_post_id > 0 ) {
				$request = new WP_REST_Request( 'GET', sprintf( '/struo/v1/posts/%d/blocks', $probe_post_id ) );
				$request->set_param( 'id', $probe_post_id );
				$result = self::get_post_blocks( $request );
				if ( is_wp_error( $result ) ) {
					$post_health = [
						'route' => '/wp-json/struo/v1/posts/<id>/blocks',
						'ok' => false,
						'status' => 'error',
						'message' => $result->get_error_message(),
					];
				} else {
					$post_health = [
						'route' => '/wp-json/struo/v1/posts/<id>/blocks',
						'ok' => true,
						'status' => 'ok',
						'message' => sprintf( 'Probe post %d available.', $probe_post_id ),
					];
				}
			}
		}

		$catalog_result = self::get_block_catalog();
		$catalog_health = is_wp_error( $catalog_result )
			? [
				'route' => '/wp-json/struo/v1/block-catalog',
				'ok' => false,
				'status' => 'error',
				'message' => $catalog_result->get_error_message(),
			]
			: [
				'route' => '/wp-json/struo/v1/block-catalog',
				'ok' => true,
				'status' => 'ok',
				'message' => sprintf( 'Catalog available (%d blocks).', count( $catalog_result['allowed_blocks'] ?? [] ) ),
			];

		return [
			'catalog' => $catalog_health,
			'post_probe' => $post_health,
			'provider' => self::build_provider_health(),
			'audit' => [
				'route' => '/wp-json/struo/v1/console/audit',
				'ok' => true,
				'status' => 'ok',
				'message' => 'Audit endpoint ready.',
			],
		];
	}

	/**
	 * Provider destination health for console/status: scheme, approved-host,
	 * and SSRF validation state plus key-host binding. No key material and
	 * no audit writes here.
	 */
	private static function build_provider_health() {
		$base_url = self::get_openai_base_url();
		$result = self::inspect_provider_destination( $base_url );
		$health = [
			'host' => (string) $result['host'],
			'scheme_ok' => ! in_array( $result['code'], [ 'provider_url_invalid', 'provider_url_insecure' ], true ),
			'host_allowed' => 'provider_host_not_allowed' !== $result['code'],
			'destination_safe' => (bool) $result['ok'],
			'key_source' => self::openai_key_source(),
			'key_host_match' => null,
		];

		if ( 'option' === $health['key_source'] ) {
			$saved_host = strtolower( trim( (string) get_option( self::OPENAI_KEY_HOST_OPTION, '' ) ) );
			$health['key_host_match'] = '' !== $saved_host && $saved_host === strtolower( (string) $result['host'] );
		}

		if ( ! $health['destination_safe'] ) {
			$health['ok'] = false;
			$health['status'] = 'error';
			$health['message'] = (string) $result['message'];
		} elseif ( false === $health['key_host_match'] ) {
			$health['ok'] = false;
			$health['status'] = 'warning';
			$health['message'] = 'Provider key was saved for a different host; re-save the key to enable requests.';
		} else {
			$health['ok'] = true;
			$health['status'] = 'ok';
			$health['message'] = sprintf( 'Provider destination validated (%s).', (string) $result['host'] );
		}

		return $health;
	}

	private static function query_audit_entries( array $filters = [] ) {
		global $wpdb;

		$page = max( 1, absint( $filters['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, absint( $filters['per_page'] ?? 25 ) ) );
		$offset = ( $page - 1 ) * $per_page;
		$action_filter = sanitize_text_field( (string) ( $filters['action'] ?? '' ) );
		$post_id_filter = absint( $filters['post_id'] ?? 0 );
		$since_filter = sanitize_text_field( (string) ( $filters['since'] ?? '' ) );
		$exclude_actions = is_array( $filters['exclude_actions'] ?? null ) ? $filters['exclude_actions'] : [];
		$exclude_actions = array_values( array_filter( array_map( 'sanitize_key', $exclude_actions ) ) );
		$since_timestamp = '' !== $since_filter ? strtotime( $since_filter ) : false;
		$since_sql = false !== $since_timestamp ? wp_date( 'Y-m-d H:i:s', $since_timestamp, wp_timezone() ) : '';

		$table_name = self::get_audit_table_name();
		if ( self::audit_table_exists() ) {
			$where_parts = [ '1=1' ];
			$prepare_values = [];

			if ( '' !== $action_filter ) {
				$where_parts[] = 'action = %s';
				$prepare_values[] = $action_filter;
			}
			if ( $post_id_filter > 0 ) {
				$where_parts[] = 'post_id = %d';
				$prepare_values[] = $post_id_filter;
			}
			if ( '' !== $since_sql ) {
				$where_parts[] = 'created_at >= %s';
				$prepare_values[] = $since_sql;
			}
			if ( ! empty( $exclude_actions ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $exclude_actions ), '%s' ) );
				$where_parts[] = "action NOT IN ({$placeholders})";
				$prepare_values = array_merge( $prepare_values, $exclude_actions );
			}

			$where_sql = implode( ' AND ', $where_parts );
			$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
			$total = (int) $wpdb->get_var(
				! empty( $prepare_values )
					? $wpdb->prepare( $count_sql, $prepare_values )
					: $count_sql
			);

			$data_values = array_merge( $prepare_values, [ $per_page, $offset ] );
			$data_sql = "SELECT id, created_at, user_id, action, post_id, details
				FROM {$table_name}
				WHERE {$where_sql}
				ORDER BY id DESC
				LIMIT %d OFFSET %d";
			$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_values ), ARRAY_A );

			$items = array_map(
				static function ( $row ) {
					$user_id = absint( $row['user_id'] ?? 0 );
					$user = $user_id > 0 ? get_user_by( 'id', $user_id ) : null;
					$details = json_decode( (string) ( $row['details'] ?? '' ), true );
					if ( ! is_array( $details ) ) {
						$details = [];
					}

					return [
						'id' => absint( $row['id'] ?? 0 ),
						'created_at' => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
						'created_at_iso' => self::normalize_audit_created_at_iso( (string) ( $row['created_at'] ?? '' ) ),
						'user_id' => $user_id,
						'user_label' => $user ? (string) $user->display_name : '',
						'action' => sanitize_text_field( (string) ( $row['action'] ?? '' ) ),
						'post_id' => absint( $row['post_id'] ?? 0 ),
						'details' => $details,
					];
				},
				is_array( $rows ) ? $rows : []
			);

			return [
				'source' => 'table',
				'items' => $items,
				'total' => $total,
			];
		}

		$legacy = get_option( self::AUDIT_KEY, [] );
		if ( ! is_array( $legacy ) ) {
			$legacy = [];
		}

		$legacy = array_values(
			array_filter(
				$legacy,
				static function ( $entry ) use ( $action_filter, $post_id_filter, $since_timestamp, $exclude_actions ) {
					if ( ! is_array( $entry ) ) {
						return false;
					}
					if ( '' !== $action_filter && sanitize_text_field( (string) ( $entry['action'] ?? '' ) ) !== $action_filter ) {
						return false;
					}
					if ( ! empty( $exclude_actions ) && in_array( sanitize_key( (string) ( $entry['action'] ?? '' ) ), $exclude_actions, true ) ) {
						return false;
					}
					if ( $post_id_filter > 0 ) {
						$details = is_array( $entry['details'] ?? null ) ? $entry['details'] : [];
						if ( absint( $details['post_id'] ?? 0 ) !== $post_id_filter ) {
							return false;
						}
					}
					if ( false !== $since_timestamp ) {
						$entry_timestamp = strtotime( (string) ( $entry['timestamp'] ?? '' ) );
						if ( false === $entry_timestamp || $entry_timestamp < $since_timestamp ) {
							return false;
						}
					}
					return true;
				}
			)
		);

		$legacy = array_reverse( $legacy );
		$total = count( $legacy );
		$paged = array_slice( $legacy, $offset, $per_page );
		$items = array_map(
			static function ( $entry, $index ) {
				$user_id = absint( $entry['user_id'] ?? 0 );
				$user = $user_id > 0 ? get_user_by( 'id', $user_id ) : null;
				$created_at = sanitize_text_field( (string) ( $entry['timestamp'] ?? '' ) );
				return [
					'id' => $index + 1,
					'created_at' => $created_at,
					'created_at_iso' => self::normalize_audit_created_at_iso( $created_at ),
					'user_id' => $user_id,
					'user_label' => $user ? (string) $user->display_name : '',
					'action' => sanitize_text_field( (string) ( $entry['action'] ?? '' ) ),
					'post_id' => absint( $entry['details']['post_id'] ?? 0 ),
					'details' => is_array( $entry['details'] ?? null ) ? $entry['details'] : [],
				];
			},
			$paged,
			array_keys( $paged )
		);

		return [
			'source' => 'legacy_option',
			'items' => $items,
			'total' => $total,
		];
	}

	private static function normalize_audit_created_at_iso( $value ) {
		$raw_value = trim( (string) $value );
		if ( '' === $raw_value ) {
			return '';
		}

		$timezone = wp_timezone();
		$from_mysql = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $raw_value, $timezone );
		if ( $from_mysql instanceof DateTimeImmutable ) {
			return $from_mysql->format( DATE_ATOM );
		}

		try {
			$from_iso = new DateTimeImmutable( $raw_value, $timezone );
			return $from_iso->format( DATE_ATOM );
		} catch ( Exception $exception ) {
			return '';
		}
	}

	private static function get_audit_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::AUDIT_TABLE_SUFFIX;
	}

	private static function get_plans_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::PLANS_TABLE_SUFFIX;
	}

	private static function plans_table_exists( $refresh = false ) {
		global $wpdb;

		if ( ! $refresh && null !== self::$plans_table_exists_cache ) {
			return (bool) self::$plans_table_exists_cache;
		}

		$table_name = self::get_plans_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		self::$plans_table_exists_cache = ( $exists === $table_name );
		return (bool) self::$plans_table_exists_cache;
	}

	private static function refresh_plans_table_engine() {
		global $wpdb;

		self::$plans_engine_cache = '';
		if ( ! self::plans_table_exists() ) {
			return '';
		}

		$table_name = self::get_plans_table_name();
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table_name ), ARRAY_A );
		self::$plans_engine_cache = is_array( $status ) ? (string) ( $status['Engine'] ?? '' ) : '';

		return self::$plans_engine_cache;
	}

	private static function plans_table_is_innodb() {
		if ( null === self::$plans_engine_cache ) {
			self::refresh_plans_table_engine();
		}

		return 0 === strcasecmp( (string) self::$plans_engine_cache, 'InnoDB' );
	}

	private static function ensure_plans_table_innodb() {
		if ( self::plans_table_is_innodb() ) {
			return true;
		}

		global $wpdb;
		$table_name = self::get_plans_table_name();
		if ( '' === $table_name || ! self::plans_table_exists() ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from the sanitized WP prefix.
		$wpdb->query( "ALTER TABLE `{$table_name}` ENGINE=InnoDB" );
		self::refresh_plans_table_engine();

		return self::plans_table_is_innodb();
	}

	private static function require_innodb_for_bundle() {
		if ( self::ensure_plans_table_innodb() ) {
			return true;
		}

		return new WP_Error(
			'sae_plans_not_innodb',
			'Bundle review requires InnoDB plan storage.',
			[ 'status' => 409 ]
		);
	}

	private static function ensure_plans_table() {
		global $wpdb;

		$table_name = self::get_plans_table_name();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			plan_id char(16) NOT NULL,
			payload_type varchar(32) NOT NULL DEFAULT 'mutation_v1',
			state varchar(20) NOT NULL DEFAULT 'planned',
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			origin varchar(16) NOT NULL DEFAULT 'rest',
			operation varchar(16) NOT NULL DEFAULT '',
			endpoint varchar(255) NOT NULL DEFAULT '',
			request_text text NOT NULL,
			payload_json longtext NOT NULL,
			preview_json longtext NULL,
			post_json longtext NOT NULL,
			base_content_hash char(64) NOT NULL DEFAULT '',
			base_content longtext NULL,
			expected_content_hash char(64) NOT NULL DEFAULT '',
			approved_at datetime NULL,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			applying_at datetime NULL,
			applied_at datetime NULL,
			applied_by bigint(20) unsigned NOT NULL DEFAULT 0,
			failure_code varchar(64) NOT NULL DEFAULT '',
			failure_message text NULL,
			apply_receipt_json longtext NULL,
			PRIMARY KEY  (plan_id),
			KEY state (state),
			KEY post_id (post_id),
			KEY user_id (user_id),
			KEY expires_at (expires_at)
		) ENGINE=InnoDB {$charset_collate};";

		dbDelta( $sql );
		self::$plans_engine_cache = null;
		if ( self::plans_table_exists( true ) ) {
			self::ensure_plans_table_innodb();
			return true;
		}

		return false;
	}

	private static function maybe_migrate_struo_capabilities() {
		$stored = absint( get_option( self::CAPS_MIGRATED_OPTION, 0 ) );
		if ( $stored >= self::CAPS_SCHEMA_VERSION ) {
			return;
		}

		$all_caps = [
			self::CAP_STRUO_PLAN,
			self::CAP_STRUO_APPROVE,
			self::CAP_STRUO_APPLY,
			self::CAP_STRUO_MANAGE_REGISTRY,
			self::CAP_STRUO_MANAGE_SETTINGS,
		];

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $all_caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::CAP_STRUO_PLAN );
		}

		update_option( self::CAPS_MIGRATED_OPTION, self::CAPS_SCHEMA_VERSION, false );
	}

	private static function plans_recovery_columns_exist() {
		global $wpdb;

		if ( ! self::plans_table_exists() ) {
			return false;
		}

		$table = self::get_plans_table_name();
		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'expected_content_hash'" );

		return is_string( $col ) && '' !== $col;
	}

	private static function maybe_upgrade_plans_storage() {
		$stored_version = absint( get_option( self::PLANS_DB_VERSION_OPTION, 0 ) );
		$needs_schema = $stored_version < self::PLANS_DB_VERSION || ! self::plans_table_exists();
		if ( ! $needs_schema && ! self::plans_recovery_columns_exist() ) {
			$needs_schema = true;
		}
		if ( ! $needs_schema ) {
			self::ensure_plans_table_innodb();
			if ( class_exists( 'Struo_Mutation_Journal' ) ) {
				Struo_Mutation_Journal::ensure_table();
			}
			self::prune_durable_plans();
			return;
		}

		if ( ! self::ensure_plans_table() ) {
			return;
		}

		self::maybe_migrate_agent_plans_option_to_table();
		update_option( self::PLANS_DB_VERSION_OPTION, self::PLANS_DB_VERSION, false );
		self::ensure_plans_table_innodb();
		if ( class_exists( 'Struo_Mutation_Journal' ) ) {
			Struo_Mutation_Journal::ensure_table();
		}
		self::prune_durable_plans();
	}

	private static function maybe_migrate_agent_plans_option_to_table() {
		if ( get_option( self::PLANS_AGENT_MIGRATED_OPTION, null ) ) {
			return;
		}

		$store = get_option( self::AGENT_PLANS_OPTION, [] );
		if ( is_array( $store ) ) {
			foreach ( $store as $id => $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}
				$record['id'] = is_string( $id ) ? $id : (string) ( $record['id'] ?? '' );
				if ( ! self::validate_agent_plan_id( $record['id'] ) ) {
					continue;
				}
				if ( empty( $record['payload_type'] ) ) {
					$record['payload_type'] = 'mutation_v1';
				}
				if ( empty( $record['state'] ) ) {
					$record['state'] = 'planned';
				}
				if ( empty( $record['base_content_hash'] ) ) {
					$post_id = absint( $record['post_id'] ?? 0 );
					$record['base_content_hash'] = $post_id > 0 ? self::hash_post_content( $post_id ) : '';
				}
				self::insert_durable_plan_record( $record );
			}
		}

		delete_option( self::AGENT_PLANS_OPTION );
		update_option( self::PLANS_AGENT_MIGRATED_OPTION, 1, false );
	}

	private static function insert_durable_plan_record( array $record ) {
		if ( ! self::plans_table_exists() && ! self::ensure_plans_table() ) {
			return false;
		}
		if ( ! class_exists( 'Struo_Durable_Plans' ) ) {
			return false;
		}

		$record['id'] = sanitize_text_field( (string) ( $record['id'] ?? '' ) );
		$record['payload_type'] = sanitize_key( (string) ( $record['payload_type'] ?? 'mutation_v1' ) );
		$record['state'] = sanitize_key( (string) ( $record['state'] ?? 'planned' ) );
		$record['origin'] = sanitize_key( (string) ( $record['origin'] ?? 'mcp' ) );
		$record['operation'] = sanitize_key( (string) ( $record['operation'] ?? '' ) );
		$record['user_id'] = absint( $record['user_id'] ?? get_current_user_id() );
		$post = is_array( $record['post'] ?? null ) ? $record['post'] : [];
		$record['post_id'] = absint( $record['post_id'] ?? ( $post['post_id'] ?? 0 ) );
		$record['base_content_hash'] = sanitize_text_field( (string) ( $record['base_content_hash'] ?? '' ) );
		if ( is_array( $record['payload'] ?? null ) ) {
			$record['payload'] = self::strip_redeemable_fields( $record['payload'] );
		}

		$created = Struo_Durable_Plans::create( $record, [ 'skip_capacity' => true ] );
		return is_string( $created ) && '' !== $created;
	}

	private static function delete_durable_plan_record( $id ) {
		global $wpdb;

		$id = sanitize_text_field( (string) $id );
		if ( ! self::validate_agent_plan_id( $id ) || ! self::plans_table_exists() ) {
			return false;
		}

		$table = self::get_plans_table_name();
		$deleted = $wpdb->delete( $table, [ 'plan_id' => $id ], [ '%s' ] );
		if ( class_exists( 'Struo_Mutation_Journal' ) ) {
			Struo_Mutation_Journal::erase_for_plan_ids( [ $id ] );
		}

		return false !== $deleted;
	}

	private static function durable_plan_row_to_record( array $row ) {
		$post = json_decode( (string) ( $row['post_json'] ?? '' ), true );
		$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
		$preview = json_decode( (string) ( $row['preview_json'] ?? '' ), true );

		return [
			'id' => (string) ( $row['plan_id'] ?? '' ),
			'payload_type' => (string) ( $row['payload_type'] ?? 'mutation_v1' ),
			'state' => (string) ( $row['state'] ?? 'planned' ),
			'created_at' => strtotime( (string) ( $row['created_at'] ?? '' ) . ' UTC' ),
			'expires_at' => strtotime( (string) ( $row['expires_at'] ?? '' ) . ' UTC' ),
			'user_id' => absint( $row['user_id'] ?? 0 ),
			'origin' => (string) ( $row['origin'] ?? 'mcp' ),
			'post_id' => absint( $row['post_id'] ?? 0 ),
			'post' => is_array( $post ) ? $post : [ 'post_id' => absint( $row['post_id'] ?? 0 ) ],
			'operation' => (string) ( $row['operation'] ?? '' ),
			'endpoint' => (string) ( $row['endpoint'] ?? '' ),
			'request' => (string) ( $row['request_text'] ?? '' ),
			'payload' => is_array( $payload ) ? self::strip_redeemable_fields( $payload ) : [],
			'preview' => is_array( $preview ) ? $preview : null,
			'base_content_hash' => (string) ( $row['base_content_hash'] ?? '' ),
			'base_content' => (string) ( $row['base_content'] ?? '' ),
			'expected_content_hash' => (string) ( $row['expected_content_hash'] ?? '' ),
			'approved_at' => (string) ( $row['approved_at'] ?? '' ),
			'approved_by' => absint( $row['approved_by'] ?? 0 ),
			'applying_at' => (string) ( $row['applying_at'] ?? '' ),
			'applied_at' => (string) ( $row['applied_at'] ?? '' ),
			'applied_by' => absint( $row['applied_by'] ?? 0 ),
			'failure_code' => (string) ( $row['failure_code'] ?? '' ),
			'failure_message' => (string) ( $row['failure_message'] ?? '' ),
		];
	}

	private static function get_durable_plan_record( $id ) {
		$record = self::get_durable_plan_record_unchecked( $id );
		if ( ! $record ) {
			return null;
		}

		if ( in_array( (string) ( $record['state'] ?? '' ), [ 'cancelled', 'expired', 'failed', 'applied' ], true ) ) {
			return null;
		}
		if ( absint( $record['expires_at'] ?? 0 ) <= time() ) {
			self::durable_plan_move( 'expire', $id );
			return null;
		}

		return $record;
	}

	private static function get_durable_plan_record_for_receipt( $id ) {
		$record = self::get_durable_plan_record_unchecked( $id );
		if ( ! $record ) {
			return null;
		}

		$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
		if ( in_array( $state, [ 'planned', 'approved' ], true ) && absint( $record['expires_at'] ?? 0 ) <= time() ) {
			self::durable_plan_move( 'expire', $id );
			$record = self::get_durable_plan_record_unchecked( $id );
		}

		return $record;
	}

	private static function list_durable_plan_records( array $args = [] ) {
		global $wpdb;

		if ( ! self::plans_table_exists() ) {
			return [];
		}

		$table = self::get_plans_table_name();
		$where = [ '1=1' ];
		$params = [];

		if ( ! empty( $args['origins'] ) && is_array( $args['origins'] ) ) {
			$origins = array_values(
				array_filter(
					array_map(
						static function ( $origin ) {
							return sanitize_key( (string) $origin );
						},
						$args['origins']
					)
				)
			);
			if ( ! empty( $origins ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $origins ), '%s' ) );
				$where[] = "origin IN ({$placeholders})";
				$params = array_merge( $params, $origins );
			}
		} elseif ( ! empty( $args['origin'] ) ) {
			$where[] = 'origin = %s';
			$params[] = sanitize_key( (string) $args['origin'] );
		}

		if ( ! empty( $args['states'] ) && is_array( $args['states'] ) ) {
			$states = array_values(
				array_filter(
					array_map(
						static function ( $state ) {
							return sanitize_key( (string) $state );
						},
						$args['states']
					)
				)
			);
			if ( ! empty( $states ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $states ), '%s' ) );
				$where[] = "state IN ({$placeholders})";
				$params = array_merge( $params, $states );
			}
		}

		if ( empty( $args['include_expired'] ) ) {
			$where[] = 'expires_at > %s';
			$params[] = gmdate( 'Y-m-d H:i:s', time() );
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC';
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$records = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$records[] = self::durable_plan_row_to_record( $row );
		}

		return $records;
	}

	/**
	 * Ask the durable plan store by intent. Callers do not pass from/to.
	 *
	 * @param string               $intent
	 * @param string               $id
	 * @param array<string, mixed> $fields
	 * @return true|WP_Error
	 */
	private static function durable_plan_move( $intent, $id, array $fields = [] ) {
		if ( ! class_exists( 'Struo_Durable_Plans' ) ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		$intent = sanitize_key( (string) $intent );
		$id = sanitize_text_field( (string) $id );
		switch ( $intent ) {
			case 'approve':
				$result = Struo_Durable_Plans::approve( $id, $fields );
				break;
			case 'claim_apply':
				$result = Struo_Durable_Plans::claim_apply( $id, $fields );
				break;
			case 'finish':
				$result = Struo_Durable_Plans::finish( $id, $fields );
				break;
			case 'fail':
				$result = Struo_Durable_Plans::fail( $id, $fields );
				break;
			case 'cancel':
				$result = Struo_Durable_Plans::cancel( $id, $fields );
				break;
			case 'expire':
				$result = Struo_Durable_Plans::expire( $id, $fields );
				break;
			case 'release_claim':
				$result = Struo_Durable_Plans::release_claim( $id, $fields );
				break;
			default:
				return new WP_Error( 'sae_plan_invalid_transition', 'Invalid plan state transition.', [ 'status' => 400 ] );
		}

		if ( true === $result ) {
			return true;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_Error( 'sae_plan_state_conflict', 'Plan state changed concurrently or is no longer valid.', [ 'status' => 409 ] );
	}

	private static function prune_durable_plans( array $keep_ids = [] ) {
		if ( ! self::plans_table_exists() ) {
			return;
		}

		if ( class_exists( 'Struo_Durable_Plans' ) ) {
			Struo_Durable_Plans::prune( $keep_ids );
		}

		$protected = self::durable_plan_protected_ids( $keep_ids );
		self::cancel_orphan_bundle_children( $protected );
	}

	private static function assert_durable_plan_capacity( $needed ) {
		$needed = absint( $needed );
		if ( $needed < 1 ) {
			return true;
		}

		self::prune_durable_plans();
		if ( ! class_exists( 'Struo_Durable_Plans' ) ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		$result = Struo_Durable_Plans::capacity_needed( $needed );
		if ( true === $result ) {
			return true;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_Error(
			'sae_plan_capacity',
			'Plan storage is at capacity. Finish or dismiss existing plans before creating more.',
			[ 'status' => 429 ]
		);
	}

	private static function durable_plan_protected_ids( array $keep_ids ) {
		$protected = [];
		foreach ( $keep_ids as $keep_id ) {
			$keep_id = (string) $keep_id;
			if ( ! self::validate_agent_plan_id( $keep_id ) ) {
				continue;
			}
			foreach ( self::durable_plan_family_ids( $keep_id ) as $family_id ) {
				$protected[ (string) $family_id ] = true;
			}
		}

		return $protected;
	}

	private static function cancel_orphan_bundle_children( array $protected = [] ) {
		global $wpdb;

		if ( ! self::plans_table_exists() ) {
			return;
		}

		$table = self::get_plans_table_name();
		$grace_before = gmdate( 'Y-m-d H:i:s', time() - self::BUNDLE_ORPHAN_GRACE );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT plan_id, payload_json FROM {$table} WHERE state IN ('planned', 'approved') AND created_at <= %s",
				$grace_before
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return;
		}

		$cancel_ids = [];
		foreach ( $rows as $row ) {
			$plan_id = (string) ( $row['plan_id'] ?? '' );
			if ( ! self::validate_agent_plan_id( $plan_id ) || isset( $protected[ $plan_id ] ) ) {
				continue;
			}
			$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
			$bundle_id = sanitize_text_field( (string) ( is_array( $payload ) ? ( $payload['bundle_id'] ?? '' ) : '' ) );
			if ( ! self::validate_agent_plan_id( $bundle_id ) ) {
				continue;
			}
			if ( self::get_durable_plan_record_unchecked( $bundle_id ) ) {
				continue;
			}
			$cancel_ids[] = $plan_id;
		}

		self::cancel_durable_plan_ids( $cancel_ids );
	}

	private static function get_durable_plan_record_unchecked( $id ) {
		global $wpdb;

		if ( ! self::plans_table_exists() ) {
			return null;
		}

		$id = (string) $id;
		if ( ! self::validate_agent_plan_id( $id ) ) {
			return null;
		}

		$table = self::get_plans_table_name();
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE plan_id = %s", $id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::durable_plan_row_to_record( $row );
	}

	private static function durable_plan_family_ids( $plan_id ) {
		if ( class_exists( 'Struo_Durable_Plans' ) ) {
			return Struo_Durable_Plans::family_ids( $plan_id );
		}

		return [ (string) $plan_id ];
	}

	private static function audit_table_exists( $refresh = false ) {
		global $wpdb;

		if ( ! $refresh && null !== self::$audit_table_exists_cache ) {
			return (bool) self::$audit_table_exists_cache;
		}

		$table_name = self::get_audit_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		self::$audit_table_exists_cache = ( $exists === $table_name );
		return (bool) self::$audit_table_exists_cache;
	}

	private static function ensure_audit_table() {
		global $wpdb;

		$table_name = self::get_audit_table_name();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details longtext NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY user_id (user_id),
			KEY action (action),
			KEY post_id (post_id)
		) {$charset_collate};";

		dbDelta( $sql );
		return self::audit_table_exists( true );
	}

	private static function maybe_migrate_legacy_audit_entries() {
		$migrated = (bool) get_option( self::AUDIT_MIGRATED_OPTION, false );
		if ( $migrated ) {
			return;
		}

		$entries = get_option( self::AUDIT_KEY, [] );
		if ( ! is_array( $entries ) || empty( $entries ) ) {
			update_option( self::AUDIT_MIGRATED_OPTION, 1, false );
			return;
		}

		$all_inserted = true;
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$inserted = self::insert_audit_table_entry(
				$entry['action'] ?? '',
				is_array( $entry['details'] ?? null ) ? $entry['details'] : [],
				[
					'timestamp' => $entry['timestamp'] ?? '',
					'user_id' => $entry['user_id'] ?? 0,
					'post_id' => is_array( $entry['details'] ?? null ) ? absint( $entry['details']['post_id'] ?? 0 ) : 0,
				]
			);
			if ( ! $inserted ) {
				$all_inserted = false;
			}
		}

		if ( $all_inserted ) {
			delete_option( self::AUDIT_KEY );
			update_option( self::AUDIT_MIGRATED_OPTION, 1, false );
		}
	}

	private static function insert_audit_table_entry( $action, array $details = [], array $meta = [] ) {
		global $wpdb;

		if ( defined( 'STRUO_EVAL_HARNESS' ) && STRUO_EVAL_HARNESS ) {
			$details['eval_harness'] = 1;
		}

		$table_name = self::get_audit_table_name();
		$created_at = sanitize_text_field( $meta['timestamp'] ?? '' );
		if ( empty( $created_at ) ) {
			$created_at = current_time( 'mysql' );
		}

		$user_id = absint( $meta['user_id'] ?? get_current_user_id() );
		$post_id = absint( $meta['post_id'] ?? ( $details['post_id'] ?? 0 ) );
		$action = sanitize_text_field( $action );
		if ( empty( $action ) ) {
			$action = 'unknown';
		}

		$details_json = wp_json_encode( $details );
		if ( false === $details_json ) {
			$details_json = wp_json_encode( [ 'encoding_error' => true ] );
		}

		$inserted = $wpdb->insert(
			$table_name,
			[
				'created_at' => $created_at,
				'user_id' => $user_id,
				'action' => $action,
				'post_id' => $post_id,
				'details' => $details_json,
			],
			[ '%s', '%d', '%s', '%d', '%s' ]
		);

		if ( false === $inserted ) {
			return false;
		}

		if ( self::AUDIT_TABLE_MAX_ROWS > 0 && self::AUDIT_TABLE_PRUNE_INTERVAL > 0 && 0 === ( (int) $wpdb->insert_id % self::AUDIT_TABLE_PRUNE_INTERVAL ) ) {
			self::prune_audit_table_rows();
			self::prune_audit_rows_by_age();
		}

		return true;
	}

	private static function prune_audit_table_rows() {
		global $wpdb;

		if ( self::AUDIT_TABLE_MAX_ROWS <= 0 ) {
			return;
		}

		$table_name = self::get_audit_table_name();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name}
				WHERE id NOT IN (
					SELECT id FROM (
						SELECT id FROM {$table_name} ORDER BY id DESC LIMIT %d
					) AS keep_rows
				)",
				self::AUDIT_TABLE_MAX_ROWS
			)
		);
	}

	/**
	 * Audit retention window in days (lane 3b): 0 disables time-based
	 * pruning and keeps only the volume-based row cap.
	 */
	private static function get_audit_retention_days() {
		$days = absint( self::get_options()['audit_retention_days'] ?? 90 );
		return min( self::AUDIT_RETENTION_MAX_DAYS, $days );
	}

	/**
	 * Delete audit rows older than the retention window. Table rows go by
	 * created_at; the legacy option-based ring buffer is filtered by its
	 * per-entry timestamp. Returns the number of rows removed.
	 */
	private static function prune_audit_rows_by_age() {
		global $wpdb;

		$retention_days = self::get_audit_retention_days();
		if ( $retention_days <= 0 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$removed = 0;

		if ( self::audit_table_exists() ) {
			$table_name = self::get_audit_table_name();
			$removed += (int) $wpdb->query(
				$wpdb->prepare( "DELETE FROM {$table_name} WHERE created_at < %s", $cutoff )
			);
		}

		$entries = get_option( self::AUDIT_KEY, [] );
		if ( is_array( $entries ) && ! empty( $entries ) ) {
			$kept = [];
			foreach ( $entries as $entry ) {
				$timestamp = strtotime( (string) ( $entry['timestamp'] ?? '' ) );
				if ( false !== $timestamp && $timestamp < strtotime( $cutoff ) ) {
					$removed++;
					continue;
				}
				$kept[] = $entry;
			}
			if ( count( $kept ) !== count( $entries ) ) {
				update_option( self::AUDIT_KEY, $kept, false );
			}
		}

		return $removed;
	}

	/**
	 * When false (fresh installs by default), audit before/after excerpts
	 * are stored as a sha256 prefix + length instead of raw text.
	 */
	private static function audit_store_excerpts_enabled() {
		return ! empty( self::get_options()['audit_store_excerpts'] );
	}

	/**
	 * Redacting excerpt builder (lane 3b item 3): raw text capped at 120
	 * characters when excerpt storage is enabled; otherwise only a sha256
	 * prefix plus the original length is recorded — never the text itself.
	 */
	private static function audit_excerpt( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		if ( self::audit_store_excerpts_enabled() ) {
			$stripped = wp_strip_all_tags( $text );
			return mb_substr( $stripped, 0, 120 );
		}

		return substr( hash( 'sha256', $text ), 0, 12 ) . ':len=' . strlen( $text );
	}

	/**
	 * Personal-data exporter (lane 3b item 4): exports this user's audit
	 * rows (action, post id, timestamp, non-key details) in the core
	 * personal-data export format.
	 */
	public static function register_audit_data_exporter( $exporters ) {
		$exporters['struo-audit-log'] = [
			'exporter_friendly_name' => __( 'Struo Audit Log', self::TEXT_DOMAIN ),
			'callback' => [ __CLASS__, 'export_audit_rows_for_user' ],
		];
		return $exporters;
	}

	public static function export_audit_rows_for_user( $email_address, $page = 1 ) {
		global $wpdb;

		$page = max( 1, absint( $page ) );
		$per_page = 100;
		$data = [];

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		// ONE concatenated list: table rows first, then this user's
		// buffered option-store rows. No special cases.
		$table_count = 0;
		if ( self::audit_table_exists() ) {
			$table_name = self::get_audit_table_name();
			$table_count = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", $user->ID )
			);
		}

		$matching = [];
		$buffered = get_option( self::AUDIT_KEY, [] );
		foreach ( (array) $buffered as $index => $entry ) {
			if ( is_array( $entry ) && absint( $entry['user_id'] ?? 0 ) === $user->ID ) {
				$matching[ absint( $index ) ] = $entry;
			}
		}

		$total = $table_count + count( $matching );
		$start = ( $page - 1 ) * $per_page;

		// Table part.
		if ( $start < $table_count && self::audit_table_exists() ) {
			$table_name = self::get_audit_table_name();
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, created_at, action, post_id, details FROM {$table_name} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
					$user->ID,
					$per_page,
					$start
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $row ) {
				$details = json_decode( (string) ( $row['details'] ?? '' ), true );
				$data[] = [
					'group_id' => 'struo_audit_log',
					'group_label' => __( 'Struo Audit Log', self::TEXT_DOMAIN ),
					'item_id' => 'struo-audit-' . absint( $row['id'] ),
					'data' => [
						[
							'name' => __( 'Timestamp', self::TEXT_DOMAIN ),
							'value' => (string) ( $row['created_at'] ?? '' ),
						],
						[
							'name' => __( 'Action', self::TEXT_DOMAIN ),
							'value' => (string) ( $row['action'] ?? '' ),
						],
						[
							'name' => __( 'Post ID', self::TEXT_DOMAIN ),
							'value' => (string) absint( $row['post_id'] ?? 0 ),
						],
						[
							'name' => __( 'Details', self::TEXT_DOMAIN ),
							'value' => is_array( $details ) ? wp_json_encode( $details ) : '',
						],
					],
				];
			}
		}

		// Buffer part: fill the remainder of the page from the offset past
		// the table rows.
		$remaining = $per_page - count( $data );
		if ( $remaining > 0 ) {
			$buffer_offset = max( 0, $start - $table_count );
			$page_slice = array_slice( $matching, $buffer_offset, $remaining, true );

			foreach ( $page_slice as $index => $entry ) {
				$data[] = [
					'group_id' => 'struo_audit_log',
					'group_label' => __( 'Struo Audit Log', self::TEXT_DOMAIN ),
					'item_id' => 'struo-audit-buffered-' . $index,
					'data' => [
						[
							'name' => __( 'Timestamp', self::TEXT_DOMAIN ),
							'value' => (string) ( $entry['timestamp'] ?? '' ),
						],
						[
							'name' => __( 'Action', self::TEXT_DOMAIN ),
							'value' => (string) ( $entry['action'] ?? '' ),
						],
						[
							'name' => __( 'Post ID', self::TEXT_DOMAIN ),
							'value' => (string) absint( $entry['details']['post_id'] ?? 0 ),
						],
						[
							'name' => __( 'Details', self::TEXT_DOMAIN ),
							'value' => wp_json_encode( $entry['details'] ?? [] ),
						],
					],
				];
			}
		}

		$done = ( $start + count( $data ) ) >= $total;

		return [
			'data' => $data,
			'done' => $done,
		];
	}

	/**
	 * Personal-data eraser (lane 3b item 4): anonymises the user id on this
	 * user's audit rows to 0 — action/post integrity is preserved for the
	 * forensic trail; no raw content is removed or altered.
	 */
	public static function register_audit_data_eraser( $erasers ) {
		$erasers['struo-audit-log'] = [
			'eraser_friendly_name' => __( 'Struo Audit Log', self::TEXT_DOMAIN ),
			'callback' => [ __CLASS__, 'anonymise_audit_rows_for_user' ],
		];
		return $erasers;
	}

	public static function anonymise_audit_rows_for_user( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', $email_address );
		$removed = 0;
		$retained = [];

		if ( $user && self::audit_table_exists() ) {
			$table_name = self::get_audit_table_name();
			$removed = (int) $wpdb->query(
				$wpdb->prepare( "UPDATE {$table_name} SET user_id = 0 WHERE user_id = %d", $user->ID )
			);
		}

		// Option-store fallback: anonymise the buffered ring buffer too.
		$buffered = get_option( self::AUDIT_KEY, [] );
		if ( $user && is_array( $buffered ) && ! empty( $buffered ) ) {
			$changed = 0;
			foreach ( $buffered as &$entry ) {
				if ( is_array( $entry ) && absint( $entry['user_id'] ?? 0 ) === $user->ID ) {
					$entry['user_id'] = 0;
					$changed++;
				}
			}
			unset( $entry );
			if ( $changed > 0 ) {
				update_option( self::AUDIT_KEY, $buffered, false );
				$removed += $changed;
			}
		}

		if ( $user ) {
			$retained[] = sprintf(
				/* translators: %d: number of anonymised audit rows. */
				__( 'Anonymised %d audit rows (user id set to 0).', self::TEXT_DOMAIN ),
				$removed
			);
		}

		return [
			'items_removed' => $removed > 0,
			'retained_messages' => $retained,
			'messages' => [],
			'done' => true,
		];
	}

	public static function register_plan_data_exporter( $exporters ) {
		$exporters['struo-plans'] = [
			'exporter_friendly_name' => __( 'Struo Plans', self::TEXT_DOMAIN ),
			'callback' => [ __CLASS__, 'export_plan_rows_for_user' ],
		];
		return $exporters;
	}

	public static function export_plan_rows_for_user( $email_address, $page = 1 ) {
		global $wpdb;

		$page = max( 1, absint( $page ) );
		$per_page = 100;
		$data = [];

		$user = get_user_by( 'email', $email_address );
		if ( ! $user || ! self::plans_table_exists() ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		$table = self::get_plans_table_name();
		$offset = ( $page - 1 ) * $per_page;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT plan_id, payload_type, state, created_at, post_id, origin, operation, request_text, payload_json, base_content FROM {$table} WHERE user_id = %d ORDER BY created_at ASC, plan_id ASC LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$data[] = [
				'group_id' => 'struo_plans',
				'group_label' => __( 'Struo Plans', self::TEXT_DOMAIN ),
				'item_id' => 'struo-plan-' . sanitize_text_field( (string) ( $row['plan_id'] ?? '' ) ),
				'data' => [
					[
						'name' => __( 'Plan ID', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['plan_id'] ?? '' ),
					],
					[
						'name' => __( 'State', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['state'] ?? '' ),
					],
					[
						'name' => __( 'Created', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['created_at'] ?? '' ),
					],
					[
						'name' => __( 'Post ID', self::TEXT_DOMAIN ),
						'value' => (string) absint( $row['post_id'] ?? 0 ),
					],
					[
						'name' => __( 'Origin', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['origin'] ?? '' ),
					],
					[
						'name' => __( 'Operation', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['operation'] ?? '' ),
					],
					[
						'name' => __( 'Request', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['request_text'] ?? '' ),
					],
					[
						'name' => __( 'Payload', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['payload_json'] ?? '' ),
					],
					[
						'name' => __( 'Base content', self::TEXT_DOMAIN ),
						'value' => (string) ( $row['base_content'] ?? '' ),
					],
				],
			];
			if ( class_exists( 'Struo_Mutation_Journal' ) ) {
				$plan_id = sanitize_text_field( (string) ( $row['plan_id'] ?? '' ) );
				$events_json = Struo_Mutation_Journal::export_for_plan( $plan_id );
				$data[ count( $data ) - 1 ]['data'][] = [
					'name' => __( 'Mutation events', self::TEXT_DOMAIN ),
					'value' => $events_json,
				];
			}
		}

		return [
			'data' => $data,
			'done' => count( $rows ) < $per_page,
		];
	}

	public static function register_plan_data_eraser( $erasers ) {
		$erasers['struo-plans'] = [
			'eraser_friendly_name' => __( 'Struo Plans', self::TEXT_DOMAIN ),
			'callback' => [ __CLASS__, 'erase_plan_rows_for_user' ],
		];
		return $erasers;
	}

	public static function erase_plan_rows_for_user( $email_address, $page = 1 ) {
		global $wpdb;

		unset( $page );
		$user = get_user_by( 'email', $email_address );
		$removed = 0;
		$done = true;

		if ( $user && self::plans_table_exists() ) {
			$table = self::get_plans_table_name();
			$plan_ids = $wpdb->get_col(
				$wpdb->prepare( "SELECT plan_id FROM {$table} WHERE user_id = %d LIMIT 100", $user->ID )
			);
			if ( is_array( $plan_ids ) && class_exists( 'Struo_Mutation_Journal' ) ) {
				Struo_Mutation_Journal::erase_for_plan_ids( $plan_ids );
			}
			$removed = (int) $wpdb->query(
				$wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d LIMIT 100", $user->ID )
			);
			$remaining = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user->ID )
			);
			$done = $remaining < 1;
		}

		return [
			'items_removed' => $removed > 0,
			'items_retained' => false,
			'messages' => [],
			'done' => $done,
		];
	}

	public static function run_audit_retention_prune() {
		return self::prune_audit_rows_by_age();
	}

	/**
	 * Self-healing schedule: the daily retention prune must exist whenever
	 * the plugin is active, even if activation ran on another site.
	 */
	public static function maybe_schedule_audit_retention_prune() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( false === wp_next_scheduled( self::AUDIT_RETENTION_CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::AUDIT_RETENTION_CRON_HOOK );
		}
	}

	private static function summarize_blocks( array $blocks, array $path = [] ) {
		$summary = [];

		foreach ( $blocks as $index => $block ) {
			$current_path = array_merge( $path, [ $index ] );
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			$block_name = (string) ( $block['blockName'] ?? '' );

			$entry = [
				'index_path' => $current_path,
				'block_name' => $block_name,
				'anchor' => $attrs['anchor'] ?? null,
				'block_id' => $attrs['id'] ?? null,
				'attrs' => $attrs,
				'inner_blocks' => ! empty( $block['innerBlocks'] )
					? self::summarize_blocks( $block['innerBlocks'], $current_path )
					: [],
			];

			if ( self::is_core_block( $block_name ) ) {
				$entry['fields'] = self::extract_core_block_fields( $block );
			}

			$summary[] = $entry;
		}

		return $summary;
	}

	public static function record_legacy_rest_route( $route ) {
		self::audit_log(
			'legacy_rest_route',
			[ 'route' => sanitize_text_field( (string) $route ) ]
		);
	}

	private static function audit_log( $action, array $details = [] ) {
		if ( defined( 'STRUO_EVAL_HARNESS' ) && STRUO_EVAL_HARNESS ) {
			$details['eval_harness'] = 1;
		}

		$written = self::insert_audit_table_entry( $action, $details );
		if ( $written ) {
			return;
		}

		$entries = get_option( self::AUDIT_KEY, [] );
		if ( ! is_array( $entries ) ) {
			$entries = [];
		}

		$entries[] = [
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'action' => sanitize_text_field( $action ),
			'details' => $details,
		];

		if ( count( $entries ) > self::AUDIT_LIMIT ) {
			$entries = array_slice( $entries, -1 * self::AUDIT_LIMIT );
		}

		update_option( self::AUDIT_KEY, $entries, false );
	}
}


require_once __DIR__ . '/includes/class-operation-catalog.php';
require_once __DIR__ . '/includes/class-authority.php';
require_once __DIR__ . '/includes/class-durable-plans.php';
require_once __DIR__ . '/includes/class-mutation-journal.php';
require_once __DIR__ . '/includes/class-mutation-recovery.php';
require_once __DIR__ . '/includes/class-findings.php';
require_once __DIR__ . '/includes/class-abilities.php';

if ( class_exists( 'Struo_Durable_Plans' ) ) {
	Struo_Durable_Plans::use_store( new Struo_Durable_Plan_Wpdb_Store() );
}

register_activation_hook( __FILE__, [ 'Struo_Block_Editor', 'on_activation' ] );
register_deactivation_hook( __FILE__, [ 'Struo_Block_Editor', 'deactivate' ] );
Struo_Block_Editor::init();

endif;
