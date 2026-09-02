<?php
/**
 * Uninstall cleanup for the Struo plugin.
 *
 * Deletes plugin options and transient families. Leftover keys from
 * older private builds (`subsurface_ai_*` options and `sae_*` transients)
 * are removed here if they still exist. That is uninstall hygiene only:
 * this public plugin never reads those keys at runtime. Fresh installs
 * have nothing to delete. The audit
 * log table is preserved unless STRUO_DROP_AUDIT_TABLE_ON_UNINSTALL
 * (or SAE_DROP_AUDIT_TABLE_ON_UNINSTALL) is defined as true in
 * wp-config.php. The plans table is preserved unless
 * STRUO_DROP_PLANS_TABLE_ON_UNINSTALL (or SAE_DROP_PLANS_TABLE_ON_UNINSTALL)
 * is defined as true. The audit-drop constant does not drop plans.
 *
 * The sae_plan_rate_ family is cleaned here but deliberately NOT on
 * deactivation, so toggling the plugin cannot reset plan rate-limit quotas.
 *
 * Option and transient names mirror the constants declared in struo.php;
 * this file runs without the plugin loaded, so the literals are repeated
 * here on purpose.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$struo_option_names = [
	'struo_options',
	'struo_legacy_seeds',
	'struo_audit',
	'struo_audit_db_version',
	'struo_audit_migrated',
	'struo_openai_api_key',
	'struo_google_service_account',
	'struo_ga4_property_id',
	'struo_gsc_site_url',
	'struo_user_templates',
	'struo_template_registry_v2',
	'struo_pattern_registry_v1',
	'struo_options_migrated_v1',
	'struo_activation_handoff_notice',
	'struo_transients_migrated_v1',
	'struo_openai_key_host',
	'struo_planner_last_hop',
	'struo_agent_plans',
	'struo_plans_db_version',
	'struo_plans_agent_migrated_v1',
	'struo_caps_migrated_v1',
	'struo_events_db_version',
];

// Legacy generation (never deleted by the migration itself; removed here).
$legacy_struo_option_names = [
	'subsurface_ai_block_editor_options',
	'subsurface_ai_block_editor_legacy_seeds',
	'subsurface_ai_block_editor_audit',
	'subsurface_ai_block_editor_audit_db_version',
	'subsurface_ai_block_editor_audit_migrated',
	'subsurface_ai_block_editor_openai_api_key',
	'subsurface_ai_block_editor_user_templates',
	'subsurface_ai_block_editor_template_registry_v2',
	'subsurface_ai_block_editor_pattern_registry_v1',
];

foreach ( array_merge( $struo_option_names, $legacy_struo_option_names ) as $struo_option_name ) {
	delete_option( $struo_option_name );
}

$struo_transient_prefixes = [
	// Current generation.
	'struo_confirm_',
	'struo_create_plan_',
	'struo_idempotency_',
	'struo_plan_rate_',
	'struo_rate_',
	'struo_session_',
	'struo_findings_',
	// Legacy generation (pre-rename rows that outlived the migration).
	'sae_confirm_',
	'sae_create_plan_',
	'sae_idempotency_',
	'sae_plan_rate_',
	'sae_rate_',
	'sae_session_',
];

global $wpdb;

$struo_rows = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_sae_' ) . '%',
		$wpdb->esc_like( '_site_transient_sae_' ) . '%',
		$wpdb->esc_like( '_transient_struo_' ) . '%',
		$wpdb->esc_like( '_site_transient_struo_' ) . '%'
	)
);

if ( is_array( $struo_rows ) ) {
	$struo_names = [];
	foreach ( $struo_rows as $struo_row ) {
		$struo_transient = preg_replace(
			'/^_site_transient_timeout_|^_site_transient_|^_transient_timeout_|^_transient_/',
			'',
			(string) $struo_row
		);
		if ( '' === $struo_transient || in_array( $struo_transient, $struo_names, true ) ) {
			continue;
		}

		$struo_matches = false;
		foreach ( $struo_transient_prefixes as $struo_prefix ) {
			if ( 0 === strpos( $struo_transient, $struo_prefix ) ) {
				$struo_matches = true;
				break;
			}
		}
		if ( ! $struo_matches ) {
			continue;
		}

		$struo_names[] = $struo_transient;
		delete_transient( $struo_transient );
	}
}

// Site transients: on single-site installs the site-transient cache lives
// in the options table too; delete those rows directly so neither sae_*
// nor struo_* state survives uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_site_transient_sae_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_sae_' ) . '%',
		$wpdb->esc_like( '_site_transient_struo_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_struo_' ) . '%'
	)
);

$struo_spend_rows = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'struo_plan_spend_' ) . '%'
	)
);
if ( is_array( $struo_spend_rows ) ) {
	foreach ( $struo_spend_rows as $struo_spend_row ) {
		delete_option( (string) $struo_spend_row );
	}
}

$struo_drop_audit = false;
if ( defined( 'SAE_DROP_AUDIT_TABLE_ON_UNINSTALL' ) ) {
	$struo_drop_audit = (bool) SAE_DROP_AUDIT_TABLE_ON_UNINSTALL;
} elseif ( defined( 'STRUO_DROP_AUDIT_TABLE_ON_UNINSTALL' ) ) {
	$struo_drop_audit = (bool) STRUO_DROP_AUDIT_TABLE_ON_UNINSTALL;
}

if ( $struo_drop_audit ) {
	$struo_table = $wpdb->prefix . 'sae_audit_log';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is built from the sanitized WP prefix.
	$wpdb->query( "DROP TABLE IF EXISTS `{$struo_table}`" );
}

$struo_drop_plans = false;
if ( defined( 'SAE_DROP_PLANS_TABLE_ON_UNINSTALL' ) ) {
	$struo_drop_plans = (bool) SAE_DROP_PLANS_TABLE_ON_UNINSTALL;
} elseif ( defined( 'STRUO_DROP_PLANS_TABLE_ON_UNINSTALL' ) ) {
	$struo_drop_plans = (bool) STRUO_DROP_PLANS_TABLE_ON_UNINSTALL;
}

if ( $struo_drop_plans ) {
	$struo_plans_table = $wpdb->prefix . 'struo_plans';
	$struo_events_table = $wpdb->prefix . 'struo_events';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is built from the sanitized WP prefix.
	$wpdb->query( "DROP TABLE IF EXISTS `{$struo_plans_table}`" );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is built from the sanitized WP prefix.
	$wpdb->query( "DROP TABLE IF EXISTS `{$struo_events_table}`" );
}
