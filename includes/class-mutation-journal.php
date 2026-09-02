<?php
/**
 * Append-only mutation journal for standalone mutation_v1 plans.
 *
 * Product code INSERT only. Privacy erase may DELETE. No payload encryption.
 *
 * @package Struo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Struo_Mutation_Journal {

	const TABLE_SUFFIX = 'struo_events';
	const DB_VERSION = 1;
	const DB_VERSION_OPTION = 'struo_events_db_version';

	/**
	 * @param array<string, mixed> $record
	 */
	public static function is_journaled_record( array $record ) {
		$payload_type = sanitize_key( (string) ( $record['payload_type'] ?? '' ) );
		if ( 'mutation_v1' !== $payload_type ) {
			return false;
		}

		$operation = sanitize_key( (string) ( $record['operation'] ?? '' ) );
		if ( ! in_array( $operation, [ 'insert', 'update', 'remove', 'batch', 'cross_field', 'restore' ], true ) ) {
			return false;
		}

		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$bundle_id = sanitize_text_field( (string) ( $payload['bundle_id'] ?? '' ) );
		if ( '' !== $bundle_id ) {
			return false;
		}

		return true;
	}

	public static function hash_payload_json( $payload_json ) {
		return hash( 'sha256', (string) $payload_json );
	}

	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function ensure_table() {
		global $wpdb;

		$stored = absint( get_option( self::DB_VERSION_OPTION, 0 ) );
		$table_name = self::get_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $stored >= self::DB_VERSION && $exists === $table_name ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			event_id char(16) NOT NULL,
			plan_id char(16) NOT NULL,
			seq int(10) unsigned NOT NULL DEFAULT 0,
			type varchar(16) NOT NULL,
			payload_hash char(64) NOT NULL DEFAULT '',
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			occurred_at datetime NOT NULL,
			body_json longtext NULL,
			PRIMARY KEY  (event_id),
			UNIQUE KEY plan_seq (plan_id, seq),
			KEY plan_id (plan_id)
		) ENGINE=InnoDB {$charset_collate};";

		dbDelta( $sql );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $exists !== $table_name ) {
			return false;
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		return true;
	}

	public static function stored_payload_json( $plan_id ) {
		global $wpdb;

		$plan_id = sanitize_text_field( (string) $plan_id );
		if ( ! Struo_Block_Editor::validate_agent_plan_id( $plan_id ) ) {
			return '';
		}

		$plans = $wpdb->prefix . 'struo_plans';
		$json = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT payload_json FROM {$plans} WHERE plan_id = %s",
				$plan_id
			)
		);

		return is_string( $json ) ? $json : '';
	}

	public static function next_seq( $plan_id ) {
		global $wpdb;

		$plan_id = sanitize_text_field( (string) $plan_id );
		if ( ! self::ensure_table() ) {
			return 1;
		}

		$table = self::get_table_name();
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(seq) FROM {$table} WHERE plan_id = %s",
				$plan_id
			)
		);

		return absint( $max ) + 1;
	}

	/**
	 * @param string $type queued|approved|applied|failed
	 * @return true|WP_Error
	 */
	public static function append( $plan_id, $seq, $type, $payload_hash, array $body = [] ) {
		global $wpdb;

		$plan_id = sanitize_text_field( (string) $plan_id );
		$type = sanitize_key( (string) $type );
		$payload_hash = sanitize_text_field( (string) $payload_hash );
		$seq = absint( $seq );

		if ( ! Struo_Block_Editor::validate_agent_plan_id( $plan_id ) ) {
			return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
		}
		if ( ! in_array( $type, [ 'queued', 'approved', 'applied', 'failed' ], true ) ) {
			return new WP_Error( 'sae_plan_invalid_transition', 'Invalid journal event type.', [ 'status' => 400 ] );
		}
		if ( 64 !== strlen( $payload_hash ) ) {
			return new WP_Error( 'sae_mutation_payload_changed', 'Mutation payload hash is invalid.', [ 'status' => 409 ] );
		}
		if ( ! self::ensure_table() ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}
		if ( $seq < 1 ) {
			$seq = self::next_seq( $plan_id );
		}

		try {
			$event_id = bin2hex( random_bytes( 8 ) );
		} catch ( Throwable $e ) {
			$event_id = '';
		}
		if ( ! Struo_Block_Editor::validate_agent_plan_id( $event_id ) ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		$body_json = wp_json_encode( $body );
		if ( false === $body_json ) {
			$body_json = '{}';
		}

		$table = self::get_table_name();
		$inserted = $wpdb->insert(
			$table,
			[
				'event_id' => $event_id,
				'plan_id' => $plan_id,
				'seq' => $seq,
				'type' => $type,
				'payload_hash' => $payload_hash,
				'actor_user_id' => get_current_user_id(),
				'occurred_at' => current_time( 'mysql' ),
				'body_json' => $body_json,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new WP_Error( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function list_for_plan( $plan_id ) {
		global $wpdb;

		$plan_id = sanitize_text_field( (string) $plan_id );
		if ( ! Struo_Block_Editor::validate_agent_plan_id( $plan_id ) || ! self::ensure_table() ) {
			return [];
		}

		$table = self::get_table_name();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_id, seq, type, payload_hash, actor_user_id, occurred_at, body_json FROM {$table} WHERE plan_id = %s ORDER BY seq ASC",
				$plan_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$events = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$body = json_decode( (string) ( $row['body_json'] ?? '' ), true );
			$events[] = [
				'event_id' => (string) ( $row['event_id'] ?? '' ),
				'seq' => absint( $row['seq'] ?? 0 ),
				'type' => sanitize_key( (string) ( $row['type'] ?? '' ) ),
				'payload_hash' => (string) ( $row['payload_hash'] ?? '' ),
				'actor_user_id' => absint( $row['actor_user_id'] ?? 0 ),
				'occurred_at' => (string) ( $row['occurred_at'] ?? '' ),
				'body' => is_array( $body ) ? $body : [],
			];
		}

		return $events;
	}

	/**
	 * @return array{plan_id:string,state:string,payload_hash:string,post_id:int}
	 */
	public static function project( $plan_id, array $record ) {
		$plan_id = sanitize_text_field( (string) $plan_id );
		$state = 'planned';
		$hash = '';
		foreach ( self::list_for_plan( $plan_id ) as $event ) {
			$hash = (string) ( $event['payload_hash'] ?? '' );
			$type = sanitize_key( (string) ( $event['type'] ?? '' ) );
			if ( 'queued' === $type ) {
				$state = 'planned';
			} elseif ( 'approved' === $type ) {
				$state = 'approved';
			} elseif ( 'applied' === $type ) {
				$state = 'applied';
			} elseif ( 'failed' === $type ) {
				$state = 'failed';
			}
		}

		return [
			'plan_id' => $plan_id,
			'state' => $state,
			'payload_hash' => $hash,
			'post_id' => absint( $record['post_id'] ?? 0 ),
		];
	}

	public static function readers_match( array $record, array $projection, $payload_hash ) {
		$record_state = sanitize_key( (string) ( $record['state'] ?? '' ) );
		if ( 'applying' === $record_state ) {
			$record_state = 'approved';
		}
		$proj_state = sanitize_key( (string) ( $projection['state'] ?? '' ) );
		$record_id = (string) ( $record['id'] ?? '' );
		$proj_id = (string) ( $projection['plan_id'] ?? '' );
		$record_post = absint( $record['post_id'] ?? 0 );
		$proj_post = absint( $projection['post_id'] ?? 0 );
		$proj_hash = (string) ( $projection['payload_hash'] ?? '' );

		return $record_id === $proj_id
			&& $record_state === $proj_state
			&& $record_post === $proj_post
			&& hash_equals( (string) $payload_hash, $proj_hash );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function maybe_backfill( array $record ) {
		if ( ! self::is_journaled_record( $record ) ) {
			return true;
		}

		$id = sanitize_text_field( (string) ( $record['id'] ?? '' ) );
		if ( ! Struo_Block_Editor::validate_agent_plan_id( $id ) ) {
			return true;
		}

		$json = self::stored_payload_json( $id );
		if ( '' === $json ) {
			return true;
		}
		$hash = self::hash_payload_json( $json );
		$events = self::list_for_plan( $id );
		$have = [];
		foreach ( $events as $event ) {
			$have[ sanitize_key( (string) ( $event['type'] ?? '' ) ) ] = true;
		}

		$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
		$needed = [ 'queued' ];
		if ( in_array( $state, [ 'approved', 'applying', 'applied', 'failed' ], true ) ) {
			$needed[] = 'approved';
		}
		if ( 'applied' === $state ) {
			$needed[] = 'applied';
		}
		if ( 'failed' === $state ) {
			$needed[] = 'failed';
		}

		foreach ( $needed as $type ) {
			if ( ! empty( $have[ $type ] ) ) {
				continue;
			}
			$seq = self::next_seq( $id );
			$appended = self::append( $id, $seq, $type, $hash, [ 'state' => $state, 'backfill' => true ] );
			if ( is_wp_error( $appended ) ) {
				return $appended;
			}
			$have[ $type ] = true;
		}

		return true;
	}

	public static function export_for_plan( $plan_id ) {
		$events = self::list_for_plan( $plan_id );
		$encoded = wp_json_encode( $events );
		return is_string( $encoded ) ? $encoded : '[]';
	}

	public static function erase_for_plan_ids( array $plan_ids ) {
		global $wpdb;

		$ids = [];
		foreach ( $plan_ids as $plan_id ) {
			$plan_id = sanitize_text_field( (string) $plan_id );
			if ( Struo_Block_Editor::validate_agent_plan_id( $plan_id ) ) {
				$ids[] = $plan_id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) || ! self::ensure_table() ) {
			return 0;
		}

		$table = self::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
		$sql = $wpdb->prepare(
			"DELETE FROM {$table} WHERE plan_id IN ({$placeholders})",
			$ids
		);

		return (int) $wpdb->query( $sql );
	}
}
