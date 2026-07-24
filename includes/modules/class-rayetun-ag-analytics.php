<?php
/**
 * Module 3 — AI Bot Analytics.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Analytics {

	private static $instance = null;
	private $bots_index      = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->build_bots_index();

		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		if ( Rayetun_AG_Modules::is_enabled( 'analytics' ) ) {
			// Bot intercept on template_redirect — page URL is reliable here.
			add_action( 'template_redirect', array( $this, 'intercept_bot_visit' ) );

			// Schedule cron jobs.
			if ( ! wp_next_scheduled( 'rayetun_ag_cleanup_old_visits' ) ) {
				wp_schedule_event( time(), 'daily', 'rayetun_ag_cleanup_old_visits' );
			}
			if ( ! wp_next_scheduled( 'rayetun_ag_check_spikes' ) ) {
				wp_schedule_event( time(), 'rayetun_ag_six_hourly', 'rayetun_ag_check_spikes' );
			}

			add_action( 'rayetun_ag_cleanup_old_visits', array( $this, 'delete_old_records' ) );
			add_action( 'rayetun_ag_check_spikes', array( $this, 'check_traffic_spikes' ) );
		}

		// AJAX — always register.
		add_action( 'wp_ajax_rayetun_ag_get_analytics', array( $this, 'handle_get_analytics' ) );
		add_action( 'wp_ajax_rayetun_ag_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'wp_ajax_rayetun_ag_live_feed', array( $this, 'handle_live_feed' ) );
	}

	// -------------------------------------------------------------------------
	// Cron schedules
	// -------------------------------------------------------------------------

	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['rayetun_ag_six_hourly'] ) ) {
			$schedules['rayetun_ag_six_hourly'] = array(
				'interval' => 6 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 6 Hours (AgentGarrison)', 'agentgarrison' ),
			);
		}
		return $schedules;
	}

	// -------------------------------------------------------------------------
	// Bot fingerprinting
	// -------------------------------------------------------------------------

	private function build_bots_index() {
		$json_file = RAYETUN_AG_DIR . 'includes/data/bots.json';
		if ( ! file_exists( $json_file ) ) {
			return;
		}
		$raw     = file_get_contents( $json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || empty( $decoded['bots'] ) ) {
			return;
		}
		foreach ( $decoded['bots'] as $bot ) {
			$this->bots_index[ strtolower( $bot['user_agent'] ) ] = $bot;
		}
	}

	private function detect_bot( $user_agent ) {
		$ua_lower = strtolower( $user_agent );
		foreach ( $this->bots_index as $ua_token => $bot ) {
			if ( false !== strpos( $ua_lower, $ua_token ) ) {
				return $bot;
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Intercept
	// -------------------------------------------------------------------------

	public function intercept_bot_visit() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( ! $user_agent ) {
			return;
		}

		$bot = $this->detect_bot( $user_agent );
		if ( ! $bot ) {
			return;
		}

		global $wp;
		$page_url = home_url( add_query_arg( array(), $wp->request ) );

		$this->log_visit( $bot, $page_url, $user_agent );
	}

	private function log_visit( $bot, $page_url, $user_agent ) {
		global $wpdb;

		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		// Deduplicate: skip if same bot+URL visited within last 60 minutes.
		$recent = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT id FROM %i WHERE bot_name = %s AND page_url = %s AND visited_at > %s LIMIT 1',
				$table,
				$bot['name'],
				$page_url,
				gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
			)
		);
		if ( $recent ) {
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'bot_name'     => $bot['name'],
				'bot_category' => $bot['category'],
				'page_url'     => $page_url,
				'user_agent'   => $user_agent,
				'ip_address'   => $ip,
				'spoofed'      => 0,
				'visited_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	// -------------------------------------------------------------------------
	// Maintenance
	// -------------------------------------------------------------------------

	public function delete_old_records() {
		global $wpdb;
		$settings  = get_option( 'rayetun_ag_general_settings', array() );
		$retention = absint( $settings['retention_days'] ?? 90 );
		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );
		$table     = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'DELETE FROM %i WHERE visited_at < %s', $table, $cutoff )
		);
	}

	public function check_traffic_spikes() {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;

		$bots = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT DISTINCT bot_name FROM %i', $table )
		);

		$settings = get_option( 'rayetun_ag_general_settings', array() );
		$email    = sanitize_email( $settings['alert_email'] ?? get_option( 'admin_email' ) );

		foreach ( $bots as $bot_name ) {
			$count_24h = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE bot_name = %s AND visited_at > %s',
					$table,
					$bot_name,
					gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
				)
			);
			$count_7d_daily_avg = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT COUNT(*) / 7 FROM %i WHERE bot_name = %s AND visited_at > %s',
					$table,
					$bot_name,
					gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
				)
			);

			if ( $count_7d_daily_avg > 0 && $count_24h > ( $count_7d_daily_avg * 3 ) ) {
				$subject = sprintf(
					/* translators: %s: bot name */
					__( '[AgentGarrison] Traffic spike detected: %s', 'agentgarrison' ),
					$bot_name
				);
				$body = sprintf(
					/* translators: 1: bot name, 2: 24h count, 3: daily average */
					__( "AgentGarrison detected a traffic spike from %1\$s.\n\n24h visits: %2\$d\n7-day daily average: %3\$.1f\n\nLog in to your AgentGarrison dashboard to investigate.", 'agentgarrison' ),
					$bot_name,
					$count_24h,
					$count_7d_daily_avg
				);
				wp_mail( $email, $subject, $body );
			}
		}
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function handle_get_analytics() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$days  = absint( $_POST['days'] ?? 30 );
		$days  = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;

		$by_bot = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT bot_name, COUNT(*) as visits FROM %i WHERE visited_at > %s GROUP BY bot_name ORDER BY visits DESC LIMIT 20',
				$table,
				$since
			),
			ARRAY_A
		);

		$by_day = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT DATE(visited_at) as day, COUNT(*) as visits FROM %i WHERE visited_at > %s GROUP BY DATE(visited_at) ORDER BY day ASC',
				$table,
				$since
			),
			ARRAY_A
		);

		$top_pages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT page_url, COUNT(*) as visits FROM %i WHERE visited_at > %s GROUP BY page_url ORDER BY visits DESC LIMIT 10',
				$table,
				$since
			),
			ARRAY_A
		);

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $table, $since )
		);

		wp_send_json_success( array(
			'total'     => $total,
			'by_bot'    => $by_bot,
			'by_day'    => $by_day,
			'top_pages' => $top_pages,
		) );
	}

	public function handle_export_csv() {
		check_ajax_referer( 'rayetun_ag_export', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'agentgarrison' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT bot_name, bot_category, page_url, ip_address, spoofed, visited_at FROM %i ORDER BY visited_at DESC LIMIT 5000', $table ),
			ARRAY_A
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="agentgarrison-bot-visits.csv"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $output, array( 'Bot Name', 'Category', 'Page URL', 'IP Address', 'Spoofed', 'Visited At' ) );
		foreach ( $rows as $row ) {
			fputcsv( $output, array_map( array( 'Rayetun_AG_DB', 'csv_safe_cell' ), array_values( $row ) ) );
		}
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	// -------------------------------------------------------------------------
	// Public helpers
	// -------------------------------------------------------------------------

	public function get_recent_visits( $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY visited_at DESC LIMIT %d', $table, $limit ),
			ARRAY_A
		);
	}

	// -------------------------------------------------------------------------
	// Real-time feed (AJAX polling)
	// -------------------------------------------------------------------------

	/**
	 * Return bot visits newer than the supplied last-seen id. The dashboard polls
	 * this every ~15s (only while the tab is visible) to surface live bot activity.
	 */
	public function handle_live_feed() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		global $wpdb;
		$table   = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$last_id = absint( $_POST['last_id'] ?? 0 );

		if ( $last_id > 0 ) {
			// Only rows newer than what the client already has.
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT id, bot_name, bot_category, page_url, visited_at FROM %i WHERE id > %d ORDER BY id DESC LIMIT 20',
					$table,
					$last_id
				),
				ARRAY_A
			);
		} else {
			// First load — seed the feed with the latest rows.
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT id, bot_name, bot_category, page_url, visited_at FROM %i ORDER BY id DESC LIMIT 12',
					$table
				),
				ARRAY_A
			);
		}

		$items   = array();
		$max_id  = $last_id;
		foreach ( $rows as $row ) {
			$id     = (int) $row['id'];
			$max_id = max( $max_id, $id );
			$items[] = array(
				'id'        => $id,
				'bot_name'  => $row['bot_name'],
				'category'  => $row['bot_category'],
				'page_path' => wp_make_link_relative( $row['page_url'] ),
				'ago'       => human_time_diff( strtotime( $row['visited_at'] ), time() ),
			);
		}

		wp_send_json_success( array(
			'items'   => $items,
			'last_id' => $max_id,
		) );
	}

	public function get_last_seen_by_bot() {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT bot_name, MAX(visited_at) as last_seen FROM %i GROUP BY bot_name',
				$table
			),
			ARRAY_A
		);
		$indexed = array();
		foreach ( $rows as $row ) {
			$indexed[ $row['bot_name'] ] = $row['last_seen'];
		}
		return $indexed;
	}
}
