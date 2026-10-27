<?php
/**
 * One authority seam for Struo operations.
 *
 * REST, Abilities, and MCP/dispatch call authorize(). Handlers, provider
 * calls, and REST registration stay elsewhere.
 *
 * @package Struo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Struo_Authority {

	/**
	 * @param string $operation_id
	 * @param array  $targets {
	 *     @type int             $post_id
	 *     @type string          $plan_id
	 *     @type string          $post_type
	 *     @type WP_REST_Request $request
	 * }
	 * @param string $door rest|ability|mcp
	 * @return true|WP_Error
	 */
	public static function authorize( $operation_id, array $targets = [], $door = 'rest' ) {
		$operation_id = sanitize_key( (string) $operation_id );
		$door = sanitize_key( (string) $door );
		if ( ! in_array( $door, [ 'rest', 'ability', 'mcp' ], true ) ) {
			$door = 'rest';
		}

		$row = Struo_Operation_Catalog::get( $operation_id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'sae_unknown_operation', 'Unknown Struo operation.', [ 'status' => 404 ] );
		}

		if ( ! empty( $row['apply_allowed'] ) && 'mcp' === $door ) {
			return new WP_Error( 'sae_insufficient_permissions', 'Insufficient permissions for this tool.', [ 'status' => 403 ] );
		}

		$cap = sanitize_key( (string) ( $row['cap'] ?? '' ) );
		if ( '' !== $cap && ! current_user_can( $cap ) ) {
			return new WP_Error(
				'sae_insufficient_permissions',
				self::cap_denied_message( $cap ),
				[ 'status' => 403 ]
			);
		}

		$object = sanitize_key( (string) ( $row['object'] ?? 'none' ) );
		switch ( $object ) {
			case 'none':
				return true;
			case 'disclose_post':
			case 'promote_post':
				return Struo_Block_Editor::object_disclose_post( self::target_post_id( $targets ) );
			case 'write_post':
				return Struo_Block_Editor::object_write_post( self::target_post_id( $targets ) );
			case 'review_plan':
			case 'apply_plan':
				$request = self::target_request( $targets );
				if ( ! $request ) {
					return new WP_Error( 'sae_agent_plan_missing', 'Agent plan not found.', [ 'status' => 404 ] );
				}
				return Struo_Block_Editor::object_review_plan( $request );
			case 'create_post_type':
				$post_type = sanitize_key( (string) ( $targets['post_type'] ?? '' ) );
				if ( '' === $post_type ) {
					return true;
				}
				return Struo_Block_Editor::object_create_post_type( $post_type );
			default:
				return new WP_Error( 'sae_unknown_operation', 'Unknown Struo operation.', [ 'status' => 404 ] );
		}
	}

	private static function cap_denied_message( $cap ) {
		switch ( $cap ) {
			case 'struo_plan':
				return 'Insufficient permissions to plan.';
			case 'struo_approve':
				return 'Insufficient permissions to approve plans.';
			case 'struo_apply':
				return 'Insufficient permissions to apply plans.';
			case 'struo_manage_registry':
				return 'Insufficient permissions to manage templates.';
			case 'struo_manage_settings':
				return 'Insufficient permissions to manage settings.';
			default:
				return 'Insufficient permissions.';
		}
	}

	private static function target_post_id( array $targets ) {
		return absint( $targets['post_id'] ?? 0 );
	}

	/**
	 * @return WP_REST_Request|null
	 */
	private static function target_request( array $targets ) {
		$request = $targets['request'] ?? null;
		return $request instanceof WP_REST_Request ? $request : null;
	}
}
