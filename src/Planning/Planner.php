<?php
/**
 * Planning seam. HTTP last-resort hops live in OpenAICompatibleProvider.
 *
 * @package Struo
 */

namespace Struo\Planning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Planner {

	/**
	 * Durable plan identity is owned by PlanRepository / Struo_Durable_Plans.
	 * Compile and preview still run in Struo_Block_Editor until later splits.
	 */
	public static function repository() {
		return PlanRepository::class;
	}
}
