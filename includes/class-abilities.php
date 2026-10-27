<?php
/**
 * WordPress 6.9 Abilities registration for Struo.
 *
 * These abilities wrap the existing REST handlers through
 * Struo_Block_Editor::dispatch_internal() for reads, plan, and dry-run
 * previews. Reads and plan may be MCP-public. Previews are dry-run only
 * and not MCP-public. Apply is a separate private ability that calls
 * apply_agent_plan() directly — dispatch_internal always forces dry_run
 * and cannot persist.
 *
 * @package Struo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Struo_Abilities {

	const CATEGORY = 'struo';

	const ABILITY_GET_BLOCK_CATALOG = 'struo/get-block-catalog';
	const ABILITY_GET_POST_BLOCKS = 'struo/get-post-blocks';
	const ABILITY_PLAN_BLOCK_CHANGE = 'struo/plan-block-change';
	const ABILITY_PREVIEW_INSERT = 'struo/preview-insert';
	const ABILITY_PREVIEW_UPDATE = 'struo/preview-update';
	const ABILITY_PREVIEW_REMOVE = 'struo/preview-remove';
	const ABILITY_PREVIEW_BATCH = 'struo/preview-batch';
	const ABILITY_APPLY_AGENT_PLAN = 'struo/apply-agent-plan';

	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label' => __( 'Struo', 'struo' ),
				'description' => __( 'Allowlisted block catalog, inspection, planning, dry-run previews, and private durable-plan apply. MCP-public surfaces never persist.', 'struo' ),
			]
		);
	}

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( self::definitions() as $name => $args ) {
			wp_register_ability( $name, $args );
		}
	}

	/**
	 * Ability catalog. Kept as a single table so smoke can assert
	 * mcp.public / ai_editor policy without executing WordPress.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions() {
		$definitions = [
			self::ABILITY_GET_BLOCK_CATALOG => [
				'label' => __( 'Get block catalog', 'struo' ),
				'description' => __( 'List allowlisted blocks and their field schemas. Read-only.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [],
				],
				'execute_callback' => [ __CLASS__, 'execute_get_block_catalog' ],
				'permission_callback' => [ __CLASS__, 'can_read_catalog' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => true,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => true,
						'destructive' => false,
						'idempotent' => true,
					],
					'ai_editor' => [
						'action_name' => 'get_block_catalog',
						'description' => __( 'Read the allowlisted Struo block catalog and field schemas.', 'struo' ),
					],
				],
			],
			self::ABILITY_GET_POST_BLOCKS => [
				'label' => __( 'Get post blocks', 'struo' ),
				'description' => __( 'List the parsed block tree for an allowlisted post. Read-only.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'post_id' => [
							'type' => 'integer',
							'description' => __( 'Allowlisted post ID.', 'struo' ),
						],
					],
					'required' => [ 'post_id' ],
				],
				'execute_callback' => [ __CLASS__, 'execute_get_post_blocks' ],
				'permission_callback' => [ __CLASS__, 'can_read_post' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => true,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => true,
						'destructive' => false,
						'idempotent' => true,
					],
					'ai_editor' => [
						'action_name' => 'get_post_blocks',
						'description' => __( 'Read the current allowlisted block tree for a post.', 'struo' ),
					],
				],
			],
			self::ABILITY_PLAN_BLOCK_CHANGE => [
				'label' => __( 'Plan block change', 'struo' ),
				'description' => __( 'Plan an allowlisted block change from request text or a structured plan. MCP and Abilities origins never receive a redeemable confirmation token; apply from the console or the private apply ability after approve.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'request' => [ 'type' => 'string' ],
						'plan' => [ 'type' => 'object' ],
						'dry_run_preview' => [ 'type' => 'boolean' ],
						'prefer_ai' => [ 'type' => 'boolean' ],
						'force_ai' => [ 'type' => 'boolean' ],
						'compact' => [ 'type' => 'boolean' ],
						'verbose' => [ 'type' => 'boolean' ],
						'post_ids' => [
							'type' => 'array',
							'items' => [ 'type' => 'integer' ],
							'description' => __( 'Allowlisted post IDs for a multi-page bundle.', 'struo' ),
						],
					],
				],
				'execute_callback' => [ __CLASS__, 'execute_plan_block_change' ],
				'permission_callback' => [ __CLASS__, 'can_struo_plan' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => true,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => false,
						'destructive' => false,
						'idempotent' => false,
					],
				],
			],
			self::ABILITY_PREVIEW_INSERT => [
				'label' => __( 'Preview insert block', 'struo' ),
				'description' => __( 'Dry-run preview of inserting an allowlisted block. Never persists. Apply is durable plan_id only.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'block_name' => [ 'type' => 'string' ],
						'fields' => [ 'type' => 'object' ],
						'position' => [ 'type' => 'object' ],
						'parent_path' => [
							'type' => 'array',
							'items' => [ 'type' => 'integer' ],
						],
						'compact' => [ 'type' => 'boolean' ],
						'verbose' => [ 'type' => 'boolean' ],
						'idempotency_key' => [ 'type' => 'string' ],
					],
					'required' => [ 'post_id', 'block_name', 'fields' ],
				],
				'execute_callback' => [ __CLASS__, 'execute_preview_insert' ],
				'permission_callback' => [ __CLASS__, 'can_write_post' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => false,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => false,
						'destructive' => false,
						'idempotent' => false,
					],
				],
			],
			self::ABILITY_PREVIEW_UPDATE => [
				'label' => __( 'Preview update block', 'struo' ),
				'description' => __( 'Dry-run preview of updating an allowlisted block. Never persists. Apply is durable plan_id only.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'target' => [ 'type' => 'object' ],
						'fields' => [ 'type' => 'object' ],
						'compact' => [ 'type' => 'boolean' ],
						'verbose' => [ 'type' => 'boolean' ],
						'idempotency_key' => [ 'type' => 'string' ],
					],
					'required' => [ 'post_id', 'target', 'fields' ],
				],
				'execute_callback' => [ __CLASS__, 'execute_preview_update' ],
				'permission_callback' => [ __CLASS__, 'can_write_post' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => false,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => false,
						'destructive' => false,
						'idempotent' => false,
					],
				],
			],
			self::ABILITY_PREVIEW_REMOVE => [
				'label' => __( 'Preview remove block', 'struo' ),
				'description' => __( 'Dry-run preview of removing an allowlisted block. Never persists. Apply is durable plan_id only.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'target' => [ 'type' => 'object' ],
						'block_name' => [ 'type' => 'string' ],
						'remove_all' => [ 'type' => 'boolean' ],
						'compact' => [ 'type' => 'boolean' ],
						'verbose' => [ 'type' => 'boolean' ],
						'idempotency_key' => [ 'type' => 'string' ],
					],
					'required' => [ 'post_id' ],
				],
				'execute_callback' => [ __CLASS__, 'execute_preview_remove' ],
				'permission_callback' => [ __CLASS__, 'can_write_post' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => false,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => false,
						'destructive' => true,
						'idempotent' => false,
					],
				],
			],
			self::ABILITY_PREVIEW_BATCH => [
				'label' => __( 'Preview batch', 'struo' ),
				'description' => __( 'Dry-run preview of a batch of allowlisted insert/update/remove operations. Never persists. Apply is durable plan_id only.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'operations' => [ 'type' => 'array' ],
						'compact' => [ 'type' => 'boolean' ],
						'verbose' => [ 'type' => 'boolean' ],
						'idempotency_key' => [ 'type' => 'string' ],
					],
					'required' => [ 'post_id', 'operations' ],
				],
				'execute_callback' => [ __CLASS__, 'execute_preview_batch' ],
				'permission_callback' => [ __CLASS__, 'can_write_post' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => false,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => false,
						'destructive' => true,
						'idempotent' => false,
					],
				],
			],
			self::ABILITY_APPLY_AGENT_PLAN => [
				'label' => __( 'Apply agent plan', 'struo' ),
				'description' => __( 'Persist an approved durable plan_id. Not MCP-public. Does not mint a confirmation token. Approve stays REST/console.', 'struo' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'plan_id' => [
							'type' => 'string',
							'description' => __( 'Approved durable plan id.', 'struo' ),
						],
					],
					'required' => [ 'plan_id' ],
				],
				'execute_callback' => [ __CLASS__, 'execute_apply_agent_plan' ],
				'permission_callback' => [ __CLASS__, 'can_apply_agent_plan' ],
				'meta' => [
					'show_in_rest' => true,
					'mcp' => [
						'public' => false,
						'type' => 'tool',
					],
					'annotations' => [
						'readonly' => false,
						'destructive' => true,
						'idempotent' => false,
					],
				],
			],
		];

		foreach ( array_keys( $definitions ) as $name ) {
			$definitions[ $name ]['meta']['mcp']['public'] = Struo_Operation_Catalog::mcp_public_for_ability( $name );
			$default_public = ! empty( $definitions[ $name ]['meta']['mcp']['public'] );
			$definitions[ $name ]['meta']['mcp']['public'] = (bool) apply_filters(
				'struo_ability_mcp_public',
				$default_public,
				$name
			);
		}

		// Persist must never be MCP-public, including via the override filter.
		if ( isset( $definitions[ self::ABILITY_APPLY_AGENT_PLAN ] ) ) {
			$definitions[ self::ABILITY_APPLY_AGENT_PLAN ]['meta']['mcp']['public'] = false; // struo_apply_ability_never_mcp_public
		}

		return $definitions;
	}

	public static function can_read_catalog( $input = [] ) {
		unset( $input );
		$permission = Struo_Block_Editor::can_read_catalog();
		return true === $permission;
	}

	public static function can_struo_plan( $input = [] ) {
		unset( $input );
		$permission = Struo_Block_Editor::can_struo_plan();
		return true === $permission;
	}

	public static function can_read_post( $input = [] ) {
		if ( $input instanceof WP_REST_Request ) {
			return Struo_Block_Editor::can_read_post( $input );
		}
		return Struo_Block_Editor::can_read_post(
			self::request_from_input( is_array( $input ) ? $input : [] )
		);
	}

	public static function can_write_post( $input = [] ) {
		if ( $input instanceof WP_REST_Request ) {
			return Struo_Block_Editor::can_write_post( $input );
		}
		return Struo_Block_Editor::can_write_post(
			self::request_from_input( is_array( $input ) ? $input : [] )
		);
	}

	public static function execute_get_block_catalog( $input = [] ) {
		return self::dispatch( 'get-block-catalog', $input, self::ABILITY_GET_BLOCK_CATALOG );
	}

	public static function execute_get_post_blocks( $input = [] ) {
		return self::dispatch( 'get-post-blocks', $input, self::ABILITY_GET_POST_BLOCKS );
	}

	public static function execute_plan_block_change( $input = [] ) {
		return self::dispatch( 'plan-block-change', $input, self::ABILITY_PLAN_BLOCK_CHANGE );
	}

	public static function execute_preview_insert( $input = [] ) {
		return self::dispatch( 'preview-insert', $input, self::ABILITY_PREVIEW_INSERT );
	}

	public static function execute_preview_update( $input = [] ) {
		return self::dispatch( 'preview-update', $input, self::ABILITY_PREVIEW_UPDATE );
	}

	public static function execute_preview_remove( $input = [] ) {
		return self::dispatch( 'preview-remove', $input, self::ABILITY_PREVIEW_REMOVE );
	}

	public static function execute_preview_batch( $input = [] ) {
		return self::dispatch( 'preview-batch', $input, self::ABILITY_PREVIEW_BATCH );
	}

	public static function can_apply_agent_plan( $input = [] ) {
		return Struo_Block_Editor::can_struo_apply( self::apply_request_from_input( $input ), 'ability' );
	}

	public static function execute_apply_agent_plan( $input = [] ) {
		// Dedicated path: preview/MCP dispatcher forces dry-run and cannot persist.
		return Struo_Block_Editor::apply_agent_plan( self::apply_request_from_input( $input ) );
	}

	private static function dispatch( $operation, $input, $ability_name ) {
		return Struo_Block_Editor::dispatch_internal(
			$operation,
			is_array( $input ) ? $input : [],
			'mcp',
			[
				'tool' => sanitize_key( str_replace( '/', '-', (string) $ability_name ) ),
				'request_id' => '',
			]
		);
	}

	private static function request_from_input( array $input ) {
		$request = new WP_REST_Request( 'POST' );
		$post_id = absint( $input['post_id'] ?? ( $input['id'] ?? 0 ) );
		if ( $post_id > 0 ) {
			$request->set_url_params( [ 'id' => $post_id ] );
			$request->set_body_params(
				[
					'id' => $post_id,
					'post_id' => $post_id,
				]
			);
		}
		return $request;
	}

	private static function apply_request_from_input( $input ) {
		if ( $input instanceof WP_REST_Request ) {
			return $input;
		}
		$input = is_array( $input ) ? $input : [];
		$plan_id = sanitize_text_field( (string) ( $input['plan_id'] ?? ( $input['id'] ?? '' ) ) );
		$request = new WP_REST_Request( 'POST', '/struo/v1/console/agent-plans/' . $plan_id . '/apply' );
		$request->set_url_params( [ 'id' => $plan_id ] );
		return $request;
	}
}
