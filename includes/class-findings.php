<?php
/**
 * Read-only Findings from Search Console and Analytics.
 *
 * A Finding is not a Plan. This class never writes struo_plans and never Applies.
 * Google auth is a service-account JWT plus wp_remote_post. No Composer client.
 *
 * @package Struo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Struo_Findings {

	public const WINDOW = 'last_28_days';
	public const CACHE_TTL = 3600;
	public const MAX_ITEMS = 12;
	public const CACHE_KEY = 'struo_findings_v1';
	public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	public const GSC_QUERY_URL = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';
	public const GA4_REPORT_URL = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport';
	public const GOOGLE_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly';

	/**
	 * REST GET /console/findings
	 *
	 * @return array<string, mixed>
	 */
	public static function rest_list() {
		nocache_headers();
		$fixture = apply_filters( 'struo_findings_fixture', null );
		if ( is_array( $fixture ) ) {
			return [
				'ok' => true,
				'connected' => true,
				'window' => self::WINDOW,
				'items' => self::present_rows( $fixture ),
			];
		}
		if ( ! self::has_google_credentials() ) {
			return [
				'ok' => true,
				'connected' => false,
				'window' => self::WINDOW,
				'items' => [],
			];
		}

		$collected = self::collect_google();
		$body = [
			'ok' => true,
			'connected' => true,
			'window' => self::WINDOW,
			'items' => $collected['items'],
		];
		if ( ! empty( $collected['notice'] ) ) {
			$body['notice'] = (string) $collected['notice'];
		}

		return $body;
	}

	/**
	 * REST GET /console/findings/{id}
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rest_get( WP_REST_Request $request ) {
		nocache_headers();
		$item = self::get_item( (string) $request->get_param( 'id' ) );
		if ( ! is_array( $item ) ) {
			return new WP_Error( 'sae_finding_missing', 'Finding not found.', [ 'status' => 404 ] );
		}

		return $item;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function list_items() {
		$raw = apply_filters( 'struo_findings_fixture', null );
		if ( is_array( $raw ) ) {
			return self::present_rows( $raw );
		}
		if ( ! self::has_google_credentials() ) {
			return [];
		}
		$collected = self::collect_google();

		return $collected['items'];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_item( $id ) {
		$id = sanitize_text_field( (string) $id );
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			return null;
		}
		foreach ( self::list_items() as $item ) {
			if ( $id === (string) ( $item['id'] ?? '' ) ) {
				return $item;
			}
		}

		return null;
	}

	public static function is_connected() {
		$fixture = apply_filters( 'struo_findings_fixture', null );

		return is_array( $fixture ) || self::has_google_credentials();
	}

	public static function bust_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Credentials are connected when a valid service account JSON plus GA4 and GSC are saved.
	 *
	 * @return bool
	 */
	private static function has_google_credentials() {
		$account = self::service_account();
		if ( null === $account ) {
			return false;
		}
		if ( '' === self::ga4_property_id() ) {
			return false;
		}
		if ( '' === self::gsc_site_url() ) {
			return false;
		}

		return true;
	}

	/**
	 * @param list<mixed> $raw
	 * @return list<array<string, mixed>>
	 */
	private static function present_rows( array $raw ) {
		$items = [];
		foreach ( $raw as $row ) {
			$item = self::present_item( $row );
			if ( null === $item ) {
				continue;
			}
			$items[] = $item;
			if ( count( $items ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * @return array{items: list<array<string, mixed>>, notice: string|null}
	 */
	private static function collect_google() {
		$fingerprint = self::cache_fingerprint();
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached )
			&& ( $cached['fingerprint'] ?? '' ) === $fingerprint
			&& isset( $cached['items'] )
			&& is_array( $cached['items'] )
		) {
			return [
				'items' => self::present_rows( $cached['items'] ),
				'notice' => null,
			];
		}

		$fetched = self::fetch_google_rows();
		if ( is_wp_error( $fetched ) ) {
			return [
				'items' => [],
				'notice' => 'fetch_failed',
			];
		}

		set_transient(
			self::CACHE_KEY,
			[
				'fingerprint' => $fingerprint,
				'items' => $fetched,
			],
			self::CACHE_TTL
		);

		return [
			'items' => self::present_rows( $fetched ),
			'notice' => null,
		];
	}

	/**
	 * @return list<array<string, mixed>>|WP_Error
	 */
	private static function fetch_google_rows() {
		$token = self::google_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$dates = self::window_dates();
		$gsc_current = self::fetch_gsc_rows( $token, $dates['current_start'], $dates['current_end'] );
		if ( is_wp_error( $gsc_current ) ) {
			return $gsc_current;
		}
		$gsc_previous = self::fetch_gsc_rows( $token, $dates['previous_start'], $dates['previous_end'] );
		if ( is_wp_error( $gsc_previous ) ) {
			return $gsc_previous;
		}
		$ga4_current = self::fetch_ga4_rows( $token, $dates['current_start'], $dates['current_end'] );
		if ( is_wp_error( $ga4_current ) ) {
			return $ga4_current;
		}
		$ga4_previous = self::fetch_ga4_rows( $token, $dates['previous_start'], $dates['previous_end'] );
		if ( is_wp_error( $ga4_previous ) ) {
			return $ga4_previous;
		}

		return self::rank_rows( $gsc_current, $gsc_previous, $ga4_current, $ga4_previous, $dates['current_start'] );
	}

	/**
	 * @return string|WP_Error
	 */
	private static function google_access_token() {
		$account = self::service_account();
		if ( null === $account ) {
			return new WP_Error( 'sae_findings_credentials', 'Google credentials are missing.', [ 'status' => 500 ] );
		}

		$now = time();
		$header = self::base64url( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$claims = self::base64url(
			wp_json_encode(
				[
					'iss' => $account['client_email'],
					'scope' => self::GOOGLE_SCOPE,
					'aud' => self::TOKEN_URL,
					'iat' => $now,
					'exp' => $now + 3600,
				]
			)
		);
		$unsigned = $header . '.' . $claims;
		$signature = '';
		$key = openssl_pkey_get_private( $account['private_key'] );
		if ( false === $key || ! openssl_sign( $unsigned, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'sae_findings_jwt', 'Could not sign the Google token.', [ 'status' => 500 ] );
		}
		$jwt = $unsigned . '.' . self::base64url( $signature );

		$body = self::google_http(
			self::TOKEN_URL,
			[
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion' => $jwt,
			],
			[]
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$token = trim( (string) ( $body['access_token'] ?? '' ) );
		if ( '' === $token ) {
			return new WP_Error( 'sae_findings_token', 'Google did not return an access token.', [ 'status' => 500 ] );
		}

		return $token;
	}

	/**
	 * @param string $token
	 * @param string $start
	 * @param string $end
	 * @return array<string, array<string, float|int>>|WP_Error
	 */
	private static function fetch_gsc_rows( $token, $start, $end ) {
		$site = rawurlencode( self::gsc_site_url() );
		$url = sprintf( self::GSC_QUERY_URL, $site );
		$body = self::google_http(
			$url,
			[
				'startDate' => $start,
				'endDate' => $end,
				'dimensions' => [ 'page' ],
				'rowLimit' => 5000,
			],
			[ 'Authorization' => 'Bearer ' . $token ]
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$out = [];
		foreach ( (array) ( $body['rows'] ?? [] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$page_url = (string) ( $row['keys'][0] ?? '' );
			$page = self::normalize_url( $page_url );
			if ( '' === $page ) {
				continue;
			}
			$metrics = [
				'clicks' => (int) round( (float) ( $row['clicks'] ?? 0 ) ),
				'impressions' => (int) round( (float) ( $row['impressions'] ?? 0 ) ),
				'ctr' => (float) ( $row['ctr'] ?? 0 ),
			];
			$out[ $page ] = $metrics;
			$path = self::normalize_path( (string) ( wp_parse_url( $page_url, PHP_URL_PATH ) ?? '' ) );
			if ( '' !== $path && ! isset( $out[ $path ] ) ) {
				$out[ $path ] = $metrics;
			}
		}

		return $out;
	}

	/**
	 * @param string $token
	 * @param string $start
	 * @param string $end
	 * @return array<string, array<string, float|int>>|WP_Error
	 */
	private static function fetch_ga4_rows( $token, $start, $end ) {
		$property = self::ga4_property_id();
		$url = sprintf( self::GA4_REPORT_URL, rawurlencode( $property ) );
		$body = self::google_http(
			$url,
			[
				'dateRanges' => [
					[
						'startDate' => $start,
						'endDate' => $end,
					],
				],
				'dimensions' => [
					[ 'name' => 'pagePath' ],
				],
				'metrics' => [
					[ 'name' => 'bounceRate' ],
					[ 'name' => 'engagementRate' ],
					[ 'name' => 'sessions' ],
				],
				'limit' => '5000',
			],
			[ 'Authorization' => 'Bearer ' . $token ]
		);
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$out = [];
		foreach ( (array) ( $body['rows'] ?? [] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$path = self::normalize_path( (string) ( $row['dimensionValues'][0]['value'] ?? '' ) );
			if ( '' === $path ) {
				continue;
			}
			$metrics = is_array( $row['metricValues'] ?? null ) ? $row['metricValues'] : [];
			$out[ $path ] = [
				'bounce' => (float) ( $metrics[0]['value'] ?? 0 ),
				'engagement' => (float) ( $metrics[1]['value'] ?? 0 ),
				'sessions' => (int) round( (float) ( $metrics[2]['value'] ?? 0 ) ),
			];
		}

		return $out;
	}

	/**
	 * @param array<string, array<string, float|int>> $gsc_current
	 * @param array<string, array<string, float|int>> $gsc_previous
	 * @param array<string, array<string, float|int>> $ga4_current
	 * @param array<string, array<string, float|int>> $ga4_previous
	 * @param string $window_start
	 * @return list<array<string, mixed>>
	 */
	private static function rank_rows( array $gsc_current, array $gsc_previous, array $ga4_current, array $ga4_previous, $window_start ) {
		$candidates = [];
		foreach ( self::allowlisted_pages() as $page ) {
			$post_id = (int) $page['post_id'];
			$url = (string) $page['url'];
			$path = (string) $page['path'];
			$gsc_now = $gsc_current[ $url ] ?? $gsc_current[ $path ] ?? null;
			$gsc_prev = $gsc_previous[ $url ] ?? $gsc_previous[ $path ] ?? null;
			if ( is_array( $gsc_now ) || is_array( $gsc_prev ) ) {
				$clicks_after = (int) ( is_array( $gsc_now ) ? ( $gsc_now['clicks'] ?? 0 ) : 0 );
				$clicks_before = (int) ( is_array( $gsc_prev ) ? ( $gsc_prev['clicks'] ?? 0 ) : 0 );
				$impressions = (int) ( is_array( $gsc_now ) ? ( $gsc_now['impressions'] ?? 0 ) : 0 );
				$ctr = (float) ( is_array( $gsc_now ) ? ( $gsc_now['ctr'] ?? 0 ) : 0 );
				$drop = $clicks_before - $clicks_after;
				if ( $drop >= 5 ) {
					$candidates[] = [
						'score' => $drop * 10,
						'id' => self::finding_id( 'search', $post_id, $window_start ),
						'source' => 'search',
						'post_id' => $post_id,
						'title' => 'Clicks on the page dropped',
						'summary' => $clicks_before . ' to ' . $clicks_after . ' clicks',
						'metrics' => [
							'clicks_before' => $clicks_before,
							'clicks_after' => $clicks_after,
							'impressions' => $impressions,
							'ctr' => $ctr,
						],
					];
				} elseif ( $impressions >= 100 && $ctr <= 0.03 ) {
					$candidates[] = [
						'score' => (int) round( $impressions * ( 0.05 - min( $ctr, 0.05 ) ) ),
						'id' => self::finding_id( 'search', $post_id, $window_start ),
						'source' => 'search',
						'post_id' => $post_id,
						'title' => 'The page shows in search and almost nobody clicks',
						'summary' => (string) $impressions . ' impressions',
						'metrics' => [
							'impressions' => $impressions,
							'clicks' => $clicks_after,
							'ctr' => $ctr,
						],
					];
				}
			}

			$ga_now = $ga4_current[ $path ] ?? null;
			$ga_prev = $ga4_previous[ $path ] ?? null;
			if ( is_array( $ga_now ) ) {
				$bounce = (float) ( $ga_now['bounce'] ?? 0 );
				$sessions = (int) ( $ga_now['sessions'] ?? 0 );
				$engagement = (float) ( $ga_now['engagement'] ?? 0 );
				$bounce_before = is_array( $ga_prev ) ? (float) ( $ga_prev['bounce'] ?? 0 ) : 0;
				if ( $sessions >= 20 && $bounce >= 0.7 ) {
					$bounce_pct = (int) round( $bounce * 100 );
					$candidates[] = [
						'score' => (int) round( $bounce * $sessions ),
						'id' => self::finding_id( 'analytics', $post_id, $window_start ),
						'source' => 'analytics',
						'post_id' => $post_id,
						'title' => 'People leave the page in a few seconds',
						'summary' => $bounce_pct . ' percent bounce',
						'metrics' => [
							'bounce' => $bounce_pct,
							'sessions' => $sessions,
							'engagement' => $engagement,
							'bounce_before' => (int) round( $bounce_before * 100 ),
						],
					];
				}
			}
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				return ( (int) ( $b['score'] ?? 0 ) ) <=> ( (int) ( $a['score'] ?? 0 ) );
			}
		);

		$rows = [];
		$seen = [];
		foreach ( $candidates as $row ) {
			$key = (string) ( $row['source'] ?? '' ) . '|' . (string) ( $row['post_id'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			unset( $row['score'] );
			$rows[] = $row;
			if ( count( $rows ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * @param string               $url
	 * @param array<string, mixed> $body
	 * @param array<string, string> $headers
	 * @return array<string, mixed>|WP_Error
	 */
	private static function google_http( $url, array $body, array $headers ) {
		$parsed = wp_parse_url( $url );
		$host = strtolower( (string) ( $parsed['host'] ?? '' ) );
		$allowed = [
			'oauth2.googleapis.com',
			'www.googleapis.com',
			'analyticsdata.googleapis.com',
		];
		if ( 'https' !== ( $parsed['scheme'] ?? '' ) || ! in_array( $host, $allowed, true ) ) {
			return new WP_Error( 'sae_findings_host', 'Google host is not allowed.', [ 'status' => 500 ] );
		}

		$request_headers = array_merge(
			[
				'Content-Type' => isset( $headers['Authorization'] ) ? 'application/json' : 'application/x-www-form-urlencoded',
				'Accept' => 'application/json',
			],
			$headers
		);
		$payload = isset( $headers['Authorization'] ) ? wp_json_encode( $body ) : $body;
		$response = wp_remote_post(
			$url,
			[
				'timeout' => 20,
				'redirection' => 0,
				'headers' => $request_headers,
				'body' => $payload,
			]
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sae_findings_http', 'Google request failed.', [ 'status' => 502 ] );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'sae_findings_http', 'Google request failed.', [ 'status' => 502 ] );
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'sae_findings_http', 'Google response was not JSON.', [ 'status' => 502 ] );
		}

		return $decoded;
	}

	/**
	 * @return list<array{post_id:int,url:string,path:string}>
	 */
	private static function allowlisted_pages() {
		$pages = [];
		foreach ( Struo_Block_Editor::get_allowed_post_ids() as $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id <= 0 ) {
				continue;
			}
			$permalink = (string) get_permalink( $post_id );
			$url = self::normalize_url( $permalink );
			$path = self::normalize_path( (string) ( wp_parse_url( $permalink, PHP_URL_PATH ) ?? '' ) );
			if ( '' === $url && '' === $path ) {
				continue;
			}
			$pages[] = [
				'post_id' => $post_id,
				'url' => $url,
				'path' => $path,
			];
		}

		return $pages;
	}

	/**
	 * @return array{current_start:string,current_end:string,previous_start:string,previous_end:string}
	 */
	private static function window_dates() {
		$now = time();
		$current_end = gmdate( 'Y-m-d', $now - DAY_IN_SECONDS );
		$current_start = gmdate( 'Y-m-d', $now - ( 28 * DAY_IN_SECONDS ) );
		$previous_end = gmdate( 'Y-m-d', $now - ( 29 * DAY_IN_SECONDS ) );
		$previous_start = gmdate( 'Y-m-d', $now - ( 56 * DAY_IN_SECONDS ) );

		return [
			'current_start' => $current_start,
			'current_end' => $current_end,
			'previous_start' => $previous_start,
			'previous_end' => $previous_end,
		];
	}

	/**
	 * @return string
	 */
	private static function cache_fingerprint() {
		$ids = Struo_Block_Editor::get_allowed_post_ids();
		sort( $ids, SORT_NUMERIC );
		$dates = self::window_dates();

		return hash(
			'sha256',
			wp_json_encode(
				[
					'ids' => $ids,
					'window' => $dates,
					'ga4' => self::ga4_property_id(),
					'gsc' => self::gsc_site_url(),
				]
			)
		);
	}

	/**
	 * @return array{client_email:string,private_key:string}|null
	 */
	private static function service_account() {
		$json = (string) get_option( Struo_Block_Editor::GOOGLE_SERVICE_ACCOUNT_OPTION, '' );
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		$email = trim( (string) ( $decoded['client_email'] ?? '' ) );
		$key = trim( (string) ( $decoded['private_key'] ?? '' ) );
		if ( '' === $email || '' === $key ) {
			return null;
		}

		return [
			'client_email' => $email,
			'private_key' => $key,
		];
	}

	/**
	 * @return string
	 */
	private static function ga4_property_id() {
		return sanitize_text_field( (string) get_option( Struo_Block_Editor::GA4_PROPERTY_ID_OPTION, '' ) );
	}

	/**
	 * @return string
	 */
	private static function gsc_site_url() {
		return trim( (string) get_option( Struo_Block_Editor::GSC_SITE_URL_OPTION, '' ) );
	}

	/**
	 * @param string $url
	 * @return string
	 */
	private static function normalize_url( $url ) {
		$url = strtolower( untrailingslashit( trim( $url ) ) );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) {
			return $url;
		}

		return self::normalize_path( $url );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = strtolower( untrailingslashit( '/' . ltrim( trim( $path ), '/' ) ) );

		return '' === $path ? '/' : $path;
	}

	/**
	 * @param string $data
	 * @return string
	 */
	private static function base64url( $data ) {
		return rtrim( strtr( base64_encode( (string) $data ), '+/', '-_' ), '=' );
	}

	/**
	 * @param mixed $row
	 * @return array<string, mixed>|null
	 */
	private static function present_item( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$post_id = absint( $row['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return null;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}
		$allowed = Struo_Block_Editor::get_allowed_post_ids();
		if ( ! in_array( $post_id, $allowed, true ) ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$source = sanitize_key( (string) ( $row['source'] ?? 'search' ) );
		if ( ! in_array( $source, [ 'search', 'analytics' ], true ) ) {
			$source = 'search';
		}

		$id = sanitize_text_field( (string) ( $row['id'] ?? '' ) );
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			$id = self::finding_id( $source, $post_id, self::WINDOW );
		}

		$metrics = [];
		if ( is_array( $row['metrics'] ?? null ) ) {
			foreach ( $row['metrics'] as $key => $value ) {
				$metric_key = sanitize_key( (string) $key );
				if ( '' === $metric_key ) {
					continue;
				}
				if ( is_int( $value ) || is_float( $value ) ) {
					$metrics[ $metric_key ] = $value;
				} elseif ( is_string( $value ) ) {
					$metrics[ $metric_key ] = sanitize_text_field( $value );
				}
			}
		}

		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		if ( '' === $title ) {
			$title = get_the_title( $post_id );
		}
		$summary = sanitize_text_field( (string) ( $row['summary'] ?? '' ) );

		return [
			'id' => $id,
			'source' => $source,
			'title' => $title,
			'post_id' => $post_id,
			'post_title' => get_the_title( $post_id ),
			'post_url' => (string) get_permalink( $post_id ),
			'window' => self::WINDOW,
			'summary' => $summary,
			'metrics' => $metrics,
			'not_a_plan' => true,
		];
	}

	/**
	 * @param string $source
	 * @param int    $post_id
	 * @param string $window_start
	 * @return string
	 */
	private static function finding_id( $source, $post_id, $window_start ) {
		return substr( hash( 'sha256', $source . '|' . absint( $post_id ) . '|' . (string) $window_start ), 0, 16 );
	}
}
