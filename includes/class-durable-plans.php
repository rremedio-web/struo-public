<?php
/**
 * Durable plan store: one module for struo_plans moves.
 *
 * Callers ask by intent. Adapters write. This class does not Apply and
 * does not authorize.
 *
 * @package Struo
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'STRUO_DURABLE_PLANS_STANDALONE' ) ) {
	exit;
}

interface Struo_Durable_Plan_Store {
	/**
	 * @param array<string, mixed> $record
	 * @return bool
	 */
	public function insert( array $record );

	/**
	 * @param string $plan_id
	 * @return array<string, mixed>|null
	 */
	public function get( $plan_id );

	/**
	 * @param string               $plan_id
	 * @param list<string>         $from
	 * @param string               $to
	 * @param array<string, mixed> $fields
	 * @return bool
	 */
	public function transition( $plan_id, array $from, $to, array $fields );

	/**
	 * @param int $now
	 * @return list<string>
	 */
	public function list_expired_active( $now );

	/**
	 * @param int $now
	 * @return int
	 */
	public function count_active( $now );
}

final class Struo_Durable_Plan_Memory_Store implements Struo_Durable_Plan_Store {
	/** @var array<string, array<string, mixed>> */
	private $rows = [];

	public function insert( array $record ) {
		$id = (string) ( $record['id'] ?? '' );
		if ( '' === $id || isset( $this->rows[ $id ] ) ) {
			return false;
		}
		$this->rows[ $id ] = $record;
		return true;
	}

	public function get( $plan_id ) {
		$id = (string) $plan_id;
		if ( ! isset( $this->rows[ $id ] ) ) {
			return null;
		}
		return $this->rows[ $id ];
	}

	public function transition( $plan_id, array $from, $to, array $fields ) {
		$id = (string) $plan_id;
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}
		$state = (string) ( $this->rows[ $id ]['state'] ?? '' );
		if ( ! in_array( $state, $from, true ) ) {
			return false;
		}
		$this->rows[ $id ]['state'] = (string) $to;
		foreach ( $fields as $key => $value ) {
			$this->rows[ $id ][ (string) $key ] = $value;
		}
		return true;
	}

	public function list_expired_active( $now ) {
		$now = (int) $now;
		$ids = [];
		foreach ( $this->rows as $id => $row ) {
			$state = (string) ( $row['state'] ?? '' );
			if ( ! in_array( $state, [ 'planned', 'approved' ], true ) ) {
				continue;
			}
			if ( (int) ( $row['expires_at'] ?? 0 ) <= $now ) {
				$ids[] = (string) $id;
			}
		}
		return $ids;
	}

	public function count_active( $now ) {
		$now = (int) $now;
		$count = 0;
		foreach ( $this->rows as $row ) {
			$state = (string) ( $row['state'] ?? '' );
			if ( ! in_array( $state, [ 'planned', 'approved' ], true ) ) {
				continue;
			}
			if ( (int) ( $row['expires_at'] ?? 0 ) > $now ) {
				$count++;
			}
		}
		return $count;
	}
}

final class Struo_Durable_Plan_Wpdb_Store implements Struo_Durable_Plan_Store {
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'struo_plans';
	}

	private function ready() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}
		$table = $this->table();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $found === $table;
	}

	public function insert( array $record ) {
		global $wpdb;
		if ( ! $this->ready() ) {
			return false;
		}
		$id = (string) ( $record['id'] ?? '' );
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			return false;
		}
		$created_at = (int) ( $record['created_at'] ?? time() );
		$expires_at = (int) ( $record['expires_at'] ?? ( $created_at + 86400 ) );
		$post = is_array( $record['post'] ?? null ) ? $record['post'] : [ 'post_id' => (int) ( $record['post_id'] ?? 0 ) ];
		$payload = is_array( $record['payload'] ?? null ) ? $record['payload'] : [];
		$preview = is_array( $record['preview'] ?? null ) ? $record['preview'] : null;
		$payload_json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );
		$post_json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $post ) : json_encode( $post );
		$preview_json = null === $preview ? null : ( function_exists( 'wp_json_encode' ) ? wp_json_encode( $preview ) : json_encode( $preview ) );
		if ( false === $payload_json || false === $post_json ) {
			return false;
		}
		$inserted = $wpdb->insert(
			$this->table(),
			[
				'plan_id' => $id,
				'payload_type' => (string) ( $record['payload_type'] ?? 'mutation_v1' ),
				'state' => (string) ( $record['state'] ?? 'planned' ),
				'created_at' => gmdate( 'Y-m-d H:i:s', $created_at ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', $expires_at ),
				'user_id' => (int) ( $record['user_id'] ?? 0 ),
				'post_id' => (int) ( $record['post_id'] ?? ( $post['post_id'] ?? 0 ) ),
				'origin' => (string) ( $record['origin'] ?? 'mcp' ),
				'operation' => (string) ( $record['operation'] ?? '' ),
				'endpoint' => (string) ( $record['endpoint'] ?? '' ),
				'request_text' => (string) ( $record['request'] ?? '' ),
				'payload_json' => $payload_json,
				'preview_json' => $preview_json,
				'post_json' => $post_json,
				'base_content_hash' => (string) ( $record['base_content_hash'] ?? '' ),
				'base_content' => (string) ( $record['base_content'] ?? '' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
		return false !== $inserted;
	}

	public function get( $plan_id ) {
		global $wpdb;
		if ( ! $this->ready() ) {
			return null;
		}
		$id = (string) $plan_id;
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE plan_id = %s', $id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return Struo_Durable_Plans::record_from_row( $row );
	}

	public function transition( $plan_id, array $from, $to, array $fields ) {
		global $wpdb;
		if ( ! $this->ready() ) {
			return false;
		}
		$id = (string) $plan_id;
		$to = (string) $to;
		$from = array_values( array_filter( array_map( 'strval', $from ) ) );
		if ( '' === $id || '' === $to || empty( $from ) ) {
			return false;
		}
		$data = [ 'state' => $to ];
		foreach ( [ 'approved_at', 'approved_by', 'applying_at', 'applied_at', 'applied_by', 'failure_code', 'failure_message', 'apply_receipt_json', 'expected_content_hash' ] as $field ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			$value = $fields[ $field ];
			if ( in_array( $field, [ 'approved_by', 'applied_by' ], true ) ) {
				$data[ $field ] = (int) $value;
				continue;
			}
			$data[ $field ] = (string) $value;
		}
		$set_parts = [];
		$query_params = [];
		foreach ( $data as $column => $value ) {
			$set_parts[] = "{$column} = " . ( in_array( $column, [ 'approved_by', 'applied_by' ], true ) ? '%d' : '%s' );
			$query_params[] = $value;
		}
		$query_params[] = $id;
		$query_params = array_merge( $query_params, $from );
		$placeholders = implode( ', ', array_fill( 0, count( $from ), '%s' ) );
		$sql = $wpdb->prepare(
			'UPDATE ' . $this->table() . ' SET ' . implode( ', ', $set_parts ) . " WHERE plan_id = %s AND state IN ({$placeholders})",
			$query_params
		);
		$updated = $wpdb->query( $sql );
		return 1 === (int) $updated;
	}

	public function list_expired_active( $now ) {
		global $wpdb;
		if ( ! $this->ready() ) {
			return [];
		}
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT plan_id FROM ' . $this->table() . " WHERE state IN ('planned', 'approved') AND expires_at <= %s",
				gmdate( 'Y-m-d H:i:s', (int) $now )
			)
		);
		return is_array( $ids ) ? array_values( array_map( 'strval', $ids ) ) : [];
	}

	public function count_active( $now ) {
		global $wpdb;
		if ( ! $this->ready() ) {
			return 0;
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->table() . " WHERE state IN ('planned', 'approved') AND expires_at > %s",
				gmdate( 'Y-m-d H:i:s', (int) $now )
			)
		);
	}
}

final class Struo_Durable_Plans {
	public const ACTIVE_CAP = 25;
	public const TTL = 86400;
	public const TERMINAL = [ 'cancelled', 'expired', 'failed', 'applied' ];
	public const ACTIVE = [ 'planned', 'approved' ];

	/** @var array<string, array{from: list<string>, to: string}> */
	private const MOVES = [
		'approve' => [ 'from' => [ 'planned' ], 'to' => 'approved' ],
		'claim_apply' => [ 'from' => [ 'approved' ], 'to' => 'applying' ],
		'finish' => [ 'from' => [ 'applying', 'approved' ], 'to' => 'applied' ],
		'fail' => [ 'from' => [ 'applying' ], 'to' => 'failed' ],
		'cancel' => [ 'from' => [ 'planned', 'approved' ], 'to' => 'cancelled' ],
		'expire' => [ 'from' => [ 'planned', 'approved' ], 'to' => 'expired' ],
		'release_claim' => [ 'from' => [ 'applying' ], 'to' => 'approved' ],
	];

	/** @var Struo_Durable_Plan_Store|null */
	private static $store = null;

	public static function use_store( Struo_Durable_Plan_Store $store ) {
		self::$store = $store;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function record_from_row( array $row ) {
		$post = json_decode( (string) ( $row['post_json'] ?? '' ), true );
		$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
		$preview = json_decode( (string) ( $row['preview_json'] ?? '' ), true );
		$created = strtotime( (string) ( $row['created_at'] ?? '' ) . ' UTC' );
		$expires = strtotime( (string) ( $row['expires_at'] ?? '' ) . ' UTC' );

		return [
			'id' => (string) ( $row['plan_id'] ?? $row['id'] ?? '' ),
			'payload_type' => (string) ( $row['payload_type'] ?? 'mutation_v1' ),
			'state' => (string) ( $row['state'] ?? 'planned' ),
			'created_at' => is_int( $created ) ? $created : 0,
			'expires_at' => is_int( $expires ) ? $expires : 0,
			'user_id' => (int) ( $row['user_id'] ?? 0 ),
			'origin' => (string) ( $row['origin'] ?? 'mcp' ),
			'post_id' => (int) ( $row['post_id'] ?? 0 ),
			'post' => is_array( $post ) ? $post : [ 'post_id' => (int) ( $row['post_id'] ?? 0 ) ],
			'operation' => (string) ( $row['operation'] ?? '' ),
			'endpoint' => (string) ( $row['endpoint'] ?? '' ),
			'request' => (string) ( $row['request_text'] ?? $row['request'] ?? '' ),
			'payload' => is_array( $payload ) ? $payload : [],
			'preview' => is_array( $preview ) ? $preview : null,
			'base_content_hash' => (string) ( $row['base_content_hash'] ?? '' ),
			'base_content' => (string) ( $row['base_content'] ?? '' ),
			'expected_content_hash' => (string) ( $row['expected_content_hash'] ?? '' ),
			'approved_at' => (string) ( $row['approved_at'] ?? '' ),
			'approved_by' => (int) ( $row['approved_by'] ?? 0 ),
			'applying_at' => (string) ( $row['applying_at'] ?? '' ),
			'applied_at' => (string) ( $row['applied_at'] ?? '' ),
			'applied_by' => (int) ( $row['applied_by'] ?? 0 ),
			'failure_code' => (string) ( $row['failure_code'] ?? '' ),
			'failure_message' => (string) ( $row['failure_message'] ?? '' ),
			'apply_receipt_json' => (string) ( $row['apply_receipt_json'] ?? '' ),
		];
	}

	/**
	 * @param array<string, mixed> $record
	 * @param array<string, mixed> $options
	 * @return string|false|\WP_Error
	 */
	public static function create( array $record, array $options = [] ) {
		$store = self::store();
		if ( ! $store ) {
			return self::err( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', 500 );
		}
		if ( empty( $options['skip_capacity'] ) ) {
			$capacity = self::capacity_needed( 1 );
			if ( true !== $capacity ) {
				return $capacity;
			}
		}
		$record = self::sanitize_record( $record, true );
		if ( null === $record ) {
			return false;
		}
		if ( ! $store->insert( $record ) ) {
			return false;
		}
		return (string) $record['id'];
	}

	/**
	 * @param string $plan_id
	 * @return array<string, mixed>|null
	 */
	public static function load( $plan_id ) {
		$row = self::raw( $plan_id );
		if ( null === $row ) {
			return null;
		}
		$state = (string) ( $row['state'] ?? '' );
		if ( in_array( $state, self::TERMINAL, true ) ) {
			return null;
		}
		if ( in_array( $state, self::ACTIVE, true ) && (int) ( $row['expires_at'] ?? 0 ) <= time() ) {
			self::expire( $plan_id );
			return null;
		}
		return $row;
	}

	/**
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	public static function approve( $plan_id, array $fields = [] ) {
		return self::move( 'approve', $plan_id, $fields );
	}

	/**
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	public static function claim_apply( $plan_id, array $fields = [] ) {
		return self::move( 'claim_apply', $plan_id, $fields );
	}

	/**
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	public static function finish( $plan_id, array $fields = [] ) {
		return self::move( 'finish', $plan_id, $fields );
	}

	/**
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	public static function fail( $plan_id, array $fields = [] ) {
		return self::move( 'fail', $plan_id, $fields );
	}

	/**
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	public static function cancel( $plan_id, array $fields = [] ) {
		return self::move( 'cancel', $plan_id, $fields );
	}

	/**
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	public static function expire( $plan_id, array $fields = [] ) {
		return self::move( 'expire', $plan_id, $fields );
	}

	/**
	 * applying → approved (crash recovery).
	 *
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	public static function release_claim( $plan_id, array $fields = [] ) {
		return self::move( 'release_claim', $plan_id, $fields );
	}

	/**
	 * @param list<string> $keep_ids
	 */
	public static function prune( array $keep_ids = [] ) {
		unset( $keep_ids );
		$store = self::store();
		if ( ! $store ) {
			return;
		}
		foreach ( $store->list_expired_active( time() ) as $id ) {
			self::expire( $id );
		}
	}

	/**
	 * @param int $n
	 * @return true|false|\WP_Error
	 */
	public static function capacity_needed( $n ) {
		$n = (int) $n;
		if ( $n < 1 ) {
			return true;
		}
		self::prune();
		$store = self::store();
		if ( ! $store ) {
			return self::err( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', 500 );
		}
		$active = $store->count_active( time() );
		if ( ( $active + $n ) > self::ACTIVE_CAP ) {
			return self::err(
				'sae_plan_capacity',
				'Plan storage is at capacity. Finish or dismiss existing plans before creating more.',
				429
			);
		}
		return true;
	}

	/**
	 * @param string $plan_id
	 * @return list<string>
	 */
	public static function family_ids( $plan_id ) {
		$plan_id = (string) $plan_id;
		$row = self::raw( $plan_id );
		if ( null === $row ) {
			return [ $plan_id ];
		}
		$payload = is_array( $row['payload'] ?? null ) ? $row['payload'] : [];
		if ( 'bundle_v1' === (string) ( $row['payload_type'] ?? '' ) ) {
			$family = [ $plan_id ];
			$child_ids = is_array( $payload['child_ids'] ?? null ) ? $payload['child_ids'] : [];
			foreach ( $child_ids as $child_id ) {
				$child_id = (string) $child_id;
				if ( 1 === preg_match( '/^[a-f0-9]{16}$/', $child_id ) && ! in_array( $child_id, $family, true ) ) {
					$family[] = $child_id;
				}
			}
			return $family;
		}
		$bundle_id = (string) ( $payload['bundle_id'] ?? '' );
		if ( 1 === preg_match( '/^[a-f0-9]{16}$/', $bundle_id ) ) {
			$parent = self::raw( $bundle_id );
			if ( is_array( $parent ) ) {
				return self::family_ids( $bundle_id );
			}
			// Crash before parent insert: cancel this child, not a missing parent id.
			return [ $plan_id ];
		}
		return [ $plan_id ];
	}

	/**
	 * @param list<string> $ids
	 */
	public static function cancel_ids( array $ids ) {
		foreach ( $ids as $id ) {
			$id = (string) $id;
			if ( 1 === preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
				self::cancel( $id );
			}
		}
	}

	/**
	 * @return Struo_Durable_Plan_Store|null
	 */
	private static function store() {
		return self::$store instanceof Struo_Durable_Plan_Store ? self::$store : null;
	}

	/**
	 * @param string $plan_id
	 * @return array<string, mixed>|null
	 */
	private static function raw( $plan_id ) {
		$store = self::store();
		if ( ! $store ) {
			return null;
		}
		$row = $store->get( (string) $plan_id );
		if ( ! is_array( $row ) ) {
			return null;
		}
		return self::sanitize_record( $row, false );
	}

	/**
	 * @param string               $intent
	 * @param string               $plan_id
	 * @param array<string, mixed> $fields
	 * @return true|false|\WP_Error
	 */
	private static function move( $intent, $plan_id, array $fields ) {
		$store = self::store();
		if ( ! $store ) {
			return self::err( 'sae_plan_storage_unavailable', 'Plan storage is unavailable.', 500 );
		}
		$spec = self::MOVES[ $intent ] ?? null;
		if ( ! is_array( $spec ) ) {
			return self::err( 'sae_plan_invalid_transition', 'Invalid plan state transition.', 400 );
		}
		$id = (string) $plan_id;
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			return self::err( 'sae_plan_invalid_transition', 'Invalid plan state transition.', 400 );
		}
		if ( ! $store->transition( $id, $spec['from'], $spec['to'], $fields ) ) {
			return self::err( 'sae_plan_state_conflict', 'Plan state changed concurrently or is no longer valid.', 409 );
		}
		return true;
	}

	/**
	 * @param string $code
	 * @param string $message
	 * @param int    $status
	 * @return false|\WP_Error
	 */
	private static function err( $code, $message, $status ) {
		if ( class_exists( 'WP_Error' ) ) {
			return new WP_Error( $code, $message, [ 'status' => $status ] );
		}
		return false;
	}

	/**
	 * @param mixed $record
	 * @param bool  $creating
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_record( $record, $creating ) {
		if ( ! is_array( $record ) ) {
			return null;
		}
		$id = (string) ( $record['id'] ?? '' );
		if ( $creating && '' === $id ) {
			$id = bin2hex( random_bytes( 8 ) );
		}
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $id ) ) {
			return null;
		}
		$created_at = isset( $record['created_at'] ) ? (int) $record['created_at'] : time();
		$expires_at = isset( $record['expires_at'] ) ? (int) $record['expires_at'] : ( $created_at + self::TTL );
		$state = (string) ( $record['state'] ?? 'planned' );
		if ( '' === $state ) {
			$state = 'planned';
		}

		return [
			'id' => $id,
			'payload_type' => (string) ( $record['payload_type'] ?? 'mutation_v1' ),
			'state' => $state,
			'created_at' => $created_at,
			'expires_at' => $expires_at,
			'user_id' => (int) ( $record['user_id'] ?? 0 ),
			'origin' => (string) ( $record['origin'] ?? 'mcp' ),
			'post_id' => (int) ( $record['post_id'] ?? 0 ),
			'post' => is_array( $record['post'] ?? null ) ? $record['post'] : [ 'post_id' => (int) ( $record['post_id'] ?? 0 ) ],
			'operation' => (string) ( $record['operation'] ?? '' ),
			'endpoint' => (string) ( $record['endpoint'] ?? '' ),
			'request' => (string) ( $record['request'] ?? '' ),
			'payload' => is_array( $record['payload'] ?? null ) ? $record['payload'] : [],
			'preview' => is_array( $record['preview'] ?? null ) ? $record['preview'] : null,
			'base_content_hash' => (string) ( $record['base_content_hash'] ?? '' ),
			'base_content' => (string) ( $record['base_content'] ?? '' ),
			'expected_content_hash' => (string) ( $record['expected_content_hash'] ?? '' ),
			'approved_at' => (string) ( $record['approved_at'] ?? '' ),
			'approved_by' => (int) ( $record['approved_by'] ?? 0 ),
			'applying_at' => (string) ( $record['applying_at'] ?? '' ),
			'applied_at' => (string) ( $record['applied_at'] ?? '' ),
			'applied_by' => (int) ( $record['applied_by'] ?? 0 ),
			'failure_code' => (string) ( $record['failure_code'] ?? '' ),
			'failure_message' => (string) ( $record['failure_message'] ?? '' ),
			'apply_receipt_json' => (string) ( $record['apply_receipt_json'] ?? '' ),
		];
	}
}
