<?php
/**
 * Privacy — GDPR personal-data tooling.
 *
 * AgentGarrison stores the IP addresses of bots that crawl the site (and, if the
 * optional Honeypot is enabled, IPs that hit the trap). IPs can be personal data,
 * so we: (1) suggest privacy-policy text, and (2) register WordPress personal-data
 * exporter + eraser callbacks. WordPress keys those requests on an email address;
 * AgentGarrison links an email to its IP(s) via the one place WordPress already stores
 * that mapping — the visitor's comments (comment_author_IP) — then exports/erases
 * any bot-visit rows logged from those IPs.
 *
 * Note: the LLM referral table stores NO IP address, so there is no personal data
 * to export or erase from it.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Privacy {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	// -------------------------------------------------------------------------
	// Suggested privacy-policy text
	// -------------------------------------------------------------------------

	public function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content =
			'<p>' . esc_html__( 'AgentGarrison logs the IP address and User-Agent of automated bots (AI crawlers and, when the optional Honeypot Bot Trap is enabled, any client that hits the hidden trap URL) that access this site. This data is stored only in this site\'s own database and is never transmitted to AgentGarrison\'s developers or any third party.', 'agentgarrison' ) . '</p>'
			. '<p>' . esc_html__( 'Bot visit records are automatically deleted after the retention period configured in AgentGarrison → Settings (90 days by default). The LLM Referral Tracker does not store any IP addresses.', 'agentgarrison' ) . '</p>'
			. '<p>' . esc_html__( 'If you enable the optional Citation Monitor with your own AI provider API key, your tracked keywords and that API key are sent to the provider you configured (OpenAI, Perplexity, or Google). See the plugin\'s External Services documentation for details.', 'agentgarrison' ) . '</p>';

		wp_add_privacy_policy_content( 'AgentGarrison', wp_kses_post( $content ) );
	}

	// -------------------------------------------------------------------------
	// Helper: resolve an email to the IP addresses WordPress associates with it
	// -------------------------------------------------------------------------

	private function ips_for_email( $email ) {
		global $wpdb;
		$ips = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT comment_author_IP FROM {$wpdb->comments} WHERE comment_author_email = %s AND comment_author_IP <> ''",
				$email
			)
		);
		return array_values( array_filter( array_unique( (array) $ips ) ) );
	}

	// -------------------------------------------------------------------------
	// Exporter
	// -------------------------------------------------------------------------

	public function register_exporter( $exporters ) {
		$exporters['agentgarrison-bot-visits'] = array(
			'exporter_friendly_name' => __( 'AgentGarrison Bot Visits', 'agentgarrison' ),
			'callback'               => array( $this, 'export_bot_data' ),
		);
		return $exporters;
	}

	public function export_bot_data( $email_address, $page = 1 ) {
		$page     = max( 1, absint( $page ) );
		$per_page = 200;
		$items    = array();

		$ips = $this->ips_for_email( $email_address );
		if ( empty( $ips ) ) {
			return array( 'data' => array(), 'done' => true );
		}

		global $wpdb;
		$table        = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$placeholders = implode( ',', array_fill( 0, count( $ips ), '%s' ) );
		$offset       = ( $page - 1 ) * $per_page;

		// $placeholders is a list of %s tokens built from a counted array, so the
		// replacement count is correct at runtime; the spread keeps the args flat.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, bot_name, ip_address, page_url, visited_at FROM %i WHERE ip_address IN ( $placeholders ) ORDER BY id ASC LIMIT %d OFFSET %d",
				...array_merge( array( $table ), $ips, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'group_id'    => 'agentgarrison-bot-visits',
				'group_label' => __( 'AgentGarrison Bot Visits', 'agentgarrison' ),
				'item_id'     => 'agentgarrison-visit-' . (int) $row['id'],
				'data'        => array(
					array( 'name' => __( 'Bot', 'agentgarrison' ), 'value' => $row['bot_name'] ),
					array( 'name' => __( 'IP Address', 'agentgarrison' ), 'value' => $row['ip_address'] ),
					array( 'name' => __( 'Page', 'agentgarrison' ), 'value' => $row['page_url'] ),
					array( 'name' => __( 'Date', 'agentgarrison' ), 'value' => $row['visited_at'] ),
				),
			);
		}

		return array(
			'data' => $items,
			'done' => count( (array) $rows ) < $per_page,
		);
	}

	// -------------------------------------------------------------------------
	// Eraser
	// -------------------------------------------------------------------------

	public function register_eraser( $erasers ) {
		$erasers['agentgarrison-bot-visits'] = array(
			'eraser_friendly_name' => __( 'AgentGarrison Bot Visits', 'agentgarrison' ),
			'callback'             => array( $this, 'erase_bot_data' ),
		);
		return $erasers;
	}

	public function erase_bot_data( $email_address, $page = 1 ) {
		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$ips = $this->ips_for_email( $email_address );
		if ( empty( $ips ) ) {
			return $response;
		}

		global $wpdb;
		$table        = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$placeholders = implode( ',', array_fill( 0, count( $ips ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM %i WHERE ip_address IN ( $placeholders )",
				...array_merge( array( $table ), $ips )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// Also drop any of these IPs from the Honeypot soft-block list.
		$blocked = (array) get_option( 'rayetun_ag_honeypot_blocked_ips', array() );
		$kept    = array_values( array_diff( $blocked, $ips ) );
		if ( count( $kept ) !== count( $blocked ) ) {
			update_option( 'rayetun_ag_honeypot_blocked_ips', $kept );
			$deleted = (int) $deleted + ( count( $blocked ) - count( $kept ) );
		}

		if ( $deleted ) {
			$response['items_removed'] = true;
		}
		return $response;
	}
}
