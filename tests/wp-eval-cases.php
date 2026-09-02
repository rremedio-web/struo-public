<?php
/**
 * Runtime defense-in-depth cases for the MCP dry-run token suppression.
 *
 * Run on a WP install (struo.local) as an admin so permissions and the
 * allowlist are satisfiable:
 *
 *   wp eval-file tests/wp-eval-cases.php --user=<admin>
 *
 * Exits non-zero (via wp_die-style die()) on any failure. No secrets are
 * printed; tokens are never echoed in full.
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "FAIL: must run inside WordPress (wp eval-file).\n";
	exit( 1 );
}

if ( ! defined( 'STRUO_EVAL_HARNESS' ) ) {
	define( 'STRUO_EVAL_HARNESS', true );
}

// Residue sweep runs UNCONDITIONALLY at the top: stale "[struo-eval-temp]"
// posts from earlier failed runs are removed before anything else, even if
// a later section exits early.
$GLOBALS['struo_eval_temp_posts'] = [];
function struo_eval_sweep_stale_temp_posts() {
	global $wpdb;
	$stale = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE '[struo-eval-temp]%' AND post_type IN ( 'page', 'post' )"
	);
	$stale_ids = array_values( array_filter( array_map( 'absint', (array) $stale ) ) );
	if ( ! empty( $stale_ids ) && struo_eval_table_exists( struo_eval_plans_table() ) ) {
		$table = struo_eval_plans_table();
		$placeholders = implode( ', ', array_fill( 0, count( $stale_ids ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET state = 'cancelled' WHERE post_id IN ({$placeholders}) AND state IN ('planned', 'approved')",
				$stale_ids
			)
		);
	}
	foreach ( $stale_ids as $stale_id ) {
		wp_delete_post( (int) $stale_id, true );
	}
	if ( ! empty( $stale_ids ) ) {
		echo 'NOTE: swept ' . count( $stale_ids ) . " stale [struo-eval-temp] post(s) from earlier runs.\n";
	}
}
struo_eval_sweep_stale_temp_posts();
// Transactional cleanup registry: registered BEFORE anything is created
// so the shutdown handler always restores state, even on failure/exit.
$GLOBALS['struo_eval_cleanup'] = [
	'option_saved' => false,
	'option_value' => null,
	'agent_plans_saved' => false,
	'agent_plans_value' => null,
	'post_ids' => [],
	'user_ids' => [],
	'plans_saved' => false,
	'plans_rows' => [],
	'plans_sample_id' => '',
	'created_plan_ids' => [],
	'audit_saved' => false,
	'audit_max_id' => 0,
	'done' => false,
];

function struo_eval_cleanup() {
	if ( ! empty( $GLOBALS['struo_eval_cleanup']['done'] ) ) {
		return;
	}
	$GLOBALS['struo_eval_cleanup']['done'] = true;

	if ( ! empty( $GLOBALS['struo_eval_cleanup']['option_saved'] ) ) {
		$value = $GLOBALS['struo_eval_cleanup']['option_value'];
		if ( null === $value ) {
			delete_option( 'struo_options' );
		} else {
			update_option( 'struo_options', $value, false );
		}
	}

	if ( ! empty( $GLOBALS['struo_eval_cleanup']['agent_plans_saved'] ) ) {
		$plans = $GLOBALS['struo_eval_cleanup']['agent_plans_value'];
		if ( null === $plans || false === $plans ) {
			delete_option( 'struo_agent_plans' );
		} else {
			update_option( 'struo_agent_plans', $plans, false );
		}
	}

	foreach ( $GLOBALS['struo_eval_cleanup']['post_ids'] as $pid ) {
		wp_delete_post( (int) $pid, true );
	}

	foreach ( (array) ( $GLOBALS['struo_eval_cleanup']['user_ids'] ?? [] ) as $uid ) {
		if ( function_exists( 'wp_delete_user' ) ) {
			wp_delete_user( (int) $uid );
		}
	}

	struo_eval_restore_durable_state();
}

register_shutdown_function( 'struo_eval_cleanup' );



$GLOBALS['struo_eval_failures'] = 0;
function check( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		echo "PASS: {$label}\n";
		return;
	}
	echo "FAIL: {$label}" . ( '' !== $detail ? " ({$detail})" : '' ) . "\n";
	$GLOBALS['struo_eval_failures']++;
}

function print_summary_and_exit() {
	$FAILURES = (int) $GLOBALS['struo_eval_failures'];
	echo $FAILURES > 0
		? "WP EVAL CASES RESULT: {$FAILURES} CHECK(S) FAILED\n"
		: "WP EVAL CASES RESULT: ALL CHECKS PASSED\n";
	exit( $FAILURES > 0 ? 1 : 0 );
}

function count_confirm_transients() {
	global $wpdb;
	$live = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_struo_confirm_' ) . '%'
		)
	);
	$legacy = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_sae_confirm_' ) . '%'
		)
	);
	return $live + $legacy;
}

function struo_eval_rest( $method, $route, $params = [] ) {
	rest_get_server();
	$request = new WP_REST_Request( $method, $route );
	if ( ! empty( $params ) ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

function struo_eval_rest_status( $response ) {
	if ( $response instanceof WP_REST_Response ) {
		return (int) $response->get_status();
	}
	if ( is_wp_error( $response ) ) {
		$data = $response->get_error_data();
		return absint( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
	}
	return 0;
}

function struo_eval_rest_data( $response ) {
	if ( $response instanceof WP_REST_Response ) {
		$data = $response->get_data();
		$data = is_array( $data ) ? $data : [];
		struo_eval_track_plans_from_value( $data );
		return $data;
	}
	if ( is_wp_error( $response ) ) {
		$err = $response->get_error_data();
		$out = [
			'code' => $response->get_error_code(),
			'message' => $response->get_error_message(),
		];
		if ( is_array( $err ) ) {
			$out['data'] = $err;
			struo_eval_track_plans_from_value( $err );
		}
		return $out;
	}
	return [];
}

function struo_eval_rest_code( $response ) {
	$data = struo_eval_rest_data( $response );
	return sanitize_text_field( (string) ( $data['code'] ?? '' ) );
}

function struo_eval_rest_confirmation( array $data ) {
	if ( is_array( $data['confirmation'] ?? null ) ) {
		return $data['confirmation'];
	}
	if ( is_array( $data['dry_run']['confirmation'] ?? null ) ) {
		return $data['dry_run']['confirmation'];
	}
	return [];
}

function struo_eval_plans_table() {
	global $wpdb;
	return $wpdb->prefix . 'struo_plans';
}

function struo_eval_audit_table() {
	global $wpdb;
	return $wpdb->prefix . 'sae_audit_log';
}

function struo_eval_table_exists( $table ) {
	global $wpdb;
	$like = $wpdb->esc_like( $table );
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	return $found === $table;
}

function struo_eval_snapshot_durable_state() {
	global $wpdb;
	$plans = struo_eval_plans_table();
	$audit = struo_eval_audit_table();
	if ( struo_eval_table_exists( $plans ) ) {
		$rows = $wpdb->get_results( "SELECT * FROM {$plans}", ARRAY_A );
		$GLOBALS['struo_eval_cleanup']['plans_saved'] = true;
		$GLOBALS['struo_eval_cleanup']['plans_rows'] = is_array( $rows ) ? $rows : [];
		$GLOBALS['struo_eval_cleanup']['plans_sample_id'] = (string) ( ( $rows[0]['plan_id'] ?? '' ) );
	}
	if ( struo_eval_table_exists( $audit ) ) {
		$GLOBALS['struo_eval_cleanup']['audit_saved'] = true;
		$GLOBALS['struo_eval_cleanup']['audit_max_id'] = absint( $wpdb->get_var( "SELECT MAX(id) FROM {$audit}" ) );
	}
}

function struo_eval_track_plan_id( $id ) {
	$id = is_scalar( $id ) ? sanitize_text_field( (string) $id ) : '';
	if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
		return;
	}
	if ( ! isset( $GLOBALS['struo_eval_cleanup']['created_plan_ids'] ) || ! is_array( $GLOBALS['struo_eval_cleanup']['created_plan_ids'] ) ) {
		$GLOBALS['struo_eval_cleanup']['created_plan_ids'] = [];
	}
	$GLOBALS['struo_eval_cleanup']['created_plan_ids'][ $id ] = true;
}

function struo_eval_insert_plan_fixture( array $overrides = [] ) {
	global $wpdb;

	$id = bin2hex( random_bytes( 8 ) );
	$now = time();
	$row = array_merge(
		[
			'plan_id' => $id,
			'payload_type' => 'mutation_v1',
			'state' => 'planned',
			'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', $now + 3600 ),
			'user_id' => get_current_user_id(),
			'post_id' => 0,
			'origin' => 'rest',
			'operation' => 'update',
			'endpoint' => '/wp-json/struo/v1/posts/0/blocks/update',
			'request_text' => 's0b-eval',
			'payload_json' => '{}',
			'post_json' => '{}',
			'base_content_hash' => '',
		],
		$overrides
	);
	$inserted = false !== $wpdb->insert( struo_eval_plans_table(), $row );
	if ( $inserted ) {
		struo_eval_track_plan_id( (string) $row['plan_id'] );
		return (string) $row['plan_id'];
	}

	return '';
}

function struo_eval_active_plan_count() {
	global $wpdb;

	$table = struo_eval_plans_table();
	$now = gmdate( 'Y-m-d H:i:s', time() );

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE state IN ('planned', 'approved') AND expires_at > %s",
			$now
		)
	);
}

function struo_eval_hex_plan_ids( $raw ) {
	$ids = [];
	foreach ( (array) $raw as $id ) {
		$id = is_scalar( $id ) ? (string) $id : '';
		if ( 1 === preg_match( '/^[a-f0-9]{16}$/', $id ) && ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

function struo_eval_plan_family_ids_from_row( $row ) {
	$ids = [];
	if ( ! is_array( $row ) ) {
		return $ids;
	}
	$ids = struo_eval_hex_plan_ids( [ $row['plan_id'] ?? '' ] );
	$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
	if ( ! is_array( $payload ) ) {
		return $ids;
	}
	$ids = array_merge(
		$ids,
		struo_eval_hex_plan_ids( [ $payload['bundle_id'] ?? '' ] ),
		struo_eval_hex_plan_ids( $payload['child_ids'] ?? [] ),
		struo_eval_hex_plan_ids( $payload['selected_ids'] ?? [] )
	);
	return struo_eval_hex_plan_ids( $ids );
}

function struo_eval_cancel_plan_ids( array $ids ) {
	global $wpdb;

	$ids = struo_eval_hex_plan_ids( $ids );
	if ( empty( $ids ) || ! struo_eval_table_exists( struo_eval_plans_table() ) ) {
		return;
	}

	$table = struo_eval_plans_table();
	$seen = [];
	$queue = $ids;
	while ( ! empty( $queue ) ) {
		$chunk = array_splice( $queue, 0, 50 );
		$placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT plan_id, payload_json FROM {$table} WHERE plan_id IN ({$placeholders})",
				$chunk
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			foreach ( struo_eval_plan_family_ids_from_row( $row ) as $id ) {
				if ( ! isset( $seen[ $id ] ) ) {
					$seen[ $id ] = true;
					$queue[] = $id;
				}
			}
		}
		foreach ( $chunk as $id ) {
			$seen[ $id ] = true;
		}
	}

	$cancel = array_keys( $seen );
	foreach ( array_chunk( $cancel, 50 ) as $chunk ) {
		$placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET state = 'cancelled' WHERE plan_id IN ({$placeholders}) AND state IN ('planned', 'approved')",
				$chunk
			)
		);
	}
}

function struo_eval_release_active_plans() {
	global $wpdb;

	if ( ! struo_eval_table_exists( struo_eval_plans_table() ) ) {
		return;
	}

	$table = struo_eval_plans_table();
	$ids = array_keys( (array) ( $GLOBALS['struo_eval_cleanup']['created_plan_ids'] ?? [] ) );
	$post_ids = array_values(
		array_unique(
			array_filter(
				array_map( 'absint', (array) ( $GLOBALS['struo_eval_cleanup']['post_ids'] ?? [] ) )
			)
		)
	);
	if ( ! empty( $post_ids ) ) {
		foreach ( array_chunk( $post_ids, 50 ) as $chunk ) {
			$placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%d' ) );
			$from_posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT plan_id FROM {$table} WHERE post_id IN ({$placeholders}) AND state IN ('planned', 'approved')",
					$chunk
				)
			);
			$ids = array_merge( $ids, (array) $from_posts );
		}
	}
	struo_eval_cancel_plan_ids( $ids );
	struo_eval_sweep_stale_eval_plans();
}

function struo_eval_free_plan_capacity( $needed ) {
	global $wpdb;

	$needed = absint( $needed );
	struo_eval_release_active_plans();
	if ( $needed < 1 || struo_eval_active_plan_count() + $needed <= 25 ) {
		return;
	}
	if ( ! struo_eval_table_exists( struo_eval_plans_table() ) ) {
		return;
	}

	// Park leftover active rows for this eval only. Shutdown restore
	// upserts the pre-eval snapshot, so concurrent operator plans return.
	$table = struo_eval_plans_table();
	$wpdb->query( "UPDATE {$table} SET state = 'cancelled' WHERE state IN ('planned', 'approved')" );
}

function struo_eval_sweep_stale_eval_plans() {
	global $wpdb;

	if ( ! struo_eval_table_exists( struo_eval_plans_table() ) ) {
		return;
	}

	$table = struo_eval_plans_table();
	$wpdb->query(
		"UPDATE {$table}
		SET state = 'cancelled'
		WHERE state IN ('planned', 'approved')
			AND (
				request_text LIKE 's0b-%'
				OR request_text LIKE 'eval-%'
				OR request_text LIKE '%[struo-eval-temp]%'
				OR request_text LIKE '%these allowlisted pages%'
				OR request_text LIKE '%bundle-after%'
				OR request_text LIKE '%bundle-apply%'
				OR request_text LIKE '%bundle-remaining%'
				OR request_text LIKE '%bundle-terminal%'
				OR request_text = 'orphan child'
			)"
	);
}

function struo_eval_track_plans_from_value( $value ) {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $item ) {
		if ( in_array( (string) $key, [ 'plan_id', 'id' ], true ) ) {
			struo_eval_track_plan_id( $item );
		}
		if ( in_array( (string) $key, [ 'child_ids', 'selected_ids', 'approved_child_ids' ], true ) && is_array( $item ) ) {
			foreach ( $item as $plan_id ) {
				struo_eval_track_plan_id( $plan_id );
			}
		}
		if ( is_array( $item ) ) {
			struo_eval_track_plans_from_value( $item );
		}
	}
}

function struo_eval_approve_agent_plan( $id ) {
	$id = (string) $id;
	$got = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $id );
	$data = struo_eval_rest_data( $got );
	$plan = is_array( $data['plan'] ?? null ) ? $data['plan'] : [];
	$body = [];
	if ( 'bundle_v1' === sanitize_key( (string) ( $plan['payload_type'] ?? '' ) ) ) {
		$body['select_revision'] = absint( $plan['select_revision'] ?? 0 );
	}
	return struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $id . '/approve', $body );
}

function struo_eval_restore_durable_state() {
	global $wpdb;
	if ( ! empty( $GLOBALS['struo_eval_cleanup']['plans_saved'] ) ) {
		$plans = struo_eval_plans_table();
		if ( ! struo_eval_table_exists( $plans ) ) {
			return;
		}
		$snapshot_rows = (array) $GLOBALS['struo_eval_cleanup']['plans_rows'];
		$snapshot_ids = [];
		foreach ( $snapshot_rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['plan_id'] ) ) {
				$snapshot_ids[ (string) $row['plan_id'] ] = true;
			}
		}
		$eval_post_ids = [];
		foreach ( (array) ( $GLOBALS['struo_eval_cleanup']['post_ids'] ?? [] ) as $pid ) {
			$eval_post_ids[ absint( $pid ) ] = true;
		}
		$created_ids = (array) ( $GLOBALS['struo_eval_cleanup']['created_plan_ids'] ?? [] );

		$wpdb->query( 'START TRANSACTION' );
		$ok = true;
		foreach ( $snapshot_rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['plan_id'] ) ) {
				continue;
			}
			$insert = array_filter(
				$row,
				static function ( $value ) {
					return null !== $value;
				}
			);
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT plan_id FROM {$plans} WHERE plan_id = %s", $row['plan_id'] ) );
			if ( $exists ) {
				$plan_id = $insert['plan_id'];
				unset( $insert['plan_id'] );
				if ( false === $wpdb->update( $plans, $insert, [ 'plan_id' => $plan_id ] ) ) {
					$ok = false;
					break;
				}
			} elseif ( false === $wpdb->insert( $plans, $insert ) ) {
				$ok = false;
				break;
			}
		}
		if ( $ok ) {
			$current = $wpdb->get_results( "SELECT plan_id, post_id FROM {$plans}", ARRAY_A );
			foreach ( (array) $current as $row ) {
				$plan_id = (string) ( $row['plan_id'] ?? '' );
				if ( '' === $plan_id || isset( $snapshot_ids[ $plan_id ] ) ) {
					continue;
				}
				$post_id = absint( $row['post_id'] ?? 0 );
				$eval_owned = isset( $created_ids[ $plan_id ] ) || ( $post_id > 0 && isset( $eval_post_ids[ $post_id ] ) );
				if ( ! $eval_owned ) {
					continue;
				}
				$deleted = $wpdb->delete( $plans, [ 'plan_id' => $plan_id ], [ '%s' ] );
				if ( false === $deleted ) {
					$ok = false;
					break;
				}
			}
		}
		if ( $ok ) {
			$wpdb->query( 'COMMIT' );
		} else {
			$wpdb->query( 'ROLLBACK' );
		}
	}
	if ( ! empty( $GLOBALS['struo_eval_cleanup']['audit_saved'] ) ) {
		struo_eval_delete_harness_audit_rows();
	}
}

function struo_eval_audit_harness_marker() {
	return '"eval_harness":1';
}

function struo_eval_delete_harness_audit_rows() {
	global $wpdb;
	$audit = struo_eval_audit_table();
	if ( ! struo_eval_table_exists( $audit ) ) {
		return;
	}
	$like = '%' . $wpdb->esc_like( struo_eval_audit_harness_marker() ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE details LIKE %s", $like ) );
}

function struo_eval_reset_disclosure_spend() {
	$GLOBALS['struo_eval_disclosure_spend'] = [
		'rag' => 0,
		'provider' => 0,
	];
	$GLOBALS['struo_eval_http_calls'] = 0;
}

function struo_eval_count_http_request( $preempt, $args, $url ) {
	unset( $preempt, $args, $url );
	$GLOBALS['struo_eval_http_calls'] = absint( $GLOBALS['struo_eval_http_calls'] ?? 0 ) + 1;
	return [
		'headers' => [ 'content-type' => 'application/json' ],
		'body' => '',
		'response' => [ 'code' => 599, 'message' => 'eval-blocked' ],
		'cookies' => [],
		'filename' => null,
	];
}

function struo_eval_begin_spend_probe() {
	struo_eval_reset_disclosure_spend();
	add_filter( 'pre_http_request', 'struo_eval_count_http_request', 0, 3 );
}

function struo_eval_end_spend_probe() {
	remove_filter( 'pre_http_request', 'struo_eval_count_http_request', 0 );
}

function struo_eval_audit_has_plan_origin( $action, $plan_id, $origin ) {
	$req = new WP_REST_Request( 'GET', '/struo/v1/console/audit' );
	$req->set_query_params( [ 'action' => $action, 'per_page' => 100 ] );
	$data = struo_eval_rest_data( rest_do_request( $req ) );
	$plan_id = (string) $plan_id;
	$origin = sanitize_key( (string) $origin );
	if ( ! is_array( $data['items'] ?? null ) ) {
		return false;
	}
	foreach ( $data['items'] as $item ) {
		if ( $action !== sanitize_key( (string) ( $item['action'] ?? '' ) ) ) {
			continue;
		}
		$details = is_array( $item['details'] ?? null ) ? $item['details'] : [];
		if ( $plan_id === (string) ( $details['plan_id'] ?? '' ) && $origin === sanitize_key( (string) ( $details['origin'] ?? '' ) ) ) {
			return true;
		}
	}
	return false;
}

function struo_eval_reset_rate_limiters() {
	global $wpdb;
	$uid = get_current_user_id();
	$write_key = Struo_Rate_Limit_Cas::counter_name( 'rate_u' . $uid, Struo_Rate_Limit_Cas::window_start( 300 ) );
	$plan_key = Struo_Rate_Limit_Cas::counter_name( 'plan_rate_u' . $uid, Struo_Rate_Limit_Cas::window_start( 3600 ) );
	foreach ( [ $write_key, $plan_key ] as $key ) {
		delete_option( $key );
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $key, Struo_Rate_Limit_Cas::CACHE_GROUP );
		}
	}
	$like = $wpdb->esc_like( 'struo_rl_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
}

function struo_eval_insert_bundle_post( $user_id, $slug, $before ) {
	$id = wp_insert_post(
		[
			'post_status' => 'draft',
			'post_title' => '[struo-eval-temp] ' . $slug,
			'post_content' => '<!-- wp:paragraph --><p>' . $before . '</p><!-- /wp:paragraph -->',
			'post_author' => $user_id,
		]
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	$GLOBALS['struo_eval_cleanup']['post_ids'][] = (int) $id;
	return (int) $id;
}

function struo_eval_allowlist_merge( array $ids ) {
	$opts = get_option( 'struo_options', [] );
	if ( ! is_array( $opts ) ) {
		$opts = [];
	}
	$opts['allowed_post_ids'] = array_values(
		array_unique(
			array_merge(
				array_map( 'absint', (array) ( $opts['allowed_post_ids'] ?? [] ) ),
				array_map( 'absint', $ids )
			)
		)
	);
	update_option( 'struo_options', $opts, false );
	if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
		Struo_Block_Editor::flush_options_cache();
	}
	return $opts;
}

function struo_eval_bundle_plan_body( array $extra = [] ) {
	return array_merge(
		[
			'request' => 'Change the paragraph to "bundle-after" on these allowlisted pages.',
			'plan' => [
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 'bundle-after' ],
			],
			'dry_run_preview' => true,
		],
		$extra
	);
}

function struo_eval_post_text( $post_id ) {
	$post = get_post( $post_id );
	return $post ? wp_strip_all_tags( (string) $post->post_content ) : '';
}

function struo_eval_mock_planner_unavailable_http( $preempt, $args, $url ) {
	unset( $preempt, $args, $url );
	return [
		'headers' => [ 'content-type' => 'application/json' ],
		'body' => wp_json_encode( [ 'error' => [ 'message' => 'eval-forced-unavailable' ] ] ),
		'response' => [ 'code' => 429, 'message' => 'Too Many Requests' ],
		'cookies' => [],
		'filename' => null,
	];
}

function struo_eval_disable_wp_ai_client() {
	if ( ! class_exists( 'Struo_Block_Editor' ) ) {
		return;
	}
	$prop = new ReflectionProperty( 'Struo_Block_Editor', 'client_text_generation' );
	$prop->setAccessible( true );
	$prop->setValue( null, false );
}

function struo_eval_begin_planner_fallback() {
	struo_eval_disable_wp_ai_client();
	add_filter( 'pre_http_request', 'struo_eval_mock_planner_unavailable_http', 10, 3 );
}

function struo_eval_create_isolation_user() {
	$login = 'struo_eval_iso_' . wp_generate_password( 6, false );
	$id = wp_insert_user(
		[
			'user_login' => $login,
			'user_pass' => wp_generate_password( 24 ),
			'user_email' => $login . '@example.test',
			'role' => 'author',
		]
	);
	$id = is_wp_error( $id ) ? 0 : absint( $id );
	if ( $id > 0 ) {
		$GLOBALS['struo_eval_cleanup']['user_ids'][] = $id;
	}
	return $id;
}

function struo_eval_other_user_id( $exclude_id ) {
	$exclude_id = absint( $exclude_id );
	$users = get_users( [ 'number' => 50, 'fields' => 'ID' ] );
	foreach ( (array) $users as $candidate ) {
		if ( absint( $candidate ) !== $exclude_id ) {
			return absint( $candidate );
		}
	}
	return struo_eval_create_isolation_user();
}

function struo_eval_ensure_plan_headroom( $need = 3, array $keep_ids = [] ) {
	$need = max( 1, absint( $need ) );
	if ( ! class_exists( 'Struo_Durable_Plans' ) ) {
		return;
	}
	$keep = [];
	foreach ( $keep_ids as $keep_id ) {
		$keep_id = sanitize_text_field( (string) $keep_id );
		if ( '' !== $keep_id ) {
			$keep[ $keep_id ] = true;
		}
	}
	$guard = 0;
	$err = Struo_Durable_Plans::capacity_needed( $need );
	while ( is_wp_error( $err ) && 'sae_plan_capacity' === $err->get_error_code() && $guard < 80 ) {
		$ids = array_keys( (array) ( $GLOBALS['struo_eval_cleanup']['created_plan_ids'] ?? [] ) );
		$id = '';
		foreach ( $ids as $candidate ) {
			$candidate = (string) $candidate;
			if ( isset( $keep[ $candidate ] ) ) {
				continue;
			}
			$id = $candidate;
			break;
		}
		if ( '' === $id ) {
			break;
		}
		unset( $GLOBALS['struo_eval_cleanup']['created_plan_ids'][ $id ] );
		Struo_Durable_Plans::cancel( $id );
		++$guard;
		$err = Struo_Durable_Plans::capacity_needed( $need );
	}
}

function struo_eval_end_planner_fallback() {
	remove_filter( 'pre_http_request', 'struo_eval_mock_planner_unavailable_http', 10 );
}

function struo_eval_crash_apply_filter( $want, $plan_id ) {
	unset( $want );
	if ( (string) $plan_id === (string) ( $GLOBALS['struo_eval_crash_plan'] ?? '' ) ) {
		return sanitize_key( (string) ( $GLOBALS['struo_eval_crash_stage'] ?? '' ) );
	}

	return '';
}

function struo_eval_begin_crash( $plan_id, $stage ) {
	$GLOBALS['struo_eval_crash_plan'] = (string) $plan_id;
	$GLOBALS['struo_eval_crash_stage'] = sanitize_key( (string) $stage );
	add_filter( 'struo_eval_crash_apply', 'struo_eval_crash_apply_filter', 10, 2 );
}

function struo_eval_end_crash() {
	remove_filter( 'struo_eval_crash_apply', 'struo_eval_crash_apply_filter', 10 );
	unset( $GLOBALS['struo_eval_crash_plan'], $GLOBALS['struo_eval_crash_stage'] );
}

function struo_eval_force_ability_mcp_public_true( $public, $name ) {
	unset( $public, $name );
	return true;
}

// (a) Mint with origin mcp via ReflectionMethod: no token key,
// redeemable=false, and NO confirmation transient created.
$minter = new ReflectionMethod( 'Struo_Block_Editor', 'issue_confirmation_token' );
$minter->setAccessible( true );

$before = count_confirm_transients();
$mcp_result = $minter->invoke( null, [ 'action' => 'insert', 'post_id' => 0 ], 0, 'mcp', [ 'tool' => 'struo_insert_block', 'request_id' => 'wp-eval-a' ] );
$after = count_confirm_transients();

check( '(a) mcp mint returns an array', is_array( $mcp_result ) );
check( '(a) mcp mint has NO token key', is_array( $mcp_result ) && ! array_key_exists( 'token', $mcp_result ) );
check( '(a) mcp mint redeemable=false', is_array( $mcp_result ) && false === ( $mcp_result['redeemable'] ?? null ) );
check( '(a) mcp mint origin=mcp', is_array( $mcp_result ) && 'mcp' === ( $mcp_result['origin'] ?? '' ) );
check( '(a) NO confirmation transient created by mcp mint', $after === $before, "before={$before} after={$after}" );

check(
	'(b) ensure_confirmation_token is not a class method',
	! method_exists( 'Struo_Block_Editor', 'ensure_confirmation_token' )
);

// Self-provision an administrator when no user is supplied: wp eval-file
// runs unauthenticated by default, and the MCP path requires the same
// permissions as REST. NOTE: pass --user=<admin> to exercise YOUR account
// instead; without it this script acts as the first administrator found.
$user_id = get_current_user_id();
$switched = false;
if ( ! $user_id ) {
	$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	if ( empty( $admins ) ) {
		check( '(c) handle_mcp_call dispatches with forced dry-run', false, 'no user and no administrator exists; run with --user=<admin>' );
		print_summary_and_exit();
	}
	$user_id = (int) $admins[0];
	wp_set_current_user( $user_id );
	$switched = true;
	echo "NOTE: no --user given; running as first administrator (ID {$user_id}).\n";
}

// Provisioning continues below; cleanup is registered at the top of the file.

// Provision a temporary allowlisted draft post so the allowlist check
// passes and the probe reaches the dry-run forcing logic.
$GLOBALS['struo_eval_cleanup']['option_saved'] = true;
$GLOBALS['struo_eval_cleanup']['option_value'] = get_option( 'struo_options', null );
$GLOBALS['struo_eval_cleanup']['agent_plans_saved'] = true;
$GLOBALS['struo_eval_cleanup']['agent_plans_value'] = get_option( 'struo_agent_plans', null );
struo_eval_sweep_stale_eval_plans();
struo_eval_snapshot_durable_state();

$temp_post_id = wp_insert_post(
	[
		'post_type' => 'page',
		'post_status' => 'draft',
		'post_title' => '[struo-eval-temp] dry-run probe',
		'post_content' => '<!-- wp:paragraph --><p>before</p><!-- /wp:paragraph -->',
		'post_author' => $user_id,
	]
);
if ( ! $temp_post_id || is_wp_error( $temp_post_id ) ) {
	check( '(c) temporary allowlisted draft post created', false, 'wp_insert_post failed' );
	print_summary_and_exit();
}
$GLOBALS['struo_eval_cleanup']['post_ids'][] = (int) $temp_post_id;

// Belt-and-braces: a closure capturing THIS post id, registered before
// any write, so even an uncaught path cannot leave the post behind.
$temp_post_id_capture = (int) $temp_post_id;
register_shutdown_function( static function () use ( $temp_post_id_capture ) {
	if ( get_post_status( $temp_post_id_capture ) ) {
		wp_delete_post( $temp_post_id_capture, true );
	}
} );

$options = is_array( $GLOBALS['struo_eval_cleanup']['option_value'] ) ? $GLOBALS['struo_eval_cleanup']['option_value'] : [];
$options['allowed_post_ids'] = array_values(
	array_unique(
		array_merge(
			array_map( 'absint', (array) ( $options['allowed_post_ids'] ?? [] ) ),
			[ (int) $temp_post_id ]
		)
	)
);
update_option( 'struo_options', $options, false );
if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
	Struo_Block_Editor::flush_options_cache();
}

// (b) A reflected REST mint can still leave a leftover token; REST persist
// cannot redeem it. Runs after the allowlisted post exists so dispatch
// reaches the plan-apply gate rather than an allowlist 403.
$rest_result = $minter->invoke(
	null,
	[ 'action' => 'insert', 'post_id' => (int) $temp_post_id ],
	(int) $temp_post_id,
	'rest',
	[]
);
check(
	'(b) rest mint is non-redeemable with no token',
	is_array( $rest_result )
		&& false === ( $rest_result['redeemable'] ?? null )
		&& '' === sanitize_text_field( (string) ( $rest_result['token'] ?? '' ) )
);
$leftover_persist = struo_eval_rest(
	'POST',
	"/struo/v1/posts/{$temp_post_id}/blocks/insert",
	[
		'block_name' => 'core/paragraph',
		'fields' => [ 'content' => 'wp-eval leftover token persist' ],
		'dry_run' => false,
		'confirmation_token' => 'legacy-rest-token',
	]
);
check(
	'(b) leftover REST token cannot persist',
	403 === struo_eval_rest_status( $leftover_persist ) && 'sae_plan_apply_required' === struo_eval_rest_code( $leftover_persist ),
	'status=' . struo_eval_rest_status( $leftover_persist ) . ' code=' . struo_eval_rest_code( $leftover_persist )
);

$args = [
	'post_id' => $temp_post_id,
	'block_name' => 'core/paragraph',
	'fields' => [ 'content' => 'wp-eval probe' ],
	'dry_run' => false, // Must be forced back to true.
	'confirmation_token' => 'attacker-supplied-token', // Must be stripped.
];

	// Pre-check: the entry point must be callable at all — an invalid tool
	// returns the unmodified result (null here) instead of erroring.
	$precheck = Struo_Block_Editor::handle_mcp_call( null, 'struo_not_a_real_tool', $args, 43 );
	check( '(c) entry point callable (invalid tool passthrough)', null === $precheck || ! is_wp_error( $precheck ) );

	try {
		$result = Struo_Block_Editor::handle_mcp_call( null, 'struo_insert_block', $args, 42 );
	} catch ( Throwable $e ) {
		// Shutdown handler restores the option and deletes the temp post.
		check( '(c) handle_mcp_call dispatches without fatals', false, get_class( $e ) . ': ' . $e->getMessage() );
		print_summary_and_exit();
	}

if ( is_wp_error( $result ) ) {
	check( '(c) mcp mutation reached the dry-run forcing logic', false, 'error: ' . $result->get_error_code() . ' — ' . $result->get_error_message() );
} else {
	$confirmation = is_array( $result ) ? ( $result['confirmation'] ?? [] ) : [];
	check( '(c) dry_run forced true despite dry_run=false in args', is_array( $result ) && true === ( $result['dry_run'] ?? false ) );
	check( '(c) confirmation is non-redeemable (mcp origin)', is_array( $confirmation ) && false === ( $confirmation['redeemable'] ?? null ) && 'mcp' === ( $confirmation['origin'] ?? '' ) );
	check( '(c) confirmation carries NO token', is_array( $confirmation ) && ! array_key_exists( 'token', $confirmation ) );
	check( '(c) attacker confirmation_token stripped (apply never attempted)', is_array( $result ) && true === ( $result['dry_run'] ?? false ) && ! array_key_exists( 'token', (array) $confirmation ) );
}

if ( method_exists( 'Struo_Block_Editor', 'dispatch_internal' ) ) {
	try {
		$dispatched = Struo_Block_Editor::dispatch_internal(
			'preview-insert',
			$args,
			'rest',
			[ 'tool' => 'eval_dispatch_internal', 'request_id' => 'eval-rest' ]
		);
	} catch ( Throwable $e ) {
		check( '(c2) dispatch_internal preview-insert without fatals', false, get_class( $e ) . ': ' . $e->getMessage() );
		$dispatched = null;
	}
	if ( is_wp_error( $dispatched ) ) {
		check( '(c2) dispatch_internal forces dry-run even when origin=rest', false, 'error: ' . $dispatched->get_error_code() . ' — ' . $dispatched->get_error_message() );
	} else {
		$dispatched_confirmation = is_array( $dispatched ) ? ( $dispatched['confirmation'] ?? [] ) : [];
		check( '(c2) dispatch_internal forces dry_run even when origin=rest', is_array( $dispatched ) && true === ( $dispatched['dry_run'] ?? false ) );
		check( '(c2) dispatch_internal never mints a redeemable token', is_array( $dispatched_confirmation ) && false === ( $dispatched_confirmation['redeemable'] ?? null ) );
	}
}

if ( class_exists( 'Struo_Abilities' ) ) {
	try {
		$ability = Struo_Abilities::execute_preview_insert( $args );
	} catch ( Throwable $e ) {
		check( '(c3) preview-insert ability without fatals', false, get_class( $e ) . ': ' . $e->getMessage() );
		$ability = null;
	}
	if ( is_wp_error( $ability ) ) {
		check( '(c3) preview-insert ability is dry-run only', false, 'error: ' . $ability->get_error_code() . ' — ' . $ability->get_error_message() );
	} else {
		$ability_confirmation = is_array( $ability ) ? ( $ability['confirmation'] ?? [] ) : [];
		check( '(c3) preview-insert ability forces dry_run', is_array( $ability ) && true === ( $ability['dry_run'] ?? false ) );
		check( '(c3) preview-insert ability is non-redeemable', is_array( $ability_confirmation ) && false === ( $ability_confirmation['redeemable'] ?? null ) );
	}
}

$planned = null;
if ( method_exists( 'Struo_Block_Editor', 'dispatch_internal' ) ) {
	$plan_args = [
		'post_id' => $temp_post_id,
		'dry_run_preview' => true,
		'prefer_ai' => false,
		'plan' => [
			'post_id' => $temp_post_id,
			'operation' => 'update',
			'target' => [ 'index_path' => [ 0 ] ],
			'fields' => [ 'content' => 'wp-eval nested-origin probe' ],
		],
	];
	$before_plan = count_confirm_transients();
	try {
		$planned = Struo_Block_Editor::dispatch_internal(
			'plan-block-change',
			$plan_args,
			'mcp',
			[ 'tool' => 'eval_plan_nested_dry_run', 'request_id' => 'eval-plan' ]
		);
	} catch ( Throwable $e ) {
		check( '(c4) plan nested dry-run without fatals', false, get_class( $e ) . ': ' . $e->getMessage() );
		$planned = null;
	}
	if ( is_wp_error( $planned ) ) {
		check( '(c4) MCP plan+dry_run_preview is non-redeemable', false, 'error: ' . $planned->get_error_code() . ' — ' . $planned->get_error_message() );
	} else {
		$plan_confirmation = [];
		if ( is_array( $planned ) && is_array( $planned['dry_run'] ?? null ) ) {
			$plan_confirmation = is_array( $planned['dry_run']['confirmation'] ?? null )
				? $planned['dry_run']['confirmation']
				: [];
		}
		$leaked_token = sanitize_text_field( (string) ( $plan_confirmation['token'] ?? '' ) );
		if ( '' !== $leaked_token ) {
			delete_transient( 'struo_confirm_' . $leaked_token );
			delete_transient( 'sae_confirm_' . $leaked_token );
		}
		$after_plan = count_confirm_transients();
		check( '(c4) nested plan dry-run ran', is_array( $planned ) && is_array( $planned['dry_run'] ?? null ) );
		check( '(c4) MCP plan+dry_run_preview is non-redeemable', is_array( $plan_confirmation ) && false === ( $plan_confirmation['redeemable'] ?? null ) && 'mcp' === ( $plan_confirmation['origin'] ?? '' ) );
		check( '(c4) MCP plan+dry_run_preview carries NO token', is_array( $plan_confirmation ) && ! array_key_exists( 'token', $plan_confirmation ) );
		check( '(c4) MCP plan+dry_run_preview minted no confirm transient', $after_plan === $before_plan, "before={$before_plan} after={$after_plan}" );
	}

	if ( class_exists( 'Struo_Abilities' ) ) {
		try {
			$ability_plan = Struo_Abilities::execute_plan_block_change( $plan_args );
		} catch ( Throwable $e ) {
			check( '(c5) plan-block-change ability without fatals', false, get_class( $e ) . ': ' . $e->getMessage() );
			$ability_plan = null;
		}
		if ( is_wp_error( $ability_plan ) ) {
			check( '(c5) plan-block-change ability is non-redeemable', false, 'error: ' . $ability_plan->get_error_code() . ' — ' . $ability_plan->get_error_message() );
		} else {
			$ability_plan_confirmation = [];
			if ( is_array( $ability_plan ) && is_array( $ability_plan['dry_run'] ?? null ) ) {
				$ability_plan_confirmation = is_array( $ability_plan['dry_run']['confirmation'] ?? null )
					? $ability_plan['dry_run']['confirmation']
					: [];
			}
			$ability_leaked = sanitize_text_field( (string) ( $ability_plan_confirmation['token'] ?? '' ) );
			if ( '' !== $ability_leaked ) {
				delete_transient( 'struo_confirm_' . $ability_leaked );
				delete_transient( 'sae_confirm_' . $ability_leaked );
			}
			check( '(c5) plan-block-change ability nested dry-run is non-redeemable', is_array( $ability_plan_confirmation ) && false === ( $ability_plan_confirmation['redeemable'] ?? null ) && 'mcp' === ( $ability_plan_confirmation['origin'] ?? '' ) );
			check( '(c5) plan-block-change ability carries NO token', is_array( $ability_plan_confirmation ) && ! array_key_exists( 'token', $ability_plan_confirmation ) );
		}
	}
}

if ( is_array( $planned ) && method_exists( 'Struo_Block_Editor', 'preview_agent_plan' ) ) {
	$agent_plan = is_array( $planned['agent_plan'] ?? null ) ? $planned['agent_plan'] : [];
	$agent_plan_id = sanitize_text_field( (string) ( $agent_plan['id'] ?? '' ) );
	check( '(i1) MCP plan queues a review object', '' !== $agent_plan_id && ! empty( $agent_plan['queued'] ) );
	check( '(i1) queued review has no token', ! isset( $agent_plan['token'] ) && ! isset( $agent_plan['confirmation_token'] ) );

	$status = Struo_Block_Editor::get_console_status();
	$inbox = is_array( $status['agent_plans'] ?? null ) ? $status['agent_plans'] : [];
	$inbox_hit = null;
	foreach ( $inbox as $item ) {
		if ( is_array( $item ) && $agent_plan_id === (string) ( $item['id'] ?? '' ) ) {
			$inbox_hit = $item;
			break;
		}
	}
	check( '(i2) console status lists the queued plan', is_array( $inbox_hit ) );
	check( '(i2) inbox summary origin is mcp', is_array( $inbox_hit ) && 'mcp' === ( $inbox_hit['origin'] ?? '' ) );
	check( '(i2) inbox summaries carry no confirmation token', is_array( $inbox_hit ) && ! isset( $inbox_hit['payload'] ) && ! isset( $inbox_hit['confirmation_token'] ) && ! isset( $inbox_hit['token'] ) && ! isset( $inbox_hit['dry_run'] ) );

	$get_req = new WP_REST_Request( 'GET' );
	$get_req->set_url_params( [ 'id' => $agent_plan_id ] );
	$stored = Struo_Block_Editor::get_agent_plan( $get_req );
	if ( is_wp_error( $stored ) ) {
		check( '(i3) stored agent plan has payload and no token', false, 'error: ' . $stored->get_error_code() . ' — ' . $stored->get_error_message() );
	} else {
		$stored_plan = is_array( $stored['plan'] ?? null ) ? $stored['plan'] : [];
		$stored_json = wp_json_encode( $stored_plan );
		check( '(i3) stored agent plan has payload and no token', is_array( $stored_plan['payload'] ?? null ) && ! empty( $stored_plan['payload'] ) && false === strpos( (string) $stored_json, 'confirmation_token' ) && ! isset( $stored_plan['dry_run'] ) );
	}

	$before_preview = count_confirm_transients();
	$preview_req = new WP_REST_Request( 'POST' );
	$preview_req->set_url_params( [ 'id' => $agent_plan_id ] );
	$previewed = Struo_Block_Editor::preview_agent_plan( $preview_req );
	if ( is_wp_error( $previewed ) ) {
		check( '(i4) operator REST preview returns a non-redeemable review', false, 'error: ' . $previewed->get_error_code() . ' — ' . $previewed->get_error_message() );
	} else {
		$preview_confirmation = [];
		if ( is_array( $previewed['dry_run'] ?? null ) ) {
			$preview_confirmation = is_array( $previewed['dry_run']['confirmation'] ?? null )
				? $previewed['dry_run']['confirmation']
				: [];
		}
		$preview_token = sanitize_text_field( (string) ( $preview_confirmation['token'] ?? '' ) );
		$after_preview = count_confirm_transients();
		check( '(i4) operator REST preview is non-redeemable', is_array( $preview_confirmation ) && false === ( $preview_confirmation['redeemable'] ?? null ) );
		check( '(i4) operator REST preview carries plan_id', '' !== sanitize_text_field( (string) ( $previewed['plan_id'] ?? '' ) ) );
		check( '(i4) operator REST preview does not mint a confirm transient', $after_preview === $before_preview && '' === $preview_token, "before={$before_preview} after={$after_preview}" );
	}

	$opts_now = get_option( 'struo_options', [] );
	if ( ! is_array( $opts_now ) ) {
		$opts_now = [];
	}
	$opts_now['kill_switch'] = 1;
	update_option( 'struo_options', $opts_now, false );
	Struo_Block_Editor::flush_options_cache();
	$blocked = Struo_Block_Editor::preview_agent_plan( $preview_req );
	check( '(i5) kill switch stops operator preview', is_wp_error( $blocked ) && 'sae_kill_switch' === $blocked->get_error_code() );
	$opts_now['kill_switch'] = 0;
	update_option( 'struo_options', $opts_now, false );
	Struo_Block_Editor::flush_options_cache();

	$rest_req = new WP_REST_Request( 'POST' );
	$rest_req->set_body_params( $plan_args );
	$rest_planned = Struo_Block_Editor::plan_block_change( $rest_req );
	if ( is_wp_error( $rest_planned ) ) {
		check( '(i6) REST/console plans are not queued as agent inbox items', false, 'error: ' . $rest_planned->get_error_code() . ' — ' . $rest_planned->get_error_message() );
	} else {
		check( '(i6) REST/console plans are not queued as agent inbox items', empty( $rest_planned['agent_plan'] ) );
		check( '(i6) REST plan returns durable plan_id', '' !== sanitize_text_field( (string) ( $rest_planned['plan_id'] ?? '' ) ) );
		$rest_confirmation = [];
		if ( is_array( $rest_planned['dry_run'] ?? null ) ) {
			$rest_confirmation = is_array( $rest_planned['dry_run']['confirmation'] ?? null )
				? $rest_planned['dry_run']['confirmation']
				: [];
		}
		$rest_token = sanitize_text_field( (string) ( $rest_confirmation['token'] ?? '' ) );
		check( '(i6) REST plan preview is plan-only (no redeemable token)', '' === $rest_token && false === ( $rest_confirmation['redeemable'] ?? null ) );

		$rest_plan_id = sanitize_text_field( (string) ( $rest_planned['plan_id'] ?? '' ) );
		if ( '' === $rest_plan_id ) {
			check( '(i8) REST plan approve transitions to approved', false, 'missing plan_id' );
		} else {
			$too_soon_req = new WP_REST_Request( 'POST' );
			$too_soon_req->set_url_params( [ 'id' => $rest_plan_id ] );
			$too_soon = Struo_Block_Editor::apply_agent_plan( $too_soon_req );
			check(
				'(i8) apply before approve is rejected',
				is_wp_error( $too_soon ) && 'sae_plan_not_approved' === $too_soon->get_error_code()
			);

			$approve_req = new WP_REST_Request( 'POST' );
			$approve_req->set_url_params( [ 'id' => $rest_plan_id ] );
			$approved = Struo_Block_Editor::approve_agent_plan( $approve_req );
			if ( is_wp_error( $approved ) ) {
				check( '(i8) REST plan approve transitions to approved', false, 'error: ' . $approved->get_error_code() . ' — ' . $approved->get_error_message() );
			} else {
				check( '(i8) REST plan approve transitions to approved', 'approved' === (string) ( $approved['state'] ?? '' ) );
			}

			$apply_req = new WP_REST_Request( 'POST' );
			$apply_req->set_url_params( [ 'id' => $rest_plan_id ] );
			$opts_now['kill_switch'] = 1;
			update_option( 'struo_options', $opts_now, false );
			Struo_Block_Editor::flush_options_cache();
			$killed_apply = Struo_Block_Editor::apply_agent_plan( $apply_req );
			check(
				'(i5b) kill switch stops apply',
				is_wp_error( $killed_apply ) && 'sae_kill_switch' === $killed_apply->get_error_code(),
				is_wp_error( $killed_apply ) ? $killed_apply->get_error_code() : 'applied-under-kill-switch'
			);
			$opts_now['kill_switch'] = 0;
			update_option( 'struo_options', $opts_now, false );
			Struo_Block_Editor::flush_options_cache();
			$applied = Struo_Block_Editor::apply_agent_plan( $apply_req );
			if ( is_wp_error( $applied ) ) {
				check( '(i9) approved plan applies via envelope without a token', false, 'error: ' . $applied->get_error_code() . ' — ' . $applied->get_error_message() );
			} else {
				check(
					'(i9) approved plan applies via envelope without a token',
					'applied' === (string) ( $applied['state'] ?? '' )
					&& empty( $applied['confirmation_token'] )
					&& empty( $applied['result']['confirmation']['token'] )
				);
			}
		}
	}

	$audit_req = new WP_REST_Request( 'GET' );
	$audit_req->set_param( 'action', 'plan' );
	$audit_req->set_param( 'post_id', $temp_post_id );
	$audit_req->set_param( 'per_page', 25 );
	$audit = Struo_Block_Editor::get_console_audit( $audit_req );
	$origins = [];
	if ( is_array( $audit['items'] ?? null ) ) {
		foreach ( $audit['items'] as $item ) {
			$details = is_array( $item['details'] ?? null ) ? $item['details'] : [];
			$origins[] = (string) ( $details['origin'] ?? '' );
		}
	}
	check( '(i7) audit distinguishes mcp vs rest', in_array( 'mcp', $origins, true ) && in_array( 'rest', $origins, true ), 'origins=' . implode( ',', array_unique( $origins ) ) );
} elseif ( method_exists( 'Struo_Block_Editor', 'preview_agent_plan' ) ) {
	check( '(i1) MCP plan queues a review object', false, 'MCP plan did not return an array; inbox cases skipped' );
}

// page_spec_v1: compile at plan time, apply writes frozen content (no regen).
if ( method_exists( 'Struo_Block_Editor', 'plan_create_page' ) && method_exists( 'Struo_Block_Editor', 'apply_agent_plan' ) ) {
	$create_req = new WP_REST_Request( 'POST' );
	$create_req->set_body_params(
		[
			'title' => 'PageSpec Eval Draft',
			'description' => 'Deterministic page_spec_v1 eval.',
			'template' => 'blog-post',
			'post_type' => 'post',
			'selected_sections' => [ 0, 1 ],
		]
	);
	struo_eval_begin_planner_fallback();
	$created_plan = Struo_Block_Editor::plan_create_page( $create_req );
	struo_eval_end_planner_fallback();
	if ( is_wp_error( $created_plan ) ) {
		check( '(ps1) plan_create_page returns page_spec envelope', false, 'error: ' . $created_plan->get_error_code() . ' — ' . $created_plan->get_error_message() );
	} else {
		$ps_plan_id = sanitize_text_field( (string) ( $created_plan['plan_id'] ?? '' ) );
		$ps_hash = sanitize_text_field( (string) ( $created_plan['content_hash'] ?? '' ) );
		check( '(ps1) plan_create_page returns durable plan_id', '' !== $ps_plan_id );
		check( '(ps1) plan_create_page stamps page_spec_v1', 'page_spec_v1' === sanitize_key( (string) ( $created_plan['payload_type'] ?? '' ) ) );
		check( '(ps1) plan_create_page freezes content_hash', '' !== $ps_hash && 64 === strlen( $ps_hash ) );
		check( '(ps1) plan_create_page is non-redeemable', empty( $created_plan['confirmation_token'] ) );

		$record_getter = new ReflectionMethod( 'Struo_Block_Editor', 'get_durable_plan_record' );
		$record_getter->setAccessible( true );
		$ps_record = $record_getter->invoke( null, $ps_plan_id );
		$stored_content = is_array( $ps_record ) ? (string) ( $ps_record['payload']['serialized_content'] ?? '' ) : '';
		check( '(ps2) durable row stores serialized_content', '' !== trim( $stored_content ) );
		check( '(ps2) durable row payload_type is page_spec_v1', is_array( $ps_record ) && 'page_spec_v1' === (string) ( $ps_record['payload_type'] ?? '' ) );
		check( '(ps2) durable base hash matches content_hash', is_array( $ps_record ) && hash_equals( (string) ( $ps_record['base_content_hash'] ?? '' ), $ps_hash ) );

		$ps_approve = new WP_REST_Request( 'POST' );
		$ps_approve->set_url_params( [ 'id' => $ps_plan_id ] );
		$ps_approved = Struo_Block_Editor::approve_agent_plan( $ps_approve );
		check( '(ps3) page_spec approve transitions to approved', ! is_wp_error( $ps_approved ) && 'approved' === (string) ( $ps_approved['state'] ?? '' ) );

		$ps_apply = new WP_REST_Request( 'POST' );
		$ps_apply->set_url_params( [ 'id' => $ps_plan_id ] );
		$ps_applied = Struo_Block_Editor::apply_agent_plan( $ps_apply );
		if ( is_wp_error( $ps_applied ) ) {
			check( '(ps4) page_spec apply writes frozen content', false, 'error: ' . $ps_applied->get_error_code() . ' — ' . $ps_applied->get_error_message() );
		} else {
			$new_id = absint( $ps_applied['result']['post_id'] ?? 0 );
			$written = $new_id > 0 ? (string) get_post_field( 'post_content', $new_id ) : '';
			check( '(ps4) page_spec apply creates a draft post', $new_id > 0 && 'draft' === get_post_status( $new_id ) );
			check( '(ps4) applied content hash matches planned hash', '' !== $written && hash_equals( $ps_hash, hash( 'sha256', $written ) ) );
			check( '(ps4) apply receipt marks content_hash_matched', ! empty( $ps_applied['result']['content_hash_matched'] ) );
			if ( $new_id > 0 ) {
				wp_delete_post( $new_id, true );
			}
		}
	}
}

// Live REST / MCP / Abilities entry-point coverage (not callback-level).
$live_write_routes = [
	'insert' => [
		"/struo/v1/posts/{$temp_post_id}/blocks/insert",
		[
			'block_name' => 'core/paragraph',
			'fields' => [ 'content' => 'live-rest-reject-insert' ],
			'position' => [ 'type' => 'append' ],
		],
	],
	'update' => [
		"/struo/v1/posts/{$temp_post_id}/blocks/update",
		[
			'target' => [ 'index_path' => [ 0 ] ],
			'fields' => [ 'content' => 'live-rest-reject-update' ],
		],
	],
	'remove' => [
		"/struo/v1/posts/{$temp_post_id}/blocks/remove",
		[
			'target' => [ 'index_path' => [ 0 ] ],
		],
	],
	'batch' => [
		"/struo/v1/posts/{$temp_post_id}/blocks/batch",
		[
			'operations' => [
				[
					'action' => 'update',
					'target' => [ 'index_path' => [ 0 ] ],
					'fields' => [ 'content' => 'live-rest-reject-batch' ],
				],
			],
		],
	],
	'fields/update' => [
		"/struo/v1/posts/{$temp_post_id}/fields/update",
		[
			'field' => 'excerpt',
			'value' => 'live-rest-reject-excerpt',
		],
	],
];

foreach ( $live_write_routes as $label => $spec ) {
	list( $route, $payload ) = $spec;
	$dry = struo_eval_rest( 'POST', $route, array_merge( $payload, [ 'dry_run' => true ] ) );
	$dry_data = struo_eval_rest_data( $dry );
	$dry_conf = struo_eval_rest_confirmation( $dry_data );
	$dry_plan = sanitize_text_field( (string) ( $dry_data['plan_id'] ?? '' ) );
	check(
		"(live) REST {$label} dry-run is 200 with plan_id",
		200 === struo_eval_rest_status( $dry ) && '' !== $dry_plan,
		'status=' . struo_eval_rest_status( $dry ) . ' code=' . struo_eval_rest_code( $dry ) . ' plan=' . $dry_plan
	);
	check(
		"(live) REST {$label} dry-run is non-redeemable",
		false === ( $dry_conf['redeemable'] ?? null ) && '' === sanitize_text_field( (string) ( $dry_conf['token'] ?? '' ) )
	);

	$persist = struo_eval_rest(
		'POST',
		$route,
		array_merge(
			$payload,
			[
				'dry_run' => false,
				'confirmation_token' => 'legacy-rest-token',
			]
		)
	);
	check(
		"(live) REST {$label} persist with token is sae_plan_apply_required",
		403 === struo_eval_rest_status( $persist ) && 'sae_plan_apply_required' === struo_eval_rest_code( $persist ),
		'status=' . struo_eval_rest_status( $persist ) . ' code=' . struo_eval_rest_code( $persist )
	);
}

$create_persist = struo_eval_rest(
	'POST',
	'/struo/v1/pages/create',
	[
		'confirmation_token' => 'legacy-create-token',
		'idempotency_key' => 'wp-eval-live-create',
	]
);
check(
	'(live) REST pages/create persist is sae_plan_apply_required',
	403 === struo_eval_rest_status( $create_persist ) && 'sae_plan_apply_required' === struo_eval_rest_code( $create_persist ),
	'status=' . struo_eval_rest_status( $create_persist ) . ' code=' . struo_eval_rest_code( $create_persist )
);

$create_no_token = struo_eval_rest( 'POST', '/struo/v1/pages/create', [] );
check(
	'(f12-e1) pages/create without a token is still sae_plan_apply_required',
	403 === struo_eval_rest_status( $create_no_token ) && 'sae_plan_apply_required' === struo_eval_rest_code( $create_no_token ),
	'status=' . struo_eval_rest_status( $create_no_token ) . ' code=' . struo_eval_rest_code( $create_no_token )
);

check( '(f12-e2) legacy REST aliases are removed', ! class_exists( 'Struo_Legacy_Rest' ) );

$f13_admin = get_role( 'administrator' );
$f13_saved_flag = get_option( Struo_Block_Editor::CAPS_MIGRATED_OPTION, null );
$f13_had_plan = $f13_admin && $f13_admin->has_cap( Struo_Block_Editor::CAP_STRUO_PLAN );
$f13_migrate = new ReflectionMethod( 'Struo_Block_Editor', 'maybe_migrate_struo_capabilities' );
$f13_migrate->setAccessible( true );
if ( $f13_admin ) {
	$f13_admin->remove_cap( Struo_Block_Editor::CAP_STRUO_PLAN );
}
delete_option( Struo_Block_Editor::CAPS_MIGRATED_OPTION );
$f13_migrate->invoke( null );
check(
	'(f13-e1) capability migration stores the schema version',
	Struo_Block_Editor::CAPS_SCHEMA_VERSION === absint( get_option( Struo_Block_Editor::CAPS_MIGRATED_OPTION, 0 ) )
);
check(
	'(f13-e2) administrator regained struo_plan after versioned migrate',
	$f13_admin && $f13_admin->has_cap( Struo_Block_Editor::CAP_STRUO_PLAN )
);
if ( $f13_admin ) {
	$f13_admin->remove_cap( Struo_Block_Editor::CAP_STRUO_PLAN );
}
update_option( Struo_Block_Editor::CAPS_MIGRATED_OPTION, Struo_Block_Editor::CAPS_SCHEMA_VERSION + 10, false );
$f13_migrate->invoke( null );
check(
	'(f13-e3) stored version at or above schema does not re-grant',
	$f13_admin && ! $f13_admin->has_cap( Struo_Block_Editor::CAP_STRUO_PLAN )
);
if ( $f13_admin && $f13_had_plan ) {
	$f13_admin->add_cap( Struo_Block_Editor::CAP_STRUO_PLAN );
}
if ( null === $f13_saved_flag ) {
	delete_option( Struo_Block_Editor::CAPS_MIGRATED_OPTION );
} else {
	update_option( Struo_Block_Editor::CAPS_MIGRATED_OPTION, $f13_saved_flag, false );
}

$live_post_id = wp_insert_post(
	[
		'post_type' => 'page',
		'post_status' => 'draft',
		'post_title' => '[struo-eval-temp] live rest apply',
		'post_content' => '<!-- wp:paragraph --><p>live-before</p><!-- /wp:paragraph -->',
		'post_author' => $user_id,
	]
);
if ( ! $live_post_id || is_wp_error( $live_post_id ) ) {
	check( '(live) dedicated apply post created', false, 'wp_insert_post failed' );
	$live_post_id = 0;
} else {
	$GLOBALS['struo_eval_cleanup']['post_ids'][] = (int) $live_post_id;
	$opts_live = get_option( 'struo_options', [] );
	$opts_live['allowed_post_ids'] = array_values(
		array_unique(
			array_merge(
				array_map( 'absint', (array) ( $opts_live['allowed_post_ids'] ?? [] ) ),
				[ (int) $live_post_id ]
			)
		)
	);
	update_option( 'struo_options', $opts_live, false );
	if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
		Struo_Block_Editor::flush_options_cache();
	}

	$apply_dry = struo_eval_rest(
		'POST',
		"/struo/v1/posts/{$live_post_id}/blocks/insert",
		[
			'block_name' => 'core/paragraph',
			'fields' => [ 'content' => 'live-rest-dispatch-insert' ],
			'position' => [ 'type' => 'append' ],
			'dry_run' => true,
		]
	);
	$apply_dry_data = struo_eval_rest_data( $apply_dry );
	$apply_plan_id = sanitize_text_field( (string) ( $apply_dry_data['plan_id'] ?? '' ) );
	check( '(live) REST insert dry-run minted plan_id for approve/apply', '' !== $apply_plan_id );

	if ( '' !== $apply_plan_id ) {
		$approved = struo_eval_rest( 'POST', "/struo/v1/console/agent-plans/{$apply_plan_id}/approve" );
		check(
			'(live) REST approve transitions to approved',
			200 === struo_eval_rest_status( $approved ) && 'approved' === sanitize_key( (string) ( struo_eval_rest_data( $approved )['state'] ?? '' ) ),
			'status=' . struo_eval_rest_status( $approved ) . ' code=' . struo_eval_rest_code( $approved )
		);
		$applied = struo_eval_rest( 'POST', "/struo/v1/console/agent-plans/{$apply_plan_id}/apply" );
		$applied_ok = 200 === struo_eval_rest_status( $applied ) && 'applied' === sanitize_key( (string) ( struo_eval_rest_data( $applied )['state'] ?? '' ) );
		check(
			'(live) REST apply persists without a token',
			$applied_ok,
			'status=' . struo_eval_rest_status( $applied ) . ' code=' . struo_eval_rest_code( $applied )
		);
		$written = (string) get_post_field( 'post_content', $live_post_id );
		check( '(live) REST apply wrote the insert', false !== strpos( $written, 'live-rest-dispatch-insert' ) );
	}
}

$ability_post_id = wp_insert_post(
	[
		'post_type' => 'page',
		'post_status' => 'draft',
		'post_title' => '[struo-eval-temp] ability apply',
		'post_content' => '<!-- wp:paragraph --><p>ability-before</p><!-- /wp:paragraph -->',
		'post_author' => $user_id,
	]
);
if ( ! $ability_post_id || is_wp_error( $ability_post_id ) ) {
	check( '(live) ability-apply post created', false, 'wp_insert_post failed' );
	$ability_post_id = 0;
} else {
	$GLOBALS['struo_eval_cleanup']['post_ids'][] = (int) $ability_post_id;
	$opts_ability = get_option( 'struo_options', [] );
	$opts_ability['allowed_post_ids'] = array_values(
		array_unique(
			array_merge(
				array_map( 'absint', (array) ( $opts_ability['allowed_post_ids'] ?? [] ) ),
				[ (int) $ability_post_id ]
			)
		)
	);
	update_option( 'struo_options', $opts_ability, false );
	if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
		Struo_Block_Editor::flush_options_cache();
	}

	$ability_dry = struo_eval_rest(
		'POST',
		"/struo/v1/posts/{$ability_post_id}/blocks/insert",
		[
			'block_name' => 'core/paragraph',
			'fields' => [ 'content' => 'live-ability-apply-insert' ],
			'position' => [ 'type' => 'append' ],
			'dry_run' => true,
		]
	);
	$ability_plan_id = sanitize_text_field( (string) ( struo_eval_rest_data( $ability_dry )['plan_id'] ?? '' ) );
	check( '(live) ability-apply dry-run minted plan_id', '' !== $ability_plan_id );

	$denied_apply = Struo_Abilities::execute_apply_agent_plan( [ 'plan_id' => $ability_plan_id ] );
	check(
		'(live) apply ability refuses a planned (not approved) plan',
		is_wp_error( $denied_apply ) && 'sae_plan_not_approved' === $denied_apply->get_error_code(),
		is_wp_error( $denied_apply ) ? $denied_apply->get_error_code() : 'applied-without-approve'
	);

	$unknown = Struo_Block_Editor::dispatch_internal( 'apply-agent-plan', [ 'plan_id' => $ability_plan_id ], 'mcp' );
	check(
		'(live) dispatch_internal cannot apply',
		is_wp_error( $unknown ) && 'sae_unknown_operation' === $unknown->get_error_code(),
		is_wp_error( $unknown ) ? $unknown->get_error_code() : 'dispatch-applied'
	);

	$door_req = new WP_REST_Request( 'POST' );
	$door_mcp = Struo_Authority::authorize( 'apply-agent-plan', [ 'request' => $door_req ], 'mcp' );
	$door_ability = Struo_Authority::authorize( 'apply-agent-plan', [ 'request' => $door_req ], 'ability' );
	check(
		'(door-e1) apply-agent-plan door=mcp is the tool 403; door=ability is not that short-circuit',
		is_wp_error( $door_mcp )
			&& 'sae_insufficient_permissions' === $door_mcp->get_error_code()
			&& 'Insufficient permissions for this tool.' === $door_mcp->get_error_message()
			&& ! (
				is_wp_error( $door_ability )
				&& 'sae_insufficient_permissions' === $door_ability->get_error_code()
				&& 'Insufficient permissions for this tool.' === $door_ability->get_error_message()
			),
		'mcp=' . ( is_wp_error( $door_mcp ) ? $door_mcp->get_error_code() : 'ok' ) . ' ability=' . ( is_wp_error( $door_ability ) ? $door_ability->get_error_code() : 'ok' )
	);

	add_filter( 'struo_ability_mcp_public', 'struo_eval_force_ability_mcp_public_true', 10, 2 );
	$forced = Struo_Abilities::definitions();
	remove_filter( 'struo_ability_mcp_public', 'struo_eval_force_ability_mcp_public_true', 10 );
	check(
		'(live) apply-agent-plan stays mcp.public false after override filter',
		empty( $forced[ Struo_Abilities::ABILITY_APPLY_AGENT_PLAN ]['meta']['mcp']['public'] )
	);

	if ( '' !== $ability_plan_id ) {
		$ability_approved = struo_eval_rest( 'POST', "/struo/v1/console/agent-plans/{$ability_plan_id}/approve" );
		check(
			'(live) ability-apply approve via REST',
			200 === struo_eval_rest_status( $ability_approved ) && 'approved' === sanitize_key( (string) ( struo_eval_rest_data( $ability_approved )['state'] ?? '' ) ),
			'status=' . struo_eval_rest_status( $ability_approved ) . ' code=' . struo_eval_rest_code( $ability_approved )
		);

		if ( function_exists( 'wp_get_ability' ) ) {
			$apply_ability = wp_get_ability( 'struo/apply-agent-plan' );
			if ( ! is_object( $apply_ability ) || ! method_exists( $apply_ability, 'execute' ) ) {
				check( '(live) apply-agent-plan ability execute exists', false, 'wp_get_ability returned no executable ability' );
			} else {
				$opts_ability['kill_switch'] = 1;
				update_option( 'struo_options', $opts_ability, false );
				Struo_Block_Editor::flush_options_cache();
				$killed_ability = Struo_Abilities::execute_apply_agent_plan( [ 'plan_id' => $ability_plan_id ] );
				check(
					'(live) apply ability respects kill switch',
					is_wp_error( $killed_ability ) && 'sae_kill_switch' === $killed_ability->get_error_code(),
					is_wp_error( $killed_ability ) ? $killed_ability->get_error_code() : 'ability-applied-under-kill-switch'
				);
				$opts_ability['kill_switch'] = 0;
				update_option( 'struo_options', $opts_ability, false );
				Struo_Block_Editor::flush_options_cache();
				$ability_applied = $apply_ability->execute( [ 'plan_id' => $ability_plan_id ] );
				if ( is_wp_error( $ability_applied ) ) {
					check( '(live) apply ability persists approved plan', false, $ability_applied->get_error_code() . ' — ' . $ability_applied->get_error_message() );
				} else {
					$ability_state = sanitize_key( (string) ( $ability_applied['state'] ?? '' ) );
					check( '(live) apply ability persists approved plan', 'applied' === $ability_state, 'state=' . $ability_state );
					$ability_written = (string) get_post_field( 'post_content', $ability_post_id );
					check( '(live) apply ability wrote the insert', false !== strpos( $ability_written, 'live-ability-apply-insert' ) );
					$ability_conf = [];
					if ( is_array( $ability_applied['confirmation'] ?? null ) ) {
						$ability_conf = $ability_applied['confirmation'];
					}
					check(
						'(live) apply ability is non-redeemable',
						empty( $ability_conf ) || false === ( $ability_conf['redeemable'] ?? null )
					);
				}
			}
		} else {
			check( '(live) apply-agent-plan ability execute exists', false, 'wp_get_ability() is not available' );
		}
	}
}

struo_eval_begin_planner_fallback();
$ps_rest = struo_eval_rest(
	'POST',
	'/struo/v1/pages/plan-create',
	[
		'title' => 'PageSpec Live REST',
		'description' => 'Live rest_do_request page_spec_v1.',
		'template' => 'blog-post',
		'post_type' => 'post',
		'selected_sections' => [ 0, 1 ],
	]
);
struo_eval_end_planner_fallback();
$ps_rest_data = struo_eval_rest_data( $ps_rest );
$ps_rest_id = sanitize_text_field( (string) ( $ps_rest_data['plan_id'] ?? '' ) );
check(
	'(live) REST pages/plan-create returns page_spec plan_id',
	200 === struo_eval_rest_status( $ps_rest ) && '' !== $ps_rest_id && 'page_spec_v1' === sanitize_key( (string) ( $ps_rest_data['payload_type'] ?? '' ) ),
	'status=' . struo_eval_rest_status( $ps_rest ) . ' code=' . struo_eval_rest_code( $ps_rest )
);
if ( '' !== $ps_rest_id ) {
	$ps_rest_approve = struo_eval_rest( 'POST', "/struo/v1/console/agent-plans/{$ps_rest_id}/approve" );
	check(
		'(live) REST page_spec approve',
		200 === struo_eval_rest_status( $ps_rest_approve ) && 'approved' === sanitize_key( (string) ( struo_eval_rest_data( $ps_rest_approve )['state'] ?? '' ) ),
		'status=' . struo_eval_rest_status( $ps_rest_approve ) . ' code=' . struo_eval_rest_code( $ps_rest_approve )
	);
	$ps_rest_apply = struo_eval_rest( 'POST', "/struo/v1/console/agent-plans/{$ps_rest_id}/apply" );
	$ps_rest_apply_data = struo_eval_rest_data( $ps_rest_apply );
	$ps_new_id = absint( $ps_rest_apply_data['result']['post_id'] ?? 0 );
	check(
		'(live) REST page_spec apply creates a draft',
		200 === struo_eval_rest_status( $ps_rest_apply ) && $ps_new_id > 0,
		'status=' . struo_eval_rest_status( $ps_rest_apply ) . ' code=' . struo_eval_rest_code( $ps_rest_apply )
	);
	if ( $ps_new_id > 0 ) {
		$GLOBALS['struo_eval_cleanup']['post_ids'][] = $ps_new_id;
		wp_delete_post( $ps_new_id, true );
	}
}

register_post_type(
	'struo_eval_cpt',
	[
		'label' => 'Struo Eval CPT',
		'public' => false,
		'show_ui' => false,
		'supports' => [ 'title', 'editor' ],
		'capability_type' => [ 'struo_eval_cpt', 'struo_eval_cpts' ],
		'map_meta_cap' => true,
		'capabilities' => [
			'create_posts' => 'create_struo_eval_cpts',
		],
	]
);
wp_get_current_user()->add_cap( 'create_struo_eval_cpts' );
wp_get_current_user()->add_cap( 'edit_struo_eval_cpts' );
wp_get_current_user()->add_cap( 'edit_struo_eval_cpt' );

struo_eval_begin_planner_fallback();
$cpt_plan = struo_eval_rest(
	'POST',
	'/struo/v1/pages/plan-create',
	[
		'title' => 'Eval CPT page_spec',
		'description' => 'create_posts vs edit_posts split.',
		'post_type' => 'struo_eval_cpt',
		'outline' => [
			[
				'section' => 'Intro',
				'purpose' => 'Opening paragraph for eval CPT.',
				'block_name' => 'core/paragraph',
			],
		],
	]
);
struo_eval_end_planner_fallback();
$cpt_plan_data = struo_eval_rest_data( $cpt_plan );
$cpt_plan_id = sanitize_text_field( (string) ( $cpt_plan_data['plan_id'] ?? '' ) );
check(
	'(live) admin with create_posts can REST-plan page_spec for the CPT',
	200 === struo_eval_rest_status( $cpt_plan ) && '' !== $cpt_plan_id,
	'status=' . struo_eval_rest_status( $cpt_plan ) . ' code=' . struo_eval_rest_code( $cpt_plan )
);

$editor_id = wp_insert_user(
	[
		'user_login' => 'struo_eval_ed_' . wp_generate_password( 8, false ),
		'user_pass' => wp_generate_password( 24, true ),
		'user_email' => 'struo-eval-' . wp_generate_password( 8, false ) . '@example.test',
		'role' => 'editor',
		'display_name' => 'Struo Eval Editor',
	]
);
if ( is_wp_error( $editor_id ) ) {
	check( '(live) editor user for create_posts split', false, $editor_id->get_error_message() );
} else {
	$GLOBALS['struo_eval_cleanup']['user_ids'][] = (int) $editor_id;
	$editor = new WP_User( (int) $editor_id );
	$editor->add_cap( 'struo_approve' );
	$editor->add_cap( 'struo_apply' );
	$editor_post = wp_insert_post(
		[
			'post_type' => 'post',
			'post_status' => 'draft',
			'post_title' => '[struo-eval-temp] editor owned',
			'post_content' => '<!-- wp:paragraph --><p>editor</p><!-- /wp:paragraph -->',
			'post_author' => (int) $editor_id,
		]
	);
	if ( $editor_post && ! is_wp_error( $editor_post ) ) {
		$GLOBALS['struo_eval_cleanup']['post_ids'][] = (int) $editor_post;
	} else {
		$editor_post = 0;
	}
	wp_set_current_user( (int) $editor_id );

	check(
		'(live) editor can edit_post on an existing post',
		$editor_post > 0 && current_user_can( 'edit_post', $editor_post )
	);
	check(
		'(live) editor lacks create_posts for the CPT',
		! current_user_can( 'create_struo_eval_cpts' )
	);

	if ( '' !== $cpt_plan_id ) {
		$denied = struo_eval_rest( 'POST', "/struo/v1/console/agent-plans/{$cpt_plan_id}/approve" );
		check(
			'(live) edit_posts without create_posts cannot approve page_spec post_id=0',
			403 === struo_eval_rest_status( $denied ) && 'sae_insufficient_permissions' === struo_eval_rest_code( $denied ),
			'status=' . struo_eval_rest_status( $denied ) . ' code=' . struo_eval_rest_code( $denied )
		);
	}

	wp_set_current_user( (int) $user_id );
	wp_get_current_user()->add_cap( 'create_struo_eval_cpts' );
	wp_get_current_user()->add_cap( 'edit_struo_eval_cpts' );
	wp_get_current_user()->add_cap( 'edit_struo_eval_cpt' );
	if ( '' !== $cpt_plan_id ) {
		$allowed = struo_eval_rest( 'POST', "/struo/v1/console/agent-plans/{$cpt_plan_id}/approve" );
		check(
			'(live) create_posts can approve page_spec post_id=0',
			200 === struo_eval_rest_status( $allowed ) && 'approved' === sanitize_key( (string) ( struo_eval_rest_data( $allowed )['state'] ?? '' ) ),
			'status=' . struo_eval_rest_status( $allowed ) . ' code=' . struo_eval_rest_code( $allowed )
		);
	}
}
wp_set_current_user( (int) $user_id );
wp_get_current_user()->remove_cap( 'create_struo_eval_cpts' );
wp_get_current_user()->remove_cap( 'edit_struo_eval_cpts' );
wp_get_current_user()->remove_cap( 'edit_struo_eval_cpt' );
unregister_post_type( 'struo_eval_cpt' );

function struo_eval_force_openai_provider( $provider ) {
	return 'openai';
}
function struo_eval_mock_cross_field_http( $preempt, $args, $url ) {
	$inner = wp_json_encode(
		[
			'proposed_value' => 'Eval excerpt from live dispatch.',
			'rationale' => 'wp-eval-live',
		]
	);
	return [
		'headers' => [ 'content-type' => 'application/json' ],
		'body' => wp_json_encode(
			[
				'choices' => [
					[
						'message' => [
							'content' => $inner,
						],
					],
				],
			]
		),
		'response' => [ 'code' => 200, 'message' => 'OK' ],
		'cookies' => [],
		'filename' => null,
	];
}

$saved_xf_key = get_option( 'struo_openai_api_key', null );
$saved_xf_host = get_option( 'struo_openai_key_host', null );
update_option( 'struo_openai_api_key', 'sk-eval-cross-field', false );
update_option( 'struo_openai_key_host', 'api.openai.com', false );
add_filter( 'struo_provider', 'struo_eval_force_openai_provider' );
add_filter( 'pre_http_request', 'struo_eval_mock_cross_field_http', 10, 3 );

$xf_args = [
	'post_id' => $temp_post_id,
	'cross_field' => 'excerpt',
	'request' => 'Generate an excerpt from this content.',
	'dry_run_preview' => true,
	'dry_run' => false,
	'confirmation_token' => 'attacker-cross-field-token',
];
$xf_mcp = Struo_Block_Editor::handle_mcp_call( null, 'struo_plan_block_change', $xf_args, 77 );
if ( is_wp_error( $xf_mcp ) ) {
	check( '(live) MCP cross-field preview via handle_mcp_call', false, $xf_mcp->get_error_code() . ' — ' . $xf_mcp->get_error_message() );
} else {
	$xf_conf = [];
	if ( is_array( $xf_mcp['dry_run']['confirmation'] ?? null ) ) {
		$xf_conf = $xf_mcp['dry_run']['confirmation'];
	} elseif ( is_array( $xf_mcp['confirmation'] ?? null ) ) {
		$xf_conf = $xf_mcp['confirmation'];
	}
	check( '(live) MCP cross-field preview is non-redeemable', false === ( $xf_conf['redeemable'] ?? null ) || '' === sanitize_text_field( (string) ( $xf_conf['token'] ?? '' ) ) );
	check( '(live) MCP cross-field carries no token', '' === sanitize_text_field( (string) ( $xf_conf['token'] ?? '' ) ) );
	check(
		'(live) MCP cross-field used the excerpt path',
		'excerpt' === sanitize_key( (string) ( $xf_mcp['payload']['field'] ?? ( $xf_mcp['operation'] ?? '' ) ) )
		|| 'cross_field' === sanitize_key( (string) ( $xf_mcp['operation'] ?? '' ) ),
		'operation=' . sanitize_key( (string) ( $xf_mcp['operation'] ?? '' ) )
	);
}

if ( function_exists( 'wp_get_ability' ) ) {
	$ability_obj = wp_get_ability( 'struo/plan-block-change' );
	if ( ! is_object( $ability_obj ) || ! method_exists( $ability_obj, 'execute' ) ) {
		check( '(live) Ability API plan-block-change execute exists', false, 'wp_get_ability returned no executable ability' );
	} else {
		$xf_ability = $ability_obj->execute( $xf_args );
		if ( is_wp_error( $xf_ability ) ) {
			check( '(live) Ability cross-field preview via wp_get_ability', false, $xf_ability->get_error_code() . ' — ' . $xf_ability->get_error_message() );
		} else {
			$xf_ability_conf = [];
			if ( is_array( $xf_ability['dry_run']['confirmation'] ?? null ) ) {
				$xf_ability_conf = $xf_ability['dry_run']['confirmation'];
			} elseif ( is_array( $xf_ability['confirmation'] ?? null ) ) {
				$xf_ability_conf = $xf_ability['confirmation'];
			}
			check( '(live) Ability cross-field preview is non-redeemable', false === ( $xf_ability_conf['redeemable'] ?? null ) || '' === sanitize_text_field( (string) ( $xf_ability_conf['token'] ?? '' ) ) );
			check( '(live) Ability cross-field carries no token', '' === sanitize_text_field( (string) ( $xf_ability_conf['token'] ?? '' ) ) );
		}
	}
} else {
	check( '(live) Ability API plan-block-change execute exists', false, 'wp_get_ability() is not available' );
}

remove_filter( 'pre_http_request', 'struo_eval_mock_cross_field_http', 10 );
remove_filter( 'struo_provider', 'struo_eval_force_openai_provider' );
if ( null === $saved_xf_key ) {
	delete_option( 'struo_openai_api_key' );
} else {
	update_option( 'struo_openai_api_key', $saved_xf_key, false );
}
if ( null === $saved_xf_host ) {
	delete_option( 'struo_openai_key_host' );
} else {
	update_option( 'struo_openai_key_host', $saved_xf_host, false );
}

wp_set_current_user( (int) $user_id );

if ( $switched ) {
	wp_set_current_user( 0 );
}


// ============================================================
// Privacy section (lane 3b runtime cases). Transactional: audit rows
// created here use the wp_eval_ action prefix and are deleted; option
// changes are snapshotted and restored.
// ============================================================
$privacy_audit_rows = [];
$GLOBALS['privacy_restore_user'] = get_current_user_id();

// Sweep THIS user's probe rows from earlier runs: their ON-leg rows carry
// raw excerpts by design and would otherwise make the OFF-leg export
// no-leak assertion fail on every subsequent run.
function struo_eval_sweep_probe_audit_rows() {
	global $wpdb;
	$table = $wpdb->prefix . 'sae_audit_log';
	$removed = $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$table} WHERE action = 'field_update' AND user_id = %d AND ( details LIKE %s OR details LIKE %s )",
		get_current_user_id(),
		'%' . $wpdb->esc_like( 'before-secret-text' ) . '%',
		'%' . $wpdb->esc_like( 'after-secret-text' ) . '%'
	) );
	if ( $removed ) {
		echo 'NOTE: swept ' . (int) $removed . " probe audit row(s) from earlier runs.\n";
	}
}
struo_eval_sweep_probe_audit_rows();

// Version guard: these cases exercise the lane-3b implementation. If the
// site plugin predates it, SKIP with an actionable message instead of
// fataling mid-section (which used to leave residue and exit 0).
if ( ! method_exists( 'Struo_Block_Editor', 'audit_excerpt' )
	|| ! method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
	check( '(p1-p6) privacy runtime cases', false,
		'site plugin predates lane 3b (missing audit_excerpt/flush_options_cache) — check out the feat/privacy-integration tip' );
	print_summary_and_exit();
}

function struo_eval_backdate( $days ) {
	return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
}

function struo_eval_insert_audit( $action, $days_old, $user_id = 0, $post_id = 0 ) {
	$minter = new ReflectionMethod( 'Struo_Block_Editor', 'insert_audit_table_entry' );
	$minter->setAccessible( true );
	$ok = $minter->invoke( null, $action, [ 'post_id' => $post_id ], [
		'timestamp' => struo_eval_backdate( $days_old ),
		'user_id' => $user_id,
	] );
	global $wpdb;
	$id = (int) $wpdb->insert_id;
	if ( $ok && $id > 0 ) {
		$GLOBALS['privacy_audit_rows'][] = $id;
	}
	return $id;
}

function struo_eval_cleanup_privacy() {
	global $wpdb;
	$rows = $GLOBALS['privacy_audit_rows'] ?? [];
	foreach ( $rows as $id ) {
		$wpdb->delete( $wpdb->prefix . 'sae_audit_log', [ 'id' => (int) $id ], [ '%d' ] );
	}
	delete_option( 'struo_openai_api_key' );
	if ( $GLOBALS['privacy_restore_user'] ) {
		wp_set_current_user( $GLOBALS['privacy_restore_user'] );
	}
}

register_shutdown_function( 'struo_eval_cleanup_privacy' );

// --- (1) Retention by time ---
$retention_rows = [
	struo_eval_insert_audit( 'wp_eval_retention', 100 ),
	struo_eval_insert_audit( 'wp_eval_retention', 50 ),
	struo_eval_insert_audit( 'wp_eval_retention', 1 ),
];
$options_now = get_option( 'struo_options', [] );
$options_now['audit_retention_days'] = 90;
update_option( 'struo_options', $options_now, false );
Struo_Block_Editor::flush_options_cache();

$pruner = new ReflectionMethod( 'Struo_Block_Editor', 'run_audit_retention_prune' );
$pruner->setAccessible( true );
$pruner->invoke( null );

$remaining = [];
foreach ( $retention_rows as $idx => $id ) {
	$row = $GLOBALS['wpdb']->get_row(
		$GLOBALS['wpdb']->prepare( "SELECT id FROM {$GLOBALS['wpdb']->prefix}sae_audit_log WHERE id = %d", $id )
	);
	if ( $row ) {
		$remaining[] = $idx; // 0=100d, 1=50d, 2=1d
	}
}
check( '(p1) 90-day retention removes only the 100-day-old row', $remaining === [ 1, 2 ], 'kept=' . json_encode( $remaining ) );

// retention 0: time-prune skipped entirely.
$options_now['audit_retention_days'] = 0;
update_option( 'struo_options', $options_now, false );
Struo_Block_Editor::flush_options_cache();
$id_keep = struo_eval_insert_audit( 'wp_eval_retention', 400 );
$pruner->invoke( null );
$still_there = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare( "SELECT id FROM {$GLOBALS['wpdb']->prefix}sae_audit_log WHERE id = %d", $id_keep )
);
check( '(p1) retention 0 skips the time prune (400-day row kept)', (int) $still_there === $id_keep );
$GLOBALS['wpdb']->delete( $GLOBALS['wpdb']->prefix . 'sae_audit_log', [ 'id' => $id_keep ], [ '%d' ] );

// clamp on save.
$sanitizer = new ReflectionMethod( 'Struo_Block_Editor', 'sanitize_options' );
$sanitizer->setAccessible( true );
$sanitized = $sanitizer->invoke( null, [ 'audit_retention_days' => 99999 ] );
check( '(p1) retention clamped to 3650 on save', isset( $sanitized['audit_retention_days'] ) && 3650 === $sanitized['audit_retention_days'] );

// --- (2) Cron: single event, no duplicates, cleared on deactivate ---
$hook = 'struo_audit_retention_prune';
wp_clear_scheduled_hook( $hook );
$scheduler = new ReflectionMethod( 'Struo_Block_Editor', 'maybe_schedule_audit_retention_prune' );
$scheduler->setAccessible( true );
$scheduler->invoke( null );
$scheduler->invoke( null );

$crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
$events = 0;
foreach ( (array) $crons as $timestamp => $cron ) {
	if ( isset( $cron[ $hook ] ) ) {
		$events += count( $cron[ $hook ] );
	}
}
check( '(p2) exactly one scheduled prune event after two bootstraps', 1 === $events, "found={$events}" );

wp_clear_scheduled_hook( $hook );
$crons = _get_cron_array();
$events = 0;
foreach ( (array) $crons as $timestamp => $cron ) {
	if ( isset( $cron[ $hook ] ) ) {
		$events += count( $cron[ $hook ] );
	}
}
check( '(p2) unscheduled after deactivate-style clear', 0 === $events );
$scheduler->invoke( null ); // restore for the site.

struo_eval_release_active_plans();

// --- (3) Redaction: fields/update dry-run + apply with excerpts OFF/ON ---
if ( ! get_current_user_id() ) {
	$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	wp_set_current_user( (int) $admins[0] );
}

$temp_post = wp_insert_post( [
	'post_type' => 'page',
	'post_status' => 'draft',
	'post_title' => '[struo-eval-temp] privacy probe',
	'post_content' => '<!-- wp:paragraph --><p>before-secret-text</p><!-- /wp:paragraph -->',
	'post_author' => get_current_user_id(),
] );
$GLOBALS['privacy_audit_rows']['temp_post'] = $temp_post;

$options_now = get_option( 'struo_options', [] );
$options_now['allowed_post_ids'] = array_values( array_unique( array_merge(
	array_map( 'absint', (array) ( $options_now['allowed_post_ids'] ?? [] ) ),
	[ (int) $temp_post ]
) ) );
update_option( 'struo_options', $options_now, false );

// OFF then ON, sharing ONE deterministic field value so the apply's
// operation_hash matches the dry-run's (a fresh random value per call
// would fail confirmation with sae_confirmation_payload_mismatch).
$probe_before = 'before-secret-text';
$probe_after = 'after-secret-text-abc12345';

$omitted_request = new WP_REST_Request();
$omitted_request->set_param( 'id', $temp_post );
$omitted_request->set_body_params(
	[
		'field' => 'excerpt',
		'value' => 'omitted-dry-run-must-preview',
	]
);
$omitted = Struo_Block_Editor::update_post_field( $omitted_request );
$omitted_confirmation = is_array( $omitted ) ? ( $omitted['confirmation'] ?? [] ) : [];
check( '(p3b) omitted dry_run on fields/update is a preview', is_array( $omitted ) && true === ( $omitted['dry_run'] ?? false ) );
check( '(p3b) omitted dry_run returns durable plan_id', '' !== sanitize_text_field( (string) ( $omitted['plan_id'] ?? '' ) ) );
check( '(p3b) omitted dry_run is plan-only (non-redeemable)', is_array( $omitted_confirmation ) && false === ( $omitted_confirmation['redeemable'] ?? null ) && '' === (string) ( $omitted_confirmation['token'] ?? '' ) );
check( '(p3b) omitted dry_run did not write the excerpt', 'omitted-dry-run-must-preview' !== (string) get_post_field( 'post_excerpt', $temp_post ) );

function struo_eval_field_update( $post_id, $dry_run, $token = '', $value = '' ) {
	$request = new WP_REST_Request();
	$request->set_param( 'id', $post_id );
	$request->set_body_params( [
		'field' => 'excerpt',
		'value' => $value,
		'dry_run' => $dry_run,
		'confirmation_token' => $token,
	] );
	return Struo_Block_Editor::update_post_field( $request );
}

function struo_eval_last_field_update_row( $post_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'sae_audit_log';
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, user_id, action, details FROM {$table} WHERE post_id = %d AND action = 'field_update' AND user_id = %d ORDER BY id DESC LIMIT 1",
			$post_id,
			get_current_user_id()
		),
		ARRAY_A
	);
}

$off_details = null;
$on_details = null;
$apply_errors = [];

try {
	// Leg 1: excerpts OFF.
	$options_now['audit_store_excerpts'] = 0;
	update_option( 'struo_options', $options_now, false );
	Struo_Block_Editor::flush_options_cache();

	$dry = struo_eval_field_update( $temp_post, true, '', $probe_after );
	$plan_id = is_array( $dry ) ? sanitize_text_field( (string) ( $dry['plan_id'] ?? '' ) ) : '';
	check( '(p3) rest dry-run returns durable plan_id (no redeemable token)', '' !== $plan_id );
	$dry_confirmation = is_array( $dry ) ? ( $dry['confirmation'] ?? [] ) : [];
	check( '(p3) rest dry-run confirmation is non-redeemable', is_array( $dry_confirmation ) && false === ( $dry_confirmation['redeemable'] ?? null ) );

	$approve_req = new WP_REST_Request( 'POST', '/struo/v1/agent-plans/' . $plan_id . '/approve' );
	$approve_req->set_url_params( [ 'id' => $plan_id ] );
	$approved = Struo_Block_Editor::approve_agent_plan( $approve_req );
	check( '(p3) durable field plan approves', ! is_wp_error( $approved ) && 'approved' === sanitize_key( (string) ( $approved['state'] ?? '' ) ) );

	$apply_req = new WP_REST_Request( 'POST', '/struo/v1/agent-plans/' . $plan_id . '/apply' );
	$apply_req->set_url_params( [ 'id' => $plan_id ] );
	$apply = Struo_Block_Editor::apply_agent_plan( $apply_req );
	if ( is_wp_error( $apply ) ) {
		$apply_errors[] = 'OFF leg: ' . $apply->get_error_code() . ': ' . $apply->get_error_message()
			. ' | data: ' . wp_json_encode( $apply->get_error_data() );
	} else {
		$row = struo_eval_last_field_update_row( $temp_post );
		check( '(p3) OFF leg wrote a field_update audit row for this user', is_array( $row ),
			is_array( $row ) ? '' : 'no field_update row found' );
		$off_details = is_array( $row ) ? json_decode( (string) ( $row['details'] ?? '' ), true ) : null;
	}

	// Export shape + no-leak asserted DURING the OFF leg.
	$exporter = new ReflectionMethod( 'Struo_Block_Editor', 'export_audit_rows_for_user' );
	$exporter->setAccessible( true );
	$email = get_userdata( get_current_user_id() )->user_email;
	$export = $exporter->invoke( null, $email, 1 );

	// Leg 2: excerpts ON.
	$options_now['audit_store_excerpts'] = 1;
	update_option( 'struo_options', $options_now, false );
	Struo_Block_Editor::flush_options_cache();

	$dry2 = struo_eval_field_update( $temp_post, true, '', $probe_after );
	$plan_id2 = is_array( $dry2 ) ? sanitize_text_field( (string) ( $dry2['plan_id'] ?? '' ) ) : '';

	$approve_req2 = new WP_REST_Request( 'POST', '/struo/v1/agent-plans/' . $plan_id2 . '/approve' );
	$approve_req2->set_url_params( [ 'id' => $plan_id2 ] );
	Struo_Block_Editor::approve_agent_plan( $approve_req2 );

	$apply_req2 = new WP_REST_Request( 'POST', '/struo/v1/agent-plans/' . $plan_id2 . '/apply' );
	$apply_req2->set_url_params( [ 'id' => $plan_id2 ] );
	$apply2 = Struo_Block_Editor::apply_agent_plan( $apply_req2 );
	if ( is_wp_error( $apply2 ) ) {
		$apply_errors[] = 'ON leg: ' . $apply2->get_error_code() . ': ' . $apply2->get_error_message()
			. ' | data: ' . wp_json_encode( $apply2->get_error_data() );
	} else {
		$row2 = struo_eval_last_field_update_row( $temp_post );
		$on_details = is_array( $row2 ) ? json_decode( (string) ( $row2['details'] ?? '' ), true ) : null;
	}
} finally {
	// Immediate targeted cleanup: restore options, delete the temp post.
	// Audit rows are removed by the shutdown handler.
	if ( $GLOBALS['struo_eval_cleanup']['option_saved'] ) {
		$snapshot = $GLOBALS['struo_eval_cleanup']['option_value'];
		if ( null === $snapshot ) {
			delete_option( 'struo_options' );
		} else {
			update_option( 'struo_options', $snapshot, false );
		}
	}
	wp_delete_post( (int) $temp_post, true );
}

check( '(p3) both legs applied without WP_Error', empty( $apply_errors ),
	empty( $apply_errors ) ? '' : implode( ' || ', $apply_errors ) );

// OFF assertions: hash + length form, no raw text anywhere.
$off_json = wp_json_encode( $off_details );
$raw_found = is_string( $off_json )
	&& ( strpos( $off_json, $probe_before ) !== false || strpos( $off_json, $probe_after ) !== false );
check( '(p3) excerpts OFF: no raw text anywhere in the audit row', is_array( $off_details ) && ! $raw_found,
	is_array( $off_details ) ? wp_json_encode( $off_details ) : 'no row' );
$hash_ok = false;
foreach ( [ 'from', 'to' ] as $field ) {
	$value = (string) ( $off_details[ $field ] ?? '' );
	if ( preg_match( '/^[0-9a-f]{12}:len=\\d+$/', $value ) ) {
		$hash_ok = true;
	}
}
check( '(p3) excerpts OFF: values are sha256-prefix + length form', $hash_ok );

// ON assertions: raw excerpt present, capped at 120 chars.
$raw_present = is_array( $on_details ) && (
	strpos( (string) ( $on_details['from'] ?? '' ), $probe_before ) !== false
	|| strpos( (string) ( $on_details['to'] ?? '' ), $probe_after ) !== false );
$length_ok = true;
foreach ( [ 'from', 'to' ] as $field ) {
	$value = (string) ( $on_details[ $field ] ?? '' );
	if ( '' !== $value && strlen( $value ) > 120 ) {
		$length_ok = false;
	}
}
check( '(p3) excerpts ON: raw excerpt stored, capped at 120 chars', $raw_present && $length_ok,
	is_array( $on_details ) ? wp_json_encode( $on_details ) : 'no ON row' );

// Export must not leak raw text while OFF — re-check export shape too.
$shape_ok = isset( $export['data'], $export['done'] )
	&& is_array( $export['data'] )
	&& array_key_exists( 'group_id', reset( $export['data'] ) ?: [] )
	&& array_key_exists( 'group_label', reset( $export['data'] ) ?: [] )
	&& array_key_exists( 'item_id', reset( $export['data'] ) ?: []
);
check( '(p4) exporter returns the core personal-data shape', $shape_ok );
$export_count = is_array( $export['data'] ?? null ) ? count( $export['data'] ) : 0;
$export_done = (bool) ( $export['done'] ?? false );
check(
	'(p4) exporter paginates (done=true when fewer than one page)',
	( $export_count < 100 && true === $export_done ) || ( 100 === $export_count && false === $export_done ),
	'count=' . $export_count . ' done=' . var_export( $export['done'] ?? null, true )
);

// --- (5) Eraser anonymises only the requested user ---
$user_a = get_current_user_id();
$user_b = struo_eval_other_user_id( $user_a );
$row_a = struo_eval_insert_audit( 'wp_eval_erase', 0, $user_a, $temp_post );
$row_b = struo_eval_insert_audit( 'wp_eval_erase', 0, $user_b, $temp_post );

$eraser = new ReflectionMethod( 'Struo_Block_Editor', 'anonymise_audit_rows_for_user' );
$eraser->setAccessible( true );
$email_a = get_userdata( $user_a )->user_email;
$erase_result = $eraser->invoke( null, $email_a, 1 );
check( '(p5) eraser returns items_removed/retained/messages/done keys',
	is_array( $erase_result )
	&& array_key_exists( 'items_removed', $erase_result )
	&& array_key_exists( 'retained_messages', $erase_result )
	&& array_key_exists( 'messages', $erase_result )
	&& array_key_exists( 'done', $erase_result ) );

global $wpdb;
$a_uid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}sae_audit_log WHERE id = %d", $row_a ) );
$b_uid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}sae_audit_log WHERE id = %d", $row_b ) );
check( '(p5) erased user rows have user_id=0', 0 === $a_uid, "uid={$a_uid}" );
check( '(p5) other users rows untouched', $user_b === $b_uid, "uidB={$b_uid} want={$user_b}" );

// --- (4b/5b) Option-store fallback: table mocked absent ---
// audit_table_exists() is backed by a cached property; flip it to false so
// the exporter/eraser take the buffered-option path. Snapshot and restore.
$cache_prop = new ReflectionProperty( 'Struo_Block_Editor', 'audit_table_exists_cache' );
$cache_prop->setAccessible( true );
$saved_cache = $cache_prop->getValue();
$saved_audit_key = get_option( 'struo_audit', null );
$cache_prop->setValue( null, false );

$user_a2 = get_current_user_id();
$user_b2 = struo_eval_other_user_id( $user_a2 );

$buffered = [
	[ 'timestamp' => gmdate( 'Y-m-d H:i:s' ), 'user_id' => $user_a2, 'action' => 'wp_eval_buffered', 'details' => [ 'post_id' => 0 ] ],
	[ 'timestamp' => gmdate( 'Y-m-d H:i:s' ), 'user_id' => $user_b2, 'action' => 'wp_eval_buffered', 'details' => [ 'post_id' => 0 ] ],
];
update_option( 'struo_audit', $buffered, false );

$email_a2 = get_userdata( $user_a2 )->user_email;
$exp = $exporter->invoke( null, $email_a2, 1 );
$a_items = [];
foreach ( (array) ( $exp['data'] ?? [] ) as $item ) {
	if ( strpos( (string) ( $item['item_id'] ?? '' ), 'struo-audit-buffered-' ) === 0 ) {
		$a_items[] = $item;
	}
}
check( '(p4b) exporter includes option-store rows for the user with the table absent', count( $a_items ) >= 1, 'items=' . count( $a_items ) );
check( "(p4b) option-store export rows carry only this user's entries", count( $a_items ) === 1 );

$erase2 = $eraser->invoke( null, $email_a2, 1 );
check( '(p5b) eraser reports the anonymised buffered rows', is_array( $erase2 ) && ( (int) $erase2['items_removed'] ) >= 1,
	is_array( $erase2 ) ? 'removed=' . var_export( $erase2['items_removed'], true ) : 'no result' );

$after = get_option( 'struo_audit', [] );
$a_uid_after = null;
$b_uid_after = null;
foreach ( (array) $after as $entry ) {
	if ( ! is_array( $entry ) || 'wp_eval_buffered' !== ( $entry['action'] ?? '' ) ) {
		continue;
	}
	if ( absint( $entry['user_id'] ?? -1 ) === 0 && null === $a_uid_after ) {
		$a_uid_after = 0;
	} elseif ( (int) $entry['user_id'] === $user_b2 ) {
		$b_uid_after = (int) $entry['user_id'];
	}
}
check( '(p5b) buffered row for erased user has user_id=0', 0 === $a_uid_after );
check( '(p5b) buffered row for the other user untouched', $user_b2 === $b_uid_after, 'uidB=' . (string) $b_uid_after . ' want=' . (string) $user_b2 );

// Two-page export: buffer larger than one page must paginate without
// duplicate item ids and each buffered row appears exactly once.
$bulk = [];
for ( $i = 0; $i < 150; $i++ ) {
	$bulk[] = [
		'timestamp' => gmdate( 'Y-m-d H:i:s' ),
		'user_id' => $user_a2,
		'action' => 'wp_eval_bulk',
		'details' => [ 'post_id' => 0, 'seq' => $i ],
	];
}
update_option( 'struo_audit', $bulk, false );

$page1 = $exporter->invoke( null, $email_a2, 1 );
$page2 = $exporter->invoke( null, $email_a2, 2 );

$ids1 = [];
foreach ( (array) ( $page1['data'] ?? [] ) as $item ) {
	$ids1[] = (string) ( $item['item_id'] ?? '' );
}
$ids2 = [];
foreach ( (array) ( $page2['data'] ?? [] ) as $item ) {
	$ids2[] = (string) ( $item['item_id'] ?? '' );
}
$buffered_ids = array_values( array_filter( array_merge( $ids1, $ids2 ), static function ( $id ) {
	return strpos( $id, 'struo-audit-buffered-' ) === 0;
} ) );

check( '(p4b) page 1 is full (100 items) and not done', 100 === count( $ids1 ) && false === ( $page1['done'] ?? true ),
	'count=' . count( $ids1 ) . ' done=' . var_export( $page1['done'] ?? null, true ) );
check( '(p4b) page 2 carries the remaining 50 items and is done', 50 === count( $ids2 ) && true === ( $page2['done'] ?? false ),
	'count=' . count( $ids2 ) . ' done=' . var_export( $page2['done'] ?? null, true ) );
check( '(p4b) no duplicate item ids across pages',
	count( $ids1 ) === count( array_unique( $ids1 ) )
	&& count( $ids2 ) === count( array_unique( $ids2 ) )
	&& 0 === count( array_intersect( $ids1, $ids2 ) ) );
check( '(p4b) buffered rows appear exactly once across pages', 150 === count( $buffered_ids ),
	'buffered=' . count( $buffered_ids ) );

// Restore.
if ( null === $saved_audit_key ) {
	delete_option( 'struo_audit' );
} else {
	update_option( 'struo_audit', $saved_audit_key, false );
}
$cache_prop->setValue( null, true );

// --- (p4b-v3) Table PRESENT, mocked row sets: concatenated-list math ---
// Mock table rows are real inserts for a dedicated test user, deleted
// right after the scenarios.
$mock_user = $user_a2;
$mock_row_ids = [];
$insert_mock = new ReflectionMethod( 'Struo_Block_Editor', 'insert_audit_table_entry' );
$insert_mock->setAccessible( true );

function struo_eval_delete_mock_rows( $user_id ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->prefix}sae_audit_log WHERE user_id = %d AND action = 'wp_eval_mock'",
		$user_id
	) );
}

function struo_eval_topup_table_rows( $target_total, $insert_mock, &$ids, $user_id ) {
	// Insert exactly enough mock rows for this user's TOTAL row count
	// (any action) to reach the scenario target — other audit rows written
	// earlier in this run count toward it.
	global $wpdb;
	$table = $wpdb->prefix . 'sae_audit_log';
	$current = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id )
	);
	$needed = max( 0, $target_total - $current );
	for ( $i = 0; $i < $needed; $i++ ) {
		$insert_mock->invoke( null, 'wp_eval_mock', [ 'seq' => $i ], [ 'user_id' => $user_id ] );
		$ids[] = (int) $GLOBALS['wpdb']->insert_id;
	}
}

function struo_eval_export_pages( $exporter, $email, $pages ) {
	$out = [];
	foreach ( range( 1, $pages ) as $p ) {
		$out[ $p ] = $exporter->invoke( null, $email, $p );
	}
	return $out;
}

function struo_eval_page_ids( $page_result ) {
	$ids = [];
	foreach ( (array) ( $page_result['data'] ?? [] ) as $item ) {
		$ids[] = (string) ( $item['item_id'] ?? '' );
	}
	return $ids;
}

function struo_eval_buffered_ids_across( $pages ) {
	$all = [];
	foreach ( $pages as $result ) {
		foreach ( (array) ( $result['data'] ?? [] ) as $item ) {
			if ( strpos( (string) ( $item['item_id'] ?? '' ), 'struo-audit-buffered-' ) === 0 ) {
				$all[] = $item['item_id'];
			}
		}
	}
	return $all;
}

// Scenario 1: table 100 + buffer 150 -> 3 pages (100 table / 100 buffered / 50 buffered).
struo_eval_delete_mock_rows( $mock_user );
struo_eval_topup_table_rows( 100, $insert_mock, $mock_row_ids, $mock_user );
$bulk150 = [];
for ( $i = 0; $i < 150; $i++ ) {
	$bulk150[] = [ 'timestamp' => gmdate( 'Y-m-d H:i:s' ), 'user_id' => $mock_user, 'action' => 'wp_eval_bulk150', 'details' => [ 'post_id' => 0 ] ];
}
update_option( 'struo_audit', $bulk150, false );

$s1 = struo_eval_export_pages( $exporter, get_userdata( $mock_user )->user_email, 3 );
$p1_ids = struo_eval_page_ids( $s1[1] );
$p2_ids = struo_eval_page_ids( $s1[2] );
$p3_ids = struo_eval_page_ids( $s1[3] );
check( '(p4b-v3) s1 page 1: 100 table rows, not done', 100 === count( $p1_ids ) && false === ( $s1[1]['done'] ?? true ) );
check( '(p4b-v3) s1 page 2: 100 buffered rows, not done', 100 === count( $p2_ids ) && false === ( $s1[2]['done'] ?? true ) );
check( '(p4b-v3) s1 page 3: 50 buffered rows, done', 50 === count( $p3_ids ) && true === ( $s1[3]['done'] ?? false ) );
$s1_all = array_merge( $p1_ids, $p2_ids, $p3_ids );
check( '(p4b-v3) s1 no duplicate item ids across pages', count( $s1_all ) === count( array_unique( $s1_all ) ) );
$s1_buf = struo_eval_buffered_ids_across( $s1 );
check( '(p4b-v3) s1 each buffered row exactly once', 150 === count( $s1_buf ) && count( $s1_buf ) === count( array_unique( $s1_buf ) ), 'buffered=' . count( $s1_buf ) );

// Scenario 2: table 250 + buffer 50 -> page 3 = 50 table + 50 buffered, done.
struo_eval_delete_mock_rows( $mock_user );
struo_eval_topup_table_rows( 250, $insert_mock, $mock_row_ids, $mock_user );
$bulk50 = [];
for ( $i = 0; $i < 50; $i++ ) {
	$bulk50[] = [ 'timestamp' => gmdate( 'Y-m-d H:i:s' ), 'user_id' => $mock_user, 'action' => 'wp_eval_bulk50', 'details' => [ 'post_id' => 0 ] ];
}
update_option( 'struo_audit', $bulk50, false );

$s2 = struo_eval_export_pages( $exporter, get_userdata( $mock_user )->user_email, 3 );
$p3_ids2 = struo_eval_page_ids( $s2[3] );
$table_part_p3 = 0;
$buffered_part_p3 = 0;
foreach ( $p3_ids2 as $id ) {
	strpos( $id, 'struo-audit-buffered-' ) === 0 ? $buffered_part_p3++ : $table_part_p3++;
}
	global $wpdb;
	$diag_table = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}sae_audit_log WHERE user_id = %d",
		$user_a2
	) );
	$diag_buffered = count( (array) get_option( 'struo_audit', [] ) );
	check( '(p4b-v3) s2 page 3: 50 table + 50 buffered, done',
		50 === $table_part_p3 && 50 === $buffered_part_p3 && true === ( $s2[3]['done'] ?? false ),
		'table=' . $table_part_p3 . ' buffered=' . $buffered_part_p3
		. ' | db: user_rows=' . $diag_table . ' buffered_option=' . $diag_buffered
		. ' | p1=' . count( $ids1 ) . ' p2=' . count( $ids2 )
		. ' done1=' . var_export( $s2[1]['done'] ?? null, true )
		. ' done2=' . var_export( $s2[2]['done'] ?? null, true )
		. ' done3=' . var_export( $s2[3]['done'] ?? null, true ) );
$s2_buf = struo_eval_buffered_ids_across( $s2 );
check( '(p4b-v3) s2 each buffered row exactly once', 50 === count( $s2_buf ) && count( $s2_buf ) === count( array_unique( $s2_buf ) ), 'buffered=' . count( $s2_buf ) );

// Scenario 3: table 200 + buffer 0 -> page 2 done=true on the exact boundary.
struo_eval_delete_mock_rows( $mock_user );
struo_eval_topup_table_rows( 200, $insert_mock, $mock_row_ids, $mock_user );
update_option( 'struo_audit', [], false );

$s3 = struo_eval_export_pages( $exporter, get_userdata( $mock_user )->user_email, 2 );
check( '(p4b-v3) s3 page 1: 100 rows, not done', 100 === count( struo_eval_page_ids( $s3[1] ) ) && false === ( $s3[1]['done'] ?? true ) );
check( '(p4b-v3) s3 page 2: done=true on the exact boundary', true === ( $s3[2]['done'] ?? false ) );

// Cleanup mock rows.
struo_eval_delete_mock_rows( $mock_user );

// --- (6) Disclosure panel: provider host visible, API key never ---
$options_now['ai_openai_base_url'] = 'https://test-provider.example/v1';
$options_now['ai_provider'] = 'openai';
update_option( 'struo_options', $options_now, false );
update_option( 'struo_openai_api_key', 'sk-test-secret-key-never-leak', false );

// Resolve the host exactly like the panel does (constants win over
// options by design), then assert the panel shows THAT host.
$host_resolver = new ReflectionMethod( 'Struo_Block_Editor', 'get_openai_base_url' );
$host_resolver->setAccessible( true );
$resolved_host = (string) wp_parse_url( $host_resolver->invoke( null ), PHP_URL_HOST );

ob_start();
Struo_Block_Editor::render_settings_page();
$html = ob_get_clean();

check( '(p6) settings panel names the resolved provider host', '' !== $resolved_host && strpos( $html, $resolved_host ) !== false,
	'expected host: ' . $resolved_host );
check( '(p6) settings panel never contains the API key', strpos( $html, 'sk-test-secret-key-never-leak' ) === false );

unset( $options_now['ai_openai_base_url'] );
$options_now['ai_provider'] = 'auto';
update_option( 'struo_options', $options_now, false );
delete_option( 'struo_openai_api_key' );
delete_option( 'struo_openai_key_host' );
if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
	Struo_Block_Editor::flush_options_cache();
}

// --- (7) Daily spend cap refuses before the provider hop; last hop is recorded ---
$spend_key = 'struo_plan_spend_' . wp_date( 'Ymd' );
$saved_spend = get_option( $spend_key, null );
$saved_hop = get_option( 'struo_planner_last_hop', null );
$saved_daily_cap = absint( $options_now['ai_plan_daily_token_cap'] ?? 0 );
$options_now['ai_plan_daily_token_cap'] = 8;
update_option( 'struo_options', $options_now, false );
Struo_Block_Editor::flush_options_cache();
update_option( $spend_key, 8, false );

$ai_caller = new ReflectionMethod( 'Struo_Block_Editor', 'call_ai_json_query' );
$ai_caller->setAccessible( true );
$blocked = $ai_caller->invoke( null, '0123456789abcdef', [], 'invalid' );
check( '(p7) over-cap returns sae_plan_spend_cap', is_wp_error( $blocked ) && 'sae_plan_spend_cap' === $blocked->get_error_code() );
$spend_data = is_wp_error( $blocked ) ? $blocked->get_error_data() : [];
check( '(p7) over-cap error is typed HTTP 429', is_array( $spend_data ) && 429 === absint( $spend_data['status'] ?? 0 ) );

$status = Struo_Block_Editor::get_console_status();
$plan_spend = is_array( $status['plan_spend'] ?? null ) ? $status['plan_spend'] : [];
$last_hop = is_array( $status['ai_provider']['last'] ?? null ) ? $status['ai_provider']['last'] : [];
check( '(p7) status reports the daily cap', 8 === absint( $plan_spend['cap'] ?? 0 ) );
check( '(p7) last hop records the spend refusal', 'sae_plan_spend_cap' === (string) ( $last_hop['code'] ?? '' ) && empty( $last_hop['ok'] ) );
check( '(p7) last hop names a backend', '' !== (string) ( $last_hop['backend'] ?? '' ) );
check( '(p7) last hop has no prompt text', ! array_key_exists( 'prompt', $last_hop ) );
$backend_now = (string) ( $status['ai_provider']['backend'] ?? '' );
check( '(p7) resolved backend is a known hop', in_array( $backend_now, [ 'openai', 'client', 'ai_engine' ], true ), 'backend=' . $backend_now );
check( '(p7) text_generation flag is boolean', is_bool( $status['platform']['text_generation'] ?? null ) );

$options_now['ai_plan_daily_token_cap'] = $saved_daily_cap;
update_option( 'struo_options', $options_now, false );
Struo_Block_Editor::flush_options_cache();
if ( null === $saved_spend ) {
	delete_option( $spend_key );
} else {
	update_option( $spend_key, $saved_spend, false );
}
if ( null === $saved_hop ) {
	delete_option( 'struo_planner_last_hop' );
} else {
	update_option( 'struo_planner_last_hop', $saved_hop, false );
}

// --- (p8) Lane 2 routing: pin wins; Auto uses Client only with text_generation ---
$backend_fn = new ReflectionMethod( 'Struo_Block_Editor', 'get_ai_planner_backend' );
$backend_fn->setAccessible( true );
$tg_prop = new ReflectionProperty( 'Struo_Block_Editor', 'client_text_generation' );
$tg_prop->setAccessible( true );
$saved_tg = $tg_prop->getValue( null );
global $mwai;
$saved_mwai = $mwai;

$live_backend = (string) $backend_fn->invoke( null );
check(
	'(p8) STRUO_OPENAI pin (or Auto fallthrough) still resolves a hop',
	in_array( $live_backend, [ 'openai', 'client', 'ai_engine' ], true ),
	'backend=' . $live_backend
);
if ( defined( 'STRUO_AI_PROVIDER' ) && 'openai' === sanitize_key( (string) STRUO_AI_PROVIDER ) ) {
	check( '(p8) STRUO_AI_PROVIDER=openai keeps the HTTP hop', 'openai' === $live_backend );
}

$auto_provider = static function () {
	return 'auto';
};
add_filter( 'struo_provider', $auto_provider );
$mwai = null;
$tg_prop->setValue( null, false );
check( '(p8) Auto without text_generation or AI Engine falls through to HTTP', 'openai' === $backend_fn->invoke( null ) );
$tg_prop->setValue( null, true );
check( '(p8) Auto with a text_generation model uses Client', 'client' === $backend_fn->invoke( null ) );
remove_filter( 'struo_provider', $auto_provider );

$pin_openai = static function () {
	return 'openai';
};
add_filter( 'struo_provider', $pin_openai );
$tg_prop->setValue( null, true );
check( '(p8) openai pin keeps HTTP even when Client would work', 'openai' === $backend_fn->invoke( null ) );
remove_filter( 'struo_provider', $pin_openai );

$mwai = $saved_mwai;
$tg_prop->setValue( null, $saved_tg );

$status_rest = struo_eval_rest( 'GET', '/struo/v1/console/status' );
$status_rest_data = struo_eval_rest_data( $status_rest );
$rest_backend = (string) ( $status_rest_data['ai_provider']['backend'] ?? '' );
$rest_last = is_array( $status_rest_data['ai_provider']['last'] ?? null ) ? $status_rest_data['ai_provider']['last'] : null;
check( '(p8) GET /console/status is 200', 200 === struo_eval_rest_status( $status_rest ) );
check(
	'(p8) GET /console/status names the hop that will run',
	in_array( $rest_backend, [ 'openai', 'client', 'ai_engine' ], true ),
	'backend=' . $rest_backend
);
check( '(p8) GET /console/status last hop has no prompt text', ! is_array( $rest_last ) || ! array_key_exists( 'prompt', $rest_last ) );

// --- (b1)–(b4) Lane 4: bundle plan compile + persist ---
struo_eval_release_active_plans();
$bundle_a = struo_eval_insert_bundle_post( $user_id, 'bundle-a', 'bundle-a-before' );
$bundle_b = struo_eval_insert_bundle_post( $user_id, 'bundle-b', 'bundle-b-before' );
$bundle_c = struo_eval_insert_bundle_post( $user_id, 'bundle-c', 'bundle-c-before' );
if ( $bundle_a <= 0 || $bundle_b <= 0 || $bundle_c <= 0 ) {
	check( '(b1) bundle fixture posts created', false, 'wp_insert_post failed' );
} else {
	struo_eval_reset_rate_limiters();
	struo_eval_allowlist_merge( [ $bundle_a, $bundle_b ] );

	$bundle_before_transients = count_confirm_transients();
	$bundle_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
			]
		)
	);
	$bundle_data = struo_eval_rest_data( $bundle_rest );
	$bundle_children = is_array( $bundle_data['children'] ?? null ) ? $bundle_data['children'] : [];
	$bundle_conf = struo_eval_rest_confirmation( $bundle_data );
	$bundle_child_posts = array_map( 'absint', wp_list_pluck( $bundle_children, 'post_id' ) );
	sort( $bundle_child_posts );
	$expect_posts = [ $bundle_a, $bundle_b ];
	sort( $expect_posts );
	$bundle_plan_id = (string) ( $bundle_data['plan_id'] ?? '' );
	check(
		'(b1) POST /plan with two post_ids returns bundle_v1',
		200 === struo_eval_rest_status( $bundle_rest ) && 'bundle_v1' === sanitize_key( (string) ( $bundle_data['payload_type'] ?? '' ) ),
		'status=' . struo_eval_rest_status( $bundle_rest ) . ' type=' . sanitize_key( (string) ( $bundle_data['payload_type'] ?? '' ) ) . ' code=' . struo_eval_rest_code( $bundle_rest )
	);
	check(
		'(b1) bundle has a durable plan_id',
		1 === preg_match( '/^[a-f0-9]{16}$/', $bundle_plan_id )
	);
	check( '(b1) bundle has two children', 2 === count( $bundle_children ) );
	check( '(b1) children match the allowlisted post_ids', $expect_posts === $bundle_child_posts );
	check(
		'(b1) bundle is non-redeemable and has no token',
		false === ( $bundle_conf['redeemable'] ?? null )
			&& '' === (string) ( $bundle_conf['token'] ?? '' )
			&& ! isset( $bundle_data['confirmation_token'] )
	);
	$bundle_afters = array_map( 'strval', wp_list_pluck( $bundle_children, 'after' ) );
	$bundle_befores = array_map( 'strval', wp_list_pluck( $bundle_children, 'before' ) );
	$bundle_after_transients = count_confirm_transients();
	check(
		'(b1) children compile a real after preview',
		2 === count( $bundle_children ) && ! in_array( '', $bundle_afters, true ) && false !== strpos( implode( ' ', $bundle_afters ), 'bundle-after' ),
		'after=' . implode( '|', $bundle_afters )
	);
	check(
		'(b1) children before is the resolved field, not the whole post',
		false !== strpos( implode( ' ', $bundle_befores ), 'bundle-a-before' )
			&& false !== strpos( implode( ' ', $bundle_befores ), 'bundle-b-before' )
			&& false === strpos( implode( ' ', $bundle_befores ), 'wp:paragraph' ),
		'before=' . implode( '|', $bundle_befores )
	);
	check(
		'(b1) child compile does not mint confirmation transients',
		$bundle_after_transients === $bundle_before_transients,
		'before=' . $bundle_before_transients . ' after=' . $bundle_after_transients
	);
	$bundle_get = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $bundle_plan_id );
	$bundle_stored = struo_eval_rest_data( $bundle_get );
	$bundle_stored_plan = is_array( $bundle_stored['plan'] ?? null ) ? $bundle_stored['plan'] : [];
	$bundle_stored_payload = is_array( $bundle_stored_plan['payload'] ?? null ) ? $bundle_stored_plan['payload'] : [];
	$bundle_stored_child_ids = is_array( $bundle_stored_payload['child_ids'] ?? null ) ? $bundle_stored_payload['child_ids'] : [];
	check(
		'(b1) parent bundle_v1 is persisted',
		200 === struo_eval_rest_status( $bundle_get )
			&& 'bundle_v1' === sanitize_key( (string) ( $bundle_stored_plan['payload_type'] ?? '' ) )
			&& 2 === count( $bundle_stored_child_ids ),
		'status=' . struo_eval_rest_status( $bundle_get ) . ' type=' . sanitize_key( (string) ( $bundle_stored_plan['payload_type'] ?? '' ) )
	);
	check(
		'(ptc-e3) two-child bundle still compiles real previews',
		2 === count( $bundle_children ) && ! in_array( '', $bundle_afters, true ) && false !== strpos( implode( ' ', $bundle_afters ), 'bundle-after' ),
		'after=' . implode( '|', $bundle_afters )
	);

	struo_eval_reset_rate_limiters();
	$solo_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a ],
			]
		)
	);
	$solo_data = struo_eval_rest_data( $solo_rest );
	$solo_fields = is_array( $solo_data['payload']['fields'] ?? null ) ? $solo_data['payload']['fields'] : [];
	$solo_field = '';
	$solo_after = '';
	if ( ! empty( $solo_fields ) ) {
		$solo_keys = array_keys( $solo_fields );
		$solo_field = sanitize_key( (string) $solo_keys[0] );
		$solo_after = is_scalar( $solo_fields[ $solo_keys[0] ] ) ? (string) $solo_fields[ $solo_keys[0] ] : '';
	}
	$child_a = null;
	foreach ( $bundle_children as $bundle_child_row ) {
		if ( is_array( $bundle_child_row ) && absint( $bundle_child_row['post_id'] ?? 0 ) === $bundle_a ) {
			$child_a = $bundle_child_row;
			break;
		}
	}
	$child_a_field = is_array( $child_a ) ? sanitize_key( (string) ( $child_a['field'] ?? '' ) ) : '';
	$child_a_after = is_array( $child_a ) ? (string) ( $child_a['after'] ?? '' ) : '';
	check(
		'(ptc-e1) same instruction, one page vs bundle child, same field and after',
		200 === struo_eval_rest_status( $solo_rest )
			&& is_array( $child_a )
			&& '' !== $solo_field
			&& $solo_field === $child_a_field
			&& $solo_after === $child_a_after
			&& false !== strpos( $solo_after, 'bundle-after' ),
		'status=' . struo_eval_rest_status( $solo_rest ) . ' solo=' . $solo_field . ':' . $solo_after . ' child=' . $child_a_field . ':' . $child_a_after
	);

	struo_eval_reset_rate_limiters();
	$missing_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Insert a paragraph.',
			'post_id' => $bundle_a,
			'plan' => [
				'operation' => 'insert',
				'block_name' => 'core/paragraph',
				'post_id' => $bundle_a,
			],
		]
	);
	check(
		'(ptc-e3) one-page Improve still 400s on missing fields',
		400 === struo_eval_rest_status( $missing_rest )
			&& 'sae_plan_missing_fields' === struo_eval_rest_code( $missing_rest ),
		'status=' . struo_eval_rest_status( $missing_rest ) . ' code=' . struo_eval_rest_code( $missing_rest )
	);

	$bundle_omit_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b, $bundle_c ],
			]
		)
	);
	$bundle_omit_data = struo_eval_rest_data( $bundle_omit_rest );
	check(
		'(b2) third post not allowlisted is omitted from children',
		403 === struo_eval_rest_status( $bundle_omit_rest )
			&& 'sae_plan_post_not_allowed' === struo_eval_rest_code( $bundle_omit_rest ),
		'status=' . struo_eval_rest_status( $bundle_omit_rest ) . ' code=' . struo_eval_rest_code( $bundle_omit_rest )
	);
	check(
		'(b2) omitted post is not in persisted child_ids',
		'' === (string) ( $bundle_omit_data['plan_id'] ?? '' ),
		'plan_id=' . (string) ( $bundle_omit_data['plan_id'] ?? '' )
	);

	$bundle_create_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'intent' => 'create',
			'request' => 'Create a new landing page',
			'post_ids' => [ $bundle_a, $bundle_b ],
		]
	);
	check(
		'(b3) intent=create with post_ids is sae_bundle_create_forbidden',
		400 === struo_eval_rest_status( $bundle_create_rest ) && 'sae_bundle_create_forbidden' === struo_eval_rest_code( $bundle_create_rest ),
		'status=' . struo_eval_rest_status( $bundle_create_rest ) . ' code=' . struo_eval_rest_code( $bundle_create_rest )
	);
	$bundle_spec_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'payload_type' => 'page_spec_v1',
			'request' => 'Create a new landing page',
			'post_ids' => [ $bundle_a, $bundle_b ],
		]
	);
	check(
		'(b3) page_spec with post_ids is sae_bundle_create_forbidden',
		400 === struo_eval_rest_status( $bundle_spec_rest ) && 'sae_bundle_create_forbidden' === struo_eval_rest_code( $bundle_spec_rest ),
		'status=' . struo_eval_rest_status( $bundle_spec_rest ) . ' code=' . struo_eval_rest_code( $bundle_spec_rest )
	);

	struo_eval_free_plan_capacity( 13 );
	$bundle_cap_ids = [];
	for ( $i = 1; $i <= 13; $i++ ) {
		$cap_id = struo_eval_insert_bundle_post( $user_id, 'bundle-cap-' . $i, 'bundle-cap-' . $i );
		if ( $cap_id > 0 ) {
			$bundle_cap_ids[] = $cap_id;
		}
	}
	if ( 13 !== count( $bundle_cap_ids ) ) {
		check( '(b4) proposed set truncates at 12', false, 'could not create 13 fixture posts' );
	} else {
		$opts_cap = get_option( 'struo_options', [] );
		if ( ! is_array( $opts_cap ) ) {
			$opts_cap = [];
		}
		$saved_allowed = array_map( 'absint', (array) ( $opts_cap['allowed_post_ids'] ?? [] ) );
		$opts_cap['allowed_post_ids'] = $bundle_cap_ids;
		update_option( 'struo_options', $opts_cap, false );
		if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
			Struo_Block_Editor::flush_options_cache();
		}
		$bundle_cap_rest = struo_eval_rest(
			'POST',
			'/struo/v1/plan',
			struo_eval_bundle_plan_body()
		);
		$bundle_cap_data = struo_eval_rest_data( $bundle_cap_rest );
		$bundle_cap_children = is_array( $bundle_cap_data['children'] ?? null ) ? $bundle_cap_data['children'] : [];
		$bundle_cap_posts = array_map( 'absint', wp_list_pluck( $bundle_cap_children, 'post_id' ) );
		$bundle_overflow_rest = struo_eval_rest(
			'POST',
			'/struo/v1/plan',
			struo_eval_bundle_plan_body(
				[
					'post_ids' => $bundle_cap_ids,
				]
			)
		);
		$opts_cap['allowed_post_ids'] = $saved_allowed;
		update_option( 'struo_options', $opts_cap, false );
		if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
			Struo_Block_Editor::flush_options_cache();
		}
		check(
			'(b4) proposed set truncates at 12',
			200 === struo_eval_rest_status( $bundle_cap_rest )
				&& 12 === count( $bundle_cap_children )
				&& ! in_array( $bundle_cap_ids[12], $bundle_cap_posts, true ),
			'status=' . struo_eval_rest_status( $bundle_cap_rest ) . ' n=' . count( $bundle_cap_children ) . ' code=' . struo_eval_rest_code( $bundle_cap_rest )
		);
		check(
			'(b4) explicit post_ids over 12 is sae_bundle_too_large',
			400 === struo_eval_rest_status( $bundle_overflow_rest ) && 'sae_bundle_too_large' === struo_eval_rest_code( $bundle_overflow_rest ),
			'status=' . struo_eval_rest_status( $bundle_overflow_rest ) . ' code=' . struo_eval_rest_code( $bundle_overflow_rest )
		);
	}

	struo_eval_release_active_plans();
	struo_eval_reset_rate_limiters();
	$b5_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
				'plan' => [
					'operation' => 'update',
					'block_name' => 'core/paragraph',
					'fields' => [ 'content' => 'bundle-apply-a' ],
				],
			]
		)
	);
	$b5_data = struo_eval_rest_data( $b5_rest );
	$b5_children = is_array( $b5_data['children'] ?? null ) ? $b5_data['children'] : [];
	$b5_id = (string) ( $b5_data['plan_id'] ?? '' );
	$b5_keep = is_array( $b5_children[0] ?? null ) ? $b5_children[0] : [];
	$b5_drop = is_array( $b5_children[1] ?? null ) ? $b5_children[1] : [];
	$b5_keep_id = (string) ( $b5_keep['plan_id'] ?? '' );
	$b5_drop_id = (string) ( $b5_drop['plan_id'] ?? '' );
	$b5_keep_post = absint( $b5_keep['post_id'] ?? 0 );
	$b5_drop_post = absint( $b5_drop['post_id'] ?? 0 );
	$b5_select = struo_eval_rest(
		'POST',
		'/struo/v1/console/agent-plans/' . $b5_id . '/select',
		[
			'selected_ids' => [ $b5_keep_id ],
			'select_revision' => absint( $b5_data['select_revision'] ?? 0 ),
		]
	);
	$b5_selected = is_array( struo_eval_rest_data( $b5_select )['selected_ids'] ?? null )
		? array_map( 'strval', struo_eval_rest_data( $b5_select )['selected_ids'] )
		: [];
	$b5_approve = struo_eval_approve_agent_plan( $b5_id );
	$b5_keep_get = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $b5_keep_id ) );
	$b5_drop_get = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $b5_drop_id ) );
	$b5_keep_state = sanitize_key( (string) ( ( $b5_keep_get['plan']['state'] ?? '' ) ) );
	$b5_drop_state = sanitize_key( (string) ( ( $b5_drop_get['plan']['state'] ?? '' ) ) );
	check(
		'(b5) select drops one child; approve bundle does not approve the dropped child',
		200 === struo_eval_rest_status( $b5_select )
			&& [ $b5_keep_id ] === $b5_selected
			&& 200 === struo_eval_rest_status( $b5_approve )
			&& 'approved' === $b5_keep_state
			&& 'planned' === $b5_drop_state,
		'select=' . struo_eval_rest_status( $b5_select ) . ' approve=' . struo_eval_rest_status( $b5_approve ) . ' keep=' . $b5_keep_state . ' drop=' . $b5_drop_state
	);
	$b5_child_approve = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b5_drop_id . '/approve' );
	$b5_locked = struo_eval_rest(
		'POST',
		'/struo/v1/console/agent-plans/' . $b5_id . '/select',
		[
			'selected_ids' => [ $b5_keep_id, $b5_drop_id ],
			'select_revision' => 0,
		]
	);
	check(
		'(b5) child approve is rejected; selection locks after parent approve',
		409 === struo_eval_rest_status( $b5_child_approve )
			&& 'sae_bundle_approve_parent' === struo_eval_rest_code( $b5_child_approve )
			&& 409 === struo_eval_rest_status( $b5_locked )
			&& 'sae_bundle_selection_locked' === struo_eval_rest_code( $b5_locked ),
		'child_approve=' . struo_eval_rest_status( $b5_child_approve ) . '/' . struo_eval_rest_code( $b5_child_approve ) . ' locked=' . struo_eval_rest_status( $b5_locked ) . '/' . struo_eval_rest_code( $b5_locked )
	);

	struo_eval_reset_rate_limiters();
	$b6_parent = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b5_id . '/apply' );
	$b6_child = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b5_keep_id . '/apply' );
	$b6_keep_text = struo_eval_post_text( $b5_keep_post );
	$b6_drop_text = struo_eval_post_text( $b5_drop_post );
	check(
		'(b6) apply parent bundle id is rejected; apply child persists that post only',
		409 === struo_eval_rest_status( $b6_parent )
			&& 'sae_bundle_apply_parent' === struo_eval_rest_code( $b6_parent )
			&& 200 === struo_eval_rest_status( $b6_child )
			&& false !== strpos( $b6_keep_text, 'bundle-apply-a' )
			&& false === strpos( $b6_drop_text, 'bundle-apply-a' ),
		'parent=' . struo_eval_rest_status( $b6_parent ) . '/' . struo_eval_rest_code( $b6_parent ) . ' child=' . struo_eval_rest_status( $b6_child )
	);
	$b6_unselected = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b5_drop_id . '/apply' );
	check(
		'(b6) unselected child apply is rejected',
		409 === struo_eval_rest_status( $b6_unselected ) && 'sae_bundle_child_not_selected' === struo_eval_rest_code( $b6_unselected ),
		'status=' . struo_eval_rest_status( $b6_unselected ) . ' code=' . struo_eval_rest_code( $b6_unselected )
	);

	struo_eval_release_active_plans();
	struo_eval_reset_rate_limiters();
	$b7_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
				'plan' => [
					'operation' => 'update',
					'block_name' => 'core/paragraph',
					'fields' => [ 'content' => 'bundle-remaining-after' ],
				],
			]
		)
	);
	$b7_data = struo_eval_rest_data( $b7_rest );
	$b7_children = is_array( $b7_data['children'] ?? null ) ? $b7_data['children'] : [];
	$b7_id = (string) ( $b7_data['plan_id'] ?? '' );
	$b7_first = (string) ( $b7_children[0]['plan_id'] ?? '' );
	$b7_second = (string) ( $b7_children[1]['plan_id'] ?? '' );
	$b7_first_post = absint( $b7_children[0]['post_id'] ?? 0 );
	$b7_second_post = absint( $b7_children[1]['post_id'] ?? 0 );
	struo_eval_reset_rate_limiters();
	$b7_approve = struo_eval_approve_agent_plan( $b7_id );
	$b7_apply_first = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b7_first . '/apply' );
	$b7_remaining = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b7_id . '/apply-remaining' );
	$b7_first_text = struo_eval_post_text( $b7_first_post );
	$b7_second_text = struo_eval_post_text( $b7_second_post );
	$b7_c_text = struo_eval_post_text( $bundle_c );
	check(
		'(b7) apply-remaining persists the other selected approved child; skipped/deselected posts unchanged',
		200 === struo_eval_rest_status( $b7_approve )
			&& 200 === struo_eval_rest_status( $b7_apply_first )
			&& 200 === struo_eval_rest_status( $b7_remaining )
			&& false !== strpos( $b7_first_text, 'bundle-remaining-after' )
			&& false !== strpos( $b7_second_text, 'bundle-remaining-after' )
			&& false === strpos( $b7_c_text, 'bundle-remaining-after' ),
		'approve=' . struo_eval_rest_status( $b7_approve ) . ' first=' . struo_eval_rest_status( $b7_apply_first ) . ' remaining=' . struo_eval_rest_status( $b7_remaining ) . ' code=' . struo_eval_rest_code( $b7_remaining )
	);
	$b7_remaining_data = struo_eval_rest_data( $b7_remaining );
	$b7_remaining_results = is_array( $b7_remaining_data['results'] ?? null ) ? $b7_remaining_data['results'] : [];
	$b7_remaining_ids = array_map( 'strval', wp_list_pluck( $b7_remaining_results, 'plan_id' ) );
	$b7_remaining_plan = is_array( $b7_remaining_data['plan'] ?? null ) ? $b7_remaining_data['plan'] : [];
	$b7_remaining_states = array_map( 'strval', wp_list_pluck( is_array( $b7_remaining_plan['children'] ?? null ) ? $b7_remaining_plan['children'] : [], 'state' ) );
	check(
		'(b7) apply-remaining reports every selected child, including already-applied',
		2 === count( $b7_remaining_results )
			&& in_array( $b7_first, $b7_remaining_ids, true )
			&& in_array( $b7_second, $b7_remaining_ids, true )
			&& in_array( 'applied', $b7_remaining_states, true ),
		'results=' . count( $b7_remaining_results ) . ' states=' . implode( ',', $b7_remaining_states )
	);

	$b7_early = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
			]
		)
	);
	$b7_early_id = (string) ( struo_eval_rest_data( $b7_early )['plan_id'] ?? '' );
	$b7_early_remaining = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b7_early_id . '/apply-remaining' );
	check(
		'(b7) apply-remaining without parent approve is rejected',
		409 === struo_eval_rest_status( $b7_early_remaining ) && 'sae_plan_not_approved' === struo_eval_rest_code( $b7_early_remaining ),
		'status=' . struo_eval_rest_status( $b7_early_remaining ) . ' code=' . struo_eval_rest_code( $b7_early_remaining )
	);

	struo_eval_reset_rate_limiters();
	$bundle_d = struo_eval_insert_bundle_post( $user_id, 'bundle-d', 'bundle-d-before' );
	$bundle_e = struo_eval_insert_bundle_post( $user_id, 'bundle-e', 'bundle-e-before' );
	if ( $bundle_d <= 0 || $bundle_e <= 0 ) {
		check( '(b7) apply-remaining skips a deselected child', false, 'fixture posts failed' );
	} else {
	struo_eval_allowlist_merge( [ $bundle_d, $bundle_e ] );
	$b7_deselect_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_d, $bundle_e ],
				'plan' => [
					'operation' => 'update',
					'block_name' => 'core/paragraph',
					'fields' => [ 'content' => 'bundle-deselect-after' ],
				],
			]
		)
	);
	$b7_deselect_data = struo_eval_rest_data( $b7_deselect_rest );
	$b7_deselect_children = is_array( $b7_deselect_data['children'] ?? null ) ? $b7_deselect_data['children'] : [];
	$b7_deselect_id = (string) ( $b7_deselect_data['plan_id'] ?? '' );
	$b7_deselect_keep = (string) ( $b7_deselect_children[0]['plan_id'] ?? '' );
	$b7_deselect_drop = (string) ( $b7_deselect_children[1]['plan_id'] ?? '' );
	$b7_deselect_keep_post = absint( $b7_deselect_children[0]['post_id'] ?? 0 );
	$b7_deselect_drop_post = absint( $b7_deselect_children[1]['post_id'] ?? 0 );
	struo_eval_reset_rate_limiters();
	struo_eval_rest(
		'POST',
		'/struo/v1/console/agent-plans/' . $b7_deselect_id . '/select',
		[
			'selected_ids' => [ $b7_deselect_keep ],
			'select_revision' => absint( $b7_deselect_data['select_revision'] ?? 0 ),
		]
	);
	struo_eval_approve_agent_plan( $b7_deselect_id );
	$b7_deselect_remaining = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b7_deselect_id . '/apply-remaining' );
	check(
		'(b7) apply-remaining skips a deselected child',
		200 === struo_eval_rest_status( $b7_deselect_remaining )
			&& false !== strpos( struo_eval_post_text( $b7_deselect_keep_post ), 'bundle-deselect-after' )
			&& false === strpos( struo_eval_post_text( $b7_deselect_drop_post ), 'bundle-deselect-after' ),
		'plan=' . struo_eval_rest_status( $b7_deselect_rest ) . '/' . struo_eval_rest_code( $b7_deselect_rest ) . ' remaining=' . struo_eval_rest_status( $b7_deselect_remaining ) . '/' . struo_eval_rest_code( $b7_deselect_remaining ) . ' keep=' . struo_eval_post_text( $b7_deselect_keep_post ) . ' drop=' . struo_eval_post_text( $b7_deselect_drop_post )
	);
	}

	struo_eval_reset_rate_limiters();
	$b8_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
			]
		)
	);
	$b8_data = struo_eval_rest_data( $b8_rest );
	$b8_id = (string) ( $b8_data['plan_id'] ?? '' );
	$b8_child = (string) ( ( $b8_data['children'][0]['plan_id'] ?? '' ) );
	struo_eval_approve_agent_plan( $b8_id );
	$opts_kill = get_option( 'struo_options', [] );
	if ( ! is_array( $opts_kill ) ) {
		$opts_kill = [];
	}
	$opts_kill['kill_switch'] = 1;
	update_option( 'struo_options', $opts_kill, false );
	if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
		Struo_Block_Editor::flush_options_cache();
	}
	$b8_remaining = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b8_id . '/apply-remaining' );
	$b8_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b8_child . '/apply' );
	$opts_kill['kill_switch'] = 0;
	update_option( 'struo_options', $opts_kill, false );
	if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
		Struo_Block_Editor::flush_options_cache();
	}
	check(
		'(b8) kill switch 403s apply-remaining and apply-this',
		403 === struo_eval_rest_status( $b8_remaining )
			&& 'sae_kill_switch' === struo_eval_rest_code( $b8_remaining )
			&& 403 === struo_eval_rest_status( $b8_apply )
			&& 'sae_kill_switch' === struo_eval_rest_code( $b8_apply ),
		'remaining=' . struo_eval_rest_status( $b8_remaining ) . '/' . struo_eval_rest_code( $b8_remaining ) . ' apply=' . struo_eval_rest_status( $b8_apply ) . '/' . struo_eval_rest_code( $b8_apply )
	);
	$b8_err = struo_eval_rest_data( $b8_remaining );
	$b8_err_data = is_array( $b8_err['data'] ?? null ) ? $b8_err['data'] : $b8_err;
	check(
		'(b8) kill switch returns interrupted results payload',
		! empty( $b8_err_data['interrupted'] ) && array_key_exists( 'results', $b8_err_data ),
		'interrupted=' . ( ! empty( $b8_err_data['interrupted'] ) ? '1' : '0' ) . ' results=' . ( array_key_exists( 'results', $b8_err_data ) ? '1' : '0' )
	);

	struo_eval_release_active_plans();
	$b9_mcp = Struo_Block_Editor::dispatch_internal(
		'plan-block-change',
		array_merge(
			struo_eval_bundle_plan_body(
				[
					'post_ids' => [ $bundle_a, $bundle_b ],
				]
			),
			[ 'prefer_ai' => false ]
		),
		'mcp',
		[ 'tool' => 'eval_bundle_mcp', 'request_id' => 'eval-bundle-mcp' ]
	);
	if ( is_array( $b9_mcp ) ) {
		struo_eval_track_plans_from_value( $b9_mcp );
	}
	$b9_conf = is_array( $b9_mcp ) && is_array( $b9_mcp['confirmation'] ?? null ) ? $b9_mcp['confirmation'] : [];
	$b9_agent_id = is_array( $b9_mcp ) ? sanitize_text_field( (string) ( $b9_mcp['agent_plan']['id'] ?? '' ) ) : '';
	$b9_child_ids = is_array( $b9_mcp['children'] ?? null ) ? array_map( 'strval', wp_list_pluck( $b9_mcp['children'], 'plan_id' ) ) : [];
	$b9_get = '' !== $b9_agent_id ? struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $b9_agent_id ) ) : [];
	$b9_origin = sanitize_key( (string) ( $b9_get['plan']['origin'] ?? '' ) );
	$b9_preview = '' !== $b9_agent_id
		? struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $b9_agent_id . '/preview' )
		: null;
	$b9_preview_data = $b9_preview ? struo_eval_rest_data( $b9_preview ) : [];
	$b9_status = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/status' ) );
	$b9_inbox_ids = array_map( 'strval', wp_list_pluck( is_array( $b9_status['agent_plans'] ?? null ) ? $b9_status['agent_plans'] : [], 'id' ) );
	$b9_children_hidden = ! empty( $b9_child_ids ) && empty( array_intersect( $b9_child_ids, $b9_inbox_ids ) );
	$b9_ability_schema = false;
	$b9_ability_ok = false;
	if ( class_exists( 'Struo_Abilities' ) ) {
		$defs = Struo_Abilities::definitions();
		$plan_def = is_array( $defs[ Struo_Abilities::ABILITY_PLAN_BLOCK_CHANGE ] ?? null )
			? $defs[ Struo_Abilities::ABILITY_PLAN_BLOCK_CHANGE ]
			: [];
		$b9_ability_schema = isset( $plan_def['input_schema']['properties']['post_ids'] );
		$b9_ability = Struo_Abilities::execute_plan_block_change(
			array_merge(
				struo_eval_bundle_plan_body(
					[
						'post_ids' => [ $bundle_a, $bundle_b ],
					]
				),
				[ 'prefer_ai' => false ]
			)
		);
		$b9_ability_ok = is_array( $b9_ability ) && 'bundle_v1' === sanitize_key( (string) ( $b9_ability['payload_type'] ?? '' ) );
		if ( is_array( $b9_ability ) ) {
			struo_eval_track_plans_from_value( $b9_ability );
		}
	}
	check(
		'(b9) MCP plan-block-change with post_ids queues bundle, redeemable false, no token, audit origin mcp',
		is_array( $b9_mcp )
			&& 'bundle_v1' === sanitize_key( (string) ( $b9_mcp['payload_type'] ?? '' ) )
			&& '' !== $b9_agent_id
			&& false === ( $b9_conf['redeemable'] ?? null )
			&& '' === (string) ( $b9_conf['token'] ?? '' )
			&& 'mcp' === $b9_origin
			&& struo_eval_audit_has_plan_origin( 'plan_bundle', $b9_agent_id, 'mcp' ),
		'type=' . sanitize_key( (string) ( is_array( $b9_mcp ) ? ( $b9_mcp['payload_type'] ?? '' ) : '' ) ) . ' origin=' . $b9_origin . ' error=' . ( is_wp_error( $b9_mcp ) ? $b9_mcp->get_error_code() : '' )
	);
	check(
		'(b9) Ability schema advertises post_ids and execute queues a bundle',
		$b9_ability_schema && $b9_ability_ok,
		'schema=' . ( $b9_ability_schema ? '1' : '0' ) . ' execute=' . ( $b9_ability_ok ? '1' : '0' )
	);
	check(
		'(b9) MCP parent preview hydrates the bundle table and is absent from the Work Queue',
		$b9_preview
			&& 200 === struo_eval_rest_status( $b9_preview )
			&& 'bundle_v1' === sanitize_key( (string) ( $b9_preview_data['payload_type'] ?? '' ) )
			&& is_array( $b9_preview_data['children'] ?? null )
			&& count( $b9_preview_data['children'] ) >= 1
			&& ! in_array( $b9_agent_id, $b9_inbox_ids, true )
			&& $b9_children_hidden,
		'preview=' . ( $b9_preview ? struo_eval_rest_status( $b9_preview ) : '0' ) . '/' . struo_eval_rest_code( $b9_preview ) . ' inbox=' . count( $b9_inbox_ids )
	);
	check(
		'(b10) REST/console bundle audit origin rest matches this plan_id',
		struo_eval_audit_has_plan_origin( 'plan_bundle', $bundle_plan_id, 'rest' )
	);

	$heading_id = wp_insert_post(
		[
			'post_status' => 'draft',
			'post_title' => '[struo-eval-temp] bundle-heading',
			'post_content' => '<!-- wp:heading --><h2>No paragraph here</h2><!-- /wp:heading -->',
			'post_author' => $user_id,
		]
	);
	if ( $heading_id && ! is_wp_error( $heading_id ) ) {
		$GLOBALS['struo_eval_cleanup']['post_ids'][] = (int) $heading_id;
		struo_eval_allowlist_merge( [ (int) $heading_id, $bundle_a ] );
		$partial_rest = struo_eval_rest(
			'POST',
			'/struo/v1/plan',
			struo_eval_bundle_plan_body(
				[
					'post_ids' => [ (int) $heading_id, $bundle_a ],
				]
			)
		);
		$partial_data = struo_eval_rest_data( $partial_rest );
		$partial_children = is_array( $partial_data['children'] ?? null ) ? $partial_data['children'] : [];
		$partial_skipped = is_array( $partial_data['skipped'] ?? null ) ? $partial_data['skipped'] : [];
		check(
			'(b11) one compiled child plus one skipped row is still a bundle',
			200 === struo_eval_rest_status( $partial_rest )
				&& 'bundle_v1' === sanitize_key( (string) ( $partial_data['payload_type'] ?? '' ) )
				&& 1 === count( $partial_children )
				&& count( $partial_skipped ) >= 1,
			'status=' . struo_eval_rest_status( $partial_rest ) . ' children=' . count( $partial_children ) . ' skipped=' . count( $partial_skipped ) . ' code=' . struo_eval_rest_code( $partial_rest )
		);
	} else {
		check( '(b11) one compiled child plus one skipped row is still a bundle', false, 'heading fixture failed' );
	}

	$cas_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
			]
		)
	);
	$cas_data = struo_eval_rest_data( $cas_rest );
	$cas_id = (string) ( $cas_data['plan_id'] ?? '' );
	$cas_children = is_array( $cas_data['children'] ?? null ) ? $cas_data['children'] : [];
	$cas_keep = (string) ( $cas_children[0]['plan_id'] ?? '' );
	$cas_drop = (string) ( $cas_children[1]['plan_id'] ?? '' );
	$cas_first = struo_eval_rest(
		'POST',
		'/struo/v1/console/agent-plans/' . $cas_id . '/select',
		[
			'selected_ids' => [ $cas_keep ],
			'select_revision' => absint( $cas_data['select_revision'] ?? 0 ),
		]
	);
	$cas_stale = struo_eval_rest(
		'POST',
		'/struo/v1/console/agent-plans/' . $cas_id . '/select',
		[
			'selected_ids' => [ $cas_keep, $cas_drop ],
			'select_revision' => absint( $cas_data['select_revision'] ?? 0 ),
		]
	);
	check(
		'(b12) stale select_revision is a conflict',
		200 === struo_eval_rest_status( $cas_first )
			&& 409 === struo_eval_rest_status( $cas_stale )
			&& 'sae_bundle_select_conflict' === struo_eval_rest_code( $cas_stale ),
		'first=' . struo_eval_rest_status( $cas_first ) . ' stale=' . struo_eval_rest_status( $cas_stale ) . '/' . struo_eval_rest_code( $cas_stale )
	);
	$cas_missing = struo_eval_rest(
		'POST',
		'/struo/v1/console/agent-plans/' . $cas_id . '/select',
		[ 'selected_ids' => [ $cas_keep ] ]
	);
	check(
		'(b12) select without select_revision is required',
		400 === struo_eval_rest_status( $cas_missing ) && 'sae_bundle_revision_required' === struo_eval_rest_code( $cas_missing ),
		'status=' . struo_eval_rest_status( $cas_missing ) . ' code=' . struo_eval_rest_code( $cas_missing )
	);
	$cas_approve_stale = struo_eval_rest(
		'POST',
		'/struo/v1/console/agent-plans/' . $cas_id . '/approve',
		[ 'select_revision' => absint( $cas_data['select_revision'] ?? 0 ) ]
	);
	check(
		'(b12) approve with a stale select_revision is a conflict',
		409 === struo_eval_rest_status( $cas_approve_stale ) && 'sae_bundle_select_conflict' === struo_eval_rest_code( $cas_approve_stale ),
		'status=' . struo_eval_rest_status( $cas_approve_stale ) . ' code=' . struo_eval_rest_code( $cas_approve_stale )
	);
	$cas_approve_missing = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $cas_id . '/approve' );
	check(
		'(b12) approve without select_revision is required',
		400 === struo_eval_rest_status( $cas_approve_missing ) && 'sae_bundle_revision_required' === struo_eval_rest_code( $cas_approve_missing ),
		'status=' . struo_eval_rest_status( $cas_approve_missing ) . ' code=' . struo_eval_rest_code( $cas_approve_missing )
	);

	struo_eval_release_active_plans();
	$dismiss_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
			]
		)
	);
	$dismiss_data = struo_eval_rest_data( $dismiss_rest );
	$dismiss_id = (string) ( $dismiss_data['plan_id'] ?? '' );
	$dismiss_child = (string) ( $dismiss_data['children'][0]['plan_id'] ?? '' );
	$dismiss_parent = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $dismiss_id . '/dismiss' );
	$unchecked = new ReflectionMethod( 'Struo_Block_Editor', 'get_durable_plan_record_unchecked' );
	$unchecked->setAccessible( true );
	$dismiss_child_row = $unchecked->invoke( null, $dismiss_child );
	check(
		'(b13) dismiss bundle cancels the child family',
		200 === struo_eval_rest_status( $dismiss_parent )
			&& 'cancelled' === sanitize_key( (string) ( $dismiss_child_row['state'] ?? '' ) ),
		'parent=' . struo_eval_rest_status( $dismiss_parent ) . ' child=' . sanitize_key( (string) ( $dismiss_child_row['state'] ?? '' ) )
	);

	$orphan_id = bin2hex( random_bytes( 8 ) );
	$ghost_id = bin2hex( random_bytes( 8 ) );
	$inserter = new ReflectionMethod( 'Struo_Block_Editor', 'insert_durable_plan_record' );
	$inserter->setAccessible( true );
	$family_fn = new ReflectionMethod( 'Struo_Block_Editor', 'durable_plan_family_ids' );
	$family_fn->setAccessible( true );
	$inserted = $inserter->invoke(
		null,
		[
			'id' => $orphan_id,
			'payload_type' => 'mutation_v1',
			'state' => 'planned',
			'created_at' => time(),
			'expires_at' => time() + 3600,
			'user_id' => $user_id,
			'origin' => 'rest',
			'post_id' => $bundle_a,
			'post' => [ 'post_id' => $bundle_a ],
			'operation' => 'update',
			'endpoint' => sprintf( '/wp-json/struo/v1/posts/%d/blocks/update', $bundle_a ),
			'request' => 'orphan child',
			'payload' => [
				'bundle_id' => $ghost_id,
				'fields' => [ 'content' => 'orphan' ],
			],
			'preview' => null,
			'base_content_hash' => '0',
		]
	);
	$orphan_family = $inserted ? $family_fn->invoke( null, $orphan_id ) : [];
	check(
		'(b14) crash-orphan family ids include the child, not a missing parent',
		$inserted
			&& in_array( $orphan_id, $orphan_family, true )
			&& ! in_array( $ghost_id, $orphan_family, true ),
		'family=' . implode( ',', array_map( 'strval', (array) $orphan_family ) )
	);
	if ( $inserted ) {
		struo_eval_track_plan_id( $orphan_id );
		$wpdb_orphan = $GLOBALS['wpdb'];
		$wpdb_orphan->update(
			struo_eval_plans_table(),
			[ 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 400 ) ],
			[ 'plan_id' => $orphan_id ],
			[ '%s' ],
			[ '%s' ]
		);
		$prune = new ReflectionMethod( 'Struo_Block_Editor', 'prune_durable_plans' );
		$prune->setAccessible( true );
		$prune->invoke( null, [] );
		$orphan_after = $unchecked->invoke( null, $orphan_id );
		check(
			'(b14) below-capacity prune cancels a stale orphan child',
			'cancelled' === sanitize_key( (string) ( $orphan_after['state'] ?? '' ) ),
			'state=' . sanitize_key( (string) ( $orphan_after['state'] ?? '' ) )
		);
	} else {
		check( '(b14) below-capacity prune cancels a stale orphan child', false, 'orphan insert failed' );
	}

	$opts_phrase = get_option( 'struo_options', [] );
	if ( ! is_array( $opts_phrase ) ) {
		$opts_phrase = [];
	}
	$saved_phrase_allowed = array_map( 'absint', (array) ( $opts_phrase['allowed_post_ids'] ?? [] ) );
	$opts_phrase['allowed_post_ids'] = [ $bundle_a, $bundle_b ];
	update_option( 'struo_options', $opts_phrase, false );
	if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
		Struo_Block_Editor::flush_options_cache();
	}
	struo_eval_reset_rate_limiters();
	$phrase_rest = struo_eval_rest( 'POST', '/struo/v1/plan', struo_eval_bundle_plan_body() );
	$phrase_data = struo_eval_rest_data( $phrase_rest );
	$phrase_children = is_array( $phrase_data['children'] ?? null ) ? $phrase_data['children'] : [];
	$phrase_posts = array_map( 'absint', wp_list_pluck( $phrase_children, 'post_id' ) );
	$opts_phrase['allowed_post_ids'] = $saved_phrase_allowed;
	update_option( 'struo_options', $opts_phrase, false );
	if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
		Struo_Block_Editor::flush_options_cache();
	}
	check(
		'(b15) phrase-only request without post_ids still compiles a bundle',
		200 === struo_eval_rest_status( $phrase_rest )
			&& 'bundle_v1' === sanitize_key( (string) ( $phrase_data['payload_type'] ?? '' ) )
			&& in_array( $bundle_a, $phrase_posts, true )
			&& in_array( $bundle_b, $phrase_posts, true ),
		'status=' . struo_eval_rest_status( $phrase_rest ) . ' type=' . sanitize_key( (string) ( $phrase_data['payload_type'] ?? '' ) ) . ' posts=' . implode( ',', $phrase_posts ) . ' code=' . struo_eval_rest_code( $phrase_rest )
	);

	$author_id = wp_insert_user(
		[
			'user_login' => 'struo_eval_au_' . wp_generate_password( 8, false ),
			'user_pass' => wp_generate_password( 24, true ),
			'user_email' => 'struo-eval-au-' . wp_generate_password( 8, false ) . '@example.test',
			'role' => 'author',
			'display_name' => 'Struo Eval Author',
		]
	);
	if ( is_wp_error( $author_id ) ) {
		check( '(b17) author cannot review a bundle that includes an uneditable child', false, $author_id->get_error_message() );
	} else {
		$GLOBALS['struo_eval_cleanup']['user_ids'][] = (int) $author_id;
		$author = new WP_User( (int) $author_id );
		$author->add_cap( 'struo_approve' );
		$author->add_cap( 'struo_apply' );
		$author_post = struo_eval_insert_bundle_post( (int) $author_id, 'bundle-author', 'bundle-author-before' );
		if ( $author_post <= 0 ) {
			check( '(b17) author cannot review a bundle that includes an uneditable child', false, 'author fixture post failed' );
		} else {
			struo_eval_allowlist_merge( [ $author_post, $bundle_a ] );
			wp_set_current_user( (int) $user_id );
			struo_eval_reset_rate_limiters();
			$acl_rest = struo_eval_rest(
				'POST',
				'/struo/v1/plan',
				struo_eval_bundle_plan_body(
					[
						'post_ids' => [ $author_post, $bundle_a ],
					]
				)
			);
			$acl_data = struo_eval_rest_data( $acl_rest );
			$acl_id = (string) ( $acl_data['plan_id'] ?? '' );
			wp_set_current_user( (int) $author_id );
			$acl_get = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $acl_id );
			$acl_select = struo_eval_rest(
				'POST',
				'/struo/v1/console/agent-plans/' . $acl_id . '/select',
				[
					'selected_ids' => is_array( $acl_data['payload']['child_ids'] ?? null ) ? $acl_data['payload']['child_ids'] : [],
					'select_revision' => absint( $acl_data['select_revision'] ?? 0 ),
				]
			);
			$acl_approve = struo_eval_rest(
				'POST',
				'/struo/v1/console/agent-plans/' . $acl_id . '/approve',
				[ 'select_revision' => absint( $acl_data['select_revision'] ?? 0 ) ]
			);
			$acl_dismiss = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $acl_id . '/dismiss' );
			check(
				'(b17) author cannot review a bundle that includes an uneditable child',
				200 === struo_eval_rest_status( $acl_rest )
					&& 403 === struo_eval_rest_status( $acl_get )
					&& 403 === struo_eval_rest_status( $acl_select )
					&& 403 === struo_eval_rest_status( $acl_approve )
					&& 403 === struo_eval_rest_status( $acl_dismiss )
					&& 'sae_insufficient_permissions' === struo_eval_rest_code( $acl_get ),
				'plan=' . struo_eval_rest_status( $acl_rest ) . ' get=' . struo_eval_rest_status( $acl_get ) . '/' . struo_eval_rest_code( $acl_get ) . ' select=' . struo_eval_rest_status( $acl_select ) . ' approve=' . struo_eval_rest_status( $acl_approve ) . ' dismiss=' . struo_eval_rest_status( $acl_dismiss )
			);
			struo_eval_reset_rate_limiters();
			$denied_plan = struo_eval_rest(
				'POST',
				'/struo/v1/plan',
				struo_eval_bundle_plan_body(
					[
						'post_ids' => [ $author_post ],
					]
				)
			);
			check(
				'(s0a) author without struo_plan cannot POST /plan',
				403 === struo_eval_rest_status( $denied_plan ),
				'status=' . struo_eval_rest_status( $denied_plan ) . ' code=' . struo_eval_rest_code( $denied_plan )
			);
			struo_eval_reset_disclosure_spend();
			$denied_catalog = struo_eval_rest( 'GET', '/struo/v1/block-catalog' );
			$denied_status = struo_eval_rest( 'GET', '/struo/v1/console/status' );
			$denied_dispatch = Struo_Block_Editor::dispatch_internal( 'get-block-catalog', [], 'mcp' );
			$denied_ability = Struo_Abilities::can_read_catalog();
			$spend = is_array( $GLOBALS['struo_eval_disclosure_spend'] ?? null ) ? $GLOBALS['struo_eval_disclosure_spend'] : [];
			check(
				'(s1-e1) author without struo_plan cannot GET /block-catalog or /console/status',
				403 === struo_eval_rest_status( $denied_catalog )
					&& 403 === struo_eval_rest_status( $denied_status )
					&& 0 === absint( $spend['rag'] ?? 0 )
					&& 0 === absint( $spend['provider'] ?? 0 ),
				'catalog=' . struo_eval_rest_status( $denied_catalog ) . ' status=' . struo_eval_rest_status( $denied_status )
			);
			check(
				'(s1-e2) ability and MCP catalog deny author without struo_plan',
				false === $denied_ability
					&& is_wp_error( $denied_dispatch )
					&& 'sae_insufficient_permissions' === $denied_dispatch->get_error_code(),
				'ability=' . ( $denied_ability ? '1' : '0' ) . ' dispatch=' . ( is_wp_error( $denied_dispatch ) ? $denied_dispatch->get_error_code() : 'ok' )
			);
			$denied_audit = struo_eval_rest( 'GET', '/struo/v1/console/audit' );
			$denied_promote = struo_eval_rest(
				'POST',
				'/struo/v1/templates/promote-page',
				[ 'post_id' => $author_post ]
			);
			$denied_create = struo_eval_rest(
				'POST',
				'/struo/v1/pages/create',
				[
					'confirmation_token' => 'not-a-token',
					'idempotency_key' => 'eval-s1-create',
				]
			);
			check(
				'(s1-e3) author cannot GET /console/audit',
				403 === struo_eval_rest_status( $denied_audit ),
				'status=' . struo_eval_rest_status( $denied_audit ) . ' code=' . struo_eval_rest_code( $denied_audit )
			);
			check(
				'(s1-e7) author cannot promote or apply-create',
				403 === struo_eval_rest_status( $denied_promote )
					&& 403 === struo_eval_rest_status( $denied_create ),
				'promote=' . struo_eval_rest_status( $denied_promote ) . ' create=' . struo_eval_rest_status( $denied_create )
			);
			wp_set_current_user( 0 );
			$anon_blocks = struo_eval_rest( 'GET', '/struo/v1/posts/' . (int) $author_post . '/blocks' );
			check(
				'(s1-e8) logged-out inspect is 401',
				401 === struo_eval_rest_status( $anon_blocks ),
				'status=' . struo_eval_rest_status( $anon_blocks ) . ' code=' . struo_eval_rest_code( $anon_blocks )
			);
			$author->add_cap( 'struo_plan' );
			wp_set_current_user( 0 );
			wp_set_current_user( (int) $author_id );
			$status_as_author = Struo_Block_Editor::get_console_status();
			$visible_ids = array_map( 'absint', (array) ( $status_as_author['allowlist']['post_ids'] ?? [] ) );
			check(
				'(s0a) status allowlist omits posts the caller cannot edit_post',
				in_array( (int) $author_post, $visible_ids, true ) && ! in_array( (int) $bundle_a, $visible_ids, true ),
				'ids=' . implode( ',', $visible_ids )
			);
			$foreign = struo_eval_rest(
				'POST',
				'/struo/v1/plan',
				[
					'request' => 'Change the paragraph to "nope".',
					'plan' => [
						'post_id' => $bundle_a,
						'operation' => 'update',
						'block_name' => 'core/paragraph',
						'fields' => [ 'content' => 'nope' ],
					],
					'dry_run_preview' => true,
				]
			);
			check(
				'(s0a) struo_plan still requires edit_post before disclosure',
				403 === struo_eval_rest_status( $foreign ),
				'status=' . struo_eval_rest_status( $foreign ) . ' code=' . struo_eval_rest_code( $foreign )
			);
			struo_eval_reset_rate_limiters();
			struo_eval_begin_spend_probe();
			$spend_denied = struo_eval_rest(
				'POST',
				'/struo/v1/plan',
				[
					'request' => 'Change the paragraph to "nope".',
					'prefer_ai' => true,
					'plan' => [
						'post_id' => $bundle_a,
						'operation' => 'update',
						'block_name' => 'core/paragraph',
						'fields' => [ 'content' => 'nope' ],
					],
					'dry_run_preview' => true,
				]
			);
			struo_eval_end_spend_probe();
			$spend = is_array( $GLOBALS['struo_eval_disclosure_spend'] ?? null ) ? $GLOBALS['struo_eval_disclosure_spend'] : [];
			check(
				'(s0a) explicit foreign target is denied before RAG/provider work',
				403 === struo_eval_rest_status( $spend_denied )
					&& 0 === absint( $spend['rag'] ?? 0 )
					&& 0 === absint( $spend['provider'] ?? 0 )
					&& 0 === absint( $GLOBALS['struo_eval_http_calls'] ?? 0 ),
				'status=' . struo_eval_rest_status( $spend_denied ) . ' rag=' . absint( $spend['rag'] ?? 0 ) . ' provider=' . absint( $spend['provider'] ?? 0 ) . ' http=' . absint( $GLOBALS['struo_eval_http_calls'] ?? 0 )
			);
			struo_eval_reset_rate_limiters();
			struo_eval_begin_spend_probe();
			$bundle_denied = struo_eval_rest(
				'POST',
				'/struo/v1/plan',
				struo_eval_bundle_plan_body(
					[
						'post_ids' => [ $bundle_a, $bundle_b ],
						'prefer_ai' => true,
					]
				)
			);
			struo_eval_end_spend_probe();
			$bundle_spend = is_array( $GLOBALS['struo_eval_disclosure_spend'] ?? null ) ? $GLOBALS['struo_eval_disclosure_spend'] : [];
			check(
				'(s0a) explicit foreign bundle IDs are denied before RAG/provider work',
				403 === struo_eval_rest_status( $bundle_denied )
					&& 0 === absint( $bundle_spend['rag'] ?? 0 )
					&& 0 === absint( $bundle_spend['provider'] ?? 0 )
					&& 0 === absint( $GLOBALS['struo_eval_http_calls'] ?? 0 ),
				'status=' . struo_eval_rest_status( $bundle_denied ) . ' rag=' . absint( $bundle_spend['rag'] ?? 0 ) . ' provider=' . absint( $bundle_spend['provider'] ?? 0 ) . ' http=' . absint( $GLOBALS['struo_eval_http_calls'] ?? 0 )
			);
			struo_eval_reset_rate_limiters();
			struo_eval_begin_spend_probe();
			$mixed_denied = struo_eval_rest(
				'POST',
				'/struo/v1/plan',
				struo_eval_bundle_plan_body(
					[
						'post_ids' => [ $author_post, $bundle_a ],
						'prefer_ai' => true,
					]
				)
			);
			struo_eval_end_spend_probe();
			$mixed_spend = is_array( $GLOBALS['struo_eval_disclosure_spend'] ?? null ) ? $GLOBALS['struo_eval_disclosure_spend'] : [];
			check(
				'(s0a) mixed explicit bundle IDs are denied before RAG/provider work',
				403 === struo_eval_rest_status( $mixed_denied )
					&& 0 === absint( $mixed_spend['rag'] ?? 0 )
					&& 0 === absint( $mixed_spend['provider'] ?? 0 )
					&& 0 === absint( $GLOBALS['struo_eval_http_calls'] ?? 0 ),
				'status=' . struo_eval_rest_status( $mixed_denied ) . ' rag=' . absint( $mixed_spend['rag'] ?? 0 ) . ' provider=' . absint( $mixed_spend['provider'] ?? 0 ) . ' http=' . absint( $GLOBALS['struo_eval_http_calls'] ?? 0 )
			);
			check(
				'(b17) compile skips posts the caller cannot edit_post',
				403 === struo_eval_rest_status( $mixed_denied )
					&& 'sae_insufficient_permissions' === struo_eval_rest_code( $mixed_denied ),
				'status=' . struo_eval_rest_status( $mixed_denied ) . ' code=' . struo_eval_rest_code( $mixed_denied )
			);
			wp_set_current_user( (int) $user_id );
		}
	}

	struo_eval_reset_rate_limiters();
	$prefer_pin = static function () {
		return 'openai';
	};
	add_filter( 'struo_provider', $prefer_pin );
	struo_eval_begin_planner_fallback();
	$prefer_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
				'prefer_ai' => true,
			]
		)
	);
	struo_eval_end_planner_fallback();
	remove_filter( 'struo_provider', $prefer_pin );
	$prefer_data = struo_eval_rest_data( $prefer_rest );
	$prefer_children = is_array( $prefer_data['children'] ?? null ) ? $prefer_data['children'] : [];
	$prefer_afters = array_map( 'strval', wp_list_pluck( $prefer_children, 'after' ) );
	check(
		'(ptc-e2) prefer_ai planner unavailable with existing operation compiles children',
		200 === struo_eval_rest_status( $prefer_rest )
			&& 'bundle_v1' === sanitize_key( (string) ( $prefer_data['payload_type'] ?? '' ) )
			&& 2 === count( $prefer_children )
			&& false !== strpos( implode( ' ', $prefer_afters ), 'bundle-after' ),
		'status=' . struo_eval_rest_status( $prefer_rest ) . ' type=' . sanitize_key( (string) ( $prefer_data['payload_type'] ?? '' ) ) . ' children=' . count( $prefer_children ) . ' after=' . implode( '|', $prefer_afters )
	);

	struo_eval_reset_rate_limiters();
	$empty_pin = static function () {
		return 'openai';
	};
	add_filter( 'struo_provider', $empty_pin );
	struo_eval_begin_planner_fallback();
	$empty_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Unspecified work on these allowlisted pages.',
			'post_ids' => [ $bundle_a, $bundle_b ],
			'prefer_ai' => true,
			'dry_run_preview' => true,
		]
	);
	struo_eval_end_planner_fallback();
	remove_filter( 'struo_provider', $empty_pin );
	$empty_data = struo_eval_rest_data( $empty_rest );
	$empty_skipped = [];
	if ( is_array( $empty_data['data']['skipped'] ?? null ) ) {
		$empty_skipped = $empty_data['data']['skipped'];
	} elseif ( is_array( $empty_data['skipped'] ?? null ) ) {
		$empty_skipped = $empty_data['skipped'];
	}
	$empty_codes = wp_list_pluck( $empty_skipped, 'code' );
	check(
		'(ptc-e4) empty operation plus planner unavailable skip-remaining empties the bundle',
		400 === struo_eval_rest_status( $empty_rest )
			&& 'sae_bundle_empty' === struo_eval_rest_code( $empty_rest )
			&& count( $empty_skipped ) >= 2
			&& in_array( 'sae_planner_unavailable', $empty_codes, true ),
		'status=' . struo_eval_rest_status( $empty_rest ) . ' code=' . struo_eval_rest_code( $empty_rest ) . ' skipped=' . count( $empty_skipped ) . ' codes=' . implode( ',', $empty_codes )
	);

	struo_eval_reset_rate_limiters();
	$term_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		struo_eval_bundle_plan_body(
			[
				'post_ids' => [ $bundle_a, $bundle_b ],
				'plan' => [
					'operation' => 'update',
					'block_name' => 'core/paragraph',
					'fields' => [ 'content' => 'bundle-terminal-after' ],
				],
			]
		)
	);
	$term_data = struo_eval_rest_data( $term_rest );
	$term_id = (string) ( $term_data['plan_id'] ?? '' );
	$term_child = (string) ( $term_data['children'][0]['plan_id'] ?? '' );
	$term_approve = struo_eval_approve_agent_plan( $term_id );
	Struo_Durable_Plans::cancel( $term_child );
	$term_remaining = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $term_id . '/apply-remaining' );
	$term_remaining_data = struo_eval_rest_data( $term_remaining );
	$term_results = is_array( $term_remaining_data['results'] ?? null ) ? $term_remaining_data['results'] : [];
	$term_cancelled_row = null;
	foreach ( $term_results as $row ) {
		if ( is_array( $row ) && $term_child === (string) ( $row['plan_id'] ?? '' ) ) {
			$term_cancelled_row = $row;
			break;
		}
	}
	check(
		'(b19) apply-remaining does not succeed while a selected child is cancelled',
		200 === struo_eval_rest_status( $term_approve )
			&& 200 === struo_eval_rest_status( $term_remaining )
			&& false === ( $term_remaining_data['ok'] ?? true )
			&& is_array( $term_cancelled_row )
			&& empty( $term_cancelled_row['ok'] )
			&& 'sae_plan_cancelled' === sanitize_key( (string) ( $term_cancelled_row['code'] ?? '' ) )
			&& 'cancelled' === sanitize_key( (string) ( $term_cancelled_row['outcome'] ?? '' ) ),
		'approve=' . struo_eval_rest_status( $term_approve ) . ' remaining=' . struo_eval_rest_status( $term_remaining ) . ' ok=' . ( empty( $term_remaining_data['ok'] ) ? '0' : '1' ) . ' code=' . sanitize_key( (string) ( $term_cancelled_row['code'] ?? '' ) )
	);

	struo_eval_reset_rate_limiters();
	$s2_plan_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s2-mutation-after".',
			'plan' => [
				'post_id' => $bundle_a,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's2-mutation-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s2_plan_data = struo_eval_rest_data( $s2_plan_rest );
	$s2_plan_id = sanitize_text_field( (string) ( $s2_plan_data['plan_id'] ?? '' ) );
	$s2_get = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s2_plan_id );
	$s2_get_data = struo_eval_rest_data( $s2_get );
	$s2_events = is_array( $s2_get_data['events'] ?? null ) ? $s2_get_data['events'] : [];
	$s2_stored_json = '';
	if ( '' !== $s2_plan_id ) {
		global $wpdb;
		$s2_stored_json = (string) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT payload_json FROM ' . struo_eval_plans_table() . ' WHERE plan_id = %s',
				$s2_plan_id
			)
		);
	}
	$s2_expected_hash = hash( 'sha256', $s2_stored_json );
	check(
		'(s2-e1) plan one allowlisted update GET shows queued journal and readers_match',
		200 === struo_eval_rest_status( $s2_plan_rest )
			&& '' !== $s2_plan_id
			&& 200 === struo_eval_rest_status( $s2_get )
			&& 'queued' === sanitize_key( (string) ( $s2_events[0]['type'] ?? '' ) )
			&& ! empty( $s2_get_data['readers_match'] )
			&& hash_equals( $s2_expected_hash, (string) ( $s2_get_data['payload_hash'] ?? '' ) ),
		'plan=' . struo_eval_rest_status( $s2_plan_rest ) . ' get=' . struo_eval_rest_status( $s2_get ) . ' type=' . sanitize_key( (string) ( $s2_events[0]['type'] ?? '' ) ) . ' match=' . ( ! empty( $s2_get_data['readers_match'] ) ? '1' : '0' )
	);

	$s2_approve = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s2_plan_id . '/approve' );
	$s2_get2 = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s2_plan_id );
	$s2_get2_data = struo_eval_rest_data( $s2_get2 );
	$s2_events2 = is_array( $s2_get2_data['events'] ?? null ) ? $s2_get2_data['events'] : [];
	$s2_types2 = array_map(
		static function ( $event ) {
			return is_array( $event ) ? sanitize_key( (string) ( $event['type'] ?? '' ) ) : '';
		},
		$s2_events2
	);
	$s2_hashes2 = array_map(
		static function ( $event ) {
			return is_array( $event ) ? (string) ( $event['payload_hash'] ?? '' ) : '';
		},
		$s2_events2
	);
	check(
		'(s2-e2) approve appends approved with the same payload hash',
		200 === struo_eval_rest_status( $s2_approve )
			&& 200 === struo_eval_rest_status( $s2_get2 )
			&& in_array( 'queued', $s2_types2, true )
			&& in_array( 'approved', $s2_types2, true )
			&& 2 === count( array_filter( $s2_hashes2 ) )
			&& 1 === count( array_unique( array_filter( $s2_hashes2 ) ) )
			&& ! empty( $s2_get2_data['readers_match'] ),
		'approve=' . struo_eval_rest_status( $s2_approve ) . ' types=' . implode( ',', $s2_types2 ) . ' match=' . ( ! empty( $s2_get2_data['readers_match'] ) ? '1' : '0' )
	);

	$s2_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s2_plan_id . '/apply' );
	$s2_get3 = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s2_plan_id );
	$s2_get3_data = struo_eval_rest_data( $s2_get3 );
	$s2_events3 = is_array( $s2_get3_data['events'] ?? null ) ? $s2_get3_data['events'] : [];
	$s2_types3 = array_map(
		static function ( $event ) {
			return is_array( $event ) ? sanitize_key( (string) ( $event['type'] ?? '' ) ) : '';
		},
		$s2_events3
	);
	check(
		'(s2-e3) apply appends applied; both readers agree and post saved',
		200 === struo_eval_rest_status( $s2_apply )
			&& 200 === struo_eval_rest_status( $s2_get3 )
			&& 'applied' === sanitize_key( (string) ( $s2_get3_data['plan']['state'] ?? '' ) )
			&& 'applied' === sanitize_key( (string) ( $s2_get3_data['projection']['state'] ?? '' ) )
			&& in_array( 'applied', $s2_types3, true )
			&& ! empty( $s2_get3_data['readers_match'] )
			&& false !== strpos( struo_eval_post_text( $bundle_a ), 's2-mutation-after' ),
		'apply=' . struo_eval_rest_status( $s2_apply ) . ' state=' . sanitize_key( (string) ( $s2_get3_data['plan']['state'] ?? '' ) ) . ' match=' . ( ! empty( $s2_get3_data['readers_match'] ) ? '1' : '0' )
	);

	struo_eval_reset_rate_limiters();
	$s2_tamper_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s2-tamper-target".',
			'plan' => [
				'post_id' => $bundle_b,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's2-tamper-target' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s2_tamper_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s2_tamper_rest )['plan_id'] ?? '' ) );
	if ( '' !== $s2_tamper_id ) {
		global $wpdb;
		$wpdb->update(
			struo_eval_plans_table(),
			[ 'payload_json' => '{"tampered":true}' ],
			[ 'plan_id' => $s2_tamper_id ],
			[ '%s' ],
			[ '%s' ]
		);
	}
	$s2_tamper_approve = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s2_tamper_id . '/approve' );
	check(
		'(s2-e4) rewritten payload_json is denied at approve',
		'' !== $s2_tamper_id
			&& 409 === struo_eval_rest_status( $s2_tamper_approve )
			&& 'sae_mutation_payload_changed' === struo_eval_rest_code( $s2_tamper_approve ),
		'id=' . $s2_tamper_id . ' status=' . struo_eval_rest_status( $s2_tamper_approve ) . ' code=' . struo_eval_rest_code( $s2_tamper_approve )
	);

	struo_eval_reset_rate_limiters();
	$s2_backfill_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s2-backfill-after".',
			'plan' => [
				'post_id' => $bundle_b,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's2-backfill-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s2_backfill_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s2_backfill_rest )['plan_id'] ?? '' ) );
	$s2_backfill_approve = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s2_backfill_id . '/approve' );
	if ( '' !== $s2_backfill_id ) {
		global $wpdb;
		$events_table = $wpdb->prefix . 'struo_events';
		if ( struo_eval_table_exists( $events_table ) ) {
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$events_table} WHERE plan_id = %s", $s2_backfill_id )
			);
		}
	}
	$s2_backfill_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s2_backfill_id . '/apply' );
	$s2_backfill_get = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s2_backfill_id );
	$s2_backfill_data = struo_eval_rest_data( $s2_backfill_get );
	$s2_backfill_events = is_array( $s2_backfill_data['events'] ?? null ) ? $s2_backfill_data['events'] : [];
	$s2_backfill_types = array_map(
		static function ( $event ) {
			return is_array( $event ) ? sanitize_key( (string) ( $event['type'] ?? '' ) ) : '';
		},
		$s2_backfill_events
	);
	check(
		'(s2-e5) pre-S2 approved row applies without a second approve and backfills events',
		200 === struo_eval_rest_status( $s2_backfill_rest )
			&& 200 === struo_eval_rest_status( $s2_backfill_approve )
			&& 200 === struo_eval_rest_status( $s2_backfill_apply )
			&& 200 === struo_eval_rest_status( $s2_backfill_get )
			&& in_array( 'queued', $s2_backfill_types, true )
			&& in_array( 'approved', $s2_backfill_types, true )
			&& in_array( 'applied', $s2_backfill_types, true ),
		'approve=' . struo_eval_rest_status( $s2_backfill_approve ) . ' apply=' . struo_eval_rest_status( $s2_backfill_apply ) . ' types=' . implode( ',', $s2_backfill_types )
	);

	$s2_phrase_id = sanitize_text_field( (string) ( $phrase_data['plan_id'] ?? '' ) );
	$s2_phrase_get = '' !== $s2_phrase_id
		? struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s2_phrase_id )
		: null;
	$s2_phrase_data = is_object( $s2_phrase_get ) ? struo_eval_rest_data( $s2_phrase_get ) : [];
	check(
		'(s2-e6) bundle parent GET omits mutation journal extras',
		'' !== $s2_phrase_id
			&& 200 === struo_eval_rest_status( $s2_phrase_get )
			&& ! array_key_exists( 'events', $s2_phrase_data )
			&& ! array_key_exists( 'readers_match', $s2_phrase_data ),
		'id=' . $s2_phrase_id . ' get=' . ( is_object( $s2_phrase_get ) ? struo_eval_rest_status( $s2_phrase_get ) : 'skip' ) . ' keys=' . implode( ',', array_keys( $s2_phrase_data ) )
	);

	check(
		'(s3-e7) bundle parent has no recovery / evidence extras',
		'' !== $s2_phrase_id
			&& 200 === struo_eval_rest_status( $s2_phrase_get )
			&& ! array_key_exists( 'recovery', $s2_phrase_data )
			&& ! array_key_exists( 'evidence', $s2_phrase_data ),
		'id=' . $s2_phrase_id . ' recovery=' . ( array_key_exists( 'recovery', $s2_phrase_data ) ? '1' : '0' )
	);

	struo_eval_reset_rate_limiters();
	$s3_happy = struo_eval_insert_bundle_post( $user_id, 's3-happy', 's3-happy-before' );
	$s3_retry = struo_eval_insert_bundle_post( $user_id, 's3-retry', 's3-retry-before' );
	$s3_recovered = struo_eval_insert_bundle_post( $user_id, 's3-recovered', 's3-recovered-before' );
	$s3_conflict = struo_eval_insert_bundle_post( $user_id, 's3-conflict', 's3-conflict-before' );
	$s3_pub = struo_eval_insert_bundle_post( $user_id, 's3-pub', 's3-pub-before' );
	$s3_draft = struo_eval_insert_bundle_post( $user_id, 's3-draft', 's3-draft-before' );
	if ( $s3_pub > 0 ) {
		wp_update_post(
			[
				'ID' => $s3_pub,
				'post_status' => 'publish',
			]
		);
	}
	struo_eval_allowlist_merge( [ $s3_happy, $s3_retry, $s3_recovered, $s3_conflict, $s3_pub, $s3_draft ] );

	$s3_plan_rest = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s3-happy-after".',
			'plan' => [
				'post_id' => $s3_happy,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's3-happy-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s3_plan_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s3_plan_rest )['plan_id'] ?? '' ) );
	$s3_approve = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_plan_id . '/approve' );
	$s3_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_plan_id . '/apply' );
	$s3_get = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s3_plan_id );
	$s3_get_data = struo_eval_rest_data( $s3_get );
	$s3_evidence = is_array( $s3_get_data['evidence'] ?? null ) ? $s3_get_data['evidence'] : [];
	check(
		'(s3-e1) apply one allowlisted update GET evidence.persisted.match true; readers_match true',
		200 === struo_eval_rest_status( $s3_plan_rest )
			&& 200 === struo_eval_rest_status( $s3_approve )
			&& 200 === struo_eval_rest_status( $s3_apply )
			&& 200 === struo_eval_rest_status( $s3_get )
			&& ! empty( $s3_evidence['persisted']['match'] )
			&& ! empty( $s3_get_data['readers_match'] )
			&& false !== strpos( struo_eval_post_text( $s3_happy ), 's3-happy-after' ),
		'plan=' . struo_eval_rest_status( $s3_plan_rest ) . ' apply=' . struo_eval_rest_status( $s3_apply ) . ' match=' . ( ! empty( $s3_evidence['persisted']['match'] ) ? '1' : '0' )
	);

	$s3_before_hash = hash( 'sha256', (string) get_post_field( 'post_content', $s3_happy ) );
	$s3_rollback = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_plan_id . '/rollback' );
	$s3_rollback_data = struo_eval_rest_data( $s3_rollback );
	$s3_restore_id = sanitize_text_field( (string) ( $s3_rollback_data['plan_id'] ?? '' ) );
	$s3_restore_approve = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_restore_id . '/approve' );
	$s3_restore_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_restore_id . '/apply' );
	$s3_original_after = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s3_plan_id );
	$s3_original_data = struo_eval_rest_data( $s3_original_after );
	$s3_restored_hash = hash( 'sha256', (string) get_post_field( 'post_content', $s3_happy ) );
	check(
		'(s3-e6) rollback queues a new planned restore; approve+apply restores base_content_hash; original stays applied',
		200 === struo_eval_rest_status( $s3_rollback )
			&& '' !== $s3_restore_id
			&& $s3_restore_id !== $s3_plan_id
			&& 200 === struo_eval_rest_status( $s3_restore_approve )
			&& 200 === struo_eval_rest_status( $s3_restore_apply )
			&& 'applied' === sanitize_key( (string) ( $s3_original_data['plan']['state'] ?? '' ) )
			&& false !== strpos( struo_eval_post_text( $s3_happy ), 's3-happy-before' )
			&& 64 === strlen( $s3_restored_hash )
			&& $s3_restored_hash !== $s3_before_hash,
		'rollback=' . struo_eval_rest_status( $s3_rollback ) . ' restore=' . $s3_restore_id . ' apply=' . struo_eval_rest_status( $s3_restore_apply ) . ' original=' . sanitize_key( (string) ( $s3_original_data['plan']['state'] ?? '' ) )
	);

	struo_eval_reset_rate_limiters();
	$s3_retry_plan = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s3-retry-after".',
			'plan' => [
				'post_id' => $s3_retry,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's3-retry-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s3_retry_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s3_retry_plan )['plan_id'] ?? '' ) );
	struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_retry_id . '/approve' );
	struo_eval_begin_crash( $s3_retry_id, 'before_write' );
	$s3_retry_crash = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_retry_id . '/apply' );
	struo_eval_end_crash();
	$s3_retry_recover = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_retry_id . '/apply' );
	$s3_retry_recover_data = struo_eval_rest_data( $s3_retry_recover );
	$s3_retry_unchanged = false !== strpos( struo_eval_post_text( $s3_retry ), 's3-retry-before' )
		&& false === strpos( struo_eval_post_text( $s3_retry ), 's3-retry-after' );
	$s3_retry_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_retry_id . '/apply' );
	check(
		'(s3-e2) crash before write → apply returns safe_to_retry; post unchanged; second apply saves once',
		599 === struo_eval_rest_status( $s3_retry_crash )
			&& 200 === struo_eval_rest_status( $s3_retry_recover )
			&& 'safe_to_retry' === sanitize_key( (string) ( $s3_retry_recover_data['outcome'] ?? '' ) )
			&& $s3_retry_unchanged
			&& 200 === struo_eval_rest_status( $s3_retry_apply )
			&& false !== strpos( struo_eval_post_text( $s3_retry ), 's3-retry-after' ),
		'crash=' . struo_eval_rest_status( $s3_retry_crash ) . ' recover=' . sanitize_key( (string) ( $s3_retry_recover_data['outcome'] ?? '' ) ) . ' apply=' . struo_eval_rest_status( $s3_retry_apply )
	);

	struo_eval_reset_rate_limiters();
	$s3_rec_plan = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s3-recovered-after".',
			'plan' => [
				'post_id' => $s3_recovered,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's3-recovered-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s3_rec_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s3_rec_plan )['plan_id'] ?? '' ) );
	struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_rec_id . '/approve' );
	struo_eval_begin_crash( $s3_rec_id, 'after_write' );
	$s3_rec_crash = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_rec_id . '/apply' );
	struo_eval_end_crash();
	$s3_rec_after_crash = struo_eval_post_text( $s3_recovered );
	$s3_rec_recover = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_rec_id . '/apply' );
	$s3_rec_recover_data = struo_eval_rest_data( $s3_rec_recover );
	check(
		'(s3-e3) crash after write before applied → apply returns recovered_persisted; no second write',
		599 === struo_eval_rest_status( $s3_rec_crash )
			&& false !== strpos( $s3_rec_after_crash, 's3-recovered-after' )
			&& 200 === struo_eval_rest_status( $s3_rec_recover )
			&& 'recovered_persisted' === sanitize_key( (string) ( $s3_rec_recover_data['outcome'] ?? '' ) )
			&& struo_eval_post_text( $s3_recovered ) === $s3_rec_after_crash,
		'crash=' . struo_eval_rest_status( $s3_rec_crash ) . ' recover=' . sanitize_key( (string) ( $s3_rec_recover_data['outcome'] ?? '' ) )
	);

	struo_eval_reset_rate_limiters();
	$s3_conf_plan = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s3-conflict-after".',
			'plan' => [
				'post_id' => $s3_conflict,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's3-conflict-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s3_conf_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s3_conf_plan )['plan_id'] ?? '' ) );
	struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_conf_id . '/approve' );
	struo_eval_begin_crash( $s3_conf_id, 'after_write' );
	$s3_conf_crash = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_conf_id . '/apply' );
	struo_eval_end_crash();
	wp_update_post(
		[
			'ID' => $s3_conflict,
			'post_content' => '<!-- wp:paragraph --><p>s3-conflict-noise</p><!-- /wp:paragraph -->',
		]
	);
	$s3_conf_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_conf_id . '/apply' );
	check(
		'(s3-e4) crash after save before reply, post mutated away from both hashes → sae_outcome_unknown_conflict; no write',
		599 === struo_eval_rest_status( $s3_conf_crash )
			&& 409 === struo_eval_rest_status( $s3_conf_apply )
			&& 'sae_outcome_unknown_conflict' === struo_eval_rest_code( $s3_conf_apply )
			&& false !== strpos( struo_eval_post_text( $s3_conflict ), 's3-conflict-noise' )
			&& false === strpos( struo_eval_post_text( $s3_conflict ), 's3-conflict-after' ),
		'crash=' . struo_eval_rest_status( $s3_conf_crash ) . ' apply=' . struo_eval_rest_status( $s3_conf_apply ) . ' code=' . struo_eval_rest_code( $s3_conf_apply )
	);

	struo_eval_reset_rate_limiters();
	$s3_pub_plan = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s3-pub-after".',
			'plan' => [
				'post_id' => $s3_pub,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's3-pub-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s3_pub_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s3_pub_plan )['plan_id'] ?? '' ) );
	struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_pub_id . '/approve' );
	struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s3_pub_id . '/apply' );
	$s3_pub_get = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s3_pub_id ) );
	$s3_pub_status = sanitize_key( (string) ( $s3_pub_get['evidence']['public']['status'] ?? '' ) );

	$s3_draft_plan = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s3-draft-after".',
			'plan' => [
				'post_id' => $s3_draft,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's3-draft-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s3_draft_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s3_draft_plan )['plan_id'] ?? '' ) );
	$s3_draft_get = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s3_draft_id ) );
	$s3_draft_status = sanitize_key( (string) ( $s3_draft_get['evidence']['public']['status'] ?? '' ) );
	check(
		'(s3-e5) published post: evidence.public is ok or failed (never omitted as live); draft: skipped',
		'' !== $s3_pub_id
			&& in_array( $s3_pub_status, [ 'ok', 'failed' ], true )
			&& 'live' !== $s3_pub_status
			&& '' !== $s3_draft_id
			&& 'skipped' === $s3_draft_status,
		'pub=' . $s3_pub_status . ' draft=' . $s3_draft_status
	);

	struo_eval_reset_rate_limiters();
	$s4_rest_post = struo_eval_insert_bundle_post( $user_id, 's4-rest', 's4-rest-before' );
	$s4_mcp_post = struo_eval_insert_bundle_post( $user_id, 's4-mcp', 's4-mcp-before' );
	$s4_roll_post = struo_eval_insert_bundle_post( $user_id, 's4-roll', 's4-roll-before' );
	struo_eval_allowlist_merge( [ $s4_rest_post, $s4_mcp_post, $s4_roll_post ] );
	$s4_rest_plan = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s4-rest-after".',
			'plan' => [
				'post_id' => $s4_rest_post,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's4-rest-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s4_rest_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s4_rest_plan )['plan_id'] ?? '' ) );
	$s4_list = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s4_items = is_array( $s4_list['items'] ?? null ) ? $s4_list['items'] : [];
	$s4_rest_item = null;
	foreach ( $s4_items as $s4_item ) {
		if ( is_array( $s4_item ) && $s4_rest_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_rest_item = $s4_item;
			break;
		}
	}
	check(
		'(s4-e1) REST-origin standalone update appears in GET /console/agent-plans items',
		200 === struo_eval_rest_status( $s4_rest_plan )
			&& '' !== $s4_rest_id
			&& is_array( $s4_rest_item )
			&& 'rest' === sanitize_key( (string) ( $s4_rest_item['origin'] ?? '' ) ),
		'id=' . $s4_rest_id . ' listed=' . ( is_array( $s4_rest_item ) ? '1' : '0' )
	);

	$s4_mcp = Struo_Block_Editor::dispatch_internal(
		'plan-block-change',
		[
			'post_id' => $s4_mcp_post,
			'dry_run_preview' => true,
			'prefer_ai' => false,
			'request' => 'Change the paragraph to "s4-mcp-after".',
			'plan' => [
				'post_id' => $s4_mcp_post,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's4-mcp-after' ],
			],
		],
		'mcp',
		[ 'tool' => 'eval_s4_mcp', 'request_id' => 'eval-s4-mcp' ]
	);
	$s4_mcp_id = is_array( $s4_mcp ) ? sanitize_text_field( (string) ( $s4_mcp['agent_plan']['id'] ?? '' ) ) : '';
	$s4_list_mcp = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s4_items_mcp = is_array( $s4_list_mcp['items'] ?? null ) ? $s4_list_mcp['items'] : [];
	$s4_mcp_item = null;
	$s4_rest_still = null;
	foreach ( $s4_items_mcp as $s4_item ) {
		if ( ! is_array( $s4_item ) ) {
			continue;
		}
		if ( $s4_mcp_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_mcp_item = $s4_item;
		}
		if ( $s4_rest_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_rest_still = $s4_item;
		}
	}
	check(
		'(s4-e2) MCP-origin standalone update appears in the same items as (s4-e1)',
		'' !== $s4_mcp_id
			&& is_array( $s4_mcp_item )
			&& 'mcp' === sanitize_key( (string) ( $s4_mcp_item['origin'] ?? '' ) )
			&& is_array( $s4_rest_still ),
		'id=' . $s4_mcp_id . ' listed=' . ( is_array( $s4_mcp_item ) ? '1' : '0' ) . ' rest=' . ( is_array( $s4_rest_still ) ? '1' : '0' )
	);

	$s4_approve = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s4_rest_id . '/approve' );
	$s4_apply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s4_rest_id . '/apply' );
	$s4_get = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $s4_rest_id );
	$s4_get_data = struo_eval_rest_data( $s4_get );
	$s4_list_applied = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s4_applied_item = null;
	foreach ( is_array( $s4_list_applied['items'] ?? null ) ? $s4_list_applied['items'] : [] as $s4_item ) {
		if ( is_array( $s4_item ) && $s4_rest_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_applied_item = $s4_item;
			break;
		}
	}
	$s4_queue = is_array( $s4_applied_item['queue'] ?? null ) ? $s4_applied_item['queue'] : [];
	check(
		'(s4-e3) after apply, GET one id has evidence.persisted and evidence.public; list item has queue.persisted and no public fetch fields',
		200 === struo_eval_rest_status( $s4_approve )
			&& 200 === struo_eval_rest_status( $s4_apply )
			&& 200 === struo_eval_rest_status( $s4_get )
			&& isset( $s4_get_data['evidence']['persisted'] )
			&& isset( $s4_get_data['evidence']['public'] )
			&& is_array( $s4_applied_item )
			&& isset( $s4_queue['persisted'] )
			&& ! array_key_exists( 'public', $s4_queue )
			&& ! isset( $s4_applied_item['public'] ),
		'apply=' . struo_eval_rest_status( $s4_apply ) . ' listed=' . ( is_array( $s4_applied_item ) ? '1' : '0' )
	);

	$s4_bundle_id = sanitize_text_field( (string) ( $phrase_data['plan_id'] ?? $bundle_plan_id ) );
	$s4_bundle_listed = false;
	foreach ( is_array( $s4_list_applied['items'] ?? null ) ? $s4_list_applied['items'] : [] as $s4_item ) {
		if ( is_array( $s4_item ) && $s4_bundle_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_bundle_listed = true;
			break;
		}
	}
	check(
		'(s4-e4) bundle parent is absent from Work Queue items',
		'' !== $s4_bundle_id && ! $s4_bundle_listed,
		'id=' . $s4_bundle_id . ' listed=' . ( $s4_bundle_listed ? '1' : '0' )
	);

	$s4_create_listed = false;
	$s4_create_id = sanitize_text_field( (string) ( $ps_rest_id ?? '' ) );
	foreach ( is_array( $s4_list_applied['items'] ?? null ) ? $s4_list_applied['items'] : [] as $s4_item ) {
		if ( is_array( $s4_item ) && $s4_create_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_create_listed = true;
			break;
		}
	}
	check(
		'(s4-e6) page_spec_v1 is absent from Work Queue items (accepted until S4.5)',
		'' !== $s4_create_id && ! $s4_create_listed,
		'id=' . $s4_create_id . ' listed=' . ( $s4_create_listed ? '1' : '0' )
	);

	$s45_list = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s45_discovery = is_array( $s45_list['discovery'] ?? null ) ? $s45_list['discovery'] : [];
	$s45_bundle_disc = null;
	foreach ( $s45_discovery as $s45_item ) {
		if ( is_array( $s45_item ) && $s4_bundle_id === (string) ( $s45_item['id'] ?? '' ) ) {
			$s45_bundle_disc = $s45_item;
			break;
		}
	}
	check(
		'(s45-e1) REST bundle_v1 parent is in discovery, absent from items',
		'' !== $s4_bundle_id
			&& is_array( $s45_bundle_disc )
			&& 'bundle_v1' === sanitize_key( (string) ( $s45_bundle_disc['payload_type'] ?? '' ) )
			&& ! isset( $s45_bundle_disc['queue'] )
			&& ! $s4_bundle_listed,
		'id=' . $s4_bundle_id . ' discovery=' . ( is_array( $s45_bundle_disc ) ? '1' : '0' ) . ' items=' . ( $s4_bundle_listed ? '1' : '0' )
	);

	struo_eval_ensure_plan_headroom( 4, [ $s4_bundle_id, $s4_create_id ] );
	struo_eval_begin_planner_fallback();
	$s45_ps = struo_eval_rest(
		'POST',
		'/struo/v1/pages/plan-create',
		[
			'title' => 'S45 Discovery PageSpec',
			'description' => 'Waiting page_spec for discovery list.',
			'template' => 'blog-post',
			'post_type' => 'post',
			'selected_sections' => [ 0, 1 ],
		]
	);
	struo_eval_end_planner_fallback();
	$s45_ps_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s45_ps )['plan_id'] ?? '' ) );
	$s45_after_ps = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s45_ps_disc = null;
	$s45_ps_item = false;
	foreach ( is_array( $s45_after_ps['discovery'] ?? null ) ? $s45_after_ps['discovery'] : [] as $s45_item ) {
		if ( is_array( $s45_item ) && $s45_ps_id === (string) ( $s45_item['id'] ?? '' ) ) {
			$s45_ps_disc = $s45_item;
			break;
		}
	}
	foreach ( is_array( $s45_after_ps['items'] ?? null ) ? $s45_after_ps['items'] : [] as $s45_item ) {
		if ( is_array( $s45_item ) && $s45_ps_id === (string) ( $s45_item['id'] ?? '' ) ) {
			$s45_ps_item = true;
			break;
		}
	}
	check(
		'(s45-e3) page_spec_v1 is in discovery; (s4-e6) still absent from items',
		200 === struo_eval_rest_status( $s45_ps )
			&& '' !== $s45_ps_id
			&& is_array( $s45_ps_disc )
			&& 'page_spec_v1' === sanitize_key( (string) ( $s45_ps_disc['payload_type'] ?? '' ) )
			&& ! isset( $s45_ps_disc['queue'] )
			&& ! $s45_ps_item,
		'id=' . $s45_ps_id . ' status=' . struo_eval_rest_status( $s45_ps ) . ' discovery=' . ( is_array( $s45_ps_disc ) ? '1' : '0' ) . ' items=' . ( $s45_ps_item ? '1' : '0' )
	);

	$s45_preview = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s45_ps_id . '/preview' );
	$s45_preview_data = struo_eval_rest_data( $s45_preview );
	$s45_preview_json = wp_json_encode( $s45_preview_data );
	check(
		'(s45-e4) POST preview on page_spec has intent=create, payload_type, plan_id, and no serialized_content',
		200 === struo_eval_rest_status( $s45_preview )
			&& 'create' === sanitize_key( (string) ( $s45_preview_data['intent'] ?? '' ) )
			&& 'page_spec_v1' === sanitize_key( (string) ( $s45_preview_data['payload_type'] ?? '' ) )
			&& $s45_ps_id === sanitize_text_field( (string) ( $s45_preview_data['plan_id'] ?? '' ) )
			&& ! preg_match( '/"serialized_content"\s*:/', (string) $s45_preview_json ),
		'status=' . struo_eval_rest_status( $s45_preview ) . ' intent=' . sanitize_key( (string) ( $s45_preview_data['intent'] ?? '' ) ) . ' type=' . sanitize_key( (string) ( $s45_preview_data['payload_type'] ?? '' ) )
	);

	$s45_mcp = Struo_Block_Editor::dispatch_internal(
		'plan-block-change',
		array_merge(
			struo_eval_bundle_plan_body(
				[
					'post_ids' => [ $s4_rest_post, $s4_mcp_post ],
					'request' => 'Change the paragraph to "s45-mcp-disc" on these allowlisted pages.',
					'plan' => [
						'operation' => 'update',
						'block_name' => 'core/paragraph',
						'fields' => [ 'content' => 's45-mcp-disc' ],
					],
				]
			),
			[ 'prefer_ai' => false ]
		),
		'mcp',
		[ 'tool' => 'eval_s45_mcp', 'request_id' => 'eval-s45-mcp' ]
	);
	if ( is_array( $s45_mcp ) ) {
		struo_eval_track_plans_from_value( $s45_mcp );
	}
	$s45_mcp_id = is_array( $s45_mcp ) ? sanitize_text_field( (string) ( $s45_mcp['agent_plan']['id'] ?? $s45_mcp['plan_id'] ?? '' ) ) : '';
	$s45_after_mcp = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s45_mcp_disc = null;
	$s45_mcp_item = false;
	foreach ( is_array( $s45_after_mcp['discovery'] ?? null ) ? $s45_after_mcp['discovery'] : [] as $s45_item ) {
		if ( is_array( $s45_item ) && $s45_mcp_id === (string) ( $s45_item['id'] ?? '' ) ) {
			$s45_mcp_disc = $s45_item;
			break;
		}
	}
	foreach ( is_array( $s45_after_mcp['items'] ?? null ) ? $s45_after_mcp['items'] : [] as $s45_item ) {
		if ( is_array( $s45_item ) && $s45_mcp_id === (string) ( $s45_item['id'] ?? '' ) ) {
			$s45_mcp_item = true;
			break;
		}
	}
	check(
		'(s45-e2) MCP bundle_v1 parent is in discovery',
		is_array( $s45_mcp )
			&& '' !== $s45_mcp_id
			&& is_array( $s45_mcp_disc )
			&& 'bundle_v1' === sanitize_key( (string) ( $s45_mcp_disc['payload_type'] ?? '' ) )
			&& 'mcp' === sanitize_key( (string) ( $s45_mcp_disc['origin'] ?? '' ) )
			&& ! isset( $s45_mcp_disc['queue'] )
			&& ! $s45_mcp_item,
		'id=' . $s45_mcp_id . ' discovery=' . ( is_array( $s45_mcp_disc ) ? '1' : '0' ) . ' origin=' . sanitize_key( (string) ( $s45_mcp_disc['origin'] ?? '' ) ) . ' items=' . ( $s45_mcp_item ? '1' : '0' ) . ' error=' . ( is_wp_error( $s45_mcp ) ? $s45_mcp->get_error_code() : '' )
	);

	$s45_dismiss = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s45_mcp_id . '/dismiss' );
	$s45_after_dismiss = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s45_dismissed_disc = false;
	foreach ( is_array( $s45_after_dismiss['discovery'] ?? null ) ? $s45_after_dismiss['discovery'] : [] as $s45_item ) {
		if ( is_array( $s45_item ) && $s45_mcp_id === (string) ( $s45_item['id'] ?? '' ) ) {
			$s45_dismissed_disc = true;
			break;
		}
	}
	check(
		'(s45-e5) after apply or dismiss, that id is absent from discovery',
		'' !== $s45_mcp_id
			&& 200 === struo_eval_rest_status( $s45_dismiss )
			&& ! $s45_dismissed_disc,
		'id=' . $s45_mcp_id . ' dismiss=' . struo_eval_rest_status( $s45_dismiss ) . ' still=' . ( $s45_dismissed_disc ? '1' : '0' )
	);

	$s4_roll_plan = struo_eval_rest(
		'POST',
		'/struo/v1/plan',
		[
			'request' => 'Change the paragraph to "s4-roll-after".',
			'plan' => [
				'post_id' => $s4_roll_post,
				'operation' => 'update',
				'block_name' => 'core/paragraph',
				'fields' => [ 'content' => 's4-roll-after' ],
			],
			'dry_run_preview' => true,
		]
	);
	$s4_roll_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s4_roll_plan )['plan_id'] ?? '' ) );
	struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s4_roll_id . '/approve' );
	struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s4_roll_id . '/apply' );
	$s4_rollback = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $s4_roll_id . '/rollback' );
	$s4_restore_id = sanitize_text_field( (string) ( struo_eval_rest_data( $s4_rollback )['plan_id'] ?? '' ) );
	$s4_after_rollback = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/agent-plans' ) );
	$s4_restore_item = null;
	$s4_original_item = null;
	foreach ( is_array( $s4_after_rollback['items'] ?? null ) ? $s4_after_rollback['items'] : [] as $s4_item ) {
		if ( ! is_array( $s4_item ) ) {
			continue;
		}
		if ( $s4_restore_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_restore_item = $s4_item;
		}
		if ( $s4_roll_id === (string) ( $s4_item['id'] ?? '' ) ) {
			$s4_original_item = $s4_item;
		}
	}
	check(
		'(s4-e5) rollback id appears in items as planned; original remains applied',
		200 === struo_eval_rest_status( $s4_rollback )
			&& '' !== $s4_restore_id
			&& is_array( $s4_restore_item )
			&& 'planned' === sanitize_key( (string) ( $s4_restore_item['state'] ?? '' ) )
			&& is_array( $s4_original_item )
			&& 'applied' === sanitize_key( (string) ( $s4_original_item['state'] ?? '' ) ),
		'rollback=' . struo_eval_rest_status( $s4_rollback ) . ' restore=' . $s4_restore_id . ' original=' . sanitize_key( (string) ( $s4_original_item['state'] ?? '' ) )
	);

	$sanitizer = new ReflectionMethod( 'Struo_Block_Editor', 'sanitize_options' );
	$sanitizer->setAccessible( true );
	$pinned = $sanitizer->invoke( null, [ 'ai_openai_base_url' => 'https://evil.example/v1' ] );
	check(
		'(s0a) settings persist refuses unapproved provider hosts',
		'api.openai.com' === (string) wp_parse_url( (string) ( $pinned['ai_openai_base_url'] ?? '' ), PHP_URL_HOST ),
		'host=' . (string) wp_parse_url( (string) ( $pinned['ai_openai_base_url'] ?? '' ), PHP_URL_HOST )
	);
	$policy = new ReflectionMethod( 'Struo_Block_Editor', 'planner_policy_options' );
	$policy->setAccessible( true );
	$opts = $policy->invoke( null, 'Planner', [ 'planner_options' => [ 'model' => 'evil', 'temperature' => 2, 'envId' => 'x' ] ] );
	check(
		'(s0a) request planner_options are ignored',
		is_array( $opts ) && empty( $opts['model'] ) && empty( $opts['envId'] ) && ! isset( $opts['temperature'] ),
		'keys=' . implode( ',', array_keys( (array) $opts ) )
	);

	$audit = struo_eval_audit_table();
	$excerpt_id = 0;
	$tagged_id = 0;
	if ( struo_eval_table_exists( $audit ) ) {
		global $wpdb;
		$excerpt_ok = $wpdb->insert(
			$audit,
			[
				'created_at' => current_time( 'mysql' ),
				'user_id' => (int) $user_id,
				'action' => 's0a_eval_excerpt_sentinel',
				'post_id' => (int) $bundle_a,
				'details' => wp_json_encode(
					[
						'from' => 'eval_harness in the excerpt',
						'to' => 'still mentions eval_harness',
					]
				),
			],
			[ '%s', '%d', '%s', '%d', '%s' ]
		);
		$excerpt_id = $excerpt_ok ? (int) $wpdb->insert_id : 0;
		$tagged_ok = $wpdb->insert(
			$audit,
			[
				'created_at' => current_time( 'mysql' ),
				'user_id' => (int) $user_id,
				'action' => 's0a_eval_tagged_sentinel',
				'post_id' => (int) $bundle_a,
				'details' => wp_json_encode(
					[
						'eval_harness' => 1,
						'note' => 'harness-owned',
					]
				),
			],
			[ '%s', '%d', '%s', '%d', '%s' ]
		);
		$tagged_id = $tagged_ok ? (int) $wpdb->insert_id : 0;
		struo_eval_delete_harness_audit_rows();
		$excerpt_row = $excerpt_id > 0 ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$audit} WHERE id = %d", $excerpt_id ) ) : null;
		$tagged_row = $tagged_id > 0 ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$audit} WHERE id = %d", $tagged_id ) ) : 'gone';
		check(
			'(s0a) eval restore keeps untagged audit excerpts that mention eval_harness',
			$excerpt_id > 0 && $tagged_id > 0 && null !== $excerpt_row && null === $tagged_row,
			'excerpt=' . $excerpt_id . '/' . ( null === $excerpt_row ? 'missing' : 'kept' ) . ' tagged=' . $tagged_id . '/' . ( null === $tagged_row ? 'deleted' : 'kept' )
		);
		if ( $excerpt_id > 0 ) {
			$wpdb->delete( $audit, [ 'id' => $excerpt_id ], [ '%d' ] );
		}
		if ( $tagged_id > 0 ) {
			$wpdb->delete( $audit, [ 'id' => $tagged_id ], [ '%d' ] );
		}
	} else {
		check( '(s0a) eval restore keeps untagged audit excerpts that mention eval_harness', false, 'audit table missing' );
	}

	wp_set_current_user( (int) $user_id );
	struo_eval_release_active_plans();
	$unchecked_s0b = new ReflectionMethod( 'Struo_Block_Editor', 'get_durable_plan_record_unchecked' );
	$unchecked_s0b->setAccessible( true );
	$prune_s0b = new ReflectionMethod( 'Struo_Block_Editor', 'prune_durable_plans' );
	$prune_s0b->setAccessible( true );

	$victim_id = struo_eval_insert_plan_fixture(
		[
			'request_text' => 's0b-capacity-victim',
			'post_id' => $bundle_a,
			'post_json' => wp_json_encode( [ 'post_id' => $bundle_a ] ),
		]
	);
	while ( struo_eval_active_plan_count() < 25 ) {
		if ( '' === struo_eval_insert_plan_fixture( [ 'request_text' => 's0b-capacity-filler' ] ) ) {
			break;
		}
	}
	$prune_s0b->invoke( null, [] );
	$victim_after_prune = $unchecked_s0b->invoke( null, $victim_id );
	check(
		'(s0b) prune at capacity does not cancel another actor family',
		'' !== $victim_id && 'planned' === sanitize_key( (string) ( $victim_after_prune['state'] ?? '' ) ),
		'victim=' . $victim_id . ' state=' . sanitize_key( (string) ( $victim_after_prune['state'] ?? '' ) )
	);
	$capacity_assert = new ReflectionMethod( 'Struo_Block_Editor', 'assert_durable_plan_capacity' );
	$capacity_assert->setAccessible( true );
	$capacity_err = $capacity_assert->invoke( null, 1 );
	$victim_after_plan = $unchecked_s0b->invoke( null, $victim_id );
	$capacity_status = is_wp_error( $capacity_err ) ? absint( ( $capacity_err->get_error_data()['status'] ?? 0 ) ) : 0;
	check(
		'(s0b) new plan fails with 429 at capacity',
		is_wp_error( $capacity_err )
			&& 'sae_plan_capacity' === $capacity_err->get_error_code()
			&& 429 === $capacity_status
			&& 'planned' === sanitize_key( (string) ( $victim_after_plan['state'] ?? '' ) ),
		'code=' . ( is_wp_error( $capacity_err ) ? $capacity_err->get_error_code() : 'none' ) . ' status=' . $capacity_status . ' victim=' . sanitize_key( (string) ( $victim_after_plan['state'] ?? '' ) )
	);
	check(
		'(dps-e6) new plan fails with 429 at capacity',
		is_wp_error( $capacity_err )
			&& 'sae_plan_capacity' === $capacity_err->get_error_code()
			&& 429 === $capacity_status
			&& 'planned' === sanitize_key( (string) ( $victim_after_plan['state'] ?? '' ) ),
		'code=' . ( is_wp_error( $capacity_err ) ? $capacity_err->get_error_code() : 'none' ) . ' status=' . $capacity_status . ' victim=' . sanitize_key( (string) ( $victim_after_plan['state'] ?? '' ) )
	);

	$applied_id = struo_eval_insert_plan_fixture(
		[
			'state' => 'applied',
			'post_id' => $bundle_a,
			'post_json' => wp_json_encode( [ 'post_id' => $bundle_a ] ),
			'request_text' => 's0b-applied-receipt',
		]
	);
	$applied_get = struo_eval_rest( 'GET', '/struo/v1/console/agent-plans/' . $applied_id );
	$applied_get_data = struo_eval_rest_data( $applied_get );
	$applied_reapply = struo_eval_rest( 'POST', '/struo/v1/console/agent-plans/' . $applied_id . '/apply' );
	$applied_reapply_data = struo_eval_rest_data( $applied_reapply );
	check(
		'(s0b) GET and re-apply keep an applied plan queryable',
		'' !== $applied_id
			&& 200 === struo_eval_rest_status( $applied_get )
			&& 'applied' === sanitize_key( (string) ( $applied_get_data['plan']['state'] ?? '' ) )
			&& 200 === struo_eval_rest_status( $applied_reapply )
			&& 'already_applied' === sanitize_key( (string) ( $applied_reapply_data['outcome'] ?? '' ) ),
		'id=' . $applied_id . ' get=' . struo_eval_rest_status( $applied_get ) . ' apply=' . struo_eval_rest_status( $applied_reapply ) . ' outcome=' . sanitize_key( (string) ( $applied_reapply_data['outcome'] ?? '' ) )
	);

	$plans_status = $GLOBALS['wpdb']->get_row(
		$GLOBALS['wpdb']->prepare( 'SHOW TABLE STATUS LIKE %s', struo_eval_plans_table() ),
		ARRAY_A
	);
	check(
		'(s0b) plans table engine is InnoDB',
		0 === strcasecmp( (string) ( $plans_status['Engine'] ?? '' ), 'InnoDB' ),
		'engine=' . (string) ( $plans_status['Engine'] ?? '' )
	);

	$privacy_user_id = wp_insert_user(
		[
			'user_login' => 'struo_eval_priv_' . wp_generate_password( 8, false ),
			'user_pass' => wp_generate_password( 24, true ),
			'user_email' => 'struo-eval-priv-' . wp_generate_password( 8, false ) . '@example.test',
			'role' => 'author',
			'display_name' => 'Struo Eval Privacy',
		]
	);
	if ( is_wp_error( $privacy_user_id ) ) {
		check( '(s0b) plan privacy export and erase the user rows', false, $privacy_user_id->get_error_message() );
	} else {
		$GLOBALS['struo_eval_cleanup']['user_ids'][] = (int) $privacy_user_id;
		$privacy_plan = struo_eval_insert_plan_fixture(
			[
				'user_id' => (int) $privacy_user_id,
				'request_text' => 's0b-privacy-secret',
				'payload_json' => wp_json_encode( [ 'note' => 's0b-privacy-payload' ] ),
			]
		);
		$plan_exporter = new ReflectionMethod( 'Struo_Block_Editor', 'export_plan_rows_for_user' );
		$plan_exporter->setAccessible( true );
		$plan_eraser = new ReflectionMethod( 'Struo_Block_Editor', 'erase_plan_rows_for_user' );
		$plan_eraser->setAccessible( true );
		$privacy_email = (string) get_userdata( (int) $privacy_user_id )->user_email;
		$plan_export = $plan_exporter->invoke( null, $privacy_email, 1 );
		$export_blob = wp_json_encode( $plan_export );
		$erased = $plan_eraser->invoke( null, $privacy_email, 1 );
		$privacy_gone = ! $GLOBALS['wpdb']->get_var(
			$GLOBALS['wpdb']->prepare(
				'SELECT plan_id FROM ' . struo_eval_plans_table() . ' WHERE plan_id = %s',
				$privacy_plan
			)
		);
		check(
			'(s0b) plan privacy export and erase the user rows',
			'' !== $privacy_plan
				&& false !== strpos( (string) $export_blob, $privacy_plan )
				&& false !== strpos( (string) $export_blob, 's0b-privacy-secret' )
				&& ! empty( $erased['items_removed'] )
				&& $privacy_gone,
			'plan=' . $privacy_plan . ' export=' . ( false !== strpos( (string) $export_blob, $privacy_plan ) ? '1' : '0' ) . ' gone=' . ( $privacy_gone ? '1' : '0' )
		);
	}

	wp_set_current_user( 0 );
	$create_req = new WP_REST_Request( 'POST', '/struo/v1/pages/plan-create' );
	$create_denied = Struo_Block_Editor::can_plan_create_page( $create_req );
	$anon_settings = Struo_Block_Editor::can_manage_console_settings();
	wp_set_current_user( (int) $user_id );
	$admin_settings = Struo_Block_Editor::can_manage_console_settings();
	check(
		'(s0b) create-page planning requires struo_plan',
		is_wp_error( $create_denied ),
		'code=' . ( is_wp_error( $create_denied ) ? $create_denied->get_error_code() : 'none' )
	);
	check(
		'(s0b) settings require struo_manage_settings',
		is_wp_error( $anon_settings ) && true === $admin_settings,
		'anon=' . ( is_wp_error( $anon_settings ) ? $anon_settings->get_error_code() : 'ok' ) . ' admin=' . ( true === $admin_settings ? 'ok' : 'no' )
	);

	global $wpdb;
	$plans_table = struo_eval_plans_table();
	$sentinel = bin2hex( random_bytes( 8 ) );
	$sentinel_inserted = false !== $wpdb->insert(
		$plans_table,
		[
			'plan_id' => $sentinel,
			'payload_type' => 'mutation_v1',
			'state' => 'planned',
			'created_at' => current_time( 'mysql' ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
			'user_id' => $user_id,
			'post_id' => $bundle_a,
			'origin' => 'rest',
			'operation' => 'update',
			'endpoint' => '',
			'request_text' => 'eval-restore-sentinel',
			'payload_json' => '{}',
			'post_json' => '{}',
			'base_content_hash' => '',
		]
	);
	if ( $sentinel_inserted ) {
		struo_eval_track_plan_id( $sentinel );
	}
	$operator_id = bin2hex( random_bytes( 8 ) );
	$operator_inserted = false !== $wpdb->insert(
		$plans_table,
		[
			'plan_id' => $operator_id,
			'payload_type' => 'mutation_v1',
			'state' => 'planned',
			'created_at' => current_time( 'mysql' ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
			'user_id' => $user_id,
			'post_id' => 0,
			'origin' => 'rest',
			'operation' => 'update',
			'endpoint' => '',
			'request_text' => 'eval-concurrent-operator',
			'payload_json' => '{}',
			'post_json' => '{}',
			'base_content_hash' => '',
		]
	);
	$snapshot_count = count( (array) $GLOBALS['struo_eval_cleanup']['plans_rows'] );
	$sample_id = (string) ( $GLOBALS['struo_eval_cleanup']['plans_sample_id'] ?? '' );
	struo_eval_restore_durable_state();
	$after_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$plans_table}" );
	$sentinel_gone = ! $wpdb->get_var( $wpdb->prepare( "SELECT plan_id FROM {$plans_table} WHERE plan_id = %s", $sentinel ) );
	$operator_kept = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT plan_id FROM {$plans_table} WHERE plan_id = %s", $operator_id ) );
	$sample_ok = '' === $sample_id
		|| (bool) $wpdb->get_var( $wpdb->prepare( "SELECT plan_id FROM {$plans_table} WHERE plan_id = %s", $sample_id ) );
	check(
		'(b16) durable restore drops eval rows, restores the snapshot, and keeps concurrent operator plans',
		$sentinel_inserted
			&& $sentinel_gone
			&& $operator_inserted
			&& $operator_kept
			&& $after_count >= ( $snapshot_count + 1 )
			&& $sample_ok,
		'insert=' . ( $sentinel_inserted ? '1' : '0' ) . ' after=' . $after_count . ' snapshot=' . $snapshot_count . ' sentinel=' . ( $sentinel_gone ? 'gone' : 'kept' ) . ' operator=' . ( $operator_kept ? 'kept' : 'gone' ) . ' sample=' . ( $sample_ok ? 'ok' : 'missing' )
	);
	if ( $operator_kept ) {
		$wpdb->delete( $plans_table, [ 'plan_id' => $operator_id ], [ '%s' ] );
	}
}

// ============================================================
// Lane 6 runtime cases: option-key migration (r1), legacy menu slug
// alias (r2), dual-namespace uninstall (r3). All transactional.
// ============================================================

// --- (r1) Foreign option keys are not copied into struo_* ---
$legacy_key = 'subsurface_ai_block_editor_options';
$r1_saved_legacy = get_option( $legacy_key, null );
$r1_saved_new = get_option( 'struo_options', null );
$GLOBALS['struo_eval_cleanup']['option_saved'] = true;
$GLOBALS['struo_eval_cleanup']['option_value'] = $r1_saved_new;

$r1_saved_flag = get_option( 'struo_options_migrated_v1', null );
delete_option( 'struo_options_migrated_v1' );

$probe = [ 'kill_switch' => 1, 'allowed_post_ids' => [ 42 ], 'ai_openai_model' => 'wp-eval-model' ];
update_option( $legacy_key, $probe, false );
delete_option( 'struo_options' );

Struo_Block_Editor::maybe_upgrade_storage( true );
check( '(r1) migration flag set by the upgrade routine', 1 === (int) get_option( 'struo_options_migrated_v1', 0 ) );

$migrated = get_option( 'struo_options', [] );
check( '(r1) foreign option keys are not copied into struo_options', is_array( $migrated ) && 'wp-eval-model' !== ( $migrated['ai_openai_model'] ?? '' ) );
check( '(r1) foreign key left in place (untouched)', get_option( $legacy_key, [] ) === $probe );

$before_rerun = get_option( 'struo_options', [] );
Struo_Block_Editor::maybe_upgrade_storage( true );
$after_rerun = get_option( 'struo_options', [] );
check( '(r1) second upgrade run is a no-op', $before_rerun === $after_rerun );

// Restore r1 state (including the one-time migration flag).
if ( null === $r1_saved_flag ) {
	delete_option( 'struo_options_migrated_v1' );
} else {
	update_option( 'struo_options_migrated_v1', $r1_saved_flag, false );
}
if ( null === $r1_saved_new ) {
	delete_option( 'struo_options' );
} else {
	update_option( 'struo_options', $r1_saved_new, false );
}
if ( null === $r1_saved_legacy ) {
	delete_option( $legacy_key );
} else {
	update_option( $legacy_key, $r1_saved_legacy, false );
}

// --- (r1d/r1e/r1f/r1g/r1h/r1i/r1j/r1k) Transient migration: collision,
//     TTL carry (incl. expired vs missing timeout), convergence, cache
//     awareness, unparseable names, site transients, flag timing ---
$migrator = new ReflectionMethod( 'Struo_Block_Editor', 'migrate_legacy_transient_prefixes' );
$migrator->setAccessible( true );
$flag_key = ( new ReflectionClass( 'Struo_Block_Editor' ) )
	->getConstant( 'TRANSIENTS_MIGRATED_FLAG' );

// Fresh flag for every migration sub-case.
delete_option( $flag_key );

// (r1d) Collision: legacy + target both present -> target kept, legacy
// gone (including its timeout row).
set_transient( 'sae_x', 'legacy-value', 600 );
set_transient( 'struo_x', 'target-value', 600 );
$migrator->invoke( null );
check( '(r1d) collision: target value kept', 'target-value' === get_transient( 'struo_x' ) );
check( '(r1d) collision: legacy key gone', false === get_transient( 'sae_x' ) );
check( '(r1d) collision: legacy timeout row gone', false === get_option( '_transient_timeout_sae_x', false ) );
delete_transient( 'struo_x' );
delete_option( $flag_key );

// (r1e) TTL carry: 300s remaining on the legacy key transfers to the new
// key within +/-5 seconds. A missing timeout row stays permanent (ttl 0).
set_transient( 'sae_y', 'ttl-value', 300 );
$legacy_timeout = get_option( '_transient_timeout_sae_y', false );
$migrator->invoke( null );
$new_timeout = get_option( '_transient_timeout_struo_y', false );
check( '(r1e) TTL carried to the new key', is_numeric( $new_timeout ) && abs( (int) $new_timeout - (int) $legacy_timeout ) <= 5,
	'legacy=' . var_export( $legacy_timeout, true ) . ' new=' . var_export( $new_timeout, true ) );
check( '(r1e) new key holds the migrated value', 'ttl-value' === get_transient( 'struo_y' ) );
delete_transient( 'struo_y' );
delete_option( $flag_key );

set_transient( 'sae_perm', 'perm-value', 0 );
$migrator->invoke( null );
check( '(r1e) missing timeout row stays permanent', 'perm-value' === get_transient( 'struo_perm' ) );
check( '(r1e) permanent new key has no timeout row', false === get_option( '_transient_timeout_struo_perm', false ) );
delete_transient( 'struo_perm' );
delete_option( $flag_key );

// (r1g) Cache awareness: a stale object-cache entry for the BARE legacy
// key (core's group/key contract) is cleared after delete_transient.
wp_cache_set( 'sae_z', 'stale', 'transient' );
wp_cache_set( 'sae_z', 'stale-timeout', 'transient_timeout' );
set_transient( 'sae_z', 'cache-value', 600 );
$migrator->invoke( null );
check( '(r1g) stale legacy object-cache entry cleared', false === wp_cache_get( 'sae_z', 'transient' ) );
check( '(r1g) stale legacy timeout cache entry cleared', false === wp_cache_get( 'sae_z', 'transient_timeout' ) );
check( '(r1g) migrated value readable under the new key', 'cache-value' === get_transient( 'struo_z' ) );
delete_transient( 'struo_z' );
delete_option( $flag_key );

// (r1h) Unparseable legacy name (LIKE matches, preg does not map sae_ ->
// struo_ because of case) must not spin: one pass removes it and sets
// the flag. Old code `continue`d before delete_transient.
$unparseable_name = '_transient_SAE_r1h_spin';
$GLOBALS['wpdb']->replace(
	$GLOBALS['wpdb']->options,
	[
		'option_name'  => $unparseable_name,
		'option_value' => 'spin',
		'autoload'     => 'no',
	],
	[ '%s', '%s', '%s' ]
);
delete_option( $flag_key );
$migrator->invoke( null );
check( '(r1h) unparseable name does not spin (flag set in one pass)', 1 === (int) get_option( $flag_key, 0 ) );
check( '(r1h) unparseable legacy row removed', false === get_option( $unparseable_name, false ) );
delete_option( $unparseable_name );
delete_option( $flag_key );

// (r1i) Expired timeout row => skip and delete; never set a permanent
// new key (set_transient(..., 0)).
set_transient( 'sae_expired', 'expired-value', 600 );
update_option( '_transient_timeout_sae_expired', time() - 30, false );
$migrator->invoke( null );
check( '(r1i) expired legacy gone', false === get_transient( 'sae_expired' ) && false === get_option( '_transient_sae_expired', false ) );
check( '(r1i) expired legacy did not become a permanent new key', false === get_transient( 'struo_expired' ) && false === get_option( '_transient_struo_expired', false ) );
delete_transient( 'struo_expired' );
delete_option( $flag_key );

// (r1j) Site-transient with TTL migrated; legacy gone.
set_site_transient( 'sae_site_j', 'site-val', 300 );
$site_legacy_timeout = get_option( '_site_transient_timeout_sae_site_j', false );
$migrator->invoke( null );
$site_new_timeout = get_option( '_site_transient_timeout_struo_site_j', false );
check( '(r1j) site-transient value migrated', 'site-val' === get_site_transient( 'struo_site_j' ) );
check( '(r1j) site-transient legacy gone', false === get_site_transient( 'sae_site_j' ) );
check( '(r1j) site-transient TTL carried', is_numeric( $site_new_timeout ) && abs( (int) $site_new_timeout - (int) $site_legacy_timeout ) <= 5,
	'legacy=' . var_export( $site_legacy_timeout, true ) . ' new=' . var_export( $site_new_timeout, true ) );
delete_site_transient( 'struo_site_j' );
delete_option( $flag_key );

// (r1k) Regular-empty + site-present must not flag-and-return before the
// site pass. After this run the site pass is empty, so the flag IS set
// and the site value was migrated (old bug left the site row behind).
set_site_transient( 'sae_r1k', 'keep-me', 600 );
delete_option( $flag_key );
$migrator->invoke( null );
check( '(r1k) site row migrated when regular store was empty', 'keep-me' === get_site_transient( 'struo_r1k' ) );
check( '(r1k) legacy site row gone (no early return)', false === get_site_transient( 'sae_r1k' ) );
check( '(r1k) flag set only after the site pass is also empty', 1 === (int) get_option( $flag_key, 0 ) );
delete_site_transient( 'struo_r1k' );
delete_option( $flag_key );

// (r1f) Convergence: a settling pass finds zero legacy rows and sets the
// flag; the two observed runs after it must perform no writes at all.
$migrator->invoke( null ); // settling pass
$rows_before = (int) $GLOBALS['wpdb']->get_var(
	"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '\\_transient\\_sae\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sae\\_%'"
);
$options_rows_before = (int) $GLOBALS['wpdb']->get_var(
	"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '%struo%'"
);
$migrator->invoke( null ); // observed run 1: must be a no-op
$migrator->invoke( null ); // observed run 2: must be a no-op
$rows_after = (int) $GLOBALS['wpdb']->get_var(
	"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '\\_transient\\_sae\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sae\\_%'"
);
$options_rows_after = (int) $GLOBALS['wpdb']->get_var(
	"SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '%struo%'"
);
check( '(r1f) convergence: no legacy transient rows remain', 0 === $rows_after && $rows_before === $rows_after, "before={$rows_before} after={$rows_after}" );
check( '(r1f) convergence: struo option-row count unchanged by the second run', $options_rows_before === $options_rows_after, "before={$options_rows_before} after={$options_rows_after}" );
check( '(r1f) migration flag set after a zero-row pass', 1 === (int) get_option( $flag_key, 0 ) );

// --- (r2) Former menu slugs are not registered ---
$legacy_hook = get_plugin_page_hookname( 'subsurface-ai-console', '' );
check( '(r2) legacy console slug is not a registered page', ! isset( $GLOBALS['_registered_pages'][ $legacy_hook ] ) );
$admin = wp_set_current_user( get_current_user_id() );
check( '(r2) admin can still open the current console', current_user_can( 'edit_posts' ) );
$r2_target = add_query_arg( [ 'page' => 'struo-console' ], admin_url( 'admin.php' ) );
check( '(r2) console URL lands on the current page', false !== strpos( $r2_target, 'page=struo-console' ) );

$r2_settings_hook = get_plugin_page_hookname( 'subsurface-ai-block-editor', 'options-general.php' );
check( '(r2) legacy settings slug is not a registered page', ! isset( $GLOBALS['_registered_pages'][ $r2_settings_hook ] ) );
$r2_settings_target = add_query_arg( [ 'page' => 'struo' ], admin_url( 'options-general.php' ) );
check( '(r2) settings URL lands on the current page', false !== strpos( $r2_settings_target, 'page=struo' ) );

$s9ro_admin_id = get_current_user_id();
$s9ro_saved_sa = (string) get_option( Struo_Block_Editor::GOOGLE_SERVICE_ACCOUNT_OPTION, '' );
$s9ro_saved_ga4 = (string) get_option( Struo_Block_Editor::GA4_PROPERTY_ID_OPTION, '' );
$s9ro_saved_gsc = (string) get_option( Struo_Block_Editor::GSC_SITE_URL_OPTION, '' );
delete_option( Struo_Block_Editor::GOOGLE_SERVICE_ACCOUNT_OPTION );
delete_option( Struo_Block_Editor::GA4_PROPERTY_ID_OPTION );
delete_option( Struo_Block_Editor::GSC_SITE_URL_OPTION );
$s9ro_empty = struo_eval_rest( 'GET', '/struo/v1/console/findings' );
$s9ro_empty_data = struo_eval_rest_data( $s9ro_empty );
check(
	'(s9ro-e1) without credentials, GET /console/findings is 200, connected false, items []',
	200 === struo_eval_rest_status( $s9ro_empty )
		&& ! empty( $s9ro_empty_data['ok'] )
		&& false === ( $s9ro_empty_data['connected'] ?? true )
		&& isset( $s9ro_empty_data['items'] )
		&& is_array( $s9ro_empty_data['items'] )
		&& [] === $s9ro_empty_data['items'],
	'status=' . struo_eval_rest_status( $s9ro_empty ) . ' connected=' . var_export( $s9ro_empty_data['connected'] ?? null, true )
);

$s9ro_allowed = struo_eval_insert_bundle_post( $s9ro_admin_id, 'findings-allowed', 'findings allowed page' );
$s9ro_allowed_b = struo_eval_insert_bundle_post( $s9ro_admin_id, 'findings-allowed-b', 'findings allowed page b' );
$s9ro_allowed_c = struo_eval_insert_bundle_post( $s9ro_admin_id, 'findings-allowed-c', 'findings allowed page c' );
$s9ro_denied = struo_eval_insert_bundle_post( $s9ro_admin_id, 'findings-denied', 'findings not allowlisted' );
if ( $s9ro_allowed && $s9ro_allowed_b && $s9ro_allowed_c && $s9ro_denied ) {
	struo_eval_allowlist_merge( [ $s9ro_allowed, $s9ro_allowed_b, $s9ro_allowed_c ] );
	$s9ro_fixture = static function () use ( $s9ro_allowed, $s9ro_allowed_b, $s9ro_allowed_c, $s9ro_denied ) {
		return [
			[
				'id' => 'aaaaaaaaaaaaaaaa',
				'source' => 'search',
				'post_id' => $s9ro_allowed,
				'title' => 'Clicks on the allowlisted page dropped',
				'summary' => '410 to 278 clicks',
				'metrics' => [ 'clicks_before' => 410, 'clicks_after' => 278 ],
			],
			[
				'id' => 'bbbbbbbbbbbbbbbb',
				'source' => 'search',
				'post_id' => $s9ro_allowed_b,
				'title' => 'Home shows in search and almost nobody clicks',
				'summary' => '1,200 impressions',
				'metrics' => [ 'impressions' => 1200, 'clicks' => 18 ],
			],
			[
				'id' => 'cccccccccccccccc',
				'source' => 'analytics',
				'post_id' => $s9ro_allowed_c,
				'title' => 'People leave the page in a few seconds',
				'summary' => '81 percent bounce',
				'metrics' => [ 'bounce' => 81 ],
			],
			[
				'id' => 'dddddddddddddddd',
				'source' => 'search',
				'post_id' => $s9ro_denied,
				'title' => 'Should not appear',
				'summary' => 'not allowlisted',
				'metrics' => [ 'clicks' => 1 ],
			],
		];
	};
	add_filter( 'struo_findings_fixture', $s9ro_fixture );
	$s9ro_list = struo_eval_rest( 'GET', '/struo/v1/console/findings' );
	$s9ro_data = struo_eval_rest_data( $s9ro_list );
	$s9ro_items = is_array( $s9ro_data['items'] ?? null ) ? $s9ro_data['items'] : [];
	$s9ro_ids = [];
	$s9ro_ok_shape = true;
	foreach ( $s9ro_items as $s9ro_item ) {
		if ( ! is_array( $s9ro_item ) ) {
			$s9ro_ok_shape = false;
			break;
		}
		$s9ro_ids[] = (string) ( $s9ro_item['id'] ?? '' );
		if ( empty( $s9ro_item['not_a_plan'] ) || array_key_exists( 'plan_id', $s9ro_item ) ) {
			$s9ro_ok_shape = false;
			break;
		}
	}
	check(
		'(s9ro-e2) fixture allowlisted Findings are connected items, not_a_plan, no plan_id',
		200 === struo_eval_rest_status( $s9ro_list )
			&& ! empty( $s9ro_data['ok'] )
			&& ! empty( $s9ro_data['connected'] )
			&& $s9ro_ok_shape
			&& in_array( 'aaaaaaaaaaaaaaaa', $s9ro_ids, true )
			&& in_array( 'bbbbbbbbbbbbbbbb', $s9ro_ids, true )
			&& in_array( 'cccccccccccccccc', $s9ro_ids, true ),
		'status=' . struo_eval_rest_status( $s9ro_list ) . ' count=' . count( $s9ro_ids )
	);
	check(
		'(s9ro-e4) fixture item whose post_id is not allowlisted is absent',
		! in_array( 'dddddddddddddddd', $s9ro_ids, true )
			&& 3 === count( $s9ro_ids ),
		'ids=' . implode( ',', $s9ro_ids )
	);
	$s9ro_status = struo_eval_rest_data( struo_eval_rest( 'GET', '/struo/v1/console/status' ) );
	check(
		'(s9ro-e5) GET /console/status JSON has no findings key',
		is_array( $s9ro_status ) && ! array_key_exists( 'findings', $s9ro_status )
	);
	$s9ro_one = struo_eval_rest( 'GET', '/struo/v1/console/findings/aaaaaaaaaaaaaaaa' );
	$s9ro_one_data = struo_eval_rest_data( $s9ro_one );
	$s9ro_missing = struo_eval_rest( 'GET', '/struo/v1/console/findings/ffffffffffffffff' );
	check(
		'(s9ro-e3) GET /console/findings/{id} returns the item; unknown id is 404 sae_finding_missing',
		200 === struo_eval_rest_status( $s9ro_one )
			&& 'aaaaaaaaaaaaaaaa' === (string) ( $s9ro_one_data['id'] ?? '' )
			&& ! empty( $s9ro_one_data['not_a_plan'] )
			&& ! array_key_exists( 'plan_id', $s9ro_one_data )
			&& 404 === struo_eval_rest_status( $s9ro_missing )
			&& 'sae_finding_missing' === struo_eval_rest_code( $s9ro_missing ),
		'one=' . struo_eval_rest_status( $s9ro_one ) . ' missing=' . struo_eval_rest_status( $s9ro_missing ) . '/' . struo_eval_rest_code( $s9ro_missing )
	);
	$s9ro_sub_id = wp_insert_user(
		[
			'user_login' => 'struo-eval-findings-sub-' . wp_rand( 1000, 9999 ),
			'user_pass' => wp_generate_password( 24, true, true ),
			'user_email' => 'struo-eval-findings-' . wp_generate_password( 8, false ) . '@example.test',
			'role' => 'subscriber',
		]
	);
	if ( is_wp_error( $s9ro_sub_id ) ) {
		check( '(s9ro-e6) subscriber without struo_plan is 403 on GET findings', false, $s9ro_sub_id->get_error_code() );
	} else {
		$GLOBALS['struo_eval_cleanup']['user_ids'][] = (int) $s9ro_sub_id;
		wp_set_current_user( (int) $s9ro_sub_id );
		$s9ro_denied_list = struo_eval_rest( 'GET', '/struo/v1/console/findings' );
		check(
			'(s9ro-e6) subscriber without struo_plan is 403 on GET findings',
			403 === struo_eval_rest_status( $s9ro_denied_list ),
			'status=' . struo_eval_rest_status( $s9ro_denied_list ) . ' code=' . struo_eval_rest_code( $s9ro_denied_list )
		);
		wp_set_current_user( (int) $s9ro_admin_id );
	}
	remove_filter( 'struo_findings_fixture', $s9ro_fixture );
} else {
	check( '(s9ro-e2) fixture allowlisted Findings are connected items, not_a_plan, no plan_id', false, 'fixture posts missing' );
	check( '(s9ro-e4) fixture item whose post_id is not allowlisted is absent', false, 'fixture posts missing' );
	check( '(s9ro-e5) GET /console/status JSON has no findings key', false, 'fixture posts missing' );
	check( '(s9ro-e3) GET /console/findings/{id} returns the item; unknown id is 404 sae_finding_missing', false, 'fixture posts missing' );
	check( '(s9ro-e6) subscriber without struo_plan is 403 on GET findings', false, 'fixture posts missing' );
}

$s9ro_secret = 'struo-eval-sa-key-' . wp_generate_password( 24, false, false );
update_option(
	Struo_Block_Editor::GOOGLE_SERVICE_ACCOUNT_OPTION,
	wp_json_encode(
		[
			'client_email' => 'eval@example.test',
			'private_key' => $s9ro_secret,
		]
	),
	false
);
ob_start();
Struo_Block_Editor::render_settings_page();
$s9ro_settings_html = (string) ob_get_clean();
check(
	'(s9ro-e7) settings HTML with a saved service-account option does not contain the private_key string',
	false === strpos( $s9ro_settings_html, $s9ro_secret )
		&& false !== strpos( $s9ro_settings_html, 'already saved' ),
	'html_len=' . strlen( $s9ro_settings_html )
);
if ( '' === $s9ro_saved_sa ) {
	delete_option( Struo_Block_Editor::GOOGLE_SERVICE_ACCOUNT_OPTION );
} else {
	update_option( Struo_Block_Editor::GOOGLE_SERVICE_ACCOUNT_OPTION, $s9ro_saved_sa, false );
}
if ( '' === $s9ro_saved_ga4 ) {
	delete_option( Struo_Block_Editor::GA4_PROPERTY_ID_OPTION );
} else {
	update_option( Struo_Block_Editor::GA4_PROPERTY_ID_OPTION, $s9ro_saved_ga4, false );
}
if ( '' === $s9ro_saved_gsc ) {
	delete_option( Struo_Block_Editor::GSC_SITE_URL_OPTION );
} else {
	update_option( Struo_Block_Editor::GSC_SITE_URL_OPTION, $s9ro_saved_gsc, false );
}

// --- F-10 dynamic allowlist cache invalidation ---
$f10_opts = get_option( 'struo_options', [] );
$f10_opts = is_array( $f10_opts ) ? $f10_opts : [];
$f10_registry = is_array( $f10_opts['allowed_content_types'] ?? null ) ? $f10_opts['allowed_content_types'] : [];
$f10_opts['allowed_content_types'] = array_merge(
	$f10_registry,
	[
		'post' => [
			'enabled' => true,
			'limit' => 50,
			'discovery' => 'recent_modified',
		],
	]
);
update_option( 'struo_options', $f10_opts, false );
Struo_Block_Editor::flush_options_cache();

$f10_post = wp_insert_post(
	[
		'post_type' => 'post',
		'post_status' => 'publish',
		'post_title' => '[struo-eval-temp] f10-dynamic',
		'post_content' => '<!-- wp:paragraph --><p>F10 cache</p><!-- /wp:paragraph -->',
	]
);
check( '(f10-e1) discoverable post fixture created', $f10_post && ! is_wp_error( $f10_post ) );
if ( $f10_post && ! is_wp_error( $f10_post ) ) {
	$GLOBALS['struo_eval_cleanup']['post_ids'][] = (int) $f10_post;
	$f10_allowed = Struo_Block_Editor::get_allowed_post_ids();
	check( '(f10-e2) published discoverable post is allowlisted', in_array( (int) $f10_post, $f10_allowed, true ) );
	wp_trash_post( (int) $f10_post );
	$f10_after = Struo_Block_Editor::get_allowed_post_ids();
	check( '(f10-e3) trash drops the id (no stale dynamic cache)', ! in_array( (int) $f10_post, $f10_after, true ) );
}

$f10_cache = new ReflectionProperty( 'Struo_Block_Editor', 'dynamic_post_ids_cache' );
$f10_cache->setAccessible( true );
$f10_cache->setValue( null, [ 999001 ] );
Struo_Block_Editor::invalidate_dynamic_allowlist_cache();
check( '(f10-e4) invalidate_dynamic_allowlist_cache clears the request cache', null === $f10_cache->getValue() );

if ( $GLOBALS['struo_eval_cleanup']['option_saved'] ) {
	$snapshot = $GLOBALS['struo_eval_cleanup']['option_value'];
	if ( null === $snapshot ) {
		delete_option( 'struo_options' );
	} else {
		update_option( 'struo_options', $snapshot, false );
	}
	Struo_Block_Editor::flush_options_cache();
}

// --- (r3) Uninstall (dry, transactional) removes BOTH namespaces ---
$GLOBALS['struo_eval_uninstall'] = true;

// Snapshot every option row the uninstall routine may touch.
$namespace_likes = [ 'struo_%', 'subsurface\_ai%' ];
$saved_rows = [];
foreach ( $namespace_likes as $like ) {
	$rows = $GLOBALS['wpdb']->get_results(
		$GLOBALS['wpdb']->prepare(
			"SELECT option_name, option_value, autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE %s",
			$like
		),
		ARRAY_A
	);
	foreach ( (array) $rows as $row ) {
		$saved_rows[ $row['option_name'] ] = $row;
	}
}

// Snapshot sae_/struo_ transient + timeout rows.
$saved_transients = [];
foreach ( [ '\_transient\_sae\_%', '\_transient\_struo\_%', '\_transient\_timeout\_sae\_%', '\_transient\_timeout\_struo\_%' ] as $like ) {
	$rows = $GLOBALS['wpdb']->get_results(
		$GLOBALS['wpdb']->prepare(
			"SELECT option_name, option_value, autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE %s",
			$like
		),
		ARRAY_A
	);
	foreach ( (array) $rows as $row ) {
		$saved_transients[ $row['option_name'] ] = $row;
	}
}

$GLOBALS['struo_eval_saved_rows']       = $saved_rows;
$GLOBALS['struo_eval_saved_transients'] = $saved_transients;

function struo_eval_restore_uninstall_state() {
	global $wpdb;
	foreach ( (array) ( $GLOBALS['struo_eval_saved_rows'] ?? [] ) as $row ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $row['option_name'] ) );
		if ( $exists ) {
			$wpdb->update( $wpdb->options, [ 'option_value' => $row['option_value'], 'autoload' => $row['autoload'] ], [ 'option_name' => $row['option_name'] ], [ '%s', '%s' ], [ '%s' ] );
		} else {
			$wpdb->insert( $wpdb->options, [ 'option_name' => $row['option_name'], 'option_value' => $row['option_value'], 'autoload' => $row['autoload'] ], [ '%s', '%s', '%s' ] );
		}
	}
	foreach ( (array) ( $GLOBALS['struo_eval_saved_transients'] ?? [] ) as $row ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $row['option_name'] ) );
		if ( ! $exists ) {
			$wpdb->insert( $wpdb->options, [ 'option_name' => $row['option_name'], 'option_value' => $row['option_value'], 'autoload' => $row['autoload'] ], [ '%s', '%s', '%s' ] );
		}
	}
}

// Seed rows in BOTH namespaces plus one transient per generation.
update_option( 'struo_options', [ 'kill_switch' => 0 ], false );
update_option( 'struo_openai_api_key', 'wp-eval-not-a-key', false );
update_option( $legacy_key, [ 'kill_switch' => 1 ], false );
set_transient( 'struo_confirm_wp_eval', 'x', 600 );
set_transient( 'sae_confirm_wp_eval', 'x', 600 );
update_option( 'struo_transients_migrated_v1', 1, false );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', true );
}

$uninstall_file = __DIR__ . '/../uninstall.php';
$plans_table_before_uninstall = struo_eval_table_exists( struo_eval_plans_table() );
include $uninstall_file;

check( '(r3) new-namespace options removed by uninstall', false === get_option( 'struo_options', false ) && false === get_option( 'struo_openai_api_key', false ) );
check( '(r3) legacy-namespace options removed by uninstall', false === get_option( $legacy_key, false ) );
check( '(r3) struo_ generation transient removed', false === get_transient( 'struo_confirm_wp_eval' ) );
check( '(r3) legacy sae_ generation transient removed', false === get_transient( 'sae_confirm_wp_eval' ) );
check( '(r3) transients-migrated flag removed by uninstall', false === get_option( 'struo_transients_migrated_v1', false ) );
check(
	'(s0b) uninstall keeps the plans table by default',
	$plans_table_before_uninstall && struo_eval_table_exists( struo_eval_plans_table() ),
	'before=' . ( $plans_table_before_uninstall ? '1' : '0' ) . ' after=' . ( struo_eval_table_exists( struo_eval_plans_table() ) ? '1' : '0' )
);

struo_eval_restore_uninstall_state();
check( '(r3) pre-uninstall state restored transactionally', is_array( get_option( 'struo_options', [] ) ) );

// S5 Gutenberg host — named live/browser proofs. Owner-gated; do not run unless asked.
// (s5-e1) Hello world in the block editor: Target is Hello world; Work Queue, Multi-page, Findings, compose in the Struo sidebar
// (s5-e2) Switch Target to another allowlisted page: editor URL/post stays Hello world
// (s5-e3) Plan + person Apply on Hello world: canvas shows the after text; did not use MCP Apply
// (s5-e4) Google disconnected: Findings empty copy in the sidebar, same as Brief
// (s5-e5) site-editor screen does not enqueue struo-gutenberg-host

print_summary_and_exit();
