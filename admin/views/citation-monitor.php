<?php
/**
 * Citation Monitor view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_cm_keys = wp_parse_args( get_option( 'rayetun_ag_citation_api_keys', array() ), array(
	'anthropic'  => '',
	'openai'     => '',
	'perplexity' => '',
) );
$rayetun_ag_cm_has_api  = ! empty( array_filter( $rayetun_ag_cm_keys ) );
$rayetun_ag_cm_settings = Rayetun_AG_Citation_Monitor::get_instance()->get_settings();
// Live scanning is possible via the WP 7.0+ core AI Client OR a saved API key.
$rayetun_ag_cm_live_ready = Rayetun_AG_AI_Provider::is_available();
?>
<div class="agentgarrison-citation-monitor">
	<div class="agentgarrison-page-header">
		<div>
			<h1 class="agentgarrison-page-title"><?php esc_html_e( 'Citation Monitor', 'agentgarrison' ); ?></h1>
			<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Track which of your pages AI platforms cite when users search your target keywords.', 'agentgarrison' ); ?></p>
		</div>
	</div>

	<?php if ( ! $rayetun_ag_cm_live_ready ) : ?>
	<div class="agentgarrison-callout agentgarrison-callout--info">
		<strong><?php esc_html_e( 'Demo Mode active.', 'agentgarrison' ); ?></strong>
		<?php esc_html_e( 'No AI provider configured — AgentGarrison generates realistic citation examples from your own posts so you can preview the feature. On WordPress 7.0+ set up a provider under Settings → Connectors, or add an API key below, for live data.', 'agentgarrison' ); ?>
	</div>
	<?php else : ?>
	<div class="agentgarrison-callout agentgarrison-callout--success">
		<strong><?php esc_html_e( 'Live scanning ready.', 'agentgarrison' ); ?></strong>
		<?php esc_html_e( 'Add your questions below and click Run Live Scan to see whether AI assistants cite your site.', 'agentgarrison' ); ?>
	</div>
	<?php endif; ?>

	<div class="agentgarrison-citation-grid">

		<!-- Left: keywords + results -->
		<div class="agentgarrison-citation-main">

			<!-- Visibility scorecard (closed-loop proof) -->
			<div class="agentgarrison-card js-citation-scorecard" style="display:none;">
				<div class="agentgarrison-card__header">
					<h2 class="agentgarrison-card__title"><?php esc_html_e( 'AI Visibility Scorecard', 'agentgarrison' ); ?></h2>
					<span class="agentgarrison-scorecard-demo-tag js-scorecard-demo" style="display:none;"><?php esc_html_e( 'DEMO', 'agentgarrison' ); ?></span>
				</div>
				<div class="agentgarrison-scorecard">
					<div class="agentgarrison-scorecard__rate">
						<div class="agentgarrison-scorecard__pct js-scorecard-rate">—</div>
						<div class="agentgarrison-scorecard__label js-scorecard-summary"><?php esc_html_e( 'cited rate', 'agentgarrison' ); ?></div>
					</div>
					<div class="agentgarrison-scorecard__cols">
						<div>
							<h3 class="agentgarrison-scorecard__h"><?php esc_html_e( 'Your questions', 'agentgarrison' ); ?></h3>
							<ul class="agentgarrison-scorecard__questions js-scorecard-questions"></ul>
						</div>
						<div>
							<h3 class="agentgarrison-scorecard__h"><?php esc_html_e( 'Who AI cites instead', 'agentgarrison' ); ?></h3>
							<ul class="agentgarrison-scorecard__competitors js-scorecard-competitors"></ul>
						</div>
					</div>
				</div>
			</div>

			<!-- Keyword manager -->
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Tracked Keywords', 'agentgarrison' ); ?></h2>
				<div class="agentgarrison-keyword-add">
					<input type="text" class="agentgarrison-input js-keyword-input"
						placeholder="<?php esc_attr_e( 'e.g. best WordPress AI bot control plugin', 'agentgarrison' ); ?>">
					<button class="agentgarrison-btn agentgarrison-btn--primary js-add-keyword"><?php esc_html_e( 'Add Keyword', 'agentgarrison' ); ?></button>
				</div>
				<div class="agentgarrison-keyword-list js-keyword-list">
					<p class="agentgarrison-loading"><?php esc_html_e( 'Loading keywords...', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-citation-actions">
					<?php if ( $rayetun_ag_cm_live_ready ) : ?>
					<button class="agentgarrison-btn agentgarrison-btn--primary js-run-live-scan">
						<?php esc_html_e( '🔎 Run Live Scan', 'agentgarrison' ); ?>
					</button>
					<?php endif; ?>
					<button class="agentgarrison-btn agentgarrison-btn--secondary js-run-demo-scan">
						<?php esc_html_e( '⚡ Run Demo Scan', 'agentgarrison' ); ?>
					</button>
					<span class="agentgarrison-save-status js-citation-status"></span>
				</div>
				<?php if ( $rayetun_ag_cm_live_ready ) : ?>
				<p class="agentgarrison-hint" style="margin-top:10px;">
					<?php esc_html_e( 'Live Scan queries your configured AI provider for every tracked question. It can take a few seconds per question, and also runs automatically on your chosen schedule.', 'agentgarrison' ); ?>
				</p>
				<?php endif; ?>
			</div>

			<!-- Results -->
			<div class="agentgarrison-card">
				<div class="agentgarrison-card__header">
					<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Citation Results', 'agentgarrison' ); ?></h2>
					<button class="agentgarrison-btn agentgarrison-btn--sm agentgarrison-btn--secondary js-clear-citations">
						<?php esc_html_e( 'Clear Demo Data', 'agentgarrison' ); ?>
					</button>
				</div>
				<div class="agentgarrison-citation-results js-citation-results">
					<p class="agentgarrison-loading"><?php esc_html_e( 'Loading...', 'agentgarrison' ); ?></p>
				</div>
			</div>

		</div>

		<!-- Right: scan settings + API keys -->
		<div class="agentgarrison-citation-side">

			<!-- Scan schedule & notifications -->
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Scan Schedule & Alerts', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc" style="margin-bottom:16px;"><?php esc_html_e( 'Automated scans run on this schedule. Live citation data requires an API key below.', 'agentgarrison' ); ?></p>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-cm-frequency"><?php esc_html_e( 'Scan Frequency', 'agentgarrison' ); ?></label>
					<div class="agentgarrison-select-wrap">
						<select id="ag-cm-frequency" class="agentgarrison-select" name="scan_frequency">
							<option value="daily" <?php selected( 'daily', $rayetun_ag_cm_settings['scan_frequency'] ); ?>><?php esc_html_e( 'Daily', 'agentgarrison' ); ?></option>
							<option value="weekly" <?php selected( 'weekly', $rayetun_ag_cm_settings['scan_frequency'] ); ?>><?php esc_html_e( 'Weekly', 'agentgarrison' ); ?></option>
							<option value="monthly" <?php selected( 'monthly', $rayetun_ag_cm_settings['scan_frequency'] ); ?>><?php esc_html_e( 'Monthly', 'agentgarrison' ); ?></option>
						</select>
					</div>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-custom-checkbox">
						<input type="checkbox" id="ag-cm-notify" name="notify_enabled" value="1"
							<?php checked( ! empty( $rayetun_ag_cm_settings['notify_enabled'] ) ); ?>>
						<span class="agentgarrison-custom-checkbox__box"></span>
						<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Email me when new citations are found', 'agentgarrison' ); ?></span>
					</label>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-cm-notify-email"><?php esc_html_e( 'Alert Email', 'agentgarrison' ); ?></label>
					<input type="email" id="ag-cm-notify-email" class="agentgarrison-input" name="notify_email"
						value="<?php echo esc_attr( $rayetun_ag_cm_settings['notify_email'] ); ?>">
				</div>

				<div class="agentgarrison-field-actions">
					<button class="agentgarrison-btn agentgarrison-btn--primary js-save-citation-settings"><?php esc_html_e( 'Save Settings', 'agentgarrison' ); ?></button>
					<span class="agentgarrison-save-status js-citation-settings-status"></span>
				</div>

				<p class="agentgarrison-hint" style="margin-top:14px;">
					<?php esc_html_e( 'Alerts only fire for real (non-demo) citations, so demo scans never email you.', 'agentgarrison' ); ?>
				</p>
			</div>

			<!-- API keys -->
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Live API Keys', 'agentgarrison' ); ?></h2>

				<?php
				$rayetun_ag_ai_backend = Rayetun_AG_AI_Provider::active_backend();
				$rayetun_ag_ai_ok      = 'none' !== $rayetun_ag_ai_backend;
				?>
				<div class="agentgarrison-callout agentgarrison-callout--<?php echo $rayetun_ag_ai_ok ? 'success' : 'info'; ?>" style="margin-bottom:16px;">
					<?php echo $rayetun_ag_ai_ok ? '✅ ' : 'ℹ️ '; ?><?php echo esc_html( Rayetun_AG_AI_Provider::status_label() ); ?>
					<?php if ( ! $rayetun_ag_ai_ok ) : ?>
						<br><span style="font-size:12px;opacity:0.85;"><?php echo esc_html( Rayetun_AG_AI_Provider::core_diagnostic() ); ?></span>
					<?php endif; ?>
				</div>

				<p class="agentgarrison-card__desc" style="margin-bottom:16px;">
					<?php esc_html_e( 'These optional keys let AgentGarrison query OpenAI or Perplexity directly on sites without the WordPress core AI Client. If you have connected a provider under Settings → Connectors (such as Anthropic / Claude), that is used automatically and you do not need to enter anything here. Keys are stored in your database and never shared.', 'agentgarrison' ); ?>
				</p>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-cm-anthropic"><?php esc_html_e( 'Anthropic (Claude) API Key', 'agentgarrison' ); ?></label>
					<input type="password" id="ag-cm-anthropic" class="agentgarrison-input" name="anthropic"
						value="<?php echo esc_attr( $rayetun_ag_cm_keys['anthropic'] ); ?>" placeholder="sk-ant-...">
				</div>
				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-cm-openai"><?php esc_html_e( 'OpenAI API Key', 'agentgarrison' ); ?></label>
					<input type="password" id="ag-cm-openai" class="agentgarrison-input" name="openai"
						value="<?php echo esc_attr( $rayetun_ag_cm_keys['openai'] ); ?>" placeholder="sk-...">
				</div>
				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-cm-perplexity"><?php esc_html_e( 'Perplexity API Key', 'agentgarrison' ); ?></label>
					<input type="password" id="ag-cm-perplexity" class="agentgarrison-input" name="perplexity"
						value="<?php echo esc_attr( $rayetun_ag_cm_keys['perplexity'] ); ?>" placeholder="pplx-...">
				</div>

				<div class="agentgarrison-field-actions">
					<button class="agentgarrison-btn agentgarrison-btn--primary js-save-api-keys"><?php esc_html_e( 'Save API Keys', 'agentgarrison' ); ?></button>
					<span class="agentgarrison-save-status js-api-status"></span>
				</div>

				<p class="agentgarrison-hint" style="margin-top:14px;">
					<?php esc_html_e( 'See the External Services section of the plugin readme for data handling details.', 'agentgarrison' ); ?>
				</p>
			</div>
		</div>

	</div>
</div>
