<?php
/**
 * Phase 3 — Email Digest (weekly / monthly bot + referral summary).
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Email_Digest {

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

		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		if ( Rayetun_AG_Modules::is_enabled( 'email_digest' ) ) {
			$this->maybe_schedule();
			add_action( 'rayetun_ag_send_weekly_digest', array( $this, 'send_digest' ) );
			add_action( 'rayetun_ag_send_monthly_digest', array( $this, 'send_digest' ) );
		}

		add_action( 'wp_ajax_rayetun_ag_save_digest_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_send_test_digest', array( $this, 'handle_send_test' ) );
	}

	private function load_settings() {
		$this->settings = wp_parse_args(
			get_option( 'rayetun_ag_digest_settings', array() ),
			array(
				'enabled'   => true,
				'frequency' => 'weekly',
				'email'     => get_option( 'admin_email' ),
				'agency_name' => '',
			)
		);
	}

	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['rayetun_ag_weekly'] ) ) {
			$schedules['rayetun_ag_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly (AgentGarrison)', 'agentgarrison' ),
			);
		}
		if ( ! isset( $schedules['rayetun_ag_monthly'] ) ) {
			$schedules['rayetun_ag_monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly (AgentGarrison)', 'agentgarrison' ),
			);
		}
		return $schedules;
	}

	private function maybe_schedule() {
		$freq  = $this->settings['frequency'];
		$hook  = 'weekly' === $freq ? 'rayetun_ag_send_weekly_digest' : 'rayetun_ag_send_monthly_digest';
		$other = 'weekly' === $freq ? 'rayetun_ag_send_monthly_digest' : 'rayetun_ag_send_weekly_digest';

		// Clear the non-active one.
		$old = wp_next_scheduled( $other );
		if ( $old ) {
			wp_unschedule_event( $old, $other );
		}
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), 'rayetun_ag_' . $freq, $hook );
		}
	}

	// -------------------------------------------------------------------------
	// Digest builder
	// -------------------------------------------------------------------------

	public function build_digest_data( $days = 7 ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$visits_table    = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
		$referrals_table = $wpdb->prefix . Rayetun_AG_DB::REFERRALS_TABLE;

		$total_visits = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $visits_table, $since )
		);

		$top_bots = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT bot_name, COUNT(*) as cnt FROM %i WHERE visited_at > %s GROUP BY bot_name ORDER BY cnt DESC LIMIT 5',
				$visits_table,
				$since
			),
			ARRAY_A
		);

		$total_referrals = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $referrals_table, $since )
		);

		$top_referral_platforms = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT platform, COUNT(*) as cnt FROM %i WHERE visited_at > %s GROUP BY platform ORDER BY cnt DESC LIMIT 5',
				$referrals_table,
				$since
			),
			ARRAY_A
		);

		$score_data = Rayetun_AG_Visibility_Score::get_instance()->get_score();

		return array(
			'period'                 => $days,
			'total_visits'           => $total_visits,
			'top_bots'               => $top_bots,
			'total_referrals'        => $total_referrals,
			'top_referral_platforms' => $top_referral_platforms,
			'visibility_score'       => $score_data['score'] ?? 0,
			'visibility_grade'       => $score_data['grade'] ?? 'F',
			'site_name'              => get_bloginfo( 'name' ),
			'site_url'               => home_url( '/' ),
			'dashboard_url'          => admin_url( 'admin.php?page=agentgarrison' ),
		);
	}

	public function send_digest( $days = null ) {
		$freq  = $this->settings['frequency'];
		$days  = is_int( $days ) ? $days : ( 'monthly' === $freq ? 30 : 7 );
		$email = sanitize_email( $this->settings['email'] );
		if ( ! $email ) {
			return;
		}

		$data    = $this->build_digest_data( $days );
		$subject = sprintf(
			/* translators: 1: site name, 2: period label */
			__( '[AgentGarrison] %1$s — %2$s AI Visibility Report', 'agentgarrison' ),
			$data['site_name'],
			'monthly' === $freq ? __( 'Monthly', 'agentgarrison' ) : __( 'Weekly', 'agentgarrison' )
		);

		$body = $this->build_email_html( $data );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $email, $subject, $body, $headers );
	}

	private function build_email_html( $data ) {
		$agency = sanitize_text_field( $this->settings['agency_name'] );
		$from   = $agency ? $agency : 'AgentGarrison';

		$top_bots_html = '';
		foreach ( $data['top_bots'] as $bot ) {
			$top_bots_html .= '<tr style="border-bottom:1px solid #E0E8EA;">'
				. '<td style="padding:8px 12px;">' . esc_html( $bot['bot_name'] ) . '</td>'
				. '<td style="padding:8px 12px;text-align:right;font-weight:700;">' . absint( $bot['cnt'] ) . '</td>'
				. '</tr>';
		}

		$ref_html = '';
		foreach ( $data['top_referral_platforms'] as $ref ) {
			$ref_html .= '<tr style="border-bottom:1px solid #E0E8EA;">'
				. '<td style="padding:8px 12px;">' . esc_html( ucfirst( $ref['platform'] ) ) . '</td>'
				. '<td style="padding:8px 12px;text-align:right;font-weight:700;">' . absint( $ref['cnt'] ) . '</td>'
				. '</tr>';
		}

		ob_start();
		?>
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#F7F9FA;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F7F9FA;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

  <!-- Header -->
  <tr><td style="background:#0F5C6B;padding:28px 32px;">
    <p style="margin:0;color:rgba(255,255,255,.7);font-size:12px;text-transform:uppercase;letter-spacing:.06em;">
      <?php echo esc_html( $from ); ?> · AI Visibility Report
    </p>
    <h1 style="margin:6px 0 0;color:#fff;font-size:22px;font-weight:700;">
      <?php echo esc_html( $data['site_name'] ); ?>
    </h1>
  </td></tr>

  <!-- Score banner -->
  <tr><td style="background:#E8F4F6;padding:20px 32px;border-bottom:1px solid #E0E8EA;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td>
          <p style="margin:0;font-size:13px;color:#5C7880;"><?php esc_html_e( 'AI Visibility Score', 'agentgarrison' ); ?></p>
          <p style="margin:4px 0 0;font-size:36px;font-weight:800;color:#0F5C6B;line-height:1;">
            <?php echo absint( $data['visibility_score'] ); ?><span style="font-size:16px;color:#5C7880;font-weight:600;"> / 100</span>
          </p>
        </td>
        <td align="right">
          <span style="display:inline-block;background:#0F5C6B;color:#fff;font-size:22px;font-weight:800;padding:10px 20px;border-radius:8px;">
            <?php echo esc_html( $data['visibility_grade'] ); ?>
          </span>
        </td>
      </tr>
    </table>
  </td></tr>

  <!-- Stats row -->
  <tr><td style="padding:24px 32px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td width="50%" style="padding-right:12px;">
          <div style="background:#F7F9FA;border:1px solid #E0E8EA;border-radius:8px;padding:16px 20px;">
            <p style="margin:0;font-size:28px;font-weight:800;color:#1A2B2E;"><?php echo absint( $data['total_visits'] ); ?></p>
            <p style="margin:4px 0 0;font-size:12px;color:#5C7880;">
              <?php
              printf(
                /* translators: %d number of days */
                esc_html__( 'Bot Visits (%d days)', 'agentgarrison' ),
                absint( $data['period'] )
              );
              ?>
            </p>
          </div>
        </td>
        <td width="50%" style="padding-left:12px;">
          <div style="background:#F7F9FA;border:1px solid #E0E8EA;border-radius:8px;padding:16px 20px;">
            <p style="margin:0;font-size:28px;font-weight:800;color:#1A2B2E;"><?php echo absint( $data['total_referrals'] ); ?></p>
            <p style="margin:4px 0 0;font-size:12px;color:#5C7880;">
              <?php
              printf(
                /* translators: %d number of days */
                esc_html__( 'LLM Referrals (%d days)', 'agentgarrison' ),
                absint( $data['period'] )
              );
              ?>
            </p>
          </div>
        </td>
      </tr>
    </table>
  </td></tr>

  <!-- Top bots -->
  <?php if ( ! empty( $data['top_bots'] ) ) : ?>
  <tr><td style="padding:0 32px 24px;">
    <h3 style="font-size:14px;font-weight:700;color:#1A2B2E;margin:0 0 10px;"><?php esc_html_e( 'Top AI Bots', 'agentgarrison' ); ?></h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E0E8EA;border-radius:8px;overflow:hidden;">
      <tr style="background:#F7F9FA;">
        <th style="padding:8px 12px;text-align:left;font-size:11px;color:#5C7880;text-transform:uppercase;font-weight:700;"><?php esc_html_e( 'Bot', 'agentgarrison' ); ?></th>
        <th style="padding:8px 12px;text-align:right;font-size:11px;color:#5C7880;text-transform:uppercase;font-weight:700;"><?php esc_html_e( 'Visits', 'agentgarrison' ); ?></th>
      </tr>
      <?php echo $top_bots_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with esc_html above ?>
    </table>
  </td></tr>
  <?php endif; ?>

  <!-- LLM referrals -->
  <?php if ( ! empty( $data['top_referral_platforms'] ) ) : ?>
  <tr><td style="padding:0 32px 24px;">
    <h3 style="font-size:14px;font-weight:700;color:#1A2B2E;margin:0 0 10px;"><?php esc_html_e( 'LLM Referrals by Platform', 'agentgarrison' ); ?></h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E0E8EA;border-radius:8px;overflow:hidden;">
      <tr style="background:#F7F9FA;">
        <th style="padding:8px 12px;text-align:left;font-size:11px;color:#5C7880;text-transform:uppercase;font-weight:700;"><?php esc_html_e( 'Platform', 'agentgarrison' ); ?></th>
        <th style="padding:8px 12px;text-align:right;font-size:11px;color:#5C7880;text-transform:uppercase;font-weight:700;"><?php esc_html_e( 'Referrals', 'agentgarrison' ); ?></th>
      </tr>
      <?php echo $ref_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with esc_html above ?>
    </table>
  </td></tr>
  <?php endif; ?>

  <!-- CTA -->
  <tr><td style="padding:0 32px 32px;text-align:center;">
    <a href="<?php echo esc_url( $data['dashboard_url'] ); ?>"
       style="display:inline-block;background:#0F5C6B;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;">
      <?php esc_html_e( 'View Full Dashboard →', 'agentgarrison' ); ?>
    </a>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#F7F9FA;border-top:1px solid #E0E8EA;padding:16px 32px;text-align:center;">
    <p style="margin:0;font-size:11px;color:#5C7880;">
      <?php
      printf(
        /* translators: 1: plugin name, 2: site URL */
        esc_html__( 'Sent by %1$s for %2$s', 'agentgarrison' ),
        'AgentGarrison',
        esc_html( $data['site_name'] )
      );
      ?>
    </p>
  </td></tr>

</table>
</td></tr>
</table>
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
		$allowed_freq = array( 'weekly', 'monthly' );
		$freq         = sanitize_key( wp_unslash( $_POST['frequency'] ?? 'weekly' ) );
		if ( ! in_array( $freq, $allowed_freq, true ) ) {
			$freq = 'weekly';
		}
		$settings = array(
			'enabled'     => ! empty( $_POST['enabled'] ),
			'frequency'   => $freq,
			'email'       => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'agency_name' => sanitize_text_field( wp_unslash( $_POST['agency_name'] ?? '' ) ),
		);
		update_option( 'rayetun_ag_digest_settings', $settings );
		$this->settings = $settings;
		$this->maybe_schedule();
		wp_send_json_success( array( 'message' => __( 'Digest settings saved.', 'agentgarrison' ) ) );
	}

	public function handle_send_test() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$this->send_digest( 7 );
		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %s: recipient email address */
				__( 'Test digest sent to %s', 'agentgarrison' ),
				sanitize_email( $this->settings['email'] )
			),
		) );
	}

	public function get_settings() {
		return $this->settings;
	}
}
