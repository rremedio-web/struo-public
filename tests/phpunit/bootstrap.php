<?php
/**
 * PHPUnit bootstrap. vendor/ is created by composer install (dev).
 */

$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( ! is_file( $autoload ) ) {
	fwrite( STDERR, "vendor/autoload.php missing; run composer install\n" );
	exit( 1 );
}
require $autoload;
