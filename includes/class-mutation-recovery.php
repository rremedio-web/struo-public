<?php
/**
 * Recoverable apply + evidence for standalone mutation_v1 plans.
 *
 * Classify stuck applying rows. Hash persisted witnesses. Queue rollback.
 * No REST registration. No provider. No payload_json rewrite.
 *
 * @package Struo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Struo_Mutation_Recovery {

	public static function is_recoverable_record( array $record ) {
		return class_exists( 'Struo_Mutation_Journal' ) && Struo_Mutation_Journal::is_journaled_record( $record );
	}

	public static function current_witness( array $record ) {
		return Struo_Block_Editor::mutation_witness_string( $record );
	}

	public static function hash_witness( $witness ) {
		return hash( 'sha256', (string) $witness );
	}

	public static function expected_witness( array $record ) {
		return Struo_Block_Editor::mutation_expected_string( $record );
	}

	/**
	 * @return array{outcome:string}
	 */
	public static function classify_applying( array $record ) {
		$current = self::hash_witness( self::current_witness( $record ) );
		$base = sanitize_text_field( (string) ( $record['base_content_hash'] ?? '' ) );
		$expected = sanitize_text_field( (string) ( $record['expected_content_hash'] ?? '' ) );

		if ( 64 === strlen( $base ) && hash_equals( $base, $current ) ) {
			return [ 'outcome' => 'safe_to_retry' ];
		}
		if ( 64 === strlen( $expected ) && hash_equals( $expected, $current ) ) {
			return [ 'outcome' => 'recovered_persisted' ];
		}

		return [ 'outcome' => 'outcome_unknown_conflict' ];
	}

	/**
	 * @return array{persisted:array{match:bool,hash:string},public:array{status:string}}
	 */
	public static function evidence( array $record ) {
		$current_hash = self::hash_witness( self::current_witness( $record ) );
		$expected = sanitize_text_field( (string) ( $record['expected_content_hash'] ?? '' ) );
		$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
		$match = false;
		if ( 'applied' === $state && 64 === strlen( $expected ) ) {
			$match = hash_equals( $expected, $current_hash );
		} elseif ( 'applied' === $state && 64 !== strlen( $expected ) ) {
			$match = true;
		}

		return [
			'persisted' => [
				'match' => $match,
				'hash' => $current_hash,
			],
			'public' => self::public_evidence( $record ),
		];
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function compile_rollback_record( array $original ) {
		if ( ! self::is_recoverable_record( $original ) ) {
			return new WP_Error( 'sae_rollback_unsupported', 'This plan cannot be rolled back.', [ 'status' => 409 ] );
		}
		if ( 'applied' !== sanitize_key( (string) ( $original['state'] ?? '' ) ) ) {
			return new WP_Error( 'sae_plan_not_applied', 'Only an applied change can be rolled back.', [ 'status' => 409 ] );
		}

		$base_content = (string) ( $original['base_content'] ?? '' );
		if ( '' === $base_content ) {
			return new WP_Error( 'sae_rollback_unsupported', 'This plan has no stored before-bytes.', [ 'status' => 409 ] );
		}

		$cas = sanitize_text_field( (string) ( $original['expected_content_hash'] ?? '' ) );
		if ( 64 !== strlen( $cas ) ) {
			$cas = self::hash_witness( self::current_witness( $original ) );
		}

		$post_id = absint( $original['post_id'] ?? 0 );
		$payload = is_array( $original['payload'] ?? null ) ? $original['payload'] : [];
		$restore_kind = 'cross_field' === sanitize_key( (string) ( $original['operation'] ?? '' ) ) ? 'cross_field' : 'post_content';
		$now = time();
		try {
			$id = bin2hex( random_bytes( 8 ) );
		} catch ( Throwable $e ) {
			$id = '';
		}
		if ( ! Struo_Block_Editor::validate_agent_plan_id( $id ) ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		return [
			'id' => $id,
			'payload_type' => 'mutation_v1',
			'state' => 'planned',
			'created_at' => $now,
			'expires_at' => $now + 86400,
			'user_id' => get_current_user_id(),
			'origin' => 'rest',
			'post_id' => $post_id,
			'post' => is_array( $original['post'] ?? null ) ? $original['post'] : [ 'post_id' => $post_id ],
			'operation' => 'restore',
			'endpoint' => sprintf( '/wp-json/struo/v1/console/agent-plans/%s/apply', $id ),
			'request' => 'Rollback the last applied change.',
			'payload' => [
				'rollback_of' => (string) ( $original['id'] ?? '' ),
				'restore_kind' => $restore_kind,
				'field' => sanitize_key( (string) ( $payload['field'] ?? '' ) ),
			],
			'preview' => null,
			'base_content_hash' => $cas,
			'base_content' => $base_content,
		];
	}

	/**
	 * @return array{status:string}
	 */
	private static function public_evidence( array $record ) {
		$post_id = absint( $record['post_id'] ?? 0 );
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post || 'publish' !== (string) $post->post_status ) {
			return [ 'status' => 'skipped' ];
		}

		$url = get_permalink( $post_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return [ 'status' => 'failed' ];
		}
		$verify_url = add_query_arg( 'struo_verify', (string) time(), $url );
		$response = wp_remote_get(
			$verify_url,
			[
				'timeout' => 8,
				'headers' => [
					'Cache-Control' => 'no-cache',
				],
			]
		);
		if ( is_wp_error( $response ) ) {
			return [ 'status' => 'failed' ];
		}
		$code = absint( wp_remote_retrieve_response_code( $response ) );
		$body = (string) wp_remote_retrieve_body( $response );
		$needle = self::approved_after_text( $record );
		if ( $code >= 200 && $code < 400 && '' !== $needle && false !== strpos( $body, $needle ) ) {
			return [ 'status' => 'ok' ];
		}

		return [ 'status' => 'failed' ];
	}

	private static function approved_after_text( array $record ) {
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$fields = is_array( $payload['fields'] ?? null ) ? $payload['fields'] : [];
		if ( isset( $fields['content'] ) ) {
			return wp_strip_all_tags( (string) $fields['content'] );
		}
		if ( isset( $payload['value'] ) ) {
			return wp_strip_all_tags( (string) $payload['value'] );
		}

		return '';
	}
}
