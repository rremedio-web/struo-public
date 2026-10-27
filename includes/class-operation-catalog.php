<?php
/**
 * Published Operation Catalog for Struo.
 *
 * One table of operations: REST route, ability name, MCP visibility,
 * required Struo cap, object rule, and whether Apply is allowed.
 * Abilities `mcp.public` is read from this table. Apply is never MCP-public.
 *
 * @package Struo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Struo_Operation_Catalog {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions() {
		$rows = [
			'get-block-catalog' => self::row( '/block-catalog', 'GET', 'struo/get-block-catalog', true, 'struo_plan', 'none', false ),
			'get-post-blocks' => self::row( '/posts/(?P<id>\\d+)/blocks', 'GET', 'struo/get-post-blocks', true, '', 'disclose_post', false ),
			'plan-block-change' => self::row( '/plan', 'POST', 'struo/plan-block-change', true, 'struo_plan', 'none', false ),
			'plan-block-change-stream' => self::row( '/plan/stream', 'POST', null, false, 'struo_plan', 'none', false ),
			'preview-insert' => self::row( '/posts/(?P<id>\\d+)/blocks/insert', 'POST', 'struo/preview-insert', false, '', 'write_post', false ),
			'preview-update' => self::row( '/posts/(?P<id>\\d+)/blocks/update', 'POST', 'struo/preview-update', false, '', 'write_post', false ),
			'preview-remove' => self::row( '/posts/(?P<id>\\d+)/blocks/remove', 'POST', 'struo/preview-remove', false, '', 'write_post', false ),
			'preview-batch' => self::row( '/posts/(?P<id>\\d+)/blocks/batch', 'POST', 'struo/preview-batch', false, '', 'write_post', false ),
			'update-post-field' => self::row( '/posts/(?P<id>\\d+)/fields/update', 'POST', null, false, '', 'write_post', false ),
			'plan-create-page' => self::row( '/pages/plan-create', 'POST', null, false, 'struo_plan', 'create_post_type', false ),
			'apply-create-page' => self::row( '/pages/create', 'POST', null, false, 'struo_apply', 'create_post_type', true ),
			'console-status' => self::row( '/console/status', 'GET', null, false, 'struo_plan', 'none', false ),
			'console-audit' => self::row( '/console/audit', 'GET', null, false, 'struo_manage_settings', 'none', false ),
			'console-audit-export' => self::row( '/console/audit/export', 'GET', null, false, 'struo_manage_settings', 'none', false ),
			'console-kill-switch' => self::row( '/console/kill-switch', 'POST', null, false, 'struo_manage_settings', 'none', false ),
			'list-templates' => self::row( '/templates', 'GET', null, false, 'struo_manage_registry', 'none', false ),
			'save-template' => self::row( '/templates', 'POST', null, false, 'struo_manage_registry', 'none', false ),
			'promote-page' => self::row( '/templates/promote-page', 'POST', null, false, 'struo_manage_registry', 'promote_post', false ),
			'list-patterns' => self::row( '/patterns', 'GET', null, false, 'struo_manage_registry', 'none', false ),
			'save-pattern' => self::row( '/patterns', 'POST', null, false, 'struo_manage_registry', 'none', false ),
			'promote-section' => self::row( '/patterns/promote-section', 'POST', null, false, 'struo_manage_registry', 'promote_post', false ),
			'save-created-template' => self::row( '/templates/save-created', 'POST', null, false, 'struo_manage_registry', 'none', false ),
			'list-agent-plans' => self::row( '/console/agent-plans', 'GET', null, false, 'struo_plan', 'none', false ),
			'get-agent-plan' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})', 'GET', null, false, 'struo_approve', 'review_plan', false ),
			'preview-agent-plan' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})/preview', 'POST', null, false, 'struo_approve', 'review_plan', false ),
			'dismiss-agent-plan' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})/dismiss', 'POST', null, false, 'struo_approve', 'review_plan', false ),
			'approve-agent-plan' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})/approve', 'POST', null, false, 'struo_approve', 'review_plan', false ),
			'select-bundle' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})/select', 'POST', null, false, 'struo_approve', 'review_plan', false ),
			'apply-agent-plan' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})/apply', 'POST', 'struo/apply-agent-plan', false, 'struo_apply', 'apply_plan', true ),
			'rollback-agent-plan' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})/rollback', 'POST', null, false, 'struo_plan', 'write_post', false ),
			'apply-bundle-remaining' => self::row( '/console/agent-plans/(?P<id>[a-f0-9]{16})/apply-remaining', 'POST', null, false, 'struo_apply', 'apply_plan', true ),
			'list-findings' => self::row( '/console/findings', 'GET', null, false, 'struo_plan', 'none', false ),
			'get-finding' => self::row( '/console/findings/(?P<id>[a-f0-9]{16})', 'GET', null, false, 'struo_plan', 'none', false ),
		];

		return $rows;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get( $operation_id ) {
		$operation_id = sanitize_key( (string) $operation_id );
		$definitions = self::definitions();
		return isset( $definitions[ $operation_id ] ) && is_array( $definitions[ $operation_id ] )
			? $definitions[ $operation_id ]
			: null;
	}

	/**
	 * @return string|null
	 */
	public static function id_for_ability( $ability_name ) {
		$ability_name = (string) $ability_name;
		if ( '' === $ability_name ) {
			return null;
		}
		foreach ( self::definitions() as $operation_id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( $ability_name === (string) ( $row['ability'] ?? '' ) ) {
				return (string) $operation_id;
			}
		}

		return null;
	}

	public static function mcp_public_for_ability( $ability_name ) {
		$id = self::id_for_ability( $ability_name );
		if ( null === $id ) {
			return false;
		}
		$row = self::get( $id );

		return is_array( $row ) && ! empty( $row['mcp_public'] );
	}

	/**
	 * @param string      $route
	 * @param string      $methods
	 * @param string|null $ability
	 * @param bool        $mcp_public
	 * @param string      $cap
	 * @param string      $object
	 * @param bool        $apply_allowed
	 * @return array<string, mixed>
	 */
	private static function row( $route, $methods, $ability, $mcp_public, $cap, $object, $apply_allowed ) {
		return [
			'rest' => [
				'route' => (string) $route,
				'methods' => (string) $methods,
			],
			'ability' => is_string( $ability ) && '' !== $ability ? $ability : null,
			'mcp_public' => (bool) $mcp_public,
			'cap' => sanitize_key( (string) $cap ),
			'object' => sanitize_key( (string) $object ),
			'apply_allowed' => (bool) $apply_allowed,
		];
	}
}
