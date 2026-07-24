<?php
/**
 * Settings view — General settings + Rank Math-style module toggles.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_all_modules  = Rayetun_AG_Modules::get_all();
$rayetun_ag_module_defs  = Rayetun_AG_Modules::get_definitions();
$rayetun_ag_general_opts = wp_parse_args( get_option( 'rayetun_ag_general_settings', array() ), array(
	'alert_email'    => get_option( 'admin_email' ),
	'retention_days' => 90,
) );
?>
<div class="agentgarrison-settings">
	<div class="agentgarrison-page-header">
		<h1 class="agentgarrison-page-title"><?php esc_html_e( 'Settings', 'agentgarrison' ); ?></h1>
	</div>

	<!-- Modules -->
	<div class="agentgarrison-section">
		<h2 class="agentgarrison-section-title"><?php esc_html_e( 'Modules', 'agentgarrison' ); ?></h2>
		<p class="agentgarrison-section-desc"><?php esc_html_e( 'Enable or disable modules. Disabled modules stop all background processing and hooks — they don\'t slow your site down.', 'agentgarrison' ); ?></p>

		<div class="agentgarrison-module-grid">
			<?php foreach ( $rayetun_ag_module_defs as $rayetun_ag_mod_id => $rayetun_ag_mod_def ) :
				$rayetun_ag_mod_enabled = ! empty( $rayetun_ag_all_modules[ $rayetun_ag_mod_id ] );
			?>
			<div class="agentgarrison-module-card <?php echo $rayetun_ag_mod_enabled ? 'is-enabled' : 'is-disabled'; ?>">
				<div class="agentgarrison-module-card__icon"><?php echo esc_html( $rayetun_ag_mod_def['icon'] ); ?></div>
				<div class="agentgarrison-module-card__body">
					<h3 class="agentgarrison-module-card__title"><?php echo esc_html( $rayetun_ag_mod_def['label'] ); ?></h3>
					<p class="agentgarrison-module-card__desc"><?php echo esc_html( $rayetun_ag_mod_def['description'] ); ?></p>
				</div>
				<div class="agentgarrison-module-card__toggle">
					<label class="agentgarrison-switch">
						<input type="checkbox" class="js-toggle-module"
							data-module="<?php echo esc_attr( $rayetun_ag_mod_id ); ?>"
							<?php checked( $rayetun_ag_mod_enabled ); ?>>
						<span class="agentgarrison-switch__slider"></span>
					</label>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- General Settings -->
	<div class="agentgarrison-section">
		<h2 class="agentgarrison-section-title"><?php esc_html_e( 'General Settings', 'agentgarrison' ); ?></h2>

		<div class="agentgarrison-card">
			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-alert-email">
					<?php esc_html_e( 'Alert Email', 'agentgarrison' ); ?>
				</label>
				<input type="email" id="ag-alert-email" class="agentgarrison-input" name="alert_email"
					value="<?php echo esc_attr( $rayetun_ag_general_opts['alert_email'] ); ?>">
				<p class="agentgarrison-hint"><?php esc_html_e( 'Receives traffic spike alerts. Defaults to admin email.', 'agentgarrison' ); ?></p>
			</div>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-retention-days">
					<?php esc_html_e( 'Analytics Data Retention (days)', 'agentgarrison' ); ?>
				</label>
				<div class="agentgarrison-select-wrap agentgarrison-select-wrap--sm">
					<select id="ag-retention-days" class="agentgarrison-select" name="retention_days">
						<?php foreach ( array( 30, 60, 90, 180, 365 ) as $rayetun_ag_days_opt ) : ?>
						<option value="<?php echo absint( $rayetun_ag_days_opt ); ?>"
							<?php selected( absint( $rayetun_ag_general_opts['retention_days'] ), $rayetun_ag_days_opt ); ?>>
							<?php
							printf(
								/* translators: %d: number of days */
								esc_html__( '%d days', 'agentgarrison' ),
								absint( $rayetun_ag_days_opt )
							);
							?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
				<p class="agentgarrison-hint"><?php esc_html_e( 'Bot visit records older than this are deleted automatically.', 'agentgarrison' ); ?></p>
			</div>

			<div class="agentgarrison-field-actions">
				<button class="agentgarrison-btn agentgarrison-btn--primary js-save-general-settings">
					<?php esc_html_e( 'Save Settings', 'agentgarrison' ); ?>
				</button>
				<span class="agentgarrison-save-status js-general-save-status"></span>
			</div>
		</div>
	</div>

	<!-- Email Digest -->
	<?php if ( Rayetun_AG_Modules::is_enabled( 'email_digest' ) ) :
		$rayetun_ag_digest = Rayetun_AG_Email_Digest::get_instance()->get_settings();
	?>
	<div class="agentgarrison-section">
		<h2 class="agentgarrison-section-title"><?php esc_html_e( 'Email Digest', 'agentgarrison' ); ?></h2>

		<div class="agentgarrison-card">
			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-digest-email"><?php esc_html_e( 'Recipient Email', 'agentgarrison' ); ?></label>
				<input type="email" id="ag-digest-email" class="agentgarrison-input" name="digest_email"
					value="<?php echo esc_attr( $rayetun_ag_digest['email'] ); ?>">
			</div>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label"><?php esc_html_e( 'Frequency', 'agentgarrison' ); ?></label>
				<div class="agentgarrison-radio-pills" style="display:inline-flex;">
					<label class="agentgarrison-radio-pill agentgarrison-radio-pill--allow <?php echo 'weekly' === $rayetun_ag_digest['frequency'] ? 'is-active' : ''; ?>">
						<input type="radio" name="digest_frequency" value="weekly" class="js-digest-freq"
							<?php checked( 'weekly', $rayetun_ag_digest['frequency'] ); ?>>
						<span class="agentgarrison-radio-pill__dot"></span>
						<?php esc_html_e( 'Weekly', 'agentgarrison' ); ?>
					</label>
					<label class="agentgarrison-radio-pill agentgarrison-radio-pill--allow <?php echo 'monthly' === $rayetun_ag_digest['frequency'] ? 'is-active' : ''; ?>">
						<input type="radio" name="digest_frequency" value="monthly" class="js-digest-freq"
							<?php checked( 'monthly', $rayetun_ag_digest['frequency'] ); ?>>
						<span class="agentgarrison-radio-pill__dot"></span>
						<?php esc_html_e( 'Monthly', 'agentgarrison' ); ?>
					</label>
				</div>
			</div>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-agency-name"><?php esc_html_e( 'Agency / Brand Name', 'agentgarrison' ); ?></label>
				<input type="text" id="ag-agency-name" class="agentgarrison-input" name="agency_name"
					value="<?php echo esc_attr( $rayetun_ag_digest['agency_name'] ); ?>"
					placeholder="<?php esc_attr_e( 'Leave blank for AgentGarrison branding', 'agentgarrison' ); ?>">
				<p class="agentgarrison-hint"><?php esc_html_e( 'White-label the digest with your agency or company name.', 'agentgarrison' ); ?></p>
			</div>

			<div class="agentgarrison-field-actions">
				<button class="agentgarrison-btn agentgarrison-btn--primary js-save-digest-settings">
					<?php esc_html_e( 'Save Digest Settings', 'agentgarrison' ); ?>
				</button>
				<button class="agentgarrison-btn agentgarrison-btn--secondary js-send-test-digest">
					<?php esc_html_e( 'Send Test Digest', 'agentgarrison' ); ?>
				</button>
				<span class="agentgarrison-save-status js-digest-save-status"></span>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Info -->
	<div class="agentgarrison-section">
		<div class="agentgarrison-card agentgarrison-info-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Plugin Info', 'agentgarrison' ); ?></h2>
			<table class="agentgarrison-table agentgarrison-info-table">
				<tr>
					<td><?php esc_html_e( 'Version', 'agentgarrison' ); ?></td>
					<td><?php echo esc_html( RAYETUN_AG_VERSION ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Bot Database', 'agentgarrison' ); ?></td>
					<td><?php
						$rayetun_ag_bots_ver = get_option( 'rayetun_ag_bots_version', __( 'Not synced', 'agentgarrison' ) );
						echo esc_html( 'v' . $rayetun_ag_bots_ver );
					?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'DB Schema', 'agentgarrison' ); ?></td>
					<td><?php echo esc_html( get_option( 'rayetun_ag_db_version', '—' ) ); ?></td>
				</tr>
			</table>
		</div>
	</div>
</div>
