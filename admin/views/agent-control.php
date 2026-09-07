<?php
/**
 * Agent Control view — WordPress 7.1 Abilities / MCP governance + activity log.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_agent     = Rayetun_AG_Agent_Control::get_instance();
$rayetun_ag_ac        = $rayetun_ag_agent->get_settings();
$rayetun_ag_api_ready = $rayetun_ag_agent->is_abilities_api_available();
$rayetun_ag_grouped   = $rayetun_ag_agent->get_abilities_grouped();
$rayetun_ag_last_used = $rayetun_ag_agent->get_last_used_by_ability();
$rayetun_ag_events    = $rayetun_ag_agent->get_recent_events( 50 );
$rayetun_ag_evt_count = $rayetun_ag_agent->get_event_count();
?>
<div class="agentgarrison-agent-control">
	<div class="agentgarrison-page-header">
		<h1 class="agentgarrison-page-title">🛡️ <?php esc_html_e( 'Agent Control', 'agentgarrison' ); ?></h1>
		<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Govern what AI agents can do on your site over MCP, and audit every action. The write/act counterpart to Bot Control.', 'agentgarrison' ); ?></p>
	</div>

	<?php if ( ! $rayetun_ag_api_ready ) : ?>
	<div class="agentgarrison-callout agentgarrison-callout--info">
		<strong><?php esc_html_e( 'Activates on WordPress 7.1+', 'agentgarrison' ); ?></strong>
		<?php esc_html_e( 'The Abilities API that lets AI agents run actions on your site ships in WordPress 7.1. This site does not expose it yet, so there is nothing for agents to reach. Agent Control is ready and will start governing exposure and logging activity automatically once the Abilities API (and an MCP adapter) are present. You can configure your policy below in advance.', 'agentgarrison' ); ?>
	</div>
	<?php endif; ?>

	<!-- MCP exposure governance -->
	<div class="agentgarrison-card">
		<div class="agentgarrison-card__header">
			<div>
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'MCP Exposure', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc"><?php esc_html_e( 'Decide whether AgentGarrison manages which Abilities are exposed to AI agents over MCP, and which specific Abilities are allowed. Off by default so your existing setup is never changed without your say-so.', 'agentgarrison' ); ?></p>
			</div>
		</div>

		<div class="agentgarrison-options-row">
			<label class="agentgarrison-custom-checkbox">
				<input type="checkbox" id="ag-manage-exposure" name="manage_exposure" value="1"
					<?php checked( ! empty( $rayetun_ag_ac['manage_exposure'] ) ); ?>>
				<span class="agentgarrison-custom-checkbox__box"></span>
				<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Let AgentGarrison manage MCP exposure', 'agentgarrison' ); ?></span>
			</label>
			<span class="agentgarrison-hint"><?php esc_html_e( 'When off, AgentGarrison does not alter what agents can reach — it only logs activity below.', 'agentgarrison' ); ?></span>
		</div>

		<div class="js-exposure-managed" style="<?php echo ! empty( $rayetun_ag_ac['manage_exposure'] ) ? '' : 'display:none;'; ?>">
			<div class="agentgarrison-options-row">
				<label class="agentgarrison-custom-checkbox">
					<input type="checkbox" id="ag-expose-enabled" name="expose_enabled" value="1"
						<?php checked( ! empty( $rayetun_ag_ac['expose_enabled'] ) ); ?>>
					<span class="agentgarrison-custom-checkbox__box"></span>
					<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Expose Abilities to AI agents over MCP', 'agentgarrison' ); ?></span>
				</label>
				<span class="agentgarrison-hint"><?php esc_html_e( 'The master switch. When off, AgentGarrison exposes nothing to agents, whatever the per-ability rules below say.', 'agentgarrison' ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( ! $rayetun_ag_api_ready || empty( $rayetun_ag_grouped ) ) : ?>
	<div class="agentgarrison-card js-exposure-managed" style="<?php echo ! empty( $rayetun_ag_ac['manage_exposure'] ) ? '' : 'display:none;'; ?>">
		<div class="agentgarrison-callout">
			<?php esc_html_e( 'No Abilities are registered on this site yet. Once WordPress core, an MCP adapter, or another plugin registers Abilities, they will appear here grouped by category — allow or block a whole category at once, or override individual Abilities.', 'agentgarrison' ); ?>
		</div>
	</div>
	<?php else : ?>
		<?php
		// One card per ability category (namespace), mirroring Bot Control.
		foreach ( $rayetun_ag_grouped as $rayetun_ag_group => $rayetun_ag_group_abilities ) :
			$rayetun_ag_cat_rule  = $rayetun_ag_agent->category_rule( $rayetun_ag_group );
			$rayetun_ag_cat_count = count( $rayetun_ag_group_abilities );
			$rayetun_ag_cat_label = ucfirst( $rayetun_ag_group );
		?>
	<div class="agentgarrison-card agentgarrison-bot-category js-exposure-managed" data-category="<?php echo esc_attr( $rayetun_ag_group ); ?>"
		style="<?php echo ! empty( $rayetun_ag_ac['manage_exposure'] ) ? '' : 'display:none;'; ?>">

		<div class="agentgarrison-cat-header">
			<div class="agentgarrison-cat-header__left">
				<h2 class="agentgarrison-cat-header__title">
					<?php
					/* translators: %s: ability namespace, e.g. "core". */
					echo esc_html( sprintf( __( '%s Abilities', 'agentgarrison' ), $rayetun_ag_cat_label ) );
					?>
					<span class="agentgarrison-cat-count"><?php echo absint( $rayetun_ag_cat_count ); ?></span>
				</h2>
				<p class="agentgarrison-cat-header__desc">
					<?php
					/* translators: %s: ability namespace, e.g. "woocommerce". */
					echo esc_html( sprintf( __( 'Abilities registered under the “%s” namespace. Allow or block the whole category, then fine-tune individual Abilities below.', 'agentgarrison' ), $rayetun_ag_group ) );
					?>
				</p>
			</div>
			<div class="agentgarrison-cat-header__controls">
				<div class="agentgarrison-radio-pills" role="group" aria-label="<?php esc_attr_e( 'Category rule', 'agentgarrison' ); ?>">
					<label class="agentgarrison-radio-pill agentgarrison-radio-pill--allow <?php echo 'allow' === $rayetun_ag_cat_rule ? 'is-active' : ''; ?>">
						<input type="radio" class="js-ability-category-rule"
							name="ability_categories[<?php echo esc_attr( $rayetun_ag_group ); ?>]"
							value="allow"
							data-category="<?php echo esc_attr( $rayetun_ag_group ); ?>"
							<?php checked( 'allow', $rayetun_ag_cat_rule ); ?>>
						<span class="agentgarrison-radio-pill__dot"></span>
						<?php esc_html_e( 'Allow', 'agentgarrison' ); ?>
					</label>
					<label class="agentgarrison-radio-pill agentgarrison-radio-pill--block <?php echo 'block' === $rayetun_ag_cat_rule ? 'is-active' : ''; ?>">
						<input type="radio" class="js-ability-category-rule"
							name="ability_categories[<?php echo esc_attr( $rayetun_ag_group ); ?>]"
							value="block"
							data-category="<?php echo esc_attr( $rayetun_ag_group ); ?>"
							<?php checked( 'block', $rayetun_ag_cat_rule ); ?>>
						<span class="agentgarrison-radio-pill__dot"></span>
						<?php esc_html_e( 'Block', 'agentgarrison' ); ?>
					</label>
				</div>
			</div>
		</div>

		<table class="agentgarrison-table agentgarrison-bots-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ability', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Last Used', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Status', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Override', 'agentgarrison' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rayetun_ag_group_abilities as $rayetun_ag_ab_name => $rayetun_ag_ability ) :
					$rayetun_ag_override  = $rayetun_ag_agent->ability_override( $rayetun_ag_ab_name );
					$rayetun_ag_effective = 'inherit' === $rayetun_ag_override ? $rayetun_ag_cat_rule : $rayetun_ag_override;
					$rayetun_ag_used_ts   = $rayetun_ag_last_used[ $rayetun_ag_ab_name ] ?? '';
					$rayetun_ag_used_disp = $rayetun_ag_used_ts
						? human_time_diff( strtotime( $rayetun_ag_used_ts ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'agentgarrison' ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
						: '—';
				?>
				<tr class="agentgarrison-bot-row js-ability-row" data-effective="<?php echo esc_attr( $rayetun_ag_effective ); ?>">
					<td class="agentgarrison-bot-cell">
						<strong class="agentgarrison-bot-name"><?php echo esc_html( $rayetun_ag_ability['label'] ); ?></strong>
						<span class="agentgarrison-bot-meta">
							<code class="agentgarrison-ua-token"><?php echo esc_html( $rayetun_ag_ab_name ); ?></code>
							<?php if ( ! empty( $rayetun_ag_ability['description'] ) ) : ?>
								&nbsp;·&nbsp;<?php echo esc_html( wp_trim_words( $rayetun_ag_ability['description'], 14 ) ); ?>
							<?php endif; ?>
						</span>
					</td>
					<td class="agentgarrison-last-seen"><?php echo esc_html( $rayetun_ag_used_disp ); ?></td>
					<td>
						<span class="agentgarrison-status-pill js-ability-status-pill <?php echo 'allow' === $rayetun_ag_effective ? 'is-allowed' : 'is-blocked'; ?>">
							<?php echo 'allow' === $rayetun_ag_effective ? esc_html__( 'Exposed', 'agentgarrison' ) : esc_html__( 'Blocked', 'agentgarrison' ); ?>
						</span>
					</td>
					<td>
						<div class="agentgarrison-select-wrap">
							<select class="agentgarrison-select js-ability-override"
								name="ability_overrides[<?php echo esc_attr( $rayetun_ag_ab_name ); ?>]"
								data-category="<?php echo esc_attr( $rayetun_ag_group ); ?>">
								<option value="inherit" <?php selected( 'inherit', $rayetun_ag_override ); ?>><?php esc_html_e( 'Inherit from category', 'agentgarrison' ); ?></option>
								<option value="allow" <?php selected( 'allow', $rayetun_ag_override ); ?>><?php esc_html_e( 'Always Allow', 'agentgarrison' ); ?></option>
								<option value="block" <?php selected( 'block', $rayetun_ag_override ); ?>><?php esc_html_e( 'Always Block', 'agentgarrison' ); ?></option>
							</select>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<!-- Agent activity log -->
	<div class="agentgarrison-card">
		<div class="agentgarrison-card__header">
			<div>
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Agent Activity Log', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc"><?php esc_html_e( 'Every Ability an AI agent executes on your site is recorded here — the audit trail for the write/act era. Stored locally and pruned on your data-retention schedule.', 'agentgarrison' ); ?></p>
			</div>
			<div class="agentgarrison-hp-stats">
				<div class="agentgarrison-hp-stat">
					<span class="agentgarrison-hp-stat__num js-agent-event-count"><?php echo absint( $rayetun_ag_evt_count ); ?></span>
					<span class="agentgarrison-hp-stat__label"><?php esc_html_e( 'Logged', 'agentgarrison' ); ?></span>
				</div>
			</div>
		</div>

		<table class="agentgarrison-table agentgarrison-agent-log">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Ability', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Source', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Actor', 'agentgarrison' ); ?></th>
					<th><?php esc_html_e( 'Result', 'agentgarrison' ); ?></th>
				</tr>
			</thead>
			<tbody class="js-agent-log-body">
				<?php if ( empty( $rayetun_ag_events ) ) : ?>
				<tr><td colspan="5" class="agentgarrison-loading"><?php esc_html_e( 'No agent activity recorded yet.', 'agentgarrison' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rayetun_ag_events as $rayetun_ag_ev ) :
						$rayetun_ag_ev_ago  = human_time_diff( strtotime( $rayetun_ag_ev->created_at ), current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
						$rayetun_ag_ev_user = $rayetun_ag_ev->actor_id ? get_userdata( $rayetun_ag_ev->actor_id ) : false;
						$rayetun_ag_ev_who  = $rayetun_ag_ev_user ? $rayetun_ag_ev_user->display_name : __( 'Unauthenticated', 'agentgarrison' );
						$rayetun_ag_ev_res  = $rayetun_ag_ev->result ? $rayetun_ag_ev->result : 'success';
						$rayetun_ag_ev_cls  = 'error' === $rayetun_ag_ev_res ? 'is-blocked' : 'is-allowed';
					?>
					<tr>
						<td class="agentgarrison-last-seen"><?php echo esc_html( sprintf( /* translators: %s: human time diff */ __( '%s ago', 'agentgarrison' ), $rayetun_ag_ev_ago ) ); ?></td>
						<td><code class="agentgarrison-ua-token"><?php echo esc_html( $rayetun_ag_ev->ability ); ?></code></td>
						<td><span class="agentgarrison-badge"><?php echo esc_html( $rayetun_ag_ev->source ? $rayetun_ag_ev->source : 'internal' ); ?></span></td>
						<td><?php echo esc_html( $rayetun_ag_ev_who ); ?></td>
						<td><span class="agentgarrison-status-pill <?php echo esc_attr( $rayetun_ag_ev_cls ); ?>"><?php echo esc_html( ucfirst( $rayetun_ag_ev_res ) ); ?></span></td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<div class="agentgarrison-field-actions">
			<button class="agentgarrison-btn agentgarrison-btn--secondary js-clear-agent-log"><?php esc_html_e( 'Clear Log', 'agentgarrison' ); ?></button>
		</div>
	</div>

	<!-- Sticky save bar -->
	<div class="agentgarrison-save-bar">
		<div class="agentgarrison-save-bar__inner">
			<span class="agentgarrison-save-bar__hint"><?php esc_html_e( 'Exposure rules take effect immediately after saving.', 'agentgarrison' ); ?></span>
			<div class="agentgarrison-save-bar__actions">
				<span class="agentgarrison-save-status js-agent-status"></span>
				<button class="agentgarrison-btn agentgarrison-btn--primary agentgarrison-btn--lg js-save-agent-settings">
					<?php esc_html_e( 'Save Agent Control Settings', 'agentgarrison' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
