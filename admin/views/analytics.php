<?php
/**
 * Analytics view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="agentgarrison-analytics">
	<div class="agentgarrison-page-header">
		<div>
			<h1 class="agentgarrison-page-title"><?php esc_html_e( 'AI Bot Analytics', 'agentgarrison' ); ?></h1>
			<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'See which AI bots crawl your site, how often, and which pages they target.', 'agentgarrison' ); ?></p>
		</div>
		<div class="agentgarrison-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=rayetun_ag_export_csv&nonce=' . wp_create_nonce( 'rayetun_ag_export' ) ) ); ?>"
			   class="agentgarrison-btn agentgarrison-btn--secondary">
				<?php esc_html_e( 'Export CSV', 'agentgarrison' ); ?>
			</a>
		</div>
	</div>

	<div class="agentgarrison-analytics-controls">
		<span><?php esc_html_e( 'Date range:', 'agentgarrison' ); ?></span>
		<button class="agentgarrison-range-btn is-active" data-days="7"><?php esc_html_e( '7 days', 'agentgarrison' ); ?></button>
		<button class="agentgarrison-range-btn" data-days="30"><?php esc_html_e( '30 days', 'agentgarrison' ); ?></button>
		<button class="agentgarrison-range-btn" data-days="90"><?php esc_html_e( '90 days', 'agentgarrison' ); ?></button>
	</div>

	<div class="agentgarrison-stat-cards">
		<div class="agentgarrison-stat-card">
			<div class="agentgarrison-stat-card__number js-total-visits">—</div>
			<div class="agentgarrison-stat-card__label"><?php esc_html_e( 'Total Bot Visits', 'agentgarrison' ); ?></div>
		</div>
		<div class="agentgarrison-stat-card">
			<div class="agentgarrison-stat-card__number js-unique-bots">—</div>
			<div class="agentgarrison-stat-card__label"><?php esc_html_e( 'Unique Bots', 'agentgarrison' ); ?></div>
		</div>
	</div>

	<!-- Charts row: timeline + donut side by side -->
	<div class="agentgarrison-analytics-charts-grid">
		<div class="agentgarrison-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Visits Over Time', 'agentgarrison' ); ?></h2>
			<div class="agentgarrison-chart-wrap agentgarrison-chart-wrap--timeline">
				<canvas id="ag-chart-timeline"></canvas>
			</div>
		</div>
		<div class="agentgarrison-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Visits by Bot', 'agentgarrison' ); ?></h2>
			<div class="agentgarrison-chart-wrap agentgarrison-chart-wrap--donut">
				<canvas id="ag-chart-by-bot"></canvas>
			</div>
		</div>
	</div>

	<!-- Top pages full width -->
	<div class="agentgarrison-card">
		<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Top Pages Crawled', 'agentgarrison' ); ?></h2>
		<table class="agentgarrison-table js-top-pages-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page URL', 'agentgarrison' ); ?></th>
					<th class="agentgarrison-col-num"><?php esc_html_e( 'Visits', 'agentgarrison' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td colspan="2" class="agentgarrison-loading"><?php esc_html_e( 'Loading...', 'agentgarrison' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
