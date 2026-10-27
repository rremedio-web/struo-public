<?php
/**
 * Struo provider destination guard (pure PHP, no WordPress dependency).
 *
 * Validates an OpenAI-compatible provider base URL before any request is
 * sent: scheme policy, approved-host policy, and SSRF protections
 * (resolves the host and refuses loopback/private/link-local/CGNAT/metadata
 * destinations). Kept free of WordPress APIs so tests/provider-url-cases.php
 * can exercise it directly.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'STRUO_GUARD_STANDALONE' ) ) {
	exit;
}

final class Struo_Provider_Url_Guard {

	/**
	 * Validate a provider base URL.
	 *
	 * @param string        $url       Base URL to validate.
	 * @param array         $config    allow_insecure (bool), allow_private (bool), allowed_hosts (string[]).
	 * @param callable|null $resolver  Optional host->IP[] resolver (injectable for tests).
	 * @return array{ok:bool,code:string,message:string,host:string}
	 */
	public static function validate( $url, array $config = [], $resolver = null ) {
		$config = array_merge(
			[
				'allow_insecure' => false,
				'allow_private'  => false,
				'allowed_hosts'  => [],
			],
			$config
		);

		$result = [
			'ok'      => false,
			'code'    => '',
			'message' => '',
			'host'    => '',
		];

		$parsed = @parse_url( trim( (string) $url ) );
		if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			$result['code']    = 'provider_url_invalid';
			$result['message'] = 'Provider base URL is not a valid absolute URL.';
			return $result;
		}

		$scheme = strtolower( (string) $parsed['scheme'] );
		$host   = strtolower( (string) $parsed['host'] );
		$result['host'] = $host;

		if ( 'https' !== $scheme ) {
			$insecure_allowed = $config['allow_insecure']
				&& 'http' === $scheme
				&& self::host_is_loopback_literal( $host );
			if ( ! $insecure_allowed ) {
				$result['code']    = 'provider_url_insecure';
				$result['message'] = 'Provider base URL must use HTTPS (plain HTTP is only allowed for localhost when STRUO_ALLOW_INSECURE_PROVIDER_URL is enabled).';
				return $result;
			}
		}

		$allowed_hosts = array_map( 'strtolower', array_filter( array_map( 'trim', (array) $config['allowed_hosts'] ) ) );
		if ( ! in_array( $host, $allowed_hosts, true ) ) {
			$result['code']    = 'provider_host_not_allowed';
			$result['message'] = sprintf( 'Provider host "%s" is not on the approved host list.', $host );
			return $result;
		}

		$ips = $resolver ? $resolver( $host ) : self::resolve_host_ips( $host );
		$ips = array_values( array_filter( (array) $ips, 'is_string' ) );
		if ( empty( $ips ) ) {
			$result['code']    = 'provider_host_unresolvable';
			$result['message'] = sprintf( 'Provider host "%s" could not be resolved.', $host );
			return $result;
		}

		// A loopback destination explicitly permitted via the insecure-URL
		// exception is exempt from the IP deny-list (local dev usage).
		$loopback_exception = $config['allow_insecure'] && self::host_is_loopback_literal( $host );

		if ( ! $config['allow_private'] && ! $loopback_exception ) {
			foreach ( $ips as $ip ) {
				if ( self::ip_is_denied( $ip ) ) {
					$result['code']    = 'provider_ip_denied';
					$result['message'] = sprintf( 'Provider host "%s" resolves to a forbidden address (%s). Set STRUO_ALLOW_PRIVATE_PROVIDER_URL to override.', $host, $ip );
					return $result;
				}
			}
		}

		$result['ok'] = true;
		return $result;
	}

	/**
	 * A redirect Location is a new destination. Validate it the same way
	 * as the original URL. Callers must not send the bearer key across a
	 * hop: HTTP requests use redirection => 0, and this helper exists so
	 * tests can prove an untrusted Location would be refused if followed.
	 *
	 * @param string        $location Absolute Location header value.
	 * @param array         $config   Same config as validate().
	 * @param callable|null $resolver Optional host->IP[] resolver.
	 * @return array{ok:bool,code:string,message:string,host:string}
	 */
	public static function validate_redirect_location( $location, array $config = [], $resolver = null ) {
		return self::validate( $location, $config, $resolver );
	}

	/**
	 * True when the host is a loopback literal eligible for the plain-HTTP
	 * exception: "localhost", 127.0.0.0/8, or ::1.
	 */
	public static function host_is_loopback_literal( $host ) {
		$host = strtolower( trim( (string) $host ) );
		if ( 'localhost' === $host ) {
			return true;
		}
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return self::ipv4_in_cidr( $host, '127.0.0.0', 8 );
		}
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return self::ipv6_in_prefix( $host, '::1', 128 );
		}
		return false;
	}

	/**
	 * Resolve a host to a list of IP addresses (A + AAAA). Literal IPs are
	 * returned as-is without DNS traffic.
	 */
	public static function resolve_host_ips( $host ) {
		$host = trim( (string) $host );
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}

		$ips = [];
		$records = @dns_get_record( $host, DNS_A | DNS_AAAA );
		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				if ( ! empty( $record['ip'] ) ) {
					$ips[] = (string) $record['ip'];
				}
				if ( ! empty( $record['ipv6'] ) ) {
					$ips[] = (string) $record['ipv6'];
				}
			}
		}
		if ( empty( $ips ) ) {
			$v4 = @gethostbyname( $host );
			if ( is_string( $v4 ) && $v4 !== $host && false !== filter_var( $v4, FILTER_VALIDATE_IP ) ) {
				$ips[] = $v4;
			}
		}
		return array_values( array_unique( $ips ) );
	}

	/**
	 * True when the address must never receive provider traffic: loopback,
	 * private (10/8, 172.16/12, 192.168/16), link-local (169.254/16,
	 * fe80::/10), CGNAT (100.64/10), unspecified, IPv6 ULA (fc00::/7),
	 * cloud metadata (169.254.169.254, fd00:ec2::254), and IPv4-mapped
	 * IPv6 forms of the above.
	 */
	public static function ip_is_denied( $ip ) {
		$ip = trim( (string) $ip );

		// Unwrap IPv4-mapped IPv6 before evaluating. Dotted form first
		// (::ffff:127.0.0.1 / ::127.0.0.1), then packed/hex form
		// (::ffff:7f00:1) via inet_pton.
		if ( preg_match( '/^::(?:ffff:)?(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m ) ) {
			$ip = $m[1];
		} else {
			$ip = self::unwrap_packed_ipv4_mapped_ipv6( $ip );
		}

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return self::ipv4_is_denied( $ip );
		}
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return self::ipv6_is_denied( $ip );
		}
		return true;
	}

	/**
	 * Map packed IPv4-mapped IPv6 (::ffff:7f00:1) to dotted IPv4.
	 * Prefix is 80 bits of zero + 16 bits of 0xffff, then 32 bits of IPv4.
	 */
	private static function unwrap_packed_ipv4_mapped_ipv6( $ip ) {
		$packed = @inet_pton( (string) $ip );
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return $ip;
		}
		$mapped_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
		if ( $mapped_prefix !== substr( $packed, 0, 12 ) ) {
			return $ip;
		}
		$v4 = @inet_ntop( substr( $packed, 12 ) );
		return is_string( $v4 ) && '' !== $v4 ? $v4 : $ip;
	}

	private static function ipv4_is_denied( $ip ) {
		$denied_ranges = [
			// $ip, $subnet, $prefix, $label
			[ '0.0.0.0', 8, 'unspecified' ],
			[ '10.0.0.0', 8, 'private' ],
			[ '100.64.0.0', 10, 'cgnat' ],
			[ '127.0.0.0', 8, 'loopback' ],
			[ '169.254.0.0', 16, 'link-local/metadata' ], // includes 169.254.169.254
			[ '172.16.0.0', 12, 'private' ],
			[ '192.168.0.0', 16, 'private' ],
		];
		foreach ( $denied_ranges as $range ) {
			if ( self::ipv4_in_cidr( $ip, $range[0], $range[1] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function ipv6_is_denied( $ip ) {
		$packed = @inet_pton( $ip );
		if ( false === $packed || strlen( $packed ) !== 16 ) {
			return true;
		}
		if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01" === $packed ) {
			return true; // ::1 loopback
		}
		if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00" === $packed ) {
			return true; // :: unspecified
		}
		$denied_prefixes = [
			[ 'fc00::', 7, 'ula' ],                  // includes fd00:ec2::254 metadata
			[ 'fe80::', 10, 'link-local' ],
		];
		foreach ( $denied_prefixes as $prefix ) {
			if ( self::ipv6_in_prefix( $ip, $prefix[0], $prefix[1] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function ipv4_in_cidr( $ip, $subnet, $prefix ) {
		$ip_long = ip2long( $ip );
		$sub_long = ip2long( $subnet );
		if ( false === $ip_long || false === $sub_long ) {
			return false;
		}
		$mask = -1 << ( 32 - $prefix );
		return ( $ip_long & $mask ) === ( $sub_long & $mask );
	}

	public static function ipv6_in_prefix( $ip, $subnet, $prefix ) {
		$ip_packed = @inet_pton( $ip );
		$sub_packed = @inet_pton( $subnet );
		if ( false === $ip_packed || false === $sub_packed || strlen( $ip_packed ) !== 16 || strlen( $sub_packed ) !== 16 ) {
			return false;
		}
		if ( $prefix <= 0 ) {
			return true;
		}
		if ( $prefix >= 128 ) {
			return $ip_packed === $sub_packed;
		}
		$full_bytes = intdiv( $prefix, 8 );
		$remainder = $prefix % 8;
		if ( $full_bytes > 0 && substr( $ip_packed, 0, $full_bytes ) !== substr( $sub_packed, 0, $full_bytes ) ) {
			return false;
		}
		if ( $remainder > 0 ) {
			$mask = chr( ( 0xFF << ( 8 - $remainder ) ) & 0xFF );
			return ( $ip_packed[ $full_bytes ] & $mask ) === ( $sub_packed[ $full_bytes ] & $mask );
		}
		return true;
	}
}
