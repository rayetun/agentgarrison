<?php
/**
 * Phase 4 — White-label PDF Reports.
 *
 * Generates a styled HTML report covering AI Visibility Score, bot analytics,
 * LLM referrals, content scores, and citations. Users print-to-PDF from their
 * browser — no server-side PDF library required.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Reports {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_rayetun_ag_save_report_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_generate_report',      array( $this, 'handle_generate_report' ) );
	}

	// -------------------------------------------------------------------------
	// Data collection
	// -------------------------------------------------------------------------

	private function collect_report_data( $days = 30 ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$visits_table    = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$referrals_table = $wpdb->prefix . Rayetun_AG_DB::REFERRALS_TABLE;

		$score_data = Rayetun_AG_Visibility_Score::get_instance()->get_score();

		$total_visits = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $visits_table, $since )
		);

		$by_bot = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT bot_name, COUNT(*) as visits FROM %i WHERE visited_at > %s GROUP BY bot_name ORDER BY visits DESC LIMIT 8',
				$visits_table,
				$since
			),
			ARRAY_A
		);

		$total_referrals = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $referrals_table, $since )
		);

		$by_platform = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT platform, COUNT(*) as cnt FROM %i WHERE visited_at > %s GROUP BY platform ORDER BY cnt DESC',
				$referrals_table,
				$since
			),
			ARRAY_A
		);

		$top_pages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT page_url, COUNT(*) as visits FROM %i WHERE visited_at > %s GROUP BY page_url ORDER BY visits DESC LIMIT 10',
				$visits_table,
				$since
			),
			ARRAY_A
		);

		// Content scores.
		$scored_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT p.post_title, pm.meta_value as score
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_rayetun_ag_ai_score'
			 AND p.post_status = 'publish'
			 ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC LIMIT 10",
			ARRAY_A
		);

		return array(
			'period'          => $days,
			'score'           => $score_data,
			'total_visits'    => $total_visits,
			'by_bot'          => $by_bot,
			'total_referrals' => $total_referrals,
			'by_platform'     => $by_platform,
			'top_pages'       => $top_pages,
			'scored_posts'    => $scored_posts,
			'site_name'       => get_bloginfo( 'name' ),
			'site_url'        => home_url( '/' ),
			'generated_at'    => current_time( 'mysql' ),
		);
	}

	// -------------------------------------------------------------------------
	// Report HTML builder
	// -------------------------------------------------------------------------

	private function build_report_html( $data, $branding ) {
		$company_name   = $branding['company_name'] ?? get_bloginfo( 'name' );
		$accent_color   = $branding['accent_color']  ?? '#0F5C6B';
		$logo_url       = $branding['logo_url']       ?? '';
		$footer_text    = $branding['footer_text']    ?? '';

		$score          = absint( $data['score']['score'] ?? 0 );
		$grade          = esc_html( $data['score']['grade'] ?? 'F' );
		$pillars        = $data['score']['pillars'] ?? array();

		// SVG donut chart for score.
		$circumference  = 2 * M_PI * 45;
		$dash_offset    = $circumference - ( $score / 100 ) * $circumference;
		$score_color    = $score >= 70 ? '#2E7D32' : ( $score >= 50 ? '#F0A500' : '#C62828' );

		// This report is a self-contained document opened in a new tab / printed to
		// PDF, so it has no wp_head(). Register the stylesheet and print it with
		// wp_print_styles() — proper enqueuing, and no literal <link> tag in source.
		wp_register_style( 'rayetun-ag-report', RAYETUN_AG_URL . 'admin/css/rayetun-ag-report.css', array(), RAYETUN_AG_VERSION );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $company_name ); ?> — AI Visibility Report</title>
<?php wp_print_styles( 'rayetun-ag-report' ); ?>
</head>
<body style="--ag-report-accent: <?php echo esc_attr( $accent_color ); ?>;">
<div class="report-page">

<!-- Header -->
<div class="report-header">
  <div>
    <?php if ( $logo_url ) : ?>
    <div class="report-header__logo"><img src="<?php echo esc_url( $logo_url ); ?>" alt=""></div>
    <?php else : ?>
    <div class="report-header__brand"><?php echo esc_html( $company_name ); ?></div>
    <?php endif; ?>
    <div style="font-size:13px;color:#5C7880;margin-top:4px;"><?php esc_html_e( 'AI Visibility Report', 'agentgarrison' ); ?></div>
  </div>
  <div class="report-header__meta">
    <div><?php echo esc_html( $data['site_name'] ); ?></div>
    <div><?php echo esc_url( $data['site_url'] ); ?></div>
    <div><?php echo esc_html( human_time_diff( strtotime( $data['generated_at'] ), time() ) . ' ago' ); ?></div>
    <div><?php
      /* translators: %d: number of days in the report period */
      printf( esc_html__( '%d-day report period', 'agentgarrison' ), absint( $data['period'] ) );
    ?></div>
  </div>
</div>

<!-- AI Visibility Score -->
<div class="report-section">
  <div class="report-section__title"><?php esc_html_e( 'AI Visibility Score', 'agentgarrison' ); ?></div>
  <div class="score-row">
    <div class="score-ring-wrap">
      <svg class="score-ring" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="45" fill="none" stroke="#E0E8EA" stroke-width="8"/>
        <circle cx="50" cy="50" r="45" fill="none" stroke="<?php echo esc_attr( $score_color ); ?>"
          stroke-width="8"
          stroke-dasharray="<?php echo esc_attr( $circumference ); ?>"
          stroke-dashoffset="<?php echo esc_attr( $dash_offset ); ?>"
          stroke-linecap="round"
          transform="rotate(-90 50 50)"/>
        <text x="50" y="45" text-anchor="middle" style="font-size:20px;font-weight:800;fill:#1A2B2E;"><?php echo absint( $score ); ?></text>
        <text x="50" y="60" text-anchor="middle" style="font-size:11px;fill:#5C7880;"><?php echo esc_html( $grade ); ?></text>
      </svg>
    </div>
    <div class="score-meta">
      <div class="pillars-grid">
        <?php foreach ( $pillars as $rayetun_ag_rp ) :
          $rayetun_ag_pc = absint( $rayetun_ag_rp['score'] );
          $rayetun_ag_col = $rayetun_ag_pc >= 70 ? '#2E7D32' : ( $rayetun_ag_pc >= 50 ? '#F0A500' : '#C62828' );
        ?>
        <div class="pillar-item">
          <div class="pillar-label"><?php echo esc_html( $rayetun_ag_rp['label'] ); ?></div>
          <div class="pillar-score" style="color:<?php echo esc_attr( $rayetun_ag_col ); ?>;"><?php echo absint( $rayetun_ag_pc ); ?>/100</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="report-section">
  <div class="report-section__title"><?php esc_html_e( 'Overview', 'agentgarrison' ); ?></div>
  <div class="stat-row">
    <div class="stat-box">
      <div class="stat-box__number"><?php echo absint( $data['total_visits'] ); ?></div>
      <div class="stat-box__label"><?php
        /* translators: %d: number of days */
        printf( esc_html__( 'Bot Visits (%d days)', 'agentgarrison' ), absint( $data['period'] ) );
      ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-box__number"><?php echo absint( $data['total_referrals'] ); ?></div>
      <div class="stat-box__label"><?php
        /* translators: %d: number of days */
        printf( esc_html__( 'LLM Referrals (%d days)', 'agentgarrison' ), absint( $data['period'] ) );
      ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-box__number"><?php echo absint( count( $data['scored_posts'] ) ); ?></div>
      <div class="stat-box__label"><?php esc_html_e( 'Posts Scored', 'agentgarrison' ); ?></div>
    </div>
  </div>
</div>

<!-- Top Bots -->
<?php if ( ! empty( $data['by_bot'] ) ) : ?>
<div class="report-section">
  <div class="report-section__title"><?php esc_html_e( 'Top AI Bots by Visits', 'agentgarrison' ); ?></div>
  <table>
    <thead><tr><th><?php esc_html_e( 'Bot', 'agentgarrison' ); ?></th><th><?php esc_html_e( 'Visits', 'agentgarrison' ); ?></th><th><?php esc_html_e( 'Share', 'agentgarrison' ); ?></th></tr></thead>
    <tbody>
      <?php
      $rayetun_ag_max_v = max( array_column( $data['by_bot'], 'visits' ) );
      foreach ( $data['by_bot'] as $rayetun_ag_rb ) :
        $rayetun_ag_pct_w = $rayetun_ag_max_v > 0 ? round( $rayetun_ag_rb['visits'] / $rayetun_ag_max_v * 100 ) : 0;
      ?>
      <tr>
        <td><?php echo esc_html( $rayetun_ag_rb['bot_name'] ); ?></td>
        <td><?php echo absint( $rayetun_ag_rb['visits'] ); ?></td>
        <td>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?php echo absint( $rayetun_ag_pct_w ); ?>%;"></div></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- LLM Referrals -->
<?php if ( ! empty( $data['by_platform'] ) ) : ?>
<div class="report-section">
  <div class="report-section__title"><?php esc_html_e( 'LLM Referrals by Platform', 'agentgarrison' ); ?></div>
  <table>
    <thead><tr><th><?php esc_html_e( 'Platform', 'agentgarrison' ); ?></th><th><?php esc_html_e( 'Referrals', 'agentgarrison' ); ?></th></tr></thead>
    <tbody>
      <?php foreach ( $data['by_platform'] as $rayetun_ag_rref ) : ?>
      <tr>
        <td><?php echo esc_html( ucfirst( $rayetun_ag_rref['platform'] ) ); ?></td>
        <td><?php echo absint( $rayetun_ag_rref['cnt'] ); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Content Scores -->
<?php if ( ! empty( $data['scored_posts'] ) ) : ?>
<div class="report-section">
  <div class="report-section__title"><?php esc_html_e( 'Content Scores — Posts Needing Attention', 'agentgarrison' ); ?></div>
  <table>
    <thead><tr><th><?php esc_html_e( 'Post', 'agentgarrison' ); ?></th><th style="width:80px;"><?php esc_html_e( 'AI Score', 'agentgarrison' ); ?></th></tr></thead>
    <tbody>
      <?php foreach ( $data['scored_posts'] as $rayetun_ag_rsp ) :
        $rayetun_ag_sc = absint( $rayetun_ag_rsp['score'] );
        $rayetun_ag_sc_col = $rayetun_ag_sc >= 70 ? '#2E7D32' : ( $rayetun_ag_sc >= 50 ? '#F0A500' : '#C62828' );
      ?>
      <tr>
        <td><?php echo esc_html( $rayetun_ag_rsp['post_title'] ); ?></td>
        <td><strong style="color:<?php echo esc_attr( $rayetun_ag_sc_col ); ?>;"><?php echo absint( $rayetun_ag_sc ); ?>/100</strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Footer -->
<div class="report-footer">
  <span><?php echo esc_html( $footer_text ?: 'Generated by AgentGarrison' ); ?></span>
  <span><?php echo esc_html( gmdate( 'F j, Y', strtotime( $data['generated_at'] ) ) ); ?></span>
</div>

</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function handle_save_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$settings = array(
			'company_name' => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
			'accent_color' => sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '#0F5C6B' ) ) ?: '#0F5C6B',
			'logo_url'     => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
			'footer_text'  => sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? '' ) ),
		);
		update_option( 'rayetun_ag_report_settings', $settings );
		wp_send_json_success( array( 'message' => __( 'Report settings saved.', 'agentgarrison' ) ) );
	}

	public function handle_generate_report() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$days     = absint( $_POST['days'] ?? 30 );
		$days     = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		$branding = get_option( 'rayetun_ag_report_settings', array() );
		$data     = $this->collect_report_data( $days );
		$html     = $this->build_report_html( $data, $branding );

		wp_send_json_success( array(
			'html'    => $html,
			'message' => __( 'Report generated. Click Download to save.', 'agentgarrison' ),
		) );
	}

	public function get_settings() {
		return wp_parse_args( get_option( 'rayetun_ag_report_settings', array() ), array(
			'company_name' => '',
			'accent_color' => '#0F5C6B',
			'logo_url'     => '',
			'footer_text'  => '',
		) );
	}
}
