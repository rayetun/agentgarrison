<?php
/**
 * Phase 8 — Honeypot Bot Trap.
 *
 * Places a hidden, robots.txt-disallowed trap URL on the site. No human and no
 * well-behaved crawler will ever request it, so any hit is a high-confidence bot
 * — typically one spoofing a human User-Agent or ignoring robots.txt. Hits are
 * logged to the analytics table with a honeypot flag. An optional (off by default)
 * soft-block can 403 repeat offenders by IP.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Honeypot {

	private static $instance = null;
	private $settings        = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_settings();

		if ( Rayetun_AG_Modules::is_enabled( 'honeypot' ) ) {
			// Priority 20 so this fires reliably AFTER the modules boot on init:10.
			add_action( 'init', array( $this, 'add_rewrite_rule' ), 20 );
			add_filter( 'query_vars', array( $this, 'add_query_var' ) );
			add_action( 'template_redirect', array( $this, 'maybe_catch' ), 1 );
			add_action( 'wp_footer', array( $this, 'render_hidden_link' ) );
			add_filter( 'robots_txt', array( $this, 'add_robots_disallow' ), 20, 2 );
			add_action( 'init', array( $this, 'maybe_block_ip' ), 0 );
			add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		}

		add_action( 'wp_ajax_rayetun_ag_save_honeypot_settings', array( $this, 'handle_save_settings' ) );
	}

	private function load_settings() {
		$this->settings = wp_parse_args(
			get_option( 'rayetun_ag_honeypot_settings', array() ),
			array(
				'trap_slug'  => '',
				'auto_block' => false,
			)
		);

		// Generate a hard-to-guess trap slug on first run and flag a rewrite flush.
		if ( empty( $this->settings['trap_slug'] ) ) {
			$this->settings['trap_slug'] = 'ag-' . strtolower( wp_generate_password( 16, false ) );
			update_option( 'rayetun_ag_honeypot_settings', $this->settings );
			update_option( 'rayetun_ag_honeypot_flush', 1 );
		}
	}

	private function trap_path() {
		return $this->settings['trap_slug'];
	}

	// -------------------------------------------------------------------------
	// Rewrite rule + trap detection
	// -------------------------------------------------------------------------

	public function add_rewrite_rule() {
		add_rewrite_rule( '^' . preg_quote( $this->trap_path(), '#' ) . '/?$', 'index.php?rayetun_ag_honeypot=1', 'top' );
	}

	public function add_query_var( $vars ) {
		$vars[] = 'rayetun_ag_honeypot';
		return $vars;
	}

	public function maybe_flush() {
		if ( get_option( 'rayetun_ag_honeypot_flush' ) ) {
			$this->add_rewrite_rule();
			flush_rewrite_rules( false );
			delete_option( 'rayetun_ag_honeypot_flush' );
		}
	}

	public function maybe_catch() {
		if ( ! get_query_var( 'rayetun_ag_honeypot' ) ) {
			return;
		}
		$this->log_catch();
		// 403 Forbidden — make it clear the request was rejected.
		status_header( 403 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		exit;
	}

	private function log_catch() {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip = $this->get_ip();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'bot_name'     => 'Honeypot Trap',
				'bot_category' => 'honeypot',
				'page_url'     => home_url( '/' . $this->trap_path() . '/' ),
				'user_agent'   => $ua,
				'ip_address'   => $ip,
				'spoofed'      => 0,
				'honeypot'     => 1,
				'visited_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		// Track the offending IP for the optional soft-block.
		if ( $ip && 'unknown' !== $ip ) {
			$blocked = (array) get_option( 'rayetun_ag_honeypot_blocked_ips', array() );
			if ( ! in_array( $ip, $blocked, true ) ) {
				$blocked[] = $ip;
				// Cap the list so the option never bloats.
				if ( count( $blocked ) > 500 ) {
					$blocked = array_slice( $blocked, -500 );
				}
				update_option( 'rayetun_ag_honeypot_blocked_ips', $blocked );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Hidden link + robots.txt
	// -------------------------------------------------------------------------

	public function render_hidden_link() {
		if ( is_admin() ) {
			return;
		}
		$url = home_url( '/' . $this->trap_path() . '/' );
		// Visually hidden, hidden from assistive tech, and marked nofollow. Only a
		// link-harvesting bot would ever request it.
		printf(
			'<a href="%s" rel="nofollow" aria-hidden="true" tabindex="-1" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">%s</a>',
			esc_url( $url ),
			esc_html__( 'Do not follow this link', 'agentgarrison' )
		);
	}

	public function add_robots_disallow( $output, $public ) {
		// Disallow the trap so well-behaved crawlers skip it; only rule-ignoring
		// bots (or hidden-link harvesters) will end up hitting it.
		$output .= "\n# AgentGarrison honeypot\nUser-agent: *\nDisallow: /" . $this->trap_path() . "/\n";
		return $output;
	}

	// -------------------------------------------------------------------------
	// Optional soft-block
	// -------------------------------------------------------------------------

	public function maybe_block_ip() {
		if ( empty( $this->settings['auto_block'] ) ) {
			return;
		}
		if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$ip = $this->get_ip();
		if ( ! $ip || 'unknown' === $ip ) {
			return;
		}
		$blocked = (array) get_option( 'rayetun_ag_honeypot_blocked_ips', array() );
		if ( in_array( $ip, $blocked, true ) ) {
			status_header( 403 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow' );
			exit;
		}
	}

	private function get_ip() {
		// REMOTE_ADDR is the actual TCP peer and cannot be spoofed at the HTTP layer.
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: 'unknown';
	}

	// -------------------------------------------------------------------------
	// Stats + AJAX
	// -------------------------------------------------------------------------

	public function get_settings() {
		return $this->settings;
	}

	public function get_catch_count() {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE honeypot = 1', $table )
		);
	}

	public function get_blocked_count() {
		return count( (array) get_option( 'rayetun_ag_honeypot_blocked_ips', array() ) );
	}

	public function handle_save_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$settings = get_option( 'rayetun_ag_honeypot_settings', array() );
		$settings['auto_block'] = ! empty( $_POST['auto_block'] );
		update_option( 'rayetun_ag_honeypot_settings', $settings );
		$this->settings = wp_parse_args( $settings, $this->settings );

		// Allow clearing the soft-block list from the UI.
		if ( ! empty( $_POST['clear_blocked'] ) ) {
			delete_option( 'rayetun_ag_honeypot_blocked_ips' );
		}

		wp_send_json_success( array(
			'message' => __( 'Honeypot settings saved.', 'agentgarrison' ),
			'blocked' => $this->get_blocked_count(),
		) );
	}
}
