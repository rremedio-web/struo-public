<?php
/**
 * WordPress-lite eval: no ACF, no provider HTTP.
 *
 *   wp eval-file tests/wp-eval-lite.php --user=<admin>
 *
 * Also run from CI via wp-env. Does not replace tests/wp-eval-cases.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "FAIL: must run inside WordPress (wp eval-file).\n";
	exit( 1 );
}

if ( ! defined( 'STRUO_EVAL_HARNESS' ) ) {
	define( 'STRUO_EVAL_HARNESS', true );
}

$GLOBALS['struo_eval_lite_failures'] = 0;
$GLOBALS['struo_eval_lite_http'] = 0;
$GLOBALS['struo_eval_lite_cleanup'] = [
	'post_ids' => [],
	'user_ids' => [],
	'options' => null,
	'done' => false,
];

function struo_eval_lite_check( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		echo "PASS: {$label}\n";
		return;
	}
	echo "FAIL: {$label}" . ( '' !== $detail ? " ({$detail})" : '' ) . "\n";
	$GLOBALS['struo_eval_lite_failures']++;
}

function struo_eval_lite_cleanup() {
	$state = $GLOBALS['struo_eval_lite_cleanup'];
	if ( ! empty( $state['done'] ) ) {
		return;
	}
	$GLOBALS['struo_eval_lite_cleanup']['done'] = true;

	remove_filter( 'pre_http_request', 'struo_eval_lite_count_http', 1 );
	remove_filter( 'struo_provider', 'struo_eval_lite_force_openai', 99 );

	if ( class_exists( 'Struo_Durable_Plans' ) && class_exists( 'Struo_Durable_Plan_Wpdb_Store' ) ) {
		Struo_Durable_Plans::use_store( new Struo_Durable_Plan_Wpdb_Store() );
	}

	foreach ( $state['post_ids'] as $pid ) {
		wp_delete_post( (int) $pid, true );
	}
	foreach ( $state['user_ids'] as $uid ) {
		if ( function_exists( 'wp_delete_user' ) ) {
			wp_delete_user( (int) $uid );
		}
	}
	if ( null !== $state['options'] ) {
		if ( false === $state['options'] ) {
			delete_option( 'struo_options' );
		} else {
			update_option( 'struo_options', $state['options'], false );
		}
		if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
			Struo_Block_Editor::flush_options_cache();
		}
	}
}
register_shutdown_function( 'struo_eval_lite_cleanup' );

function struo_eval_lite_count_http( $preempt, $args = [], $url = '' ) {
	unset( $args, $url );
	$GLOBALS['struo_eval_lite_http']++;
	return $preempt;
}

function struo_eval_lite_force_openai( $provider ) {
	unset( $provider );
	return 'openai';
}

function struo_eval_lite_rest( $method, $route, $params = [] ) {
	rest_get_server();
	$request = new WP_REST_Request( $method, $route );
	if ( ! empty( $params ) ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

function struo_eval_lite_status( $response ) {
	if ( $response instanceof WP_REST_Response ) {
		return (int) $response->get_status();
	}
	if ( is_wp_error( $response ) ) {
		$data = $response->get_error_data();
		return absint( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
	}
	return 0;
}

$admin_id = get_current_user_id();
if ( $admin_id < 1 || ! current_user_can( 'manage_options' ) ) {
	echo "FAIL: run as an administrator (--user=<admin>).\n";
	exit( 1 );
}

struo_eval_lite_check(
	'(lite) plugin class loads',
	class_exists( 'Struo_Block_Editor' ) && class_exists( 'Struo_Durable_Plans' )
);

global $wpdb;
$plans_table = $wpdb->prefix . 'struo_plans';
$plans_found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $plans_table ) ) );
struo_eval_lite_check(
	'(lite) struo_plans table exists',
	$plans_found === $plans_table,
	'table=' . (string) $plans_found
);

$saved_options = get_option( 'struo_options', false );
$GLOBALS['struo_eval_lite_cleanup']['options'] = $saved_options;

$memory = new Struo_Durable_Plan_Memory_Store();
Struo_Durable_Plans::use_store( $memory );
for ( $i = 0; $i < Struo_Durable_Plans::ACTIVE_CAP; $i++ ) {
	Struo_Durable_Plans::create(
		[
			'id' => sprintf( '%016x', $i + 1 ),
			'state' => 'planned',
			'expires_at' => time() + 3600,
		],
		[ 'skip_capacity' => true ]
	);
}
$capacity_err = Struo_Durable_Plans::capacity_needed( 1 );
$capacity_status = is_wp_error( $capacity_err ) ? absint( $capacity_err->get_error_data()['status'] ?? 0 ) : 0;
$first_kept = Struo_Durable_Plans::load( sprintf( '%016x', 1 ) );
struo_eval_lite_check(
	'(dps-e6) new plan fails with 429 at capacity',
	is_wp_error( $capacity_err )
		&& 'sae_plan_capacity' === $capacity_err->get_error_code()
		&& 429 === $capacity_status
		&& is_array( $first_kept ),
	'code=' . ( is_wp_error( $capacity_err ) ? $capacity_err->get_error_code() : 'none' ) . ' status=' . $capacity_status
);
Struo_Durable_Plans::use_store( new Struo_Durable_Plan_Wpdb_Store() );

$post_id = wp_insert_post(
	[
		'post_status' => 'draft',
		'post_title' => '[struo-eval-lite] paragraph',
		'post_content' => '<!-- wp:paragraph --><p>before</p><!-- /wp:paragraph -->',
		'post_author' => $admin_id,
	]
);
$post_id = is_wp_error( $post_id ) ? 0 : absint( $post_id );
if ( $post_id > 0 ) {
	$GLOBALS['struo_eval_lite_cleanup']['post_ids'][] = $post_id;
}
$opts = is_array( $saved_options ) ? $saved_options : [];
$opts['allowed_post_ids'] = array_values( array_unique( array_merge( array_map( 'absint', (array) ( $opts['allowed_post_ids'] ?? [] ) ), [ $post_id ] ) ) );
update_option( 'struo_options', $opts, false );
if ( method_exists( 'Struo_Block_Editor', 'flush_options_cache' ) ) {
	Struo_Block_Editor::flush_options_cache();
}

$author_id = wp_insert_user(
	[
		'user_login' => 'struo_eval_lite_author_' . wp_generate_password( 6, false ),
		'user_pass' => wp_generate_password( 24 ),
		'user_email' => 'struo-eval-lite-' . wp_generate_password( 8, false ) . '@example.test',
		'role' => 'author',
	]
);
$author_id = is_wp_error( $author_id ) ? 0 : absint( $author_id );
if ( $author_id > 0 ) {
	$GLOBALS['struo_eval_lite_cleanup']['user_ids'][] = $author_id;
	$author = new WP_User( $author_id );
	$author->remove_cap( 'struo_plan' );
}

wp_set_current_user( $author_id );
$denied_plan = struo_eval_lite_rest(
	'POST',
	'/struo/v1/plan',
	[
		'request' => 'Update the paragraph to after.',
		'dry_run_preview' => true,
		'post_id' => $post_id,
	]
);
struo_eval_lite_check(
	'(lite) author without struo_plan cannot POST /plan',
	403 === struo_eval_lite_status( $denied_plan ),
	'status=' . struo_eval_lite_status( $denied_plan )
);

wp_set_current_user( $admin_id );
$dispatched = Struo_Block_Editor::dispatch_internal(
	'preview-insert',
	[
		'post_id' => $post_id,
		'block_name' => 'core/paragraph',
		'fields' => [ 'content' => 'lite-insert' ],
		'dry_run' => false,
		'confirmation_token' => 'should-be-stripped',
	],
	'rest',
	[ 'tool' => 'eval_lite', 'request_id' => 'lite-1' ]
);
$confirmation = is_array( $dispatched ) ? ( $dispatched['confirmation'] ?? [] ) : [];
$dispatch_ok = is_array( $dispatched ) && true === ( $dispatched['dry_run'] ?? false );
$origin_mcp = is_array( $confirmation ) && 'mcp' === ( $confirmation['origin'] ?? '' );
$not_redeemable = is_array( $confirmation ) && false === ( $confirmation['redeemable'] ?? null );
struo_eval_lite_check(
	'(lite) dispatch_internal forces dry_run and origin mcp',
	$dispatch_ok && $origin_mcp && $not_redeemable,
	is_wp_error( $dispatched )
		? $dispatched->get_error_code() . ' — ' . $dispatched->get_error_message()
		: 'dry_run=' . ( ! empty( $dispatched['dry_run'] ) ? '1' : '0' ) . ' origin=' . (string) ( $confirmation['origin'] ?? '' )
);

$policy = new ReflectionMethod( 'Struo_Block_Editor', 'planner_policy_options' );
$policy->setAccessible( true );
$opts_out = $policy->invoke( null, 'Planner', [ 'planner_options' => [ 'model' => 'evil', 'temperature' => 2, 'envId' => 'x' ] ] );
struo_eval_lite_check(
	'(lite) request planner_options are ignored',
	is_array( $opts_out ) && empty( $opts_out['model'] ) && empty( $opts_out['envId'] ) && ! isset( $opts_out['temperature'] ),
	'keys=' . implode( ',', array_keys( (array) $opts_out ) )
);

add_filter( 'pre_http_request', 'struo_eval_lite_count_http', 1, 3 );
add_filter( 'struo_provider', 'struo_eval_lite_force_openai', 99 );

$call = new ReflectionMethod( 'Struo_Block_Editor', 'call_ai_json_query' );
$call->setAccessible( true );
$GLOBALS['struo_eval_lite_http'] = 0;
$GLOBALS['struo_eval_disclosure_spend'] = [ 'rag' => 0, 'provider' => 0 ];
$empty = $call->invoke( null, '   ' );
$empty_http = (int) $GLOBALS['struo_eval_lite_http'];
$empty_spend = absint( $GLOBALS['struo_eval_disclosure_spend']['provider'] ?? 0 );
struo_eval_lite_check(
	'(lite) empty prompt fails before provider HTTP',
	is_wp_error( $empty )
		&& 'sae_planner_invalid' === $empty->get_error_code()
		&& 0 === $empty_http
		&& 0 === $empty_spend,
	'code=' . ( is_wp_error( $empty ) ? $empty->get_error_code() : 'ok' ) . ' http=' . $empty_http . ' spend=' . $empty_spend
);

$max = new ReflectionClass( 'Struo_Block_Editor' );
$limit = (int) $max->getConstant( 'PLAN_MAX_PROMPT_LENGTH' );
$GLOBALS['struo_eval_lite_http'] = 0;
$GLOBALS['struo_eval_disclosure_spend'] = [ 'rag' => 0, 'provider' => 0 ];
$oversize = $call->invoke( null, str_repeat( 'a', $limit + 1 ) );
$over_http = (int) $GLOBALS['struo_eval_lite_http'];
$over_spend = absint( $GLOBALS['struo_eval_disclosure_spend']['provider'] ?? 0 );
struo_eval_lite_check(
	'(lite) oversize prompt fails before provider HTTP',
	is_wp_error( $oversize )
		&& 'sae_planner_invalid' === $oversize->get_error_code()
		&& 0 === $over_http
		&& 0 === $over_spend,
	'code=' . ( is_wp_error( $oversize ) ? $oversize->get_error_code() : 'ok' ) . ' http=' . $over_http . ' spend=' . $over_spend
);

struo_eval_lite_cleanup();

$failed = (int) $GLOBALS['struo_eval_lite_failures'];
echo $failed ? "RESULT: {$failed} failing\n" : "RESULT: all passing\n";
exit( $failed > 0 ? 1 : 0 );
