<?php
/**
 * Dashboard view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_score_data    = Rayetun_AG_Visibility_Score::get_instance()->get_score();
$rayetun_ag_score         = $rayetun_ag_score_data['score'] ?? 0;
$rayetun_ag_grade         = $rayetun_ag_score_data['grade'] ?? 'F';
$rayetun_ag_pillars       = $rayetun_ag_score_data['pillars'] ?? array();
$rayetun_ag_checklist     = $rayetun_ag_score_data['checklist'] ?? array();
$rayetun_ag_analytics     = Rayetun_AG_Analytics::get_instance();
$rayetun_ag_recent_visits = $rayetun_ag_analytics->get_recent_visits( 5 );

$rayetun_ag_circumference = 2 * M_PI * 54;
$rayetun_ag_offset        = $rayetun_ag_circumference - ( $rayetun_ag_score / 100 ) * $rayetun_ag_circumference;
$rayetun_ag_ring_color    = $rayetun_ag_score >= 70 ? '#2E7D32' : ( $rayetun_ag_score >= 50 ? '#F0A500' : '#C62828' );
?>
<div class="agentgarrison-dashboard">
	<div class="agentgarrison-page-header">
		<h1 class="agentgarrison-page-title"><?php esc_html_e( 'Dashboard', 'agentgarrison' ); ?></h1>
		<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Your AI visibility overview', 'agentgarrison' ); ?></p>
	</div>

	<div class="agentgarrison-dashboard-grid">

		<!-- Score Ring -->
		<div class="agentgarrison-card agentgarrison-score-card">
			<div class="agentgarrison-score-ring-wrap">
				<svg class="agentgarrison-score-ring" viewBox="0 0 120 120">
					<circle cx="60" cy="60" r="54" fill="none" stroke="#E0E8EA" stroke-width="10"/>
					<circle cx="60" cy="60" r="54" fill="none"
						stroke="<?php echo esc_attr( $rayetun_ag_ring_color ); ?>"
						stroke-width="10"
						stroke-dasharray="<?php echo esc_attr( $rayetun_ag_circumference ); ?>"
						stroke-dashoffset="<?php echo esc_attr( $rayetun_ag_offset ); ?>"
						stroke-linecap="round"
						transform="rotate(-90 60 60)"/>
					<text class="agentgarrison-score-num" x="60" y="56" text-anchor="middle" style="font-size:24px;font-weight:700;">
						<?php echo absint( $rayetun_ag_score ); ?>
					</text>
					<text class="agentgarrison-score-grade" x="60" y="74" text-anchor="middle" style="font-size:12px;">
						<?php echo esc_html( $rayetun_ag_grade ); ?>
					</text>
				</svg>
			</div>
			<h3 class="agentgarrison-score-label"><?php esc_html_e( 'AI Visibility Score', 'agentgarrison' ); ?></h3>
		</div>

		<!-- Pillars -->
		<div class="agentgarrison-card agentgarrison-pillars-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Score Breakdown', 'agentgarrison' ); ?></h2>
			<div class="agentgarrison-pillars">
				<?php foreach ( $rayetun_ag_pillars as $rayetun_ag_pillar_key => $rayetun_ag_pillar ) :
					$rayetun_ag_pct   = absint( $rayetun_ag_pillar['score'] );
					$rayetun_ag_color = $rayetun_ag_pct >= 70 ? '#2E7D32' : ( $rayetun_ag_pct >= 50 ? '#F0A500' : '#C62828' );
				?>
				<div class="agentgarrison-pillar">
					<div class="agentgarrison-pillar__header">
						<span class="agentgarrison-pillar__label"><?php echo esc_html( $rayetun_ag_pillar['label'] ); ?></span>
						<span class="agentgarrison-pillar__score" style="color:<?php echo esc_attr( $rayetun_ag_color ); ?>">
							<?php echo absint( $rayetun_ag_pct ); ?>/100
						</span>
					</div>
					<div class="agentgarrison-progress-bar">
						<div class="agentgarrison-progress-bar__fill" style="width:<?php echo absint( $rayetun_ag_pct ); ?>%;background:<?php echo esc_attr( $rayetun_ag_color ); ?>;"></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Checklist -->
		<?php if ( ! empty( $rayetun_ag_checklist ) ) :
			// Filter out info-only items with no action for a cleaner list.
			$rayetun_ag_actionable = array_filter( $rayetun_ag_checklist, function( $rayetun_ag_ci ) {
				return ! empty( $rayetun_ag_ci['title'] );
			} );
		?>
		<div class="agentgarrison-card agentgarrison-checklist-card">
			<div class="agentgarrison-card__header">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Improvement Checklist', 'agentgarrison' ); ?></h2>
				<span class="agentgarrison-badge">
					<?php
					printf(
						/* translators: %d: number of tasks */
						esc_html( _n( '%d task', '%d tasks', count( $rayetun_ag_actionable ), 'agentgarrison' ) ),
						absint( count( $rayetun_ag_actionable ) )
					);
					?>
				</span>
			</div>
			<div class="agentgarrison-checklist">
				<?php foreach ( $rayetun_ag_actionable as $rayetun_ag_ci ) :
					$rayetun_ag_priority_map = array(
						'high'   => array( 'label' => __( 'High', 'agentgarrison' ),   'class' => 'is-high' ),
						'medium' => array( 'label' => __( 'Medium', 'agentgarrison' ), 'class' => 'is-medium' ),
						'low'    => array( 'label' => __( 'Info', 'agentgarrison' ),   'class' => 'is-low' ),
					);
					$rayetun_ag_p    = $rayetun_ag_priority_map[ $rayetun_ag_ci['priority'] ] ?? $rayetun_ag_priority_map['low'];
					$rayetun_ag_icon = $rayetun_ag_ci['icon'] ?? '•';
				?>
				<div class="agentgarrison-checklist-item agentgarrison-checklist-item--<?php echo esc_attr( $rayetun_ag_ci['priority'] ); ?>">
					<div class="agentgarrison-checklist-item__icon"><?php echo esc_html( $rayetun_ag_icon ); ?></div>
					<div class="agentgarrison-checklist-item__body">
						<div class="agentgarrison-checklist-item__header">
							<strong class="agentgarrison-checklist-item__title"><?php echo esc_html( $rayetun_ag_ci['title'] ); ?></strong>
							<span class="agentgarrison-priority-badge agentgarrison-priority-badge--<?php echo esc_attr( $rayetun_ag_ci['priority'] ); ?>">
								<?php echo esc_html( $rayetun_ag_p['label'] ); ?>
							</span>
						</div>
						<p class="agentgarrison-checklist-item__desc"><?php echo esc_html( $rayetun_ag_ci['description'] ); ?></p>
					</div>
					<?php if ( ! empty( $rayetun_ag_ci['action_url'] ) ) : ?>
					<a href="<?php echo esc_url( $rayetun_ag_ci['action_url'] ); ?>"
					   class="agentgarrison-btn agentgarrison-btn--secondary agentgarrison-btn--sm agentgarrison-checklist-item__btn">
						<?php echo esc_html( $rayetun_ag_ci['action_label'] ); ?> →
					</a>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Live Bot Activity -->
		<?php
		$rayetun_ag_seed_id = 0;
		if ( ! empty( $rayetun_ag_recent_visits ) ) {
			$rayetun_ag_seed_id = (int) $rayetun_ag_recent_visits[0]['id'];
		}
		?>
		<div class="agentgarrison-card agentgarrison-recent-card agentgarrison-livefeed"
			data-last-id="<?php echo absint( $rayetun_ag_seed_id ); ?>"
			data-enabled="<?php echo Rayetun_AG_Modules::is_enabled( 'analytics' ) ? '1' : '0'; ?>">
			<div class="agentgarrison-card__header">
				<h2 class="agentgarrison-card__title">
					<?php esc_html_e( 'Live Bot Activity', 'agentgarrison' ); ?>
					<span class="agentgarrison-live-dot js-live-dot" title="<?php esc_attr_e( 'Live — updates automatically', 'agentgarrison' ); ?>"></span>
				</h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentgarrison&tab=analytics' ) ); ?>" class="agentgarrison-link">
					<?php esc_html_e( 'View all →', 'agentgarrison' ); ?>
				</a>
			</div>

			<?php if ( ! Rayetun_AG_Modules::is_enabled( 'analytics' ) ) : ?>
				<p class="agentgarrison-empty-state"><?php esc_html_e( 'Enable the Bot Analytics module in Settings to see live bot activity.', 'agentgarrison' ); ?></p>
			<?php else : ?>
				<div class="agentgarrison-livefeed-list js-livefeed-list">
					<?php if ( empty( $rayetun_ag_recent_visits ) ) : ?>
						<p class="agentgarrison-empty-state js-livefeed-empty"><?php esc_html_e( 'Waiting for bot activity… Bots typically crawl within a few days of your site going live.', 'agentgarrison' ); ?></p>
					<?php else : ?>
						<?php foreach ( $rayetun_ag_recent_visits as $rayetun_ag_visit ) : ?>
						<div class="agentgarrison-feed-row" data-id="<?php echo absint( $rayetun_ag_visit['id'] ); ?>">
							<span class="agentgarrison-feed-row__bot agentgarrison-badge"><?php echo esc_html( $rayetun_ag_visit['bot_name'] ); ?></span>
							<span class="agentgarrison-feed-row__path agentgarrison-truncate"><?php echo esc_html( wp_make_link_relative( $rayetun_ag_visit['page_url'] ) ); ?></span>
							<span class="agentgarrison-feed-row__time"><?php echo esc_html( human_time_diff( strtotime( $rayetun_ag_visit['visited_at'] ), time() ) . ' ' . __( 'ago', 'agentgarrison' ) ); ?></span>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

	</div>
</div>
