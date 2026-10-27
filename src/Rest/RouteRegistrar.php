<?php
/**
 * REST route table for struo/v1.
 *
 * @package Struo
 */

namespace Struo\Rest;

use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RouteRegistrar {

	/**
	 * @param string $rest_namespace REST namespace, normally struo/v1.
	 */
	public static function register( $rest_namespace ) {
		$namespace = (string) $rest_namespace;
		register_rest_route(
			$namespace,
			'/block-catalog',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_read_catalog' ],
				'callback' => [ 'Struo_Block_Editor', 'get_block_catalog' ],
			]
		);

		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/blocks',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_read_post' ],
				'callback' => [ 'Struo_Block_Editor', 'get_post_blocks' ],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/blocks/insert',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_write_post' ],
				'callback' => [ 'Struo_Block_Editor', 'insert_block' ],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/blocks/update',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_write_post' ],
				'callback' => [ 'Struo_Block_Editor', 'update_block' ],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/blocks/remove',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_write_post' ],
				'callback' => [ 'Struo_Block_Editor', 'remove_block' ],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/blocks/batch',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_write_post' ],
				'callback' => [ 'Struo_Block_Editor', 'batch_blocks' ],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
					'bundle_name' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/fields/update',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_write_post' ],
				'callback' => [ 'Struo_Block_Editor', 'update_post_field' ],
				'args' => [
					'id' => [
						'type' => 'integer',
						'required' => true,
					],
					'field' => [
						'type' => 'string',
						'required' => true,
					],
					'value' => [
						'type' => 'string',
						'required' => true,
					],
					'dry_run' => [
						'type' => 'boolean',
						'required' => false,
						'default' => true,
					],
					'confirmation_token' => [
						'type' => 'string',
						'required' => false,
					],
					'idempotency_key' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/pages/plan-create',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_plan_create_page' ],
				'callback' => [ 'Struo_Block_Editor', 'plan_create_page' ],
				'args' => [
					'title' => [
						'type' => 'string',
						'required' => true,
					],
					'template' => [
						'type' => 'string',
						'required' => false,
					],
					'post_type' => [
						'type' => 'string',
						'required' => false,
					],
					'description' => [
						'type' => 'string',
						'required' => false,
					],
					'outline' => [
						'type' => 'array',
						'required' => false,
					],
					'selected_sections' => [
						'type' => 'array',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/pages/create',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_apply_create_page' ],
				'callback' => [ 'Struo_Block_Editor', 'apply_create_page' ],
				'args' => [
					'idempotency_key' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/templates',
			[
				[
					'methods' => WP_REST_Server::READABLE,
					'permission_callback' => [ 'Struo_Block_Editor', 'can_manage_template_registry' ],
					'callback' => [ 'Struo_Block_Editor', 'list_template_records' ],
					'args' => [
						'post_type' => [
							'type' => 'string',
							'required' => false,
						],
						'status' => [
							'type' => 'string',
							'required' => false,
						],
						'source_type' => [
							'type' => 'string',
							'required' => false,
						],
						'include_hidden' => [
							'type' => 'boolean',
							'required' => false,
						],
					],
				],
				[
					'methods' => WP_REST_Server::CREATABLE,
					'permission_callback' => [ 'Struo_Block_Editor', 'can_manage_template_registry' ],
					'callback' => [ 'Struo_Block_Editor', 'save_template_record' ],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/templates/promote-page',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_promote_page_template' ],
				'callback' => [ 'Struo_Block_Editor', 'promote_page_template_preview' ],
				'args' => [
					'post_id' => [
						'type' => 'integer',
						'required' => true,
					],
					'label' => [
						'type' => 'string',
						'required' => false,
					],
					'description' => [
						'type' => 'string',
						'required' => false,
					],
					'metadata' => [
						'type' => 'object',
						'required' => false,
					],
					'status' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/patterns',
			[
				[
					'methods' => WP_REST_Server::READABLE,
					'permission_callback' => [ 'Struo_Block_Editor', 'can_manage_template_registry' ],
					'callback' => [ 'Struo_Block_Editor', 'list_pattern_records' ],
					'args' => [
						'post_type' => [
							'type' => 'string',
							'required' => false,
						],
						'status' => [
							'type' => 'string',
							'required' => false,
						],
						'source_type' => [
							'type' => 'string',
							'required' => false,
						],
						'block_name' => [
							'type' => 'string',
							'required' => false,
						],
						'include_hidden' => [
							'type' => 'boolean',
							'required' => false,
						],
					],
				],
				[
					'methods' => WP_REST_Server::CREATABLE,
					'permission_callback' => [ 'Struo_Block_Editor', 'can_manage_template_registry' ],
					'callback' => [ 'Struo_Block_Editor', 'save_pattern_record' ],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/patterns/promote-section',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_promote_pattern_section' ],
				'callback' => [ 'Struo_Block_Editor', 'promote_section_pattern_preview' ],
				'args' => [
					'post_id' => [
						'type' => 'integer',
						'required' => true,
					],
					'block_index' => [
						'type' => 'integer',
						'required' => true,
					],
					'label' => [
						'type' => 'string',
						'required' => false,
					],
					'description' => [
						'type' => 'string',
						'required' => false,
					],
					'metadata' => [
						'type' => 'object',
						'required' => false,
					],
					'status' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/templates/save-created',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_save_created_template' ],
				'callback' => [ 'Struo_Block_Editor', 'save_created_template' ],
				'args' => [
					'idempotency_key' => [
						'type' => 'string',
						'required' => true,
					],
					'label' => [
						'type' => 'string',
						'required' => true,
					],
					'description' => [
						'type' => 'string',
						'required' => false,
					],
					'based_on_post_id' => [
						'type' => 'integer',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/plan',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_plan' ],
				'callback' => [ 'Struo_Block_Editor', 'plan_block_change' ],
			]
		);

		register_rest_route(
			$namespace,
			'/plan/stream',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_plan' ],
				'callback' => [ 'Struo_Block_Editor', 'plan_block_change_stream' ],
			]
		);

		register_rest_route(
			$namespace,
			'/console/status',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_read_console_status' ],
				'callback' => [ 'Struo_Block_Editor', 'get_console_status' ],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})/preview',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_approve' ],
				'callback' => [ 'Struo_Block_Editor', 'preview_agent_plan' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})/dismiss',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_approve' ],
				'callback' => [ 'Struo_Block_Editor', 'dismiss_agent_plan' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})/approve',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_approve' ],
				'callback' => [ 'Struo_Block_Editor', 'approve_agent_plan' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})/select',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_approve' ],
				'callback' => [ 'Struo_Block_Editor', 'select_bundle_children' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})/apply-remaining',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_apply' ],
				'callback' => [ 'Struo_Block_Editor', 'apply_bundle_remaining' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})/apply',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_apply' ],
				'callback' => [ 'Struo_Block_Editor', 'apply_agent_plan' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})/rollback',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_rollback_agent_plan' ],
				'callback' => [ 'Struo_Block_Editor', 'rollback_agent_plan' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans/(?P<id>[a-f0-9]{16})',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_approve' ],
				'callback' => [ 'Struo_Block_Editor', 'get_agent_plan' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/agent-plans',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_struo_plan' ],
				'callback' => [ 'Struo_Block_Editor', 'list_agent_plans' ],
			]
		);

		register_rest_route(
			$namespace,
			'/console/findings/(?P<id>[a-f0-9]{16})',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_get_finding' ],
				'callback' => [ 'Struo_Findings', 'rest_get' ],
				'args' => [
					'id' => [
						'type' => 'string',
						'required' => true,
						'validate_callback' => [ 'Struo_Block_Editor', 'validate_agent_plan_id' ],
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/findings',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_list_findings' ],
				'callback' => [ 'Struo_Findings', 'rest_list' ],
			]
		);

		register_rest_route(
			$namespace,
			'/console/audit',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_read_console_audit' ],
				'callback' => [ 'Struo_Block_Editor', 'get_console_audit' ],
				'args' => [
					'page' => [
						'type' => 'integer',
						'default' => 1,
					],
					'per_page' => [
						'type' => 'integer',
						'default' => 25,
					],
					'action' => [
						'type' => 'string',
						'required' => false,
					],
					'post_id' => [
						'type' => 'integer',
						'required' => false,
					],
					'since' => [
						'type' => 'string',
						'required' => false,
					],
					'exclude_actions' => [
						'type' => 'string',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/audit/export',
			[
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_read_console_audit' ],
				'callback' => [ 'Struo_Block_Editor', 'get_console_audit_export' ],
				'args' => [
					'action' => [
						'type' => 'string',
						'required' => false,
					],
					'post_id' => [
						'type' => 'integer',
						'required' => false,
					],
					'since' => [
						'type' => 'string',
						'required' => false,
					],
					'exclude_actions' => [
						'type' => 'string',
						'required' => false,
					],
					'format' => [
						'type' => 'string',
						'required' => false,
					],
					'max_rows' => [
						'type' => 'integer',
						'required' => false,
					],
				],
			]
		);

		register_rest_route(
			$namespace,
			'/console/kill-switch',
			[
				'methods' => WP_REST_Server::CREATABLE,
				'permission_callback' => [ 'Struo_Block_Editor', 'can_manage_console_settings' ],
				'callback' => [ 'Struo_Block_Editor', 'set_console_kill_switch' ],
			]
		);
	}
}
