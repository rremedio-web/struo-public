<?php
/**
 * Durable plan store adapter. Callers ask by intent; this class does not Apply.
 *
 * @package Struo
 */

namespace Struo\Planning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanRepository {

	public static function load( $plan_id ) {
		return \Struo_Durable_Plans::load( $plan_id );
	}

	public static function active_capacity() {
		return \Struo_Durable_Plans::ACTIVE_CAP;
	}
}
