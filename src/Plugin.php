<?php
/**
 * Plugin bootstrap seam. struo.php stays the WordPress entry file.
 *
 * @package Struo
 */

namespace Struo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/**
	 * Load extracted modules. WordPress hooks still register from
	 * Struo_Block_Editor::init() until further splits.
	 */
	public static function boot() {
		// Autoload already registered from struo.php.
	}
}
