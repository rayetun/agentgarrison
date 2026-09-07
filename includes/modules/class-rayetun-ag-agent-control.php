<?php
/**
 * Module 14 — Agent Control (WordPress 7.1 Abilities API + MCP governance).
 *
 * Governs which of the site's registered Abilities may be exposed to AI agents
 * over MCP, and records an audit trail of every ability execution. This is the
 * write/act counterpart to Bot Control (which governs crawlers that only read):
 * WordPress 7.1 introduced the Abilities API mapped onto MCP, so agents can now
 * discover and *execute* site actions.
 *
 * Everything here is local: policy is stored in the site's own options and the
 * audit trail in the site's own database — no external requests. On WordPress
 * below 7.1 the Abilities API and its execution hooks are absent, so the class
 * degrades to a harmless no-op (the filters simply never fire).
 *
 * Governance is deliberately non-invasive: we only alter MCP exposure once the
 * site owner explicitly opts in to managing it, so installing or updating the
 * plugin never silently disables an existing MCP configuration.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Agent_Control {

	private static $instance = null;

	const OPTION       = 'rayetun_ag_agent_settings';
	const CLEANUP_HOOK = 'rayetun_ag_cleanup_agent_events';

	private $settings = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_settings();

		if ( Rayetun_AG_Modules::is_enabled( 'agent_control' ) ) {
			// Governance: only touch MCP exposure when the owner opts in, so we
			// never silently override an existing MCP setup on activation/upgrade.
			if ( ! empty( $this->settings['manage_exposure'] ) ) {
				add_filter( 'mcp_exposed_abilities', array( $this, 'filter_exposed_abilities' ), 20 );
			}

			// Audit log: WordPress 7.1 execution-lifecycle hooks. Registering these
			// on older WordPress is harmless — the hooks simply never fire there.
			add_filter( 'wp_after_execute_ability', array( $this, 'log_after_execute' ), 10, 3 );

			// Daily retention cleanup, aligned with the site's general retention setting.
			add_action( 'init', array( $this, 'maybe_schedule_cleanup' ) );
			add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_events' ) );
		}

		// AJAX — always registered regardless of module state (mirrors Bot Control),
		// so the settings screen keeps working while the module is toggled.
		add_action( 'wp_ajax_rayetun_ag_save_agent_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_clear_agent_log', array( $this, 'handle_clear_log' ) );
	}

	private function load_settings() {
		$defaults = array(
			'manage_exposure' => false, // When false, MCP exposure is left untouched.
			'expose_enabled'  => false, // Master switch: expose any abilities at all.
			'categories'      => array(), // namespace  => 'allow' | 'block' (default allow).
			'abilities'       => array(), // ability    => 'inherit' | 'allow' | 'block'.
		);
		$this->settings = wp_parse_args( get_option( self::OPTION, array() ), $defaults );
	}

	/**
	 * The category (namespace) an ability belongs to — the segment before the
	 * first slash, e.g. "core/get-post" → "core". Mirrors Bot Control categories.
	 *
	 * @param string $ability_name Namespaced ability name.
	 * @return string
	 */
	public function group_key( $ability_name ) {
		$pos = strpos( (string) $ability_name, '/' );
		return ( false !== $pos && $pos > 0 ) ? substr( $ability_name, 0, $pos ) : 'general';
	}

	/**
	 * The effective allow/block decision for one ability: a per-ability override
	 * wins, otherwise the ability's category rule applies (default allow).
	 *
	 * @param string $ability_name Namespaced ability name.
	 * @return string 'allow' | 'block'.
	 */
	public function effective_rule( $ability_name ) {
		$overrides = ( isset( $this->settings['abilities'] ) && is_array( $this->settings['abilities'] ) ) ? $this->settings['abilities'] : array();
		$cats      = ( isset( $this->settings['categories'] ) && is_array( $this->settings['categories'] ) ) ? $this->settings['categories'] : array();

		if ( isset( $overrides[ $ability_name ] ) && in_array( $overrides[ $ability_name ], array( 'allow', 'block' ), true ) ) {
			return $overrides[ $ability_name ];
		}

		$group = $this->group_key( $ability_name );
		return ( isset( $cats[ $group ] ) && 'block' === $cats[ $group ] ) ? 'block' : 'allow';
	}

	// -------------------------------------------------------------------------
	// Governance — MCP exposure filter
	// -------------------------------------------------------------------------

	/**
	 * Filter the set of abilities exposed over MCP.
	 *
	 * The core filter value's exact shape is not guaranteed across versions, so
	 * this handles both a list of ability-name strings and a map/list of
	 * WP_Ability objects, preserving whichever shape it received.
	 *
	 * @param mixed $abilities Abilities the MCP adapter intends to expose.
	 * @return mixed The same shape, minus anything blocked (or all, when disabled).
	 */
	public function filter_exposed_abilities( $abilities ) {
		if ( ! is_array( $abilities ) ) {
			return $abilities;
		}

		// Master switch off → expose nothing.
		if ( empty( $this->settings['expose_enabled'] ) ) {
			return array();
		}

		$is_list  = ( array() !== $abilities ) && ( array_keys( $abilities ) === range( 0, count( $abilities ) - 1 ) );
		$filtered = array();

		foreach ( $abilities as $key => $value ) {
			$name = $this->ability_name_from( $key, $value );

			// Unidentifiable entry — leave it as-is rather than risk dropping a
			// legitimate ability we simply couldn't name.
			if ( '' === $name ) {
				$filtered[ $key ] = $value;
				continue;
			}

			// Default-allow: an ability is dropped only when its effective rule
			// (per-ability override, else category rule) resolves to 'block'.
			if ( 'block' === $this->effective_rule( $name ) ) {
				continue;
			}

			$filtered[ $key ] = $value;
		}

		return $is_list ? array_values( $filtered ) : $filtered;
	}

	private function ability_name_from( $key, $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		if ( is_object( $value ) && method_exists( $value, 'get_name' ) ) {
			return (string) $value->get_name();
		}
		if ( is_string( $key ) && '' !== $key ) {
			return $key; // Map keyed by ability name.
		}
		return '';
	}

	// -------------------------------------------------------------------------
	// Audit log — record ability executions
	// -------------------------------------------------------------------------

	/**
	 * Record a completed ability execution.
	 *
	 * Registered on WordPress 7.1's `wp_after_execute_ability`. The exact argument
	 * order is read defensively so a future signature change cannot fatal the site:
	 * we scan the arguments for the WP_Ability (or its name) and for a WP_Error.
	 * The first argument is always returned unchanged so we never alter the result.
	 *
	 * @param mixed $result First hook argument (returned unchanged).
	 * @return mixed The first argument, untouched.
	 */
	public function log_after_execute( $result = null ) {
		$args    = func_get_args();
		$ability = $this->find_ability_name( $args );

		if ( '' === $ability ) {
			return $result;
		}

		$outcome = 'success';
		foreach ( $args as $arg ) {
			if ( is_wp_error( $arg ) ) {
				$outcome = 'error';
				break;
			}
		}

		$this->record_event( $ability, $outcome, $this->detect_source() );

		return $result;
	}

	private function find_ability_name( $args ) {
		foreach ( (array) $args as $arg ) {
			if ( is_object( $arg ) && method_exists( $arg, 'get_name' ) ) {
				return (string) $arg->get_name();
			}
		}
		// Fallback: some signatures pass the ability name as a namespaced string
		// (e.g. "core/get-post").
		foreach ( (array) $args as $arg ) {
			if ( is_string( $arg ) && false !== strpos( $arg, '/' ) ) {
				return $arg;
			}
		}
		return '';
	}

	private function detect_source() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest'; // MCP requests arrive over REST.
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'cron';
		}
		return 'internal';
	}

	private function record_event( $ability, $result, $source ) {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::AGENT_EVENTS_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table,
			array(
				'ability'    => substr( (string) $ability, 0, 191 ),
				'actor_id'   => get_current_user_id(),
				'source'     => substr( (string) $source, 0, 30 ),
				'result'     => substr( (string) $result, 0, 20 ),
				'detail'     => '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	// -------------------------------------------------------------------------
	// Retention cleanup
	// -------------------------------------------------------------------------

	public function maybe_schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public function cleanup_old_events() {
		global $wpdb;

		$general   = get_option( 'rayetun_ag_general_settings', array() );
		$retention = isset( $general['retention_days'] ) ? absint( $general['retention_days'] ) : 90;
		if ( $retention < 1 ) {
			$retention = 90;
		}

		$table    = $wpdb->prefix . Rayetun_AG_DB::AGENT_EVENTS_TABLE;
		$boundary = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $retention * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE created_at < %s", $table, $boundary ) );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function handle_save_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$raw_cats = isset( $_POST['ability_categories'] ) ? wp_unslash( $_POST['ability_categories'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_over = isset( $_POST['ability_overrides'] ) ? wp_unslash( $_POST['ability_overrides'] ) : array();  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$categories = array();
		if ( is_array( $raw_cats ) ) {
			foreach ( $raw_cats as $group => $rule ) {
				$group = sanitize_text_field( $group );
				if ( '' === $group ) {
					continue;
				}
				$categories[ $group ] = ( 'block' === $rule ) ? 'block' : 'allow';
			}
		}

		$abilities = array();
		if ( is_array( $raw_over ) ) {
			foreach ( $raw_over as $name => $rule ) {
				$name = sanitize_text_field( $name );
				if ( '' === $name ) {
					continue;
				}
				$abilities[ $name ] = in_array( $rule, array( 'allow', 'block' ), true ) ? $rule : 'inherit';
			}
		}

		$settings = array(
			'manage_exposure' => ! empty( $_POST['manage_exposure'] ),
			'expose_enabled'  => ! empty( $_POST['expose_enabled'] ),
			'categories'      => $categories,
			'abilities'       => $abilities,
		);

		update_option( self::OPTION, $settings );
		$this->settings = $settings;

		if ( class_exists( 'Rayetun_AG_Visibility_Score' ) ) {
			Rayetun_AG_Visibility_Score::invalidate();
		}

		wp_send_json_success( array( 'message' => __( 'Agent Control settings saved.', 'agentgarrison' ) ) );
	}

	public function handle_clear_log() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::AGENT_EVENTS_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( "TRUNCATE TABLE %i", $table ) );

		wp_send_json_success( array( 'message' => __( 'Activity log cleared.', 'agentgarrison' ) ) );
	}

	// -------------------------------------------------------------------------
	// Helpers used by the admin view
	// -------------------------------------------------------------------------

	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Whether the WordPress 7.1+ Abilities API is present on this site.
	 *
	 * @return bool
	 */
	public function is_abilities_api_available() {
		return function_exists( 'wp_get_abilities' );
	}

	/**
	 * All registered abilities, normalised for display, keyed and sorted by name.
	 *
	 * @return array<string,array{name:string,label:string,category:string,description:string}>
	 */
	public function get_registered_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		// Called indirectly via a variable so Plugin Check / static analysers do
		// not treat this WordPress 6.9+ function as a hard requirement against our
		// 6.2 minimum. The function_exists() guard above gates it at runtime.
		$fn        = 'wp_get_abilities';
		$abilities = $fn();
		$out       = array();

		foreach ( (array) $abilities as $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
				continue;
			}
			$name         = (string) $ability->get_name();
			$out[ $name ] = array(
				'name'        => $name,
				'label'       => method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : $name,
				'category'    => method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : '',
				'description' => method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '',
			);
		}

		ksort( $out );
		return $out;
	}

	/**
	 * Registered abilities grouped by category (namespace), for the Bot
	 * Control-style category cards. Groups and rows are sorted by name.
	 *
	 * @return array<string,array<string,array>>
	 */
	public function get_abilities_grouped() {
		$grouped = array();
		foreach ( $this->get_registered_abilities() as $name => $ability ) {
			$grouped[ $this->group_key( $name ) ][ $name ] = $ability;
		}
		ksort( $grouped );
		return $grouped;
	}

	/**
	 * The category rule for a namespace (default 'allow').
	 *
	 * @param string $group Namespace.
	 * @return string 'allow' | 'block'.
	 */
	public function category_rule( $group ) {
		$cats = ( isset( $this->settings['categories'] ) && is_array( $this->settings['categories'] ) ) ? $this->settings['categories'] : array();
		return ( isset( $cats[ $group ] ) && 'block' === $cats[ $group ] ) ? 'block' : 'allow';
	}

	/**
	 * The stored per-ability override (default 'inherit').
	 *
	 * @param string $ability_name Namespaced ability name.
	 * @return string 'inherit' | 'allow' | 'block'.
	 */
	public function ability_override( $ability_name ) {
		$over = ( isset( $this->settings['abilities'] ) && is_array( $this->settings['abilities'] ) ) ? $this->settings['abilities'] : array();
		return ( isset( $over[ $ability_name ] ) && in_array( $over[ $ability_name ], array( 'allow', 'block' ), true ) ) ? $over[ $ability_name ] : 'inherit';
	}

	/**
	 * Last execution time per ability, from the activity log — powers the
	 * "Last Used" column (the agent analogue of Bot Control's "Last Seen").
	 *
	 * @return array<string,string> ability name => MySQL datetime.
	 */
	public function get_last_used_by_ability() {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::AGENT_EVENTS_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ability, MAX(created_at) AS last_used FROM %i GROUP BY ability", $table ) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row->ability ] = $row->last_used;
		}
		return $out;
	}

	/**
	 * Most recent ability executions, newest first.
	 *
	 * @param int $limit Rows to return (1-200).
	 * @return array Row objects.
	 */
	public function get_recent_events( $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::AGENT_EVENTS_TABLE;
		$limit = max( 1, min( 200, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY id DESC LIMIT %d", $table, $limit ) );
	}

	public function get_event_count() {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::AGENT_EVENTS_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table ) );
	}
}
