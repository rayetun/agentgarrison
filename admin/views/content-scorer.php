<?php
/**
 * Content Scorer bulk view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="agentgarrison-content-scorer">
	<div class="agentgarrison-page-header">
		<div>
			<h1 class="agentgarrison-page-title"><?php esc_html_e( 'Content Readability Scorer', 'agentgarrison' ); ?></h1>
			<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'See which posts need work. Fix the lowest scores first for the fastest visibility gains.', 'agentgarrison' ); ?></p>
		</div>
	</div>

	<div class="agentgarrison-analytics-controls">
		<span><?php esc_html_e( 'Filter:', 'agentgarrison' ); ?></span>
		<button class="agentgarrison-range-btn is-active" data-filter="all"><?php esc_html_e( 'All', 'agentgarrison' ); ?></button>
		<button class="agentgarrison-range-btn" data-filter="low"><?php esc_html_e( 'Low (0–49)', 'agentgarrison' ); ?></button>
		<button class="agentgarrison-range-btn" data-filter="mid"><?php esc_html_e( 'Medium (50–69)', 'agentgarrison' ); ?></button>
		<button class="agentgarrison-range-btn" data-filter="good"><?php esc_html_e( 'Good (70+)', 'agentgarrison' ); ?></button>
	</div>

	<div class="agentgarrison-card">
		<table class="agentgarrison-table js-scores-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Type', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'AI Score', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'agentgarrison' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td colspan="4" class="agentgarrison-loading"><?php esc_html_e( 'Loading...', 'agentgarrison' ); ?></td></tr>
			</tbody>
		</table>
		<div class="agentgarrison-pagination js-scores-pagination"></div>
	</div>

	<div class="agentgarrison-callout agentgarrison-callout--info">
		<?php esc_html_e( 'Tip: Posts are scored automatically when saved. Use "Re-score" to refresh after editing content directly in the database or via import.', 'agentgarrison' ); ?>
	</div>
</div>
