<?php
/**
 * REST permission callbacks delegate to the authority seam.
 *
 * @package Struo
 */

namespace Struo\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PermissionPolicy {

	public static function can_read_catalog() {
		return \Struo_Authority::authorize( 'get-block-catalog' );
	}

	public static function can_read_console_status() {
		return \Struo_Authority::authorize( 'console-status' );
	}

	public static function can_struo_plan() {
		return \Struo_Authority::authorize( 'plan-block-change' );
	}
}
