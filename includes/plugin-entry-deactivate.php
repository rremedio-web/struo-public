<?php
/**
 * Load-time deactivation helper (PHP floor + WP floor).
 *
 * Pure-PHP tests load this file under STRUO_GUARD_STANDALONE with mocks.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'STRUO_GUARD_STANDALONE' ) ) {
	exit;
}

if ( ! function_exists( 'struo_deactivate_active_plugin_entry' ) ) {
	/**
	 * Deactivate the loaded plugin file.
	 *
	 * @param string $loaded_file Absolute path of the file that hit the floor (__FILE__).
	 */
	function struo_deactivate_active_plugin_entry( $loaded_file ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			} else {
				return;
			}
		}
		if ( ! function_exists( 'plugin_basename' ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( $loaded_file ) );
	}
}
