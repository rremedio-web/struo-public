<?php
/**
 * Pure-PHP unit cases for the provider destination guard (no WordPress).
 *
 * Exercises Struo_Provider_Url_Guard::validate() with good/bad URLs using
 * an injected resolver so no DNS or network traffic happens. Exits
 * non-zero on any mismatch.
 *
 * Usage: php tests/provider-url-cases.php
 */

if ( ! defined( 'STRUO_GUARD_STANDALONE' ) ) {
	define( 'STRUO_GUARD_STANDALONE', true );
}
require_once __DIR__ . '/../provider-url-guard.php';

$FAILURES = 0;

function check_case( $label, $url, array $config, array $resolver_map, $expect_ok, $expect_code = '' ) {
	global $FAILURES;
	$resolver = static function ( $host ) use ( $resolver_map ) {
		$host = strtolower( $host );
		if ( isset( $resolver_map[ $host ] ) ) {
			return $resolver_map[ $host ];
		}
		// Literal IPs need no resolution.
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}
		return [];
	};
	$result = Struo_Provider_Url_Guard::validate( $url, $config, $resolver );

	if ( (bool) $result['ok'] !== (bool) $expect_ok ) {
		echo "FAIL: {$label} — expected ok=" . var_export( $expect_ok, true ) . ", got ok=" . var_export( $result['ok'], true ) . " (code: {$result['code']})\n";
		$FAILURES++;
		return;
	}
	if ( ! $expect_ok && '' !== $expect_code && $result['code'] !== $expect_code ) {
		echo "FAIL: {$label} — expected code {$expect_code}, got {$result['code']}\n";
		$FAILURES++;
		return;
	}
	echo "PASS: {$label}\n";
}

$DEFAULT_HOSTS = [ 'api.openai.com', 'openrouter.ai' ];
$PUBLIC_OPENAI = [ '104.18.7.192' ];

// Good destinations.
check_case( '01 default OpenAI host is allowed',
	'https://api.openai.com/v1',
	[ 'allowed_hosts' => $DEFAULT_HOSTS ],
	[ 'api.openai.com' => $PUBLIC_OPENAI ],
	true );
check_case( '02 OpenRouter host is allowed',
	'https://openrouter.ai/v1',
	[ 'allowed_hosts' => $DEFAULT_HOSTS ],
	[ 'openrouter.ai' => [ '104.18.30.9' ] ],
	true );

// Scheme policy.
check_case( '03 plain HTTP refused even for approved host',
	'http://api.openai.com/v1',
	[ 'allowed_hosts' => $DEFAULT_HOSTS ],
	[ 'api.openai.com' => $PUBLIC_OPENAI ],
	false, 'provider_url_insecure' );
check_case( '04 plain HTTP allowed for localhost only with allow_insecure',
	'http://localhost:8080/v1',
	[ 'allow_insecure' => true, 'allowed_hosts' => [ 'localhost' ] ],
	[ 'localhost' => [ '127.0.0.1' ] ],
	true );
check_case( '05 plain HTTP for localhost refused without allow_insecure',
	'http://localhost:8080/v1',
	[ 'allowed_hosts' => [ 'localhost' ] ],
	[ 'localhost' => [ '127.0.0.1' ] ],
	false, 'provider_url_insecure' );
check_case( '06 plain HTTP loopback literal allowed with allow_insecure',
	'http://127.0.0.1:9000/v1',
	[ 'allow_insecure' => true, 'allowed_hosts' => [ '127.0.0.1' ] ],
	[],
	true );
check_case( '07 plain HTTP non-local refused even with allow_insecure',
	'http://10.0.0.5/v1',
	[ 'allow_insecure' => true, 'allowed_hosts' => [ '10.0.0.5' ] ],
	[],
	false, 'provider_url_insecure' );

// Approved-host policy.
check_case( '08 unapproved host refused by policy',
	'https://evil.example/v1',
	[ 'allowed_hosts' => $DEFAULT_HOSTS ],
	[ 'evil.example' => [ '93.184.216.34' ] ],
	false, 'provider_host_not_allowed' );

// SSRF deny-list.
check_case( '09 private 192.168/16 refused',
	'https://192.168.1.10/v1',
	[ 'allowed_hosts' => [ '192.168.1.10' ] ],
	[],
	false, 'provider_ip_denied' );
check_case( '10 private 10/8 refused',
	'https://10.0.0.5/v1',
	[ 'allowed_hosts' => [ '10.0.0.5' ] ],
	[],
	false, 'provider_ip_denied' );
check_case( '11 private 172.16/12 refused',
	'https://172.16.0.9/v1',
	[ 'allowed_hosts' => [ '172.16.0.9' ] ],
	[],
	false, 'provider_ip_denied' );
check_case( '12 cloud metadata 169.254.169.254 refused',
	'https://169.254.169.254/latest/meta-data/',
	[ 'allowed_hosts' => [ '169.254.169.254' ] ],
	[],
	false, 'provider_ip_denied' );
check_case( '13 CGNAT 100.64/10 refused',
	'https://100.64.0.1/v1',
	[ 'allowed_hosts' => [ '100.64.0.1' ] ],
	[],
	false, 'provider_ip_denied' );
check_case( '14 loopback 127.0.0.1 over HTTPS refused',
	'https://127.0.0.1/v1',
	[ 'allowed_hosts' => [ '127.0.0.1' ] ],
	[],
	false, 'provider_ip_denied' );
check_case( '15 IPv6 ::1 refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ '::1' ] ],
	false, 'provider_ip_denied' );
check_case( '16 IPv6 ULA fc00::/7 refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ 'fc00::1' ] ],
	false, 'provider_ip_denied' );
check_case( '17 IPv6 link-local fe80::/10 refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ 'fe80::1' ] ],
	false, 'provider_ip_denied' );
check_case( '18 IPv6 EC2 metadata fd00:ec2::254 refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ 'fd00:ec2::254' ] ],
	false, 'provider_ip_denied' );
check_case( '19 IPv4-mapped IPv6 loopback refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ '::ffff:127.0.0.1' ] ],
	false, 'provider_ip_denied' );
check_case( '19b packed mapped IPv6 loopback ::ffff:7f00:1 refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ '::ffff:7f00:1' ] ],
	false, 'provider_ip_denied' );
check_case( '19c packed mapped IPv6 private ::ffff:a00:1 refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ '::ffff:a00:1' ] ],
	false, 'provider_ip_denied' );
check_case( '19d packed mapped IPv6 metadata ::ffff:a9fe:a9fe refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ '::ffff:a9fe:a9fe' ] ],
	false, 'provider_ip_denied' );
check_case( '19e uppercase packed mapped loopback ::FFFF:7F00:1 refused',
	'https://internal.example/v1',
	[ 'allowed_hosts' => [ 'internal.example' ] ],
	[ 'internal.example' => [ '::FFFF:7F00:1' ] ],
	false, 'provider_ip_denied' );

if ( ! function_exists( 'check_ip' ) ) {
	function check_ip( $label, $ip, $expect_denied ) {
		global $FAILURES;
		$denied = Struo_Provider_Url_Guard::ip_is_denied( $ip );
		if ( (bool) $denied !== (bool) $expect_denied ) {
			echo "FAIL: {$label} — ip_is_denied({$ip}) expected " . var_export( $expect_denied, true ) . ", got " . var_export( $denied, true ) . "\n";
			$FAILURES++;
			return;
		}
		echo "PASS: {$label}\n";
	}
}
check_ip( '19f ip_is_denied packed loopback', '::ffff:7f00:1', true );
check_ip( '19g ip_is_denied packed private 10/8', '::ffff:a00:1', true );
check_ip( '19h ip_is_denied packed metadata 169.254.169.254', '::ffff:a9fe:a9fe', true );
check_ip( '19i ip_is_denied public IPv4 still allowed', '104.18.7.192', false );

// Overrides and malformed input.
check_case( '20 allow_private overrides the deny-list',
	'https://192.168.1.10/v1',
	[ 'allow_private' => true, 'allowed_hosts' => [ '192.168.1.10' ] ],
	[],
	true );
check_case( '21 malformed URL refused',
	'not-a-url',
	[ 'allowed_hosts' => $DEFAULT_HOSTS ],
	[],
	false, 'provider_url_invalid' );
check_case( '22 unresolvable host refused',
	'https://unresolvable.invalid/v1',
	[ 'allowed_hosts' => [ 'unresolvable.invalid' ] ],
	[],
	false, 'provider_host_unresolvable' );

// Redirect hops are new destinations. The HTTP client uses redirection => 0
// so the bearer key never follows Location; these cases prove a hop would
// still fail the same policy if someone later enabled redirects.
function check_redirect( $label, $location, array $config, array $resolver_map, $expect_ok, $expect_code = '' ) {
	global $FAILURES;
	$resolver = static function ( $host ) use ( $resolver_map ) {
		$host = strtolower( $host );
		if ( isset( $resolver_map[ $host ] ) ) {
			return $resolver_map[ $host ];
		}
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}
		return [];
	};
	$result = Struo_Provider_Url_Guard::validate_redirect_location( $location, $config, $resolver );
	if ( (bool) $result['ok'] !== (bool) $expect_ok ) {
		echo "FAIL: {$label} — expected ok=" . var_export( $expect_ok, true ) . ", got ok=" . var_export( $result['ok'], true ) . " (code: {$result['code']})\n";
		$FAILURES++;
		return;
	}
	if ( ! $expect_ok && '' !== $expect_code && $result['code'] !== $expect_code ) {
		echo "FAIL: {$label} — expected code {$expect_code}, got {$result['code']}\n";
		$FAILURES++;
		return;
	}
	echo "PASS: {$label}\n";
}

check_redirect( '23 redirect to metadata IP refused',
	'https://169.254.169.254/latest/meta-data',
	[ 'allowed_hosts' => [ '169.254.169.254' ] ],
	[],
	false, 'provider_ip_denied' );
check_redirect( '24 redirect to loopback refused without allow_insecure',
	'http://127.0.0.1/v1',
	[ 'allowed_hosts' => [ '127.0.0.1' ] ],
	[],
	false, 'provider_url_insecure' );
check_redirect( '25 redirect off the allowlist refused',
	'https://evil.example/steal',
	[ 'allowed_hosts' => $DEFAULT_HOSTS ],
	[ 'evil.example' => $PUBLIC_OPENAI ],
	false, 'provider_host_not_allowed' );
check_redirect( '26 redirect to the original approved host still allowed',
	'https://api.openai.com/v1/other',
	[ 'allowed_hosts' => $DEFAULT_HOSTS ],
	[ 'api.openai.com' => $PUBLIC_OPENAI ],
	true );

echo $FAILURES > 0
	? "PROVIDER URL CASES RESULT: {$FAILURES} CHECK(S) FAILED\n"
	: "PROVIDER URL CASES RESULT: ALL CHECKS PASSED\n";
exit( $FAILURES > 0 ? 1 : 0 );
