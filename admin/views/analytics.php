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

	<!-- Unknown crawlers -->
	<?php
	$rayetun_ag_unknown = Rayetun_AG_Analytics::get_instance()->get_unknown_crawlers( 5, 20 );
	if ( ! empty( $rayetun_ag_unknown ) ) :
	?>
	<div class="agentgarrison-card">
		<div class="agentgarrison-card__header">
			<div>
				<h2 class="agentgarrison-card__title">🕵️ <?php esc_html_e( 'Unknown Crawlers', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc"><?php esc_html_e( 'Bot-like visitors that crawled several pages but match none of AgentGarrison’s known bots — a possible new AI crawler worth watching. Only the user-agent is recorded, never an IP.', 'agentgarrison' ); ?></p>
			</div>
		</div>
		<table class="agentgarrison-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'User-Agent', 'agentgarrison' ); ?></th>
					<th class="agentgarrison-col-num"><?php esc_html_e( 'Pages', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'First Seen', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Last Seen', 'agentgarrison' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rayetun_ag_unknown as $rayetun_ag_ua ) : ?>
				<tr>
					<td><code class="agentgarrison-ua-token"><?php echo esc_html( $rayetun_ag_ua['user_agent'] ); ?></code></td>
					<td class="agentgarrison-col-num"><?php echo absint( $rayetun_ag_ua['hits'] ); ?></td>
					<td class="agentgarrison-last-seen">
						<?php
						/* translators: %s: human time diff */
						printf( esc_html__( '%s ago', 'agentgarrison' ), esc_html( human_time_diff( strtotime( $rayetun_ag_ua['first_seen'] ), time() ) ) );
						?>
					</td>
					<td class="agentgarrison-last-seen">
						<?php
						/* translators: %s: human time diff */
						printf( esc_html__( '%s ago', 'agentgarrison' ), esc_html( human_time_diff( strtotime( $rayetun_ag_ua['last_seen'] ), time() ) ) );
						?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
