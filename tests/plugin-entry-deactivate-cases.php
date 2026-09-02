<?php
/**
 * Pure-PHP regression for load-time deactivation of the active basename.
 *
 * Usage: php tests/plugin-entry-deactivate-cases.php
 */

if ( ! defined( 'STRUO_GUARD_STANDALONE' ) ) {
	define( 'STRUO_GUARD_STANDALONE', true );
}

$GLOBALS['struo_deactivated'] = [];

function deactivate_plugins( $plugins, $silent = false, $network_wide = false ) {
	unset( $silent, $network_wide );
	$GLOBALS['struo_deactivated'] = array_values( (array) $plugins );
}

function plugin_basename( $file ) {
	$file = str_replace( '\\', '/', (string) $file );
	if ( preg_match( '#struo/struo\.php$#', $file ) ) {
		return 'struo/struo.php';
	}
	return basename( $file );
}

require_once __DIR__ . '/../includes/plugin-entry-deactivate.php';

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

$GLOBALS['struo_deactivated'] = [];
struo_deactivate_active_plugin_entry( '/wp-content/plugins/struo/struo.php' );
$got = $GLOBALS['struo_deactivated'];
check(
	'01 loaded from struo.php deactivates current basename',
	in_array( 'struo/struo.php', $got, true ),
	'saw ' . implode( ',', $got )
);
check(
	'01 does not invent a second basename',
	1 === count( $got ),
	'saw ' . implode( ',', $got )
);

echo $FAILURES > 0
	? "PLUGIN ENTRY DEACTIVATE CASES RESULT: {$FAILURES} CHECK(S) FAILED\n"
	: "PLUGIN ENTRY DEACTIVATE CASES RESULT: ALL CHECKS PASSED\n";
exit( $FAILURES > 0 ? 1 : 0 );
