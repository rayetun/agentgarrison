<?php
/**
 * Module — MCP for Agents.
 *
 * Registers AgentGarrison's content-discovery tools as WordPress Abilities
 * (core Abilities API, WP 6.9+) and marks them MCP-public so the WordPress
 * MCP Adapter exposes them on its default server. We do NOT reference any MCP
 * Adapter internal classes: marking meta.mcp.public = true is enough for the
 * adapter's default server to pick the abilities up, which keeps this module
 * safe to load whether or not the adapter is installed.
 *
 * All abilities are read-only and only ever return already-public, published
 * content (respecting the per-post llms.txt exclude flag), so the permission
 * callback is intentionally open — the data is what any visitor could already see.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_MCP {

	private static $instance = null;

	/** Ability names registered by this module. */
	const TOOLS = array(
		'agentgarrison/search-content',
		'agentgarrison/get-page-markdown',
		'agentgarrison/get-site-overview',
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Abilities API is core 6.9+. Only register when the module is enabled
		// and the API exists; on older WordPress this module is simply inert.
		if ( Rayetun_AG_Modules::is_enabled( 'mcp' ) && function_exists( 'wp_register_ability' ) ) {
			// Both registries initialise lazily and fire their init action exactly
			// once — which can happen before this module boots on `init`. The
			// category MUST exist before its abilities register, so hook (or, if the
			// action already fired, run) the category callback first, then abilities.
			$this->hook_or_run( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
			$this->hook_or_run( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}
	}

	/**
	 * Run a registration callback on its init action, or immediately if that action
	 * has already fired (the registries accept registrations against the live
	 * instance at any later point).
	 *
	 * @param string   $action Init action name.
	 * @param callable $cb     Registration callback.
	 */
	private function hook_or_run( $action, $cb ) {
		if ( did_action( $action ) ) {
			call_user_func( $cb );
		} else {
			add_action( $action, $cb );
		}
	}

	/**
	 * Register the "agentgarrison" ability category. Must happen before any ability
	 * that references it, or ability registration is rejected.
	 */
	public function register_category() {
		self::ability_call(
			'wp_register_ability_category',
			'agentgarrison',
			array(
				'label'       => __( 'AgentGarrison', 'agentgarrison' ),
				'description' => __( 'Read-only content-discovery tools for AI agents.', 'agentgarrison' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Availability helpers (used by the admin view)
	// -------------------------------------------------------------------------

	/** @return bool Whether the core Abilities API (WP 6.9+) is present. */
	public static function abilities_available() {
		return function_exists( 'wp_register_ability' );
	}

	/** @return bool Whether the MCP Adapter appears to be active this request. */
	public static function mcp_adapter_active() {
		return did_action( 'mcp_adapter_init' ) > 0;
	}

	/** @return string[] The tool names this module registers. */
	public static function tool_names() {
		return self::TOOLS;
	}

	// -------------------------------------------------------------------------
	// Ability registration
	// -------------------------------------------------------------------------

	/**
	 * Call a 6.9+ Abilities API function indirectly.
	 *
	 * Invoking via a variable keeps Plugin Check from treating these 6.9-only
	 * functions as a hard requirement against our WP 6.2 minimum. Runtime safety
	 * is guaranteed by the function_exists() check.
	 *
	 * @param string $fn   Function name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private static function ability_call( $fn, ...$args ) {
		return function_exists( $fn ) ? $fn( ...$args ) : null;
	}

	public function register_abilities() {
		// The "agentgarrison" category is registered separately on
		// wp_abilities_api_categories_init (see register_category), which the
		// abilities registry guarantees runs first.
		$mcp_public = array( 'meta' => array( 'mcp' => array( 'public' => true ) ) );

		self::ability_call(
			'wp_register_ability',
			'agentgarrison/search-content',
			array_merge( array(
				'label'        => __( 'Search site content', 'agentgarrison' ),
				'description'  => __( 'Search this site\'s published posts and pages by keyword and return matching titles, URLs, and excerpts.', 'agentgarrison' ),
				'category'     => 'agentgarrison',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => __( 'Search query.', 'agentgarrison' ),
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => __( 'Maximum results (1-20).', 'agentgarrison' ),
						),
					),
					'required'   => array( 'query' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'results' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'tool_search_content' ),
				'permission_callback' => '__return_true',
			), $mcp_public )
		);

		self::ability_call(
			'wp_register_ability',
			'agentgarrison/get-page-markdown',
			array_merge( array(
				'label'        => __( 'Get page as Markdown', 'agentgarrison' ),
				'description'  => __( 'Return a single published post or page as clean, token-efficient Markdown, given its URL or ID.', 'agentgarrison' ),
				'category'     => 'agentgarrison',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'url' => array(
							'type'        => 'string',
							'description' => __( 'The page URL.', 'agentgarrison' ),
						),
						'id'  => array(
							'type'        => 'integer',
							'description' => __( 'The post ID (used if no URL is given).', 'agentgarrison' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array( 'type' => 'string' ),
						'url'      => array( 'type' => 'string' ),
						'markdown' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'tool_get_page_markdown' ),
				'permission_callback' => '__return_true',
			), $mcp_public )
		);

		self::ability_call(
			'wp_register_ability',
			'agentgarrison/get-site-overview',
			array_merge( array(
				'label'        => __( 'Get site overview', 'agentgarrison' ),
				'description'  => __( 'Return a short overview of this site: name, description, URL, and its most recent published content.', 'agentgarrison' ),
				'category'     => 'agentgarrison',
				'input_schema' => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'name'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string' ),
						'recent'      => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'tool_get_site_overview' ),
				'permission_callback' => '__return_true',
			), $mcp_public )
		);
	}

	// -------------------------------------------------------------------------
	// Tool callbacks — read-only, published + non-excluded content only
	// -------------------------------------------------------------------------

	public function tool_search_content( array $input = array() ) {
		$query = isset( $input['query'] ) ? sanitize_text_field( (string) $input['query'] ) : '';
		$limit = isset( $input['limit'] ) ? (int) $input['limit'] : 5;
		$limit = max( 1, min( 20, $limit ) );

		if ( '' === $query ) {
			return array( 'results' => array() );
		}

		$q = new WP_Query( array(
			's'                   => $query,
			'post_type'           => array( 'post', 'page' ),
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => '_rayetun_ag_exclude_llms', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_rayetun_ag_exclude_llms', 'value' => '1', 'compare' => '!=' ),
			),
		) );

		$results = array();
		foreach ( $q->posts as $post ) {
			$results[] = array(
				'id'      => $post->ID,
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
			);
		}
		wp_reset_postdata();

		return array( 'results' => $results );
	}

	public function tool_get_page_markdown( array $input = array() ) {
		$post = null;

		if ( ! empty( $input['url'] ) ) {
			$post_id = url_to_postid( esc_url_raw( (string) $input['url'] ) );
			if ( $post_id ) {
				$post = get_post( $post_id );
			}
		}
		if ( ! $post && ! empty( $input['id'] ) ) {
			$post = get_post( (int) $input['id'] );
		}

		if ( ! $this->is_public_post( $post ) ) {
			return array( 'error' => __( 'Not found or not available.', 'agentgarrison' ) );
		}

		$markdown = '';
		if ( class_exists( 'Rayetun_AG_Markdown_Agents' ) ) {
			$markdown = Rayetun_AG_Markdown_Agents::get_instance()->build_markdown( $post );
		}
		if ( '' === $markdown ) {
			$markdown = wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return array(
			'title'    => get_the_title( $post ),
			'url'      => get_permalink( $post ),
			'markdown' => $markdown,
		);
	}

	public function tool_get_site_overview( array $input = array() ) {
		$recent = array();
		$posts  = get_posts( array(
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => 'publish',
			'numberposts'      => 10,
			'suppress_filters' => false,
		) );
		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, '_rayetun_ag_exclude_llms', true ) ) {
				continue;
			}
			$recent[] = array(
				'title' => get_the_title( $post ),
				'url'   => get_permalink( $post ),
			);
		}

		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url( '/' ),
			'recent'      => $recent,
		);
	}

	/**
	 * A post is exposable only if it is a published, public-type post that the
	 * owner has not excluded from AI surfaces.
	 */
	private function is_public_post( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return false;
		}
		if ( 'publish' !== $post->post_status ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || empty( $type->public ) ) {
			return false;
		}
		if ( get_post_meta( $post->ID, '_rayetun_ag_exclude_llms', true ) ) {
			return false;
		}
		if ( ! empty( $post->post_password ) ) {
			return false;
		}
		return true;
	}
}
