<?php
/**
 * Rate-limit window constants. The atomic CAS core stays inlined in
 * struo.php so tests/rate-limit-cases.php keep extracting the shipped block.
 *
 * @package Struo
 */

namespace Struo\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RateLimiter {

	public const WRITE_WINDOW_SECONDS = 300;
	public const WRITE_MAX = 30;
	public const PLAN_WINDOW_SECONDS = 3600;
	public const PLAN_MAX = 60;
}
