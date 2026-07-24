<?php
/**
 * Module 4 — LLM Referral Tracker.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Referral_Tracker {

	private static $instance = null;

	private $platforms = array(
		'chatgpt'    => array( 'label' => 'ChatGPT', 'hosts' => array( 'chatgpt.com', 'chat.openai.com' ), 'utm' => array( 'chatgpt', 'openai' ) ),
		'perplexity' => array( 'label' => 'Perplexity', 'hosts' => array( 'perplexity.ai' ), 'utm' => array( 'perplexity' ) ),
		'gemini'     => array( 'label' => 'Gemini', 'hosts' => array( 'gemini.google.com' ), 'utm' => array( 'gemini' ) ),
		'claude'     => array( 'label' => 'Claude', 'hosts' => array( 'claude.ai' ), 'utm' => array( 'claude', 'anthropic' ) ),
		'copilot'    => array( 'label' => 'Copilot', 'hosts' => array( 'copilot.microsoft.com' ), 'utm' => array( 'copilot' ) ),
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( Rayetun_AG_Modules::is_enabled( 'referral_tracker' ) ) {
			add_action( 'wp', array( $this, 'detect_and_log_referral' ) );
			add_filter( 'manage_posts_columns', array( $this, 'add_referral_column' ) );
			add_action( 'manage_posts_custom_column', array( $this, 'render_referral_column' ), 10, 2 );
		}

		add_action( 'wp_ajax_rayetun_ag_get_referrals', array( $this, 'handle_get_referrals' ) );
		add_action( 'wp_ajax_rayetun_ag_export_referrals', array( $this, 'handle_export_csv' ) );
	}

	// -------------------------------------------------------------------------
	// Detection
	// -------------------------------------------------------------------------

	public function detect_and_log_referral() {
		if ( is_admin() || is_robots() || is_feed() ) {
			return;
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$utm_src = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$platform = $this->match_platform( $referer, $utm_src );
		if ( ! $platform ) {
			return;
		}

		// Avoid double-logging within the same session via a short transient.
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$post_id  = get_queried_object_id();
		$dedupe   = 'rayetun_ag_ref_' . md5( $ip . $platform . $post_id );
		if ( get_transient( $dedupe ) ) {
			return;
		}
		set_transient( $dedupe, 1, 30 * MINUTE_IN_SECONDS );

		$this->log_referral( $platform, $referer, $utm_src, $post_id );
	}

	private function match_platform( $referer, $utm_src ) {
		$ref_host = $referer ? wp_parse_url( $referer, PHP_URL_HOST ) : '';
		$ref_host = $ref_host ? strtolower( $ref_host ) : '';

		foreach ( $this->platforms as $key => $platform ) {
			foreach ( $platform['hosts'] as $host ) {
				if ( $ref_host && false !== strpos( $ref_host, $host ) ) {
					return $key;
				}
			}
			if ( $utm_src ) {
				foreach ( $platform['utm'] as $utm ) {
					if ( strtolower( $utm_src ) === $utm ) {
						return $key;
					}
				}
			}
		}
		return '';
	}

	private function log_referral( $platform, $referer, $utm_src, $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::REFERRALS_TABLE;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'platform'   => $platform,
				'page_url'   => home_url( add_query_arg( array() ) ),
				'post_id'    => absint( $post_id ),
				'referrer'   => $referer,
				'utm_source' => $utm_src,
				'visited_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	// -------------------------------------------------------------------------
	// Post list column
	// -------------------------------------------------------------------------

	public function add_referral_column( $columns ) {
		$columns['rayetun_ag_referrals'] = __( 'AI Citations', 'agentgarrison' );
		return $columns;
	}

	public function render_referral_column( $column, $post_id ) {
		if ( 'rayetun_ag_referrals' !== $column ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::REFERRALS_TABLE;
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_id = %d', $table, $post_id )
		);
		if ( $count > 0 ) {
			echo '<span class="agentgarrison-badge">' . esc_html( number_format_i18n( $count ) ) . '</span>';
		} else {
			echo '<span style="color:#9DB1B7;">—</span>';
		}
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function handle_get_referrals() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$days  = absint( $_POST['days'] ?? 30 );
		$days  = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::REFERRALS_TABLE;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $table, $since )
		);

		$by_platform = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT platform, COUNT(*) as cnt FROM %i WHERE visited_at > %s GROUP BY platform ORDER BY cnt DESC',
				$table,
				$since
			),
			ARRAY_A
		);

		$top_pages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT page_url, post_id, COUNT(*) as cnt FROM %i WHERE visited_at > %s GROUP BY page_url ORDER BY cnt DESC LIMIT 15',
				$table,
				$since
			),
			ARRAY_A
		);

		// Attach platform labels.
		foreach ( $by_platform as &$row ) {
			$row['label'] = $this->platforms[ $row['platform'] ]['label'] ?? ucfirst( $row['platform'] );
		}
		unset( $row );

		foreach ( $top_pages as &$page ) {
			$page['title'] = $page['post_id'] ? get_the_title( $page['post_id'] ) : $page['page_url'];
		}
		unset( $page );

		wp_send_json_success( array(
			'total'       => $total,
			'by_platform' => $by_platform,
			'top_pages'   => $top_pages,
		) );
	}

	public function handle_export_csv() {
		check_ajax_referer( 'rayetun_ag_export', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'agentgarrison' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::REFERRALS_TABLE;
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT platform, page_url, referrer, utm_source, visited_at FROM %i ORDER BY visited_at DESC LIMIT 5000', $table ),
			ARRAY_A
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="agentgarrison-llm-referrals.csv"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $output, array( 'Platform', 'Page URL', 'Referrer', 'UTM Source', 'Visited At' ) );
		foreach ( $rows as $row ) {
			fputcsv( $output, array_map( array( 'Rayetun_AG_DB', 'csv_safe_cell' ), array_values( $row ) ) );
		}
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function get_platforms() {
		return $this->platforms;
	}
}
