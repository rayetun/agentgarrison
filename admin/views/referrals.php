<?php
/**
 * LLM Referral Tracker view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="agentgarrison-referrals">
	<div class="agentgarrison-page-header">
		<div>
			<h1 class="agentgarrison-page-title"><?php esc_html_e( 'LLM Referral Tracker', 'agentgarrison' ); ?></h1>
			<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Track human visitors who arrived from ChatGPT, Perplexity, Gemini, Claude, and Copilot citations.', 'agentgarrison' ); ?></p>
		</div>
		<div class="agentgarrison-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=rayetun_ag_export_referrals&nonce=' . wp_create_nonce( 'rayetun_ag_export' ) ) ); ?>"
			   class="agentgarrison-btn agentgarrison-btn--secondary">
				<?php esc_html_e( 'Export CSV', 'agentgarrison' ); ?>
			</a>
		</div>
	</div>

	<div class="agentgarrison-analytics-controls">
		<span><?php esc_html_e( 'Date range:', 'agentgarrison' ); ?></span>
		<button class="agentgarrison-range-btn is-active" data-ref-days="7"><?php esc_html_e( '7 days', 'agentgarrison' ); ?></button>
		<button class="agentgarrison-range-btn" data-ref-days="30"><?php esc_html_e( '30 days', 'agentgarrison' ); ?></button>
		<button class="agentgarrison-range-btn" data-ref-days="90"><?php esc_html_e( '90 days', 'agentgarrison' ); ?></button>
	</div>

	<div class="agentgarrison-stat-cards">
		<div class="agentgarrison-stat-card">
			<div class="agentgarrison-stat-card__number js-ref-total">—</div>
			<div class="agentgarrison-stat-card__label"><?php esc_html_e( 'Total AI Referrals', 'agentgarrison' ); ?></div>
		</div>
		<div class="agentgarrison-stat-card">
			<div class="agentgarrison-stat-card__number js-ref-platforms">—</div>
			<div class="agentgarrison-stat-card__label"><?php esc_html_e( 'Active Platforms', 'agentgarrison' ); ?></div>
		</div>
	</div>

	<div class="agentgarrison-charts-grid">
		<div class="agentgarrison-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Referrals by Platform', 'agentgarrison' ); ?></h2>
			<canvas id="ag-chart-referrals" height="120"></canvas>
		</div>
		<div class="agentgarrison-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Top Cited Pages', 'agentgarrison' ); ?></h2>
			<table class="agentgarrison-table js-ref-pages-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Page', 'agentgarrison' ); ?></th>
						<th><?php esc_html_e( 'Citations', 'agentgarrison' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td colspan="2" class="agentgarrison-loading"><?php esc_html_e( 'Loading...', 'agentgarrison' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
