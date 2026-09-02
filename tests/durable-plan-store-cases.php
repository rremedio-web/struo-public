<?php
/**
 * Pure-PHP cases for the durable plan store (no WP).
 *
 * Usage: php tests/durable-plan-store-cases.php
 */

define( 'STRUO_DURABLE_PLANS_STANDALONE', true );
require_once __DIR__ . '/../includes/class-durable-plans.php';

if ( ! class_exists( 'Struo_Durable_Plans' ) ) {
	echo "FAIL: Struo_Durable_Plans did not load\n";
	exit( 1 );
}

$FAILURES = 0;

function check( $label, $condition, $detail = '' ) {
	global $FAILURES;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		return;
	}
	echo "FAIL: {$label}" . ( '' !== $detail ? " ({$detail})" : '' ) . "\n";
	$FAILURES++;
}

function dps_fresh() {
	$store = new Struo_Durable_Plan_Memory_Store();
	Struo_Durable_Plans::use_store( $store );
	return $store;
}

dps_fresh();
$id = Struo_Durable_Plans::create(
	[
		'id' => 'aaaaaaaaaaaaaaaa',
		'payload_type' => 'mutation_v1',
		'state' => 'planned',
		'post_id' => 7,
		'origin' => 'rest',
		'operation' => 'update',
		'payload' => [ 'block_id' => 'b1' ],
	]
);
$loaded = Struo_Durable_Plans::load( 'aaaaaaaaaaaaaaaa' );
check(
	'(dps-e1) in-memory: create then load returns the record',
	'aaaaaaaaaaaaaaaa' === $id
		&& is_array( $loaded )
		&& 'aaaaaaaaaaaaaaaa' === (string) ( $loaded['id'] ?? '' )
		&& 'planned' === (string) ( $loaded['state'] ?? '' )
		&& 7 === (int) ( $loaded['post_id'] ?? 0 )
		&& 'update' === (string) ( $loaded['operation'] ?? '' )
		&& 'b1' === (string) ( $loaded['payload']['block_id'] ?? '' ),
	'id=' . var_export( $id, true )
);
check( '(dps-e1) unknown id is null', null === Struo_Durable_Plans::load( 'ffffffffffffffff' ) );

dps_fresh();
Struo_Durable_Plans::create(
	[
		'id' => 'bbbbbbbbbbbbbbbb',
		'state' => 'planned',
		'expires_at' => time() + 3600,
	]
);
Struo_Durable_Plans::create(
	[
		'id' => 'bbbbbbbbbbbbbbba',
		'state' => 'planned',
		'expires_at' => time() + 3600,
	]
);
$expired_ok = Struo_Durable_Plans::expire( 'bbbbbbbbbbbbbbbb' );
$applied_illegal = Struo_Durable_Plans::finish( 'bbbbbbbbbbbbbbba' );
check(
	'(dps-e2) in-memory: planned → expire is legal; planned → applied is not',
	true === $expired_ok
		&& false === $applied_illegal
		&& null === Struo_Durable_Plans::load( 'bbbbbbbbbbbbbbbb' )
		&& is_array( Struo_Durable_Plans::load( 'bbbbbbbbbbbbbbba' ) )
);

dps_fresh();
Struo_Durable_Plans::create(
	[
		'id' => 'cccccccccccccccc',
		'state' => 'planned',
		'expires_at' => time() - 10,
	]
);
Struo_Durable_Plans::create(
	[
		'id' => 'dddddddddddddddd',
		'state' => 'planned',
		'expires_at' => time() + 3600,
	]
);
Struo_Durable_Plans::prune();
check(
	'(dps-e3) in-memory: prune expires due rows through expire, not a side channel',
	null === Struo_Durable_Plans::load( 'cccccccccccccccc' )
		&& is_array( Struo_Durable_Plans::load( 'dddddddddddddddd' ) )
		&& false === Struo_Durable_Plans::finish( 'cccccccccccccccc' )
);

dps_fresh();
for ( $i = 0; $i < Struo_Durable_Plans::ACTIVE_CAP; $i++ ) {
	Struo_Durable_Plans::create(
		[
			'id' => sprintf( '%016x', $i + 1 ),
			'state' => 'planned',
			'expires_at' => time() + 3600,
		]
	);
}
$over = Struo_Durable_Plans::create(
	[
		'id' => 'eeeeeeeeeeeeeeee',
		'state' => 'planned',
		'expires_at' => time() + 3600,
	]
);
$still = Struo_Durable_Plans::load( sprintf( '%016x', 1 ) );
check(
	'(dps-e4) in-memory: capacity refuses when active count is at cap',
	false === $over && is_array( $still )
);

dps_fresh();
Struo_Durable_Plans::create(
	[
		'id' => '1111111111111111',
		'payload_type' => 'bundle_v1',
		'state' => 'planned',
		'expires_at' => time() + 3600,
		'payload' => [ 'child_ids' => [ '2222222222222222', '3333333333333333' ] ],
	]
);
Struo_Durable_Plans::create(
	[
		'id' => '2222222222222222',
		'state' => 'planned',
		'expires_at' => time() + 3600,
		'payload' => [ 'bundle_id' => '1111111111111111' ],
	]
);
Struo_Durable_Plans::create(
	[
		'id' => '3333333333333333',
		'state' => 'planned',
		'expires_at' => time() + 3600,
		'payload' => [ 'bundle_id' => '1111111111111111' ],
	]
);
$family = Struo_Durable_Plans::family_ids( '1111111111111111' );
Struo_Durable_Plans::cancel_ids( $family );
check(
	'(dps-e5) family cancel expires/cancels children through cancel',
	3 === count( $family )
		&& in_array( '2222222222222222', $family, true )
		&& in_array( '3333333333333333', $family, true )
		&& null === Struo_Durable_Plans::load( '1111111111111111' )
		&& null === Struo_Durable_Plans::load( '2222222222222222' )
		&& null === Struo_Durable_Plans::load( '3333333333333333' )
		&& false === Struo_Durable_Plans::approve( '2222222222222222' )
);

echo $FAILURES > 0
	? "DURABLE PLAN STORE CASES RESULT: {$FAILURES} CHECK(S) FAILED\n"
	: "DURABLE PLAN STORE CASES RESULT: ALL CHECKS PASSED\n";
exit( $FAILURES > 0 ? 1 : 0 );
