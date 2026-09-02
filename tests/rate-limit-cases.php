<?php
/**
 * Pure-PHP concurrency-ish cases for the atomic rate limiter (no WP).
 *
 * Simulates N interleaved increments against the CAS contract using an
 * array-backed store whose increment_if_below() mirrors the atomic
 * semantics of the real primitives (wp_cache_incr / conditional UPDATE):
 * the read-modify-write is a single indivisible op, so the cap can never
 * be exceeded no matter how many attempts interleave. Exits non-zero on
 * any mismatch.
 *
 * Usage: php tests/rate-limit-cases.php
 */

// Extract the EXACT Struo_Rate_Limit_Cas block shipped in struo.php so the
// pure-PHP cases always test the implementation that runs in production.
$source = file_get_contents( __DIR__ . '/../struo.php' );
$start = strpos( $source, '// BEGIN Struo_Rate_Limit_Cas' );
$end = strpos( $source, '// END Struo_Rate_Limit_Cas' );
if ( false === $start || false === $end || $end <= $start ) {
	echo "FAIL: could not locate the Struo_Rate_Limit_Cas block in struo.php\n";
	exit( 1 );
}
eval( substr( $source, $start, $end - $start ) );
if ( ! class_exists( 'Struo_Rate_Limit_Cas' ) ) {
	echo "FAIL: Struo_Rate_Limit_Cas did not load from struo.php\n";
	exit( 1 );
}

$FAILURES = 0;

function check( $label, $condition, $detail = '' ) {
	global $FAILURES;
	if ( $condition ) {
		echo "PASS: {$label}\n";
	} else {
		echo "FAIL: {$label}" . ( '' !== $detail ? " ({$detail})" : '' ) . "\n";
		$FAILURES++;
	}
}

/**
 * Array-backed store mirroring the atomic primitive semantics:
 * increment_if_below() is one indivisible operation returning the new
 * count, or false when the counter is already at the cap.
 */
final class Rate_Limit_Array_Store {
	public $rows = [];
	public $prune_calls = [];
	public $maybe_prune_calls = 0;
	public $pruned_rows = 0;
	public $stale_rows = [];   // expired window rows awaiting pruning
	public $prune_limit = 200; // bounded rows per pass

	public function create( $key ) {
		if ( ! isset( $this->rows[ $key ] ) ) {
			$this->rows[ $key ] = 0;
		}
	}

	public function maybe_prune( $allowed, $before_start ) {
		$this->maybe_prune_calls++;
		if ( $allowed && 0 !== $this->maybe_prune_calls % 50 ) {
			return;
		}
		$this->prune_calls[] = $before_start;
		$removed = 0;
		foreach ( array_keys( $this->stale_rows ) as $key ) {
			if ( $removed >= $this->prune_limit ) {
				break; // bounded pass
			}
			unset( $this->stale_rows[ $key ] );
			$removed++;
		}
		$this->pruned_rows += $removed;
	}

	public function increment_if_below( $key, $max ) {
		if ( ! isset( $this->rows[ $key ] ) ) {
			$this->rows[ $key ] = 0;
		}
		if ( $this->rows[ $key ] >= $max ) {
			return false;
		}
		$this->rows[ $key ]++;
		return $this->rows[ $key ];
	}

	public function prune( $before_start ) {
		$this->prune_calls[] = $before_start;
		foreach ( $this->rows as $key => $value ) {
			if ( Struo_Rate_Limit_Cas::should_prune( $key, $before_start ) ) {
				unset( $this->rows[ $key ] );
			}
		}
	}
}

function self_rate_max() {
	return 30; // mirrors RATE_LIMIT_MAX for the write-limiter burst case.
}

function make_store( Rate_Limit_Array_Store $s ) {
	return [
		'create'             => static function ( $key ) use ( $s ) { $s->create( $key ); },
		'increment_if_below' => static function ( $key, $max ) use ( $s ) { return $s->increment_if_below( $key, $max ); },
		'maybe_prune'        => static function ( $allowed, $before ) use ( $s ) { $s->maybe_prune( $allowed, $before ); },
	];
}

// 1. Hard cap never exceeded under a burst far above the limit.
$store = new Rate_Limit_Array_Store();
$allowed = 0;
$denied = 0;
for ( $i = 0; $i < 50; $i++ ) {
	$result = Struo_Rate_Limit_Cas::consume_with_store( 'plan_rate_u1', 5, 3600, make_store( $store ), 1000 );
	$result['allowed'] ? $allowed++ : $denied++;
}
check( 'burst of 50 allows exactly 5', 5 === $allowed, "allowed={$allowed}" );
check( 'burst of 50 denies the other 45', 45 === $denied, "denied={$denied}" );
check( 'counter never exceeds the cap', 5 === $store->rows[ Struo_Rate_Limit_Cas::counter_name( 'plan_rate_u1', 0 ) ], 'row=' . $store->rows[ Struo_Rate_Limit_Cas::counter_name( 'plan_rate_u1', 0 ) ] );

// 2. Window rollover: a new window means a fresh key and fresh quota.
$result = Struo_Rate_Limit_Cas::consume_with_store( 'plan_rate_u1', 5, 3600, make_store( $store ), 1000 + 3600 );
check( 'next window allows again', true === $result['allowed'] );
check( 'next window uses a fresh aligned key', 'struo_rl_plan_rate_u1_3600' === Struo_Rate_Limit_Cas::counter_name( 'plan_rate_u1', $result['window_start'] ) );

// 3. Distinct identities are independent.
$store2 = make_store( new Rate_Limit_Array_Store() );
$store2_arr = null;
$a1 = Struo_Rate_Limit_Cas::consume_with_store( 'rate_u1', 3, 60, $store2, 600 );
$b1 = Struo_Rate_Limit_Cas::consume_with_store( 'rate_u2', 3, 60, $store2, 600 );
check( 'user A first slot allowed', true === $a1['allowed'] && 1 === $a1['count'] );
check( 'user B independent of user A', true === $b1['allowed'] && 1 === $b1['count'] );
for ( $i = 0; $i < 10; $i++ ) {
	Struo_Rate_Limit_Cas::consume_with_store( 'rate_u1', 3, 60, $store2, 600 );
}
$b_after = Struo_Rate_Limit_Cas::consume_with_store( 'rate_u2', 3, 60, $store2, 600 );
check( 'exhausting user A does not touch user B', true === $b_after['allowed'], 'count=' . $b_after['count'] );

// 4. Interleaved users/windows: every key respects its own cap exactly.
$store3 = make_store( new Rate_Limit_Array_Store() );
$allowed_by_key = [];
for ( $i = 0; $i < 120; $i++ ) {
	$user = 1 + ( $i % 3 );
	$now = 5000 + 60 * intdiv( $i, 40 ); // rolls into new windows mid-loop
	$result = Struo_Rate_Limit_Cas::consume_with_store( "rate_u{$user}", 4, 60, $store3, $now );
	$key = Struo_Rate_Limit_Cas::counter_name( "rate_u{$user}", $result['window_start'] );
	if ( $result['allowed'] ) {
		$allowed_by_key[ $key ] = ( $allowed_by_key[ $key ] ?? 0 ) + 1;
	}
}
$cap_violations = 0;
foreach ( $allowed_by_key as $key => $count ) {
	if ( $count > 4 ) {
		$cap_violations++;
	}
}
check( 'interleaved keys each respect their cap (max 4)', 0 === $cap_violations, "violations={$cap_violations}" );
check( 'interleaving produced multiple windows', count( $allowed_by_key ) > 3, 'keys=' . count( $allowed_by_key ) );

// 5. Window alignment math.
check( 'window_start aligns down (130, w=60 -> 120)', 120 === Struo_Rate_Limit_Cas::window_start( 60, 130 ) );
check( 'window_start exact boundary stays put (120, w=60 -> 120)', 120 === Struo_Rate_Limit_Cas::window_start( 60, 120 ) );
check( 'window_start floors tiny windows (w=1)', 131 === Struo_Rate_Limit_Cas::window_start( 1, 131 ) );

// 6. Counter naming: sanitized identity + numeric window suffix.
check( 'counter name format', 'struo_rl_plan_rate_u7_1000' === Struo_Rate_Limit_Cas::counter_name( 'plan_rate_u7', 1000 ) );
check( 'counter name sanitizes hostile input', 0 === strpos( Struo_Rate_Limit_Cas::counter_name( 'a b<c>', 5 ), 'struo_rl_abc_' ) );

// 7. Prune predicate truth table.
check( 'prune: older window prunable', true === Struo_Rate_Limit_Cas::should_prune( 'struo_rl_plan_rate_u1_1000', 2000 ) );
check( 'prune: current window kept', false === Struo_Rate_Limit_Cas::should_prune( 'struo_rl_plan_rate_u1_2000', 2000 ) );
check( 'prune: newer window kept', false === Struo_Rate_Limit_Cas::should_prune( 'struo_rl_plan_rate_u1_3000', 2000 ) );
check( 'prune: non-numeric suffix ignored', false === Struo_Rate_Limit_Cas::should_prune( 'struo_rl_bogus', 2000 ) );

// 8. Denials trigger opportunistic pruning.
$store4_arr = new Rate_Limit_Array_Store();
$store4 = make_store( $store4_arr );
for ( $i = 0; $i < 6; $i++ ) {
	Struo_Rate_Limit_Cas::consume_with_store( 'rate_u9', 5, 60, $store4, 1000 );
}
check( 'denial triggers prune pass', count( $store4_arr->prune_calls ) >= 1, 'prunes=' . count( $store4_arr->prune_calls ) );

// 9. Write limiter (check_rate_limit identity) burst: cap holds exactly.
$store5 = make_store( new Rate_Limit_Array_Store() );
$w_allowed = 0;
$w_denied = 0;
for ( $i = 0; $i < 40; $i++ ) {
	$result = Struo_Rate_Limit_Cas::consume_with_store( 'rate_u1', self_rate_max(), 300, $store5, 90000 );
	$result['allowed'] ? $w_allowed++ : $w_denied++;
}
check( 'write-limiter burst of 40 allows exactly 30 (RATE_LIMIT_MAX)', 30 === $w_allowed, "allowed={$w_allowed}" );
check( 'write-limiter burst denies the remaining 10', 10 === $w_denied, "denied={$w_denied}" );

// 10. Cache path: rollback keeps the stored value at or below the cap.
$cell = [ 'value' => 0 ];
$decrs = 0;
$cache_p = [
	'incr' => static function () use ( &$cell ) { $cell['value']++; return $cell['value']; },
	'add'  => static function () use ( &$cell ) { return false; },
	'get'  => static function () use ( &$cell ) { return $cell['value']; },
	'decr' => static function () use ( &$cell, &$decrs ) { $cell['value']--; $decrs++; },
];
$c_allowed = 0;
for ( $i = 0; $i < 200; $i++ ) {
	$count = Struo_Rate_Limit_Cas::cache_increment( 'k', 30, $cache_p );
	if ( false !== $count ) {
		$c_allowed++;
	}
}
check( 'cache burst of 200 allows exactly 30', 30 === $c_allowed, "allowed={$c_allowed}" );
check( 'cache stored value never exceeds max', 30 === $cell['value'], 'stored=' . $cell['value'] );
check( 'every over-cap increment was rolled back', 170 === $decrs, "decrs={$decrs}" );

// 11. Cache eviction: two incr misses fail closed.
$saw_add = false;
$evict_p = [
	'incr' => static function () { return false; },
	'add'  => static function () { return false; },
	'get'  => static function () { return false; },
	'decr' => static function () {},
];
check( 'cache eviction with invisible key fails closed', false === Struo_Rate_Limit_Cas::cache_increment( 'k', 5, $evict_p ) );

// 12. Cache eviction where the key exists: retry incr once succeeds.
$exists_cell = [ 'value' => 2 ];
$recover_p = [
	'incr' => static function () use ( &$tries, &$exists_cell ) { $tries = ( isset( $tries ) ? $tries : 0 ) + 1; return $tries < 2 ? false : ++$exists_cell['value']; },
	'add'  => static function () { return false; }, // add fails: key already exists
	'get'  => static function () use ( &$exists_cell ) { return $exists_cell['value']; },
	'decr' => static function () {},
];
check( 'cache key-exists recovery retries incr once', 3 === Struo_Rate_Limit_Cas::cache_increment( 'k', 5, $recover_p ), 'count=' . $exists_cell['value'] );

// 13. Prune runs on allowed traffic and is bounded per pass.
$store6_arr = new Rate_Limit_Array_Store();
$store6_arr->prune_limit = 200;
for ( $i = 0; $i < 500; $i++ ) {
	$store6_arr->stale_rows[ "struo_rl_old_{$i}" ] = 0;
}
$store6 = make_store( $store6_arr );
for ( $i = 0; $i < 50; $i++ ) {
	Struo_Rate_Limit_Cas::consume_with_store( 'rate_u10', 100, 60, $store6, 7000 + $i );
}
check( 'prune runs on allowed traffic (every 50th call)', $store6_arr->maybe_prune_calls >= 1, 'calls=' . $store6_arr->maybe_prune_calls );
check( 'prune pass is bounded to the configured limit', $store6_arr->pruned_rows <= 200, 'pruned=' . $store6_arr->pruned_rows );

echo $FAILURES > 0
	? "RATE LIMIT CASES RESULT: {$FAILURES} CHECK(S) FAILED\n"
	: "RATE LIMIT CASES RESULT: ALL CHECKS PASSED\n";
exit( $FAILURES > 0 ? 1 : 0 );
