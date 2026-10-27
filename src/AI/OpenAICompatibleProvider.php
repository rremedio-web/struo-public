<?php
/**
 * OpenAI-compatible HTTP hop. Redirects are disabled so a Location header
 * cannot carry the bearer key to an unvalidated host.
 *
 * @package Struo
 */

namespace Struo\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OpenAICompatibleProvider {

	public const REQUEST_TIMEOUT_SECONDS = 45;

	/**
	 * POST JSON to a already-validated provider endpoint.
	 *
	 * @param string               $endpoint Absolute URL (guarded by the caller).
	 * @param string               $api_key  Bearer token. Never logged.
	 * @param array<string, mixed> $body     JSON body.
	 * @return array|\WP_Error
	 */
	public static function post_json( $endpoint, $api_key, array $body ) {
		return wp_remote_post(
			$endpoint,
			[
				'timeout' => self::REQUEST_TIMEOUT_SECONDS,
				// Redirect targets cannot pass the destination validator, so
				// redirects are disabled outright. The bearer key is never
				// sent across a hop.
				'redirection' => 0,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode( $body ),
			]
		);
	}
}
