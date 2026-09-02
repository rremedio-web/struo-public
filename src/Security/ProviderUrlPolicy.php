<?php
/**
 * Provider destination policy. Wraps the WordPress-free guard.
 *
 * @package Struo
 */

namespace Struo\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProviderUrlPolicy {

	/**
	 * @param string        $url      Base URL to validate.
	 * @param array         $config   allow_insecure, allow_private, allowed_hosts.
	 * @param callable|null $resolver Optional host->IP[] resolver.
	 * @return array{ok:bool,code:string,message:string,host:string}
	 */
	public static function validate( $url, array $config = [], $resolver = null ) {
		return \Struo_Provider_Url_Guard::validate( $url, $config, $resolver );
	}

	/**
	 * A redirect Location is a new destination. Validate it the same way
	 * and never treat a hop as trusted because the original URL passed.
	 *
	 * @param string        $location Absolute Location header value.
	 * @param array         $config   Same config as validate().
	 * @param callable|null $resolver Optional host->IP[] resolver.
	 * @return array{ok:bool,code:string,message:string,host:string}
	 */
	public static function validate_redirect_location( $location, array $config = [], $resolver = null ) {
		return \Struo_Provider_Url_Guard::validate_redirect_location( $location, $config, $resolver );
	}
}
