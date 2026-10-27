<?php
/**
 * PHPUnit wrapper around the pure-PHP provider URL cases, including redirect hops.
 */

use PHPUnit\Framework\TestCase;

final class ProviderUrlTest extends TestCase {

	public function test_provider_url_cases_pass() {
		$script = dirname( __DIR__ ) . '/provider-url-cases.php';
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script );
		exec( $cmd . ' 2>&1', $out, $code );
		$this->assertSame( 0, $code, implode( "\n", $out ) );
	}

	public function test_eval_harness_still_names_the_four_ci_cases() {
		$eval = dirname( __DIR__ ) . '/wp-eval-cases.php';
		$src = file_get_contents( $eval );
		$this->assertNotFalse( $src );
		foreach ( [ '(p4b)', '(ptc-e4)', '(s45-e3)', '(s45-e4)' ] as $label ) {
			$this->assertStringContainsString( $label, $src );
		}
	}
}
