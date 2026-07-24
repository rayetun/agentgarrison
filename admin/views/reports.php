<?php
/**
 * White-label Reports view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_report_settings = Rayetun_AG_Reports::get_instance()->get_settings();
?>
<div class="agentgarrison-reports">
	<div class="agentgarrison-page-header">
		<div>
			<h1 class="agentgarrison-page-title"><?php esc_html_e( 'White-label Reports', 'agentgarrison' ); ?></h1>
			<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Generate a branded AI visibility report. Print to PDF directly from your browser.', 'agentgarrison' ); ?></p>
		</div>
	</div>

	<div class="agentgarrison-reports-grid">

		<!-- Branding settings -->
		<div class="agentgarrison-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Report Branding', 'agentgarrison' ); ?></h2>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-rep-company"><?php esc_html_e( 'Company / Agency Name', 'agentgarrison' ); ?></label>
				<input type="text" id="ag-rep-company" class="agentgarrison-input" name="company_name"
					value="<?php echo esc_attr( $rayetun_ag_report_settings['company_name'] ); ?>"
					placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</div>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-rep-logo"><?php esc_html_e( 'Logo URL', 'agentgarrison' ); ?></label>
				<input type="url" id="ag-rep-logo" class="agentgarrison-input" name="logo_url"
					value="<?php echo esc_attr( $rayetun_ag_report_settings['logo_url'] ); ?>"
					placeholder="https://example.com/logo.png">
			</div>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-rep-accent"><?php esc_html_e( 'Accent Color', 'agentgarrison' ); ?></label>
				<input type="text" id="ag-rep-accent" class="agentgarrison-input agentgarrison-input--sm" name="accent_color"
					value="<?php echo esc_attr( $rayetun_ag_report_settings['accent_color'] ); ?>"
					placeholder="#0F5C6B">
			</div>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label" for="ag-rep-footer"><?php esc_html_e( 'Footer Text', 'agentgarrison' ); ?></label>
				<input type="text" id="ag-rep-footer" class="agentgarrison-input" name="footer_text"
					value="<?php echo esc_attr( $rayetun_ag_report_settings['footer_text'] ); ?>"
					placeholder="<?php esc_attr_e( 'Prepared by Your Agency', 'agentgarrison' ); ?>">
			</div>

			<div class="agentgarrison-field-actions">
				<button class="agentgarrison-btn agentgarrison-btn--secondary js-save-report-settings"><?php esc_html_e( 'Save Branding', 'agentgarrison' ); ?></button>
				<span class="agentgarrison-save-status js-report-status"></span>
			</div>
		</div>

		<!-- Generate -->
		<div class="agentgarrison-card">
			<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Generate Report', 'agentgarrison' ); ?></h2>

			<div class="agentgarrison-field">
				<label class="agentgarrison-label"><?php esc_html_e( 'Report Period', 'agentgarrison' ); ?></label>
				<div class="agentgarrison-select-wrap agentgarrison-select-wrap--sm">
					<select class="agentgarrison-select js-report-period">
						<option value="7"><?php esc_html_e( 'Last 7 days', 'agentgarrison' ); ?></option>
						<option value="30" selected><?php esc_html_e( 'Last 30 days', 'agentgarrison' ); ?></option>
						<option value="90"><?php esc_html_e( 'Last 90 days', 'agentgarrison' ); ?></option>
					</select>
				</div>
			</div>

			<div class="agentgarrison-field-actions">
				<button class="agentgarrison-btn agentgarrison-btn--primary js-generate-report"><?php esc_html_e( 'Generate Report', 'agentgarrison' ); ?></button>
				<button class="agentgarrison-btn agentgarrison-btn--secondary js-download-report" style="display:none;"><?php esc_html_e( '⬇ Download HTML', 'agentgarrison' ); ?></button>
				<button class="agentgarrison-btn agentgarrison-btn--secondary js-print-report" style="display:none;"><?php esc_html_e( '🖨 Print / Save PDF', 'agentgarrison' ); ?></button>
			</div>

			<p class="agentgarrison-hint" style="margin-top:14px;">
				<?php esc_html_e( 'The report opens in a new tab. Use your browser\'s Print → Save as PDF for a polished, print-ready document.', 'agentgarrison' ); ?>
			</p>
		</div>

	</div>
</div>
