<?php
/**
 * Bot Control view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_bot_control = Rayetun_AG_Bot_Control::get_instance();
$rayetun_ag_bots_data   = $rayetun_ag_bot_control->get_bots_data();
$rayetun_ag_bc_settings = $rayetun_ag_bot_control->get_settings();
$rayetun_ag_analytics   = Rayetun_AG_Analytics::get_instance();
$rayetun_ag_last_seen   = $rayetun_ag_analytics->get_last_seen_by_bot();

$rayetun_ag_categories = $rayetun_ag_bots_data['categories'] ?? array();
$rayetun_ag_bots_list  = $rayetun_ag_bots_data['bots'] ?? array();

$rayetun_ag_grouped = array();
foreach ( $rayetun_ag_bots_list as $rayetun_ag_bc_bot ) {
	$rayetun_ag_grouped[ $rayetun_ag_bc_bot['category'] ][] = $rayetun_ag_bc_bot;
}
?>
<div class="agentgarrison-bot-control">
	<div class="agentgarrison-page-header">
		<h1 class="agentgarrison-page-title"><?php esc_html_e( 'AI Bot Control', 'agentgarrison' ); ?></h1>
		<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Control which AI bots can access your site. Changes update robots.txt instantly.', 'agentgarrison' ); ?></p>
	</div>

	<?php if ( empty( $rayetun_ag_bc_settings['import_done'] ) ) : ?>
	<div class="agentgarrison-callout agentgarrison-callout--info">
		<strong><?php esc_html_e( 'Import existing robots.txt rules?', 'agentgarrison' ); ?></strong>
		<?php esc_html_e( 'We detected you may have existing rules. Click to import them — your previous configuration will be preserved.', 'agentgarrison' ); ?>
		<button class="agentgarrison-btn agentgarrison-btn--sm js-import-robots" style="margin-left:12px;">
			<?php esc_html_e( 'Import Now', 'agentgarrison' ); ?>
		</button>
	</div>
	<?php endif; ?>

	<!-- Global Options -->
	<div class="agentgarrison-card">
		<div class="agentgarrison-card__header">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Global Options', 'agentgarrison' ); ?></h2>
		</div>
		<div class="agentgarrison-options-row">
			<label class="agentgarrison-custom-checkbox">
				<input type="checkbox" name="x_robots_tag" value="1"
					<?php checked( ! empty( $rayetun_ag_bc_settings['x_robots_tag'] ) ); ?>>
				<span class="agentgarrison-custom-checkbox__box"></span>
				<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Inject X-Robots-Tag header for blocked bots', 'agentgarrison' ); ?></span>
			</label>
			<span class="agentgarrison-hint"><?php esc_html_e( 'Adds noai, noimageai HTTP header for bots that ignore robots.txt.', 'agentgarrison' ); ?></span>
		</div>
	</div>

	<!-- Honeypot Bot Trap -->
	<?php
	if ( Rayetun_AG_Modules::is_enabled( 'honeypot' ) ) :
		$rayetun_ag_hp          = Rayetun_AG_Honeypot::get_instance();
		$rayetun_ag_hp_settings = $rayetun_ag_hp->get_settings();
		$rayetun_ag_hp_catches  = $rayetun_ag_hp->get_catch_count();
		$rayetun_ag_hp_blocked  = $rayetun_ag_hp->get_blocked_count();
	?>
	<div class="agentgarrison-card">
		<div class="agentgarrison-card__header">
			<div>
				<h2 class="agentgarrison-card__title">🍯 <?php esc_html_e( 'Honeypot Bot Trap', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc"><?php esc_html_e( 'A hidden, robots.txt-disallowed URL no human or well-behaved crawler will ever request. Any hit is a high-confidence bot.', 'agentgarrison' ); ?></p>
			</div>
			<div class="agentgarrison-hp-stats">
				<div class="agentgarrison-hp-stat">
					<span class="agentgarrison-hp-stat__num"><?php echo absint( $rayetun_ag_hp_catches ); ?></span>
					<span class="agentgarrison-hp-stat__label"><?php esc_html_e( 'Caught', 'agentgarrison' ); ?></span>
				</div>
				<div class="agentgarrison-hp-stat">
					<span class="agentgarrison-hp-stat__num js-hp-blocked-count"><?php echo absint( $rayetun_ag_hp_blocked ); ?></span>
					<span class="agentgarrison-hp-stat__label"><?php esc_html_e( 'Blocked IPs', 'agentgarrison' ); ?></span>
				</div>
			</div>
		</div>

		<div class="agentgarrison-options-row">
			<label class="agentgarrison-custom-checkbox">
				<input type="checkbox" id="ag-hp-autoblock" name="auto_block" value="1"
					<?php checked( ! empty( $rayetun_ag_hp_settings['auto_block'] ) ); ?>>
				<span class="agentgarrison-custom-checkbox__box"></span>
				<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Soft-block repeat offenders by IP', 'agentgarrison' ); ?></span>
			</label>
			<span class="agentgarrison-hint"><?php esc_html_e( 'Off by default. When on, IPs that hit the trap get a 403 on future requests. Use with care — shared/CDN IPs could affect real visitors.', 'agentgarrison' ); ?></span>
		</div>

		<div class="agentgarrison-field-actions">
			<button class="agentgarrison-btn agentgarrison-btn--primary js-save-honeypot-settings"><?php esc_html_e( 'Save Honeypot Settings', 'agentgarrison' ); ?></button>
			<button class="agentgarrison-btn agentgarrison-btn--secondary js-clear-honeypot-blocked"><?php esc_html_e( 'Clear Blocked IPs', 'agentgarrison' ); ?></button>
			<span class="agentgarrison-save-status js-honeypot-status"></span>
		</div>
	</div>
	<?php endif; ?>

	<!-- Category cards -->
	<?php foreach ( $rayetun_ag_categories as $rayetun_ag_cat_id => $rayetun_ag_cat ) :
		$rayetun_ag_cat_rule  = $rayetun_ag_bc_settings['categories'][ $rayetun_ag_cat_id ] ?? $rayetun_ag_cat['default'];
		$rayetun_ag_cat_bots  = $rayetun_ag_grouped[ $rayetun_ag_cat_id ] ?? array();
		$rayetun_ag_cat_count = count( $rayetun_ag_cat_bots );
	?>
	<div class="agentgarrison-card agentgarrison-bot-category" data-category="<?php echo esc_attr( $rayetun_ag_cat_id ); ?>">

		<!-- Category header -->
		<div class="agentgarrison-cat-header">
			<div class="agentgarrison-cat-header__left">
				<h2 class="agentgarrison-cat-header__title">
					<?php echo esc_html( $rayetun_ag_cat['label'] ); ?>
					<span class="agentgarrison-cat-count"><?php echo absint( $rayetun_ag_cat_count ); ?></span>
				</h2>
				<p class="agentgarrison-cat-header__desc"><?php echo esc_html( $rayetun_ag_cat['description'] ); ?></p>
			</div>
			<div class="agentgarrison-cat-header__controls">
				<!-- Radio pill: Allow / Block -->
				<div class="agentgarrison-radio-pills" role="group" aria-label="<?php esc_attr_e( 'Category rule', 'agentgarrison' ); ?>">
					<label class="agentgarrison-radio-pill agentgarrison-radio-pill--allow <?php echo 'allow' === $rayetun_ag_cat_rule ? 'is-active' : ''; ?>">
						<input type="radio" class="js-category-rule"
							name="categories[<?php echo esc_attr( $rayetun_ag_cat_id ); ?>]"
							value="allow"
							data-category="<?php echo esc_attr( $rayetun_ag_cat_id ); ?>"
							<?php checked( 'allow', $rayetun_ag_cat_rule ); ?>>
						<span class="agentgarrison-radio-pill__dot"></span>
						<?php esc_html_e( 'Allow', 'agentgarrison' ); ?>
					</label>
					<label class="agentgarrison-radio-pill agentgarrison-radio-pill--block <?php echo 'block' === $rayetun_ag_cat_rule ? 'is-active' : ''; ?>">
						<input type="radio" class="js-category-rule"
							name="categories[<?php echo esc_attr( $rayetun_ag_cat_id ); ?>]"
							value="block"
							data-category="<?php echo esc_attr( $rayetun_ag_cat_id ); ?>"
							<?php checked( 'block', $rayetun_ag_cat_rule ); ?>>
						<span class="agentgarrison-radio-pill__dot"></span>
						<?php esc_html_e( 'Block', 'agentgarrison' ); ?>
					</label>
				</div>
			</div>
		</div>

		<!-- Bot rows -->
		<table class="agentgarrison-table agentgarrison-bots-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Bot', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Last Seen', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Status', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Override', 'agentgarrison' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rayetun_ag_cat_bots as $rayetun_ag_list_bot ) :
					$rayetun_ag_bot_rule     = $rayetun_ag_bc_settings['bots'][ $rayetun_ag_list_bot['id'] ] ?? 'inherit';
					$rayetun_ag_seen_ts      = $rayetun_ag_last_seen[ $rayetun_ag_list_bot['name'] ] ?? '';
					$rayetun_ag_seen_display = $rayetun_ag_seen_ts
						? human_time_diff( strtotime( $rayetun_ag_seen_ts ), time() ) . ' ' . __( 'ago', 'agentgarrison' )
						: '—';

					// Effective rule for status pill.
					if ( 'inherit' === $rayetun_ag_bot_rule ) {
						$rayetun_ag_effective = $rayetun_ag_cat_rule;
					} else {
						$rayetun_ag_effective = $rayetun_ag_bot_rule;
					}
				?>
				<tr class="agentgarrison-bot-row js-bot-row" data-effective="<?php echo esc_attr( $rayetun_ag_effective ); ?>">
					<td class="agentgarrison-bot-cell">
						<strong class="agentgarrison-bot-name"><?php echo esc_html( $rayetun_ag_list_bot['name'] ); ?></strong>
						<span class="agentgarrison-bot-meta">
							<?php echo esc_html( $rayetun_ag_list_bot['company'] ); ?>
							&nbsp;·&nbsp;
							<code class="agentgarrison-ua-token">UA: <?php echo esc_html( $rayetun_ag_list_bot['user_agent'] ); ?></code>
						</span>
					</td>
					<td class="agentgarrison-last-seen"><?php echo esc_html( $rayetun_ag_seen_display ); ?></td>
					<td>
						<span class="agentgarrison-status-pill js-status-pill <?php echo 'allow' === $rayetun_ag_effective ? 'is-allowed' : 'is-blocked'; ?>">
							<?php echo 'allow' === $rayetun_ag_effective ? esc_html__( 'Allowed', 'agentgarrison' ) : esc_html__( 'Blocked', 'agentgarrison' ); ?>
						</span>
					</td>
					<td>
						<div class="agentgarrison-select-wrap">
							<select class="agentgarrison-select js-bot-override"
								name="bots[<?php echo esc_attr( $rayetun_ag_list_bot['id'] ); ?>]"
								data-bot="<?php echo esc_attr( $rayetun_ag_list_bot['id'] ); ?>"
								data-category="<?php echo esc_attr( $rayetun_ag_cat_id ); ?>">
								<option value="inherit" <?php selected( 'inherit', $rayetun_ag_bot_rule ); ?>><?php esc_html_e( 'Inherit from category', 'agentgarrison' ); ?></option>
								<option value="allow" <?php selected( 'allow', $rayetun_ag_bot_rule ); ?>><?php esc_html_e( 'Always Allow', 'agentgarrison' ); ?></option>
								<option value="block" <?php selected( 'block', $rayetun_ag_bot_rule ); ?>><?php esc_html_e( 'Always Block', 'agentgarrison' ); ?></option>
							</select>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endforeach; ?>

	<!-- Sticky save bar at bottom -->
	<div class="agentgarrison-save-bar">
		<div class="agentgarrison-save-bar__inner">
			<span class="agentgarrison-save-bar__hint"><?php esc_html_e( 'Changes apply to robots.txt immediately after saving.', 'agentgarrison' ); ?></span>
			<div class="agentgarrison-save-bar__actions">
				<span class="agentgarrison-save-status js-save-status"></span>
				<button class="agentgarrison-btn agentgarrison-btn--primary agentgarrison-btn--lg js-save-bot-settings">
					<?php esc_html_e( 'Save Bot Control Settings', 'agentgarrison' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
