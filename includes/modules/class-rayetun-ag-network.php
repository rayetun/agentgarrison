<?php
/**
 * Phase 4 — Multisite Network Dashboard.
 *
 * Adds a Network Admin page that aggregates AI visibility data from every
 * site in the network — scores, bot visits, referrals, and llms.txt status.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Network {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( ! is_multisite() ) {
			return;
		}
		add_action( 'network_admin_menu', array( $this, 'register_network_menu' ) );
		add_action( 'wp_ajax_rayetun_ag_network_data', array( $this, 'handle_network_data' ) );
	}

	public function register_network_menu() {
		$svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3" y="10" width="4" height="11" rx="1" fill="%23a7aaad"/><rect x="10" y="6" width="4" height="15" rx="1" fill="%230F5C6B"/><rect x="17" y="2" width="4" height="19" rx="1" fill="%23a7aaad"/></svg>' );

		add_menu_page(
			__( 'AgentGarrison Network', 'agentgarrison' ),
			__( 'AgentGarrison', 'agentgarrison' ),
			'manage_network_options',
			'agentgarrison-network',
			array( $this, 'render_network_page' ),
			$svg,
			76
		);
	}

	// -------------------------------------------------------------------------
	// Per-site data collection
	// -------------------------------------------------------------------------

	private function get_site_data( $site_id ) {
		switch_to_blog( $site_id );

		$score_cache  = get_option( 'rayetun_ag_visibility_score_cache', array() );
		$llms_settings = get_option( 'rayetun_ag_llms_settings', array() );
		$llms_health  = get_option( 'rayetun_ag_llms_health', array() );

		global $wpdb;
		$visits_table    = $wpdb->prefix . 'rayetun_ag_bot_visits';
		$referrals_table = $wpdb->prefix . 'rayetun_ag_llm_referrals';

		$tables_exist = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
				$visits_table
			)
		);

		$visits_30d = 0;
		$refs_30d   = 0;
		if ( $tables_exist ) {
			$since      = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
			$visits_30d = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $visits_table, $since )
			);
			$refs_30d   = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE visited_at > %s', $referrals_table, $since )
			);
		}

		$blog_details = get_blog_details( $site_id );
		$data = array(
			'site_id'        => $site_id,
			'site_name'      => $blog_details->blogname,
			'site_url'       => $blog_details->siteurl,
			'dashboard_url'  => get_admin_url( $site_id, 'admin.php?page=agentgarrison' ),
			'score'          => absint( $score_cache['score'] ?? 0 ),
			'grade'          => $score_cache['grade'] ?? '—',
			'llms_ok'        => ! empty( $llms_health['ok'] ),
			'llms_generated' => ! empty( $llms_settings['last_generated'] ),
			'visits_30d'     => $visits_30d,
			'refs_30d'       => $refs_30d,
		);

		restore_current_blog();
		return $data;
	}

	// -------------------------------------------------------------------------
	// Network page render
	// -------------------------------------------------------------------------

	public function render_network_page() {
		$sites      = get_sites( array( 'number' => 200 ) );
		$sites_data = array();
		foreach ( $sites as $site ) {
			$sites_data[] = $this->get_site_data( $site->blog_id );
		}

		// Sort by score descending.
		usort( $sites_data, function( $rayetun_ag_na, $rayetun_ag_nb ) {
			return $rayetun_ag_nb['score'] - $rayetun_ag_na['score'];
		} );

		$network_avg  = count( $sites_data ) > 0
			? (int) ( array_sum( array_column( $sites_data, 'score' ) ) / count( $sites_data ) )
			: 0;
		$total_visits = array_sum( array_column( $sites_data, 'visits_30d' ) );
		$total_refs   = array_sum( array_column( $sites_data, 'refs_30d' ) );
		?>
		<div class="wrap agentgarrison-network-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'AgentGarrison — Network Dashboard', 'agentgarrison' ); ?></h1>
			<p class="description" style="margin:6px 0 24px;"><?php esc_html_e( 'AI visibility overview across all sites in this WordPress network.', 'agentgarrison' ); ?></p>

			<!-- Network stats -->
			<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
				<div style="background:#fff;border:1px solid #E0E8EA;border-radius:8px;padding:20px 24px;">
					<div style="font-size:32px;font-weight:800;color:#0F5C6B;"><?php echo absint( $network_avg ); ?></div>
					<div style="font-size:13px;color:#5C7880;margin-top:4px;"><?php esc_html_e( 'Network Average Score', 'agentgarrison' ); ?></div>
				</div>
				<div style="background:#fff;border:1px solid #E0E8EA;border-radius:8px;padding:20px 24px;">
					<div style="font-size:32px;font-weight:800;color:#0F5C6B;"><?php echo absint( $total_visits ); ?></div>
					<div style="font-size:13px;color:#5C7880;margin-top:4px;"><?php esc_html_e( 'Total Bot Visits (30d)', 'agentgarrison' ); ?></div>
				</div>
				<div style="background:#fff;border:1px solid #E0E8EA;border-radius:8px;padding:20px 24px;">
					<div style="font-size:32px;font-weight:800;color:#0F5C6B;"><?php echo absint( $total_refs ); ?></div>
					<div style="font-size:13px;color:#5C7880;margin-top:4px;"><?php esc_html_e( 'LLM Referrals (30d)', 'agentgarrison' ); ?></div>
				</div>
			</div>

			<!-- Site table -->
			<table class="wp-list-table widefat fixed striped" style="border-radius:8px;overflow:hidden;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Site', 'agentgarrison' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'AI Score', 'agentgarrison' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'llms.txt', 'agentgarrison' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Bot Visits (30d)', 'agentgarrison' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'LLM Refs (30d)', 'agentgarrison' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'Actions', 'agentgarrison' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sites_data as $rayetun_ag_ns ) :
						$rayetun_ag_ns_color = $rayetun_ag_ns['score'] >= 70 ? '#2E7D32' : ( $rayetun_ag_ns['score'] >= 50 ? '#F0A500' : '#C62828' );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $rayetun_ag_ns['site_name'] ); ?></strong>
							<span style="display:block;font-size:11px;color:#5C7880;"><?php echo esc_html( $rayetun_ag_ns['site_url'] ); ?></span>
						</td>
						<td>
							<span style="font-size:18px;font-weight:700;color:<?php echo esc_attr( $rayetun_ag_ns_color ); ?>;">
								<?php echo absint( $rayetun_ag_ns['score'] ); ?>
							</span>
							<span style="font-size:12px;color:#5C7880;">/ 100 (<?php echo esc_html( $rayetun_ag_ns['grade'] ); ?>)</span>
						</td>
						<td>
							<?php if ( $rayetun_ag_ns['llms_generated'] ) : ?>
								<span style="color:#2E7D32;font-weight:600;"><?php echo $rayetun_ag_ns['llms_ok'] ? '✓ OK' : '⚠ Error'; ?></span>
							<?php else : ?>
								<span style="color:#C62828;">✗ <?php esc_html_e( 'None', 'agentgarrison' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo absint( $rayetun_ag_ns['visits_30d'] ); ?></td>
						<td><?php echo absint( $rayetun_ag_ns['refs_30d'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $rayetun_ag_ns['dashboard_url'] ); ?>"
							   class="button button-small"
							   target="_blank"><?php esc_html_e( 'Dashboard', 'agentgarrison' ); ?></a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_network_data() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$sites = get_sites( array( 'number' => 200 ) );
		$data  = array_map( function( $site ) {
			return $this->get_site_data( $site->blog_id );
		}, $sites );
		wp_send_json_success( $data );
	}
}
