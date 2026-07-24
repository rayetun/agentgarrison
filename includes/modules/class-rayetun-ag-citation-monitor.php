<?php
/**
 * Phase 4 — Citation Monitor.
 *
 * Tracks which of the site's pages are cited by AI platforms when users
 * search for configured keywords. Ships with a Demo Mode that generates
 * realistic examples from your own posts — no API key required to preview.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Citation_Monitor {

	private static $instance = null;

	private $platforms = array(
		'chatgpt'    => array( 'label' => 'ChatGPT',    'color' => '#10A37F' ),
		'perplexity' => array( 'label' => 'Perplexity', 'color' => '#5436DA' ),
		'gemini'     => array( 'label' => 'Gemini',     'color' => '#4285F4' ),
		'claude'     => array( 'label' => 'Claude',     'color' => '#D97706' ),
		'core'       => array( 'label' => 'WordPress AI', 'color' => '#3858E9' ),
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );

		if ( Rayetun_AG_Modules::is_enabled( 'citation_monitor' ) ) {
			if ( ! wp_next_scheduled( 'rayetun_ag_citation_scan' ) ) {
				$settings = $this->get_settings();
				wp_schedule_event( time(), $this->frequency_to_schedule( $settings['scan_frequency'] ), 'rayetun_ag_citation_scan' );
			}
			add_action( 'rayetun_ag_citation_scan', array( $this, 'run_scan' ) );
		}

		add_action( 'wp_ajax_rayetun_ag_add_keyword',        array( $this, 'handle_add_keyword' ) );
		add_action( 'wp_ajax_rayetun_ag_delete_keyword',     array( $this, 'handle_delete_keyword' ) );
		add_action( 'wp_ajax_rayetun_ag_get_citations',      array( $this, 'handle_get_citations' ) );
		add_action( 'wp_ajax_rayetun_ag_run_demo_scan',      array( $this, 'handle_demo_scan' ) );
		add_action( 'wp_ajax_rayetun_ag_run_live_scan',      array( $this, 'handle_live_scan' ) );
		add_action( 'wp_ajax_rayetun_ag_save_api_keys',      array( $this, 'handle_save_api_keys' ) );
		add_action( 'wp_ajax_rayetun_ag_clear_citations',    array( $this, 'handle_clear_citations' ) );
		add_action( 'wp_ajax_rayetun_ag_save_citation_settings', array( $this, 'handle_save_citation_settings' ) );
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	public function get_settings() {
		return wp_parse_args(
			get_option( 'rayetun_ag_citation_settings', array() ),
			array(
				'scan_frequency' => 'daily',
				'notify_enabled' => false,
				'notify_email'   => get_option( 'admin_email' ),
			)
		);
	}

	private function frequency_to_schedule( $frequency ) {
		$map = array(
			'daily'   => 'rayetun_ag_daily_scan',
			'weekly'  => 'rayetun_ag_citation_weekly',
			'monthly' => 'rayetun_ag_citation_monthly',
		);
		return $map[ $frequency ] ?? 'rayetun_ag_daily_scan';
	}

	// -------------------------------------------------------------------------
	// Cron
	// -------------------------------------------------------------------------

	public function add_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['rayetun_ag_daily_scan'] ) ) {
			$schedules['rayetun_ag_daily_scan'] = array(
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Once Daily (AgentGarrison Citation Scan)', 'agentgarrison' ),
			);
		}
		if ( ! isset( $schedules['rayetun_ag_citation_weekly'] ) ) {
			$schedules['rayetun_ag_citation_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly (AgentGarrison Citation Scan)', 'agentgarrison' ),
			);
		}
		if ( ! isset( $schedules['rayetun_ag_citation_monthly'] ) ) {
			$schedules['rayetun_ag_citation_monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly (AgentGarrison Citation Scan)', 'agentgarrison' ),
			);
		}
		return $schedules;
	}

	private function reschedule_scan() {
		$settings  = $this->get_settings();
		$timestamp = wp_next_scheduled( 'rayetun_ag_citation_scan' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'rayetun_ag_citation_scan' );
		}
		wp_schedule_event(
			time() + HOUR_IN_SECONDS,
			$this->frequency_to_schedule( $settings['scan_frequency'] ),
			'rayetun_ag_citation_scan'
		);
	}

	// -------------------------------------------------------------------------
	// Demo Mode — generates realistic results from the site's own posts
	// -------------------------------------------------------------------------

	public function generate_demo_results( $keyword_id, $keyword ) {
		global $wpdb;

		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'orderby'        => 'rand',
		) );

		if ( empty( $posts ) ) {
			return 0;
		}

		$platforms_list  = array_keys( $this->platforms );
		$confidence_pool = array( 72, 78, 81, 85, 88, 91, 94 );
		$table           = $wpdb->prefix . Rayetun_AG_DB::CITATION_RESULTS_TABLE;
		$inserted        = 0;

		foreach ( array_slice( $posts, 0, 4 ) as $i => $post ) {
			$platform  = $platforms_list[ $i % count( $platforms_list ) ];
			$excerpt   = get_the_excerpt( $post );
			$snippet   = $excerpt
				? '"' . wp_trim_words( $excerpt, 20, '…' ) . '"'
				: '"' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '…' ) . '"';
			$confidence = $confidence_pool[ array_rand( $confidence_pool ) ];

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'keyword_id'      => absint( $keyword_id ),
					'platform'        => $platform,
					'cited_url'       => get_permalink( $post ),
					'post_id'         => $post->ID,
					'context_snippet' => $snippet,
					'confidence_score' => $confidence,
					'is_demo'         => 1,
					'checked_at'      => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
			);
			$inserted++;
		}

		// Demo closed-loop outcomes: one cited and one not-cited (with sample
		// competitors) so the scorecard shows a realistic rate and leaderboard.
		$demo_competitors = array( 'wikipedia.org', 'reddit.com', 'medium.com', 'g2.com', 'forbes.com' );
		shuffle( $demo_competitors );
		$this->record_scan( $keyword_id, $platforms_list[0], true, array(), (string) ( $posts[0]->post_title ?? '' ), 1 );
		$this->record_scan( $keyword_id, $platforms_list[1 % count( $platforms_list )], false, array_slice( $demo_competitors, 0, wp_rand( 2, 4 ) ), '', 1 );

		return $inserted;
	}

	/**
	 * Live (non-demo) cited-rate as a percentage, for the Visibility Score.
	 *
	 * @return int|null Percentage 0–100, or null if no live scan has run yet.
	 */
	public function get_live_cited_rate() {
		global $wpdb;
		$scans = $wpdb->prefix . Rayetun_AG_DB::CITATION_SCANS_TABLE;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(DISTINCT keyword_id) FROM %i WHERE is_demo = 0', $scans )
		);
		if ( ! $total ) {
			return null;
		}
		$cited = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(DISTINCT keyword_id) FROM %i WHERE is_demo = 0 AND cited = 1', $scans )
		);
		return (int) round( $cited / $total * 100 );
	}

	/**
	 * Closed-loop scorecard: cited-rate, per-question status, competitor leaderboard.
	 * Prefers real scan data; falls back to demo data when no live scans exist yet.
	 *
	 * @return array
	 */
	public function get_scorecard() {
		global $wpdb;
		$scans_table = $wpdb->prefix . Rayetun_AG_DB::CITATION_SCANS_TABLE;
		$kw_table    = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;

		$has_real = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE is_demo = 0', $scans_table )
		);
		$is_demo = $has_real ? 0 : 1;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT keyword_id, cited, competitor_domains FROM %i WHERE is_demo = %d', $scans_table, $is_demo ),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array( 'has_data' => false );
		}

		$keywords = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT id, keyword FROM %i', $kw_table ),
			OBJECT_K
		);

		$by_kw   = array();
		$tally   = array();
		foreach ( $rows as $r ) {
			$kid = (int) $r['keyword_id'];
			if ( ! isset( $by_kw[ $kid ] ) ) {
				$by_kw[ $kid ] = false;
			}
			if ( $r['cited'] ) {
				$by_kw[ $kid ] = true;
			}
			$domains = json_decode( (string) $r['competitor_domains'], true );
			if ( is_array( $domains ) ) {
				foreach ( $domains as $d ) {
					$tally[ $d ] = ( isset( $tally[ $d ] ) ? $tally[ $d ] : 0 ) + 1;
				}
			}
		}

		$total = count( $by_kw );
		$cited = count( array_filter( $by_kw ) );

		$questions = array();
		foreach ( $by_kw as $kid => $is_cited ) {
			$questions[] = array(
				'keyword' => isset( $keywords[ $kid ] ) ? $keywords[ $kid ]->keyword : '#' . $kid,
				'cited'   => (bool) $is_cited,
			);
		}

		arsort( $tally );
		$competitors = array();
		foreach ( array_slice( $tally, 0, 10, true ) as $domain => $count ) {
			$competitors[] = array( 'domain' => $domain, 'count' => (int) $count );
		}

		return array(
			'has_data'    => true,
			'is_demo'     => (bool) $is_demo,
			'cited_rate'  => $total ? (int) round( $cited / $total * 100 ) : 0,
			'cited_count' => $cited,
			'total_count' => $total,
			'questions'   => $questions,
			'competitors' => $competitors,
		);
	}

	// -------------------------------------------------------------------------
	// Live scan (requires API keys)
	// -------------------------------------------------------------------------

	public function run_scan() {
		global $wpdb;

		// Usable if the WP 7.0+ core AI Client is configured OR a BYO key is set.
		if ( ! Rayetun_AG_AI_Provider::is_available() ) {
			return;
		}

		$kw_table = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;
		$keywords = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY added_at DESC', $kw_table ),
			ARRAY_A
		);

		foreach ( $keywords as $kw ) {
			$this->scan_keyword( $kw['id'], $kw['keyword'] );
		}

		// After a live scan, notify on any newly-found citations.
		$this->maybe_notify_new_citations();
	}

	// -------------------------------------------------------------------------
	// Email diff notifications
	// -------------------------------------------------------------------------

	/**
	 * Compare real (non-demo) citations against the last notification baseline
	 * and email a "N new citations" digest when there are new ones.
	 */
	public function maybe_notify_new_citations() {
		$settings = $this->get_settings();
		if ( empty( $settings['notify_enabled'] ) ) {
			return;
		}

		$last_notified = (int) get_option( 'rayetun_ag_citation_last_notified', 0 );

		// First run establishes a baseline so we don't email the entire backlog.
		if ( 0 === $last_notified ) {
			update_option( 'rayetun_ag_citation_last_notified', time() );
			return;
		}

		global $wpdb;
		$res_table = $wpdb->prefix . Rayetun_AG_DB::CITATION_RESULTS_TABLE;
		$kw_table  = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;
		$since     = gmdate( 'Y-m-d H:i:s', $last_notified );

		// Only real citations (is_demo = 0) count toward notifications.
		$new_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT r.*, k.keyword FROM %i r
				 LEFT JOIN %i k ON r.keyword_id = k.id
				 WHERE r.is_demo = 0 AND r.checked_at > %s
				 ORDER BY r.checked_at DESC LIMIT 50',
				$res_table,
				$kw_table,
				$since
			),
			ARRAY_A
		);

		if ( empty( $new_results ) ) {
			return;
		}

		$this->send_citation_email( $new_results );
		update_option( 'rayetun_ag_citation_last_notified', time() );
	}

	private function send_citation_email( $new_results ) {
		$settings = $this->get_settings();
		$email    = sanitize_email( $settings['notify_email'] );
		if ( ! $email ) {
			return;
		}

		// White-label: reuse the agency name from the Email Digest settings if present.
		$digest = get_option( 'rayetun_ag_digest_settings', array() );
		$brand  = ! empty( $digest['agency_name'] ) ? $digest['agency_name'] : 'AgentGarrison';

		$count   = count( $new_results );
		$subject = sprintf(
			/* translators: 1: site name, 2: number of new citations */
			_n(
				'[%1$s] %2$d new AI citation found',
				'[%1$s] %2$d new AI citations found',
				$count,
				'agentgarrison'
			),
			get_bloginfo( 'name' ),
			$count
		);

		$body    = $this->build_citation_email_html( $new_results, $brand, $count );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $email, $subject, $body, $headers );
	}

	private function build_citation_email_html( $results, $brand, $count ) {
		$rows = '';
		foreach ( $results as $r ) {
			$platform = $this->platforms[ $r['platform'] ]['label'] ?? ucfirst( $r['platform'] );
			$color    = $this->platforms[ $r['platform'] ]['color'] ?? '#5C7880';
			$title    = $r['post_id'] ? get_the_title( (int) $r['post_id'] ) : $r['cited_url'];
			$rows    .= '<tr style="border-bottom:1px solid #E0E8EA;">'
				. '<td style="padding:10px 12px;"><span style="display:inline-block;background:' . esc_attr( $color ) . ';color:#fff;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;">' . esc_html( $platform ) . '</span></td>'
				. '<td style="padding:10px 12px;font-size:13px;">' . esc_html( $r['keyword'] ) . '</td>'
				. '<td style="padding:10px 12px;font-size:13px;"><a href="' . esc_url( $r['cited_url'] ) . '" style="color:#0F5C6B;">' . esc_html( $title ) . '</a></td>'
				. '</tr>';
		}

		$dashboard_url = admin_url( 'admin.php?page=agentgarrison&tab=citation-monitor' );

		ob_start();
		?>
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#F7F9FA;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F7F9FA;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

  <tr><td style="background:#0F5C6B;padding:26px 32px;">
    <p style="margin:0;color:rgba(255,255,255,.7);font-size:12px;text-transform:uppercase;letter-spacing:.06em;">
      <?php echo esc_html( $brand ); ?> · Citation Monitor
    </p>
    <h1 style="margin:6px 0 0;color:#fff;font-size:22px;font-weight:700;">
      <?php
      printf(
        /* translators: %d: number of new citations */
        esc_html( _n( '%d new AI citation', '%d new AI citations', $count, 'agentgarrison' ) ),
        absint( $count )
      );
      ?>
    </h1>
  </td></tr>

  <tr><td style="padding:24px 32px;">
    <p style="margin:0 0 16px;font-size:14px;color:#1A2B2E;">
      <?php esc_html_e( 'AI platforms cited your content since the last scan:', 'agentgarrison' ); ?>
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E0E8EA;border-radius:8px;overflow:hidden;">
      <tr style="background:#F7F9FA;">
        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#5C7880;"><?php esc_html_e( 'Platform', 'agentgarrison' ); ?></th>
        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#5C7880;"><?php esc_html_e( 'Keyword', 'agentgarrison' ); ?></th>
        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#5C7880;"><?php esc_html_e( 'Cited Page', 'agentgarrison' ); ?></th>
      </tr>
      <?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows built with esc_html / esc_url above ?>
    </table>
  </td></tr>

  <tr><td style="padding:0 32px 32px;text-align:center;">
    <a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;background:#0F5C6B;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;">
      <?php esc_html_e( 'View Citation Monitor →', 'agentgarrison' ); ?>
    </a>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	private function scan_keyword( $keyword_id, $keyword ) {
		global $wpdb;
		$table    = $wpdb->prefix . Rayetun_AG_DB::CITATION_RESULTS_TABLE;
		$site_url = home_url( '/' );

		$site_domain = wp_parse_url( $site_url, PHP_URL_HOST );

		// Query every backend available right now. On WP 6.x with BYO keys this loops
		// OpenAI + Perplexity exactly as before; on WP 7.0+ it is the core AI Client.
		foreach ( Rayetun_AG_AI_Provider::active_backends() as $backend ) {
			$result = Rayetun_AG_AI_Provider::complete( $keyword, $backend, array( 'max_tokens' => 500 ) );
			if ( is_wp_error( $result ) ) {
				continue;
			}

			// Map the backend to the platform label the UI already knows.
			$platform = 'core';
			if ( 'openai' === $backend ) {
				$platform = 'chatgpt';
			} elseif ( 'perplexity' === $backend ) {
				$platform = 'perplexity';
			} elseif ( 'anthropic' === $backend ) {
				$platform = 'claude';
			}

			$found = $this->extract_and_store_citations(
				$keyword_id,
				$platform,
				$result['content'],
				$site_url,
				$table,
				$result['citations']
			);

			// Closed-loop outcome: record cited/not-cited + which competitors were cited.
			$competitors = $this->extract_competitor_domains( $result['content'], $result['citations'], $site_domain );
			$this->record_scan( $keyword_id, $platform, ! empty( $found ), $competitors, $result['content'], 0 );
		}
	}

	/**
	 * Pull external domains cited in an answer, excluding the site's own domain.
	 *
	 * @param string   $content            AI answer text.
	 * @param string[] $explicit_citations Provider-supplied citation URLs (Perplexity).
	 * @param string   $site_domain        This site's host, to exclude.
	 * @return string[] Unique competitor domains.
	 */
	private function extract_competitor_domains( $content, $explicit_citations, $site_domain ) {
		$urls = (array) $explicit_citations;
		if ( preg_match_all( '#https?://[^\s\]\)"\'<>]+#i', (string) $content, $m ) ) {
			$urls = array_merge( $urls, $m[0] );
		}

		$site_domain = strtolower( preg_replace( '/^www\./i', '', (string) $site_domain ) );
		$domains     = array();
		foreach ( $urls as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( ! $host ) {
				continue;
			}
			$host = strtolower( preg_replace( '/^www\./i', '', $host ) );
			if ( $host && $host !== $site_domain ) {
				$domains[ $host ] = true;
			}
		}
		return array_keys( $domains );
	}

	/**
	 * Write one closed-loop outcome row.
	 *
	 * @param int      $keyword_id  Question/keyword id.
	 * @param string   $platform    Platform key.
	 * @param bool     $cited       Whether this site was cited.
	 * @param string[] $competitors Competitor domains cited instead.
	 * @param string   $answer      Full answer text (excerpt stored).
	 * @param int      $is_demo     1 for demo data.
	 */
	private function record_scan( $keyword_id, $platform, $cited, $competitors, $answer, $is_demo ) {
		global $wpdb;
		$scans_table = $wpdb->prefix . Rayetun_AG_DB::CITATION_SCANS_TABLE;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$scans_table,
			array(
				'keyword_id'         => absint( $keyword_id ),
				'platform'           => $platform,
				'cited'              => $cited ? 1 : 0,
				'competitor_domains' => wp_json_encode( array_values( array_slice( (array) $competitors, 0, 20 ) ) ),
				'answer_excerpt'     => sanitize_textarea_field( function_exists( 'mb_substr' ) ? mb_substr( (string) $answer, 0, 500 ) : substr( (string) $answer, 0, 500 ) ),
				'is_demo'            => $is_demo ? 1 : 0,
				'scanned_at'         => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	private function extract_and_store_citations( $keyword_id, $platform, $content, $site_url, $table, $explicit_citations = array() ) {
		global $wpdb;

		$site_domain = wp_parse_url( $site_url, PHP_URL_HOST );
		$found       = array();

		// Check explicit citation list (Perplexity provides these).
		foreach ( $explicit_citations as $url ) {
			if ( false !== strpos( $url, $site_domain ) ) {
				$found[] = $url;
			}
		}

		// Fallback: scan response text for site URLs.
		if ( empty( $found ) && $site_domain ) {
			preg_match_all( '/https?:\/\/' . preg_quote( $site_domain, '/' ) . '[^\s\]\)\"]+/i', $content, $matches );
			if ( ! empty( $matches[0] ) ) {
				$found = array_unique( $matches[0] );
			}
		}

		foreach ( $found as $cited_url ) {
			$post_id = url_to_postid( $cited_url );
			$snippet = '';

			// Extract surrounding sentence from response.
			if ( $site_domain ) {
				$escaped = preg_quote( $site_domain, '/' );
				if ( preg_match( '/([^.!?]*' . $escaped . '[^.!?]*[.!?])/', $content, $m ) ) {
					$snippet = '"' . trim( $m[1] ) . '"';
				}
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'keyword_id'       => absint( $keyword_id ),
					'platform'         => $platform,
					'cited_url'        => esc_url_raw( $cited_url ),
					'post_id'          => absint( $post_id ),
					'context_snippet'  => sanitize_textarea_field( $snippet ),
					'confidence_score' => 90,
					'is_demo'          => 0,
					'checked_at'       => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
			);
		}

		return $found;
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function handle_add_keyword() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$keyword = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
		if ( ! $keyword ) {
			wp_send_json_error( array( 'message' => __( 'Keyword cannot be empty.', 'agentgarrison' ) ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'keyword' => $keyword, 'added_at' => current_time( 'mysql', true ) ),
			array( '%s', '%s' )
		);
		wp_send_json_success( array( 'id' => $wpdb->insert_id, 'keyword' => $keyword ) );
	}

	public function handle_delete_keyword() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'agentgarrison' ) ) );
		}
		global $wpdb;
		$kw_table    = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;
		$res_table   = $wpdb->prefix . Rayetun_AG_DB::CITATION_RESULTS_TABLE;
		$scans_table = $wpdb->prefix . Rayetun_AG_DB::CITATION_SCANS_TABLE;
		$wpdb->delete( $kw_table,    array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $res_table,   array( 'keyword_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $scans_table, array( 'keyword_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_send_json_success();
	}

	public function handle_get_citations() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		global $wpdb;
		$kw_table  = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;
		$res_table = $wpdb->prefix . Rayetun_AG_DB::CITATION_RESULTS_TABLE;

		$keywords = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY added_at DESC', $kw_table ),
			ARRAY_A
		);

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT r.*, k.keyword FROM %i r
				 LEFT JOIN %i k ON r.keyword_id = k.id
				 ORDER BY r.checked_at DESC LIMIT 100',
				$res_table,
				$kw_table
			),
			ARRAY_A
		);

		// Attach post titles.
		foreach ( $results as &$row ) {
			$row['post_title'] = $row['post_id'] ? get_the_title( (int) $row['post_id'] ) : '';
		}
		unset( $row );

		// "Live" scanning is possible via the core AI Client (WP 7.0+) OR a BYO key.
		$has_api = Rayetun_AG_AI_Provider::is_available();

		wp_send_json_success( array(
			'keywords'  => $keywords,
			'results'   => $results,
			'has_api'   => $has_api,
			'platforms' => $this->platforms,
			'total'     => count( $results ),
			'scorecard' => $this->get_scorecard(),
		) );
	}

	public function handle_demo_scan() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		global $wpdb;
		$kw_table = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;
		$keywords = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM %i', $kw_table ),
			ARRAY_A
		);

		if ( empty( $keywords ) ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one keyword first.', 'agentgarrison' ) ) );
		}

		$total = 0;
		foreach ( $keywords as $kw ) {
			$total += $this->generate_demo_results( $kw['id'], $kw['keyword'] );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of results */
				__( 'Demo scan complete. Generated %d citation examples from your content.', 'agentgarrison' ),
				$total
			),
		) );
	}

	/**
	 * Run a live scan on demand (WP 7.0 core AI Client or a saved API key).
	 * This is the same routine the twice-daily cron runs, exposed as a button so
	 * users can populate the real scorecard immediately instead of waiting.
	 */
	public function handle_live_scan() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		if ( ! Rayetun_AG_AI_Provider::is_available() ) {
			wp_send_json_error( array( 'message' => __( 'No AI backend is available. On WordPress 7.0+ configure a provider under Settings → Connectors, or add an API key below.', 'agentgarrison' ) ) );
		}

		global $wpdb;
		$kw_table  = $wpdb->prefix . Rayetun_AG_DB::CITATION_KEYWORDS_TABLE;
		$has_kw    = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $kw_table )
		);
		if ( ! $has_kw ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one question first.', 'agentgarrison' ) ) );
		}

		$this->run_scan();

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %s: active AI backend label */
				__( 'Live scan complete via %s. Scorecard updated.', 'agentgarrison' ),
				Rayetun_AG_AI_Provider::status_label()
			),
		) );
	}

	public function handle_save_api_keys() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$keys = array(
			'anthropic'  => sanitize_text_field( wp_unslash( $_POST['anthropic'] ?? '' ) ),
			'openai'     => sanitize_text_field( wp_unslash( $_POST['openai'] ?? '' ) ),
			'perplexity' => sanitize_text_field( wp_unslash( $_POST['perplexity'] ?? '' ) ),
		);
		update_option( 'rayetun_ag_citation_api_keys', $keys );
		wp_send_json_success( array( 'message' => __( 'API keys saved.', 'agentgarrison' ) ) );
	}

	public function handle_clear_citations() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		global $wpdb;
		$table   = $wpdb->prefix . Rayetun_AG_DB::CITATION_RESULTS_TABLE;
		$scans   = $wpdb->prefix . Rayetun_AG_DB::CITATION_SCANS_TABLE;
		$is_demo = ! empty( $_POST['demo_only'] );
		if ( $is_demo ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE is_demo = 1', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE is_demo = 1', $scans ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $scans ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		wp_send_json_success( array( 'message' => __( 'Citations cleared.', 'agentgarrison' ) ) );
	}

	public function handle_save_citation_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$allowed_freq = array( 'daily', 'weekly', 'monthly' );
		$frequency    = sanitize_key( wp_unslash( $_POST['scan_frequency'] ?? 'daily' ) );
		if ( ! in_array( $frequency, $allowed_freq, true ) ) {
			$frequency = 'daily';
		}

		$notify_enabled = ! empty( $_POST['notify_enabled'] );

		$settings = array(
			'scan_frequency' => $frequency,
			'notify_enabled' => $notify_enabled,
			'notify_email'   => sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) ),
		);
		update_option( 'rayetun_ag_citation_settings', $settings );

		// Re-arm the scan cron at the chosen frequency.
		$this->reschedule_scan();

		// When notifications are first switched on, set the baseline to "now" so the
		// next scan only reports citations found AFTER this point (no backlog blast).
		if ( $notify_enabled && ! get_option( 'rayetun_ag_citation_last_notified', 0 ) ) {
			update_option( 'rayetun_ag_citation_last_notified', time() );
		}

		wp_send_json_success( array( 'message' => __( 'Scan & notification settings saved.', 'agentgarrison' ) ) );
	}

	public function get_platforms() {
		return $this->platforms;
	}
}
