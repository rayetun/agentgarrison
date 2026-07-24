<?php
/**
 * llms.txt Generator view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_llms           = Rayetun_AG_Llms_Txt::get_instance();
$rayetun_ag_llms_settings  = $rayetun_ag_llms->get_settings();
$rayetun_ag_llms_health    = get_option( 'rayetun_ag_llms_health', array() );
$rayetun_ag_llms_pt        = get_post_types( array( 'public' => true ), 'objects' );
$rayetun_ag_selected_types = (array) ( $rayetun_ag_llms_settings['post_types'] ?? array( 'post', 'page' ) );
?>
<div class="agentgarrison-llms-txt">
	<div class="agentgarrison-page-header">
		<h1 class="agentgarrison-page-title"><?php esc_html_e( 'llms.txt Generator', 'agentgarrison' ); ?></h1>
		<p class="agentgarrison-page-subtitle">
			<?php esc_html_e( 'Auto-generate /llms.txt to tell AI models what your site is about.', 'agentgarrison' ); ?>
		</p>
	</div>

	<?php if ( ! empty( $rayetun_ag_llms_health ) ) :
		$rayetun_ag_health_ok   = ! empty( $rayetun_ag_llms_health['ok'] );
		$rayetun_ag_health_type = $rayetun_ag_health_ok ? 'success' : 'error';
		?>
	<div class="agentgarrison-callout agentgarrison-callout--<?php echo esc_attr( $rayetun_ag_health_type ); ?>">
		<?php if ( $rayetun_ag_health_ok ) : ?>
			✅ <?php esc_html_e( 'llms.txt is reachable and returning correctly.', 'agentgarrison' ); ?>
			<?php if ( ! empty( $rayetun_ag_llms_health['stale'] ) ) : ?>
				<strong><?php esc_html_e( 'Note: File is stale (not regenerated in 7+ days). Click Regenerate.', 'agentgarrison' ); ?></strong>
			<?php endif; ?>
		<?php else : ?>
			⚠️ <?php esc_html_e( 'llms.txt is not reachable.', 'agentgarrison' ); ?>
			<?php
			printf(
				/* translators: %d: HTTP status code */
				esc_html__( 'Status: %d. Try saving your permalink settings.', 'agentgarrison' ),
				absint( $rayetun_ag_llms_health['status_code'] ?? 0 )
			);
			?>
		<?php endif; ?>
		<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener" class="agentgarrison-link">
			<?php esc_html_e( 'View llms.txt ↗', 'agentgarrison' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php
	$rayetun_ag_llms_drift = $rayetun_ag_llms->get_drift_count();
	if ( $rayetun_ag_llms_drift > 0 ) :
		?>
	<div class="agentgarrison-callout agentgarrison-callout--info">
		📝
		<?php
		printf(
			/* translators: %d: number of changed pages */
			esc_html( _n( '%d page has changed since your llms.txt was last generated.', '%d pages have changed since your llms.txt was last generated.', $rayetun_ag_llms_drift, 'agentgarrison' ) ),
			absint( $rayetun_ag_llms_drift )
		);
		?>
		<button class="agentgarrison-btn agentgarrison-btn--sm agentgarrison-btn--primary js-regenerate-llms" style="margin-left:10px;">
			<?php esc_html_e( 'Regenerate now', 'agentgarrison' ); ?>
		</button>
	</div>
	<?php endif; ?>

	<div class="agentgarrison-two-col">
		<div class="agentgarrison-col agentgarrison-col--settings">
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Settings', 'agentgarrison' ); ?></h2>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-site-context">
						<?php esc_html_e( 'Site Context', 'agentgarrison' ); ?>
					</label>
					<textarea id="ag-site-context" class="agentgarrison-textarea" rows="4" name="site_context" placeholder="<?php esc_attr_e( 'Describe your site in one or two sentences for AI models. E.g. "A WordPress plugin development blog covering best practices, security, and WP.org submission."', 'agentgarrison' ); ?>"><?php echo esc_textarea( $rayetun_ag_llms_settings['site_context'] ?? '' ); ?></textarea>
					<p class="agentgarrison-hint"><?php esc_html_e( 'This appears as the > description at the top of your llms.txt.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label"><?php esc_html_e( 'Include Post Types', 'agentgarrison' ); ?></label>
					<div class="agentgarrison-checkboxes">
						<?php foreach ( $rayetun_ag_llms_pt as $rayetun_ag_llms_pt_obj ) : ?>
						<label class="agentgarrison-checkbox-pill">
							<input type="checkbox" name="post_types[]"
								value="<?php echo esc_attr( $rayetun_ag_llms_pt_obj->name ); ?>"
								<?php checked( in_array( $rayetun_ag_llms_pt_obj->name, $rayetun_ag_selected_types, true ) ); ?>>
							<span class="agentgarrison-checkbox-pill__mark"></span>
							<span class="agentgarrison-checkbox-pill__text"><?php echo esc_html( $rayetun_ag_llms_pt_obj->labels->name ); ?></span>
						</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-custom-additions"><?php esc_html_e( 'Custom Additions', 'agentgarrison' ); ?></label>
					<textarea id="ag-custom-additions" class="agentgarrison-textarea" rows="4" name="custom_additions"
						placeholder="<?php esc_attr_e( "## Resources&#10;- API Docs: https://example.com/docs&#10;- Pricing: https://example.com/pricing", 'agentgarrison' ); ?>"><?php echo esc_textarea( $rayetun_ag_llms_settings['custom_additions'] ?? '' ); ?></textarea>
					<p class="agentgarrison-hint"><?php esc_html_e( 'Markdown appended to the end of the auto-generated file — add extra links or sections (docs, pricing, partner sites). Auto-generation stays intact.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-custom-checkbox">
						<input type="checkbox" id="ag-manual-mode" name="manual_mode" value="1"
							<?php checked( ! empty( $rayetun_ag_llms_settings['manual_mode'] ) ); ?>>
						<span class="agentgarrison-custom-checkbox__box"></span>
						<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Manual override — write the entire file myself', 'agentgarrison' ); ?></span>
					</label>
					<p class="agentgarrison-hint"><?php esc_html_e( 'Advanced. When on, the content below replaces the entire auto-generated file. The validator still checks it. Leave off to keep auto-generation.', 'agentgarrison' ); ?></p>
					<textarea id="ag-manual-content" class="agentgarrison-textarea js-manual-content" rows="8" name="manual_content"
						placeholder="# Your Site Name" style="margin-top:10px;font-family:monospace;font-size:12px;<?php echo empty( $rayetun_ag_llms_settings['manual_mode'] ) ? 'display:none;' : ''; ?>"><?php echo esc_textarea( $rayetun_ag_llms_settings['manual_content'] ?? '' ); ?></textarea>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-custom-checkbox">
						<input type="checkbox" id="ag-static-file" name="static_file" value="1"
							<?php checked( ! empty( $rayetun_ag_llms_settings['static_file'] ) ); ?>>
						<span class="agentgarrison-custom-checkbox__box"></span>
						<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Write a static llms.txt file to my site root', 'agentgarrison' ); ?></span>
					</label>
					<p class="agentgarrison-hint"><?php esc_html_e( 'Enable this only if /llms.txt returns a 404 on your host (common on WP Engine and some nginx setups, which serve .txt as static files). AgentGarrison will write a real llms.txt and llms-full.txt to your web root. Leave off otherwise — the virtual file works on most hosts. The file is removed automatically if you turn this off or deactivate the plugin.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field-actions agentgarrison-field-actions--stacked">
					<div class="agentgarrison-field-actions__btns">
						<button class="agentgarrison-btn agentgarrison-btn--primary js-save-llms-settings">
							<?php esc_html_e( 'Save Settings', 'agentgarrison' ); ?>
						</button>
						<button class="agentgarrison-btn agentgarrison-btn--secondary js-regenerate-llms">
							<?php esc_html_e( 'Regenerate Now', 'agentgarrison' ); ?>
						</button>
					</div>
					<?php if ( ! empty( $rayetun_ag_llms_settings['last_generated'] ) ) : ?>
					<p class="agentgarrison-hint" style="margin:8px 0 0;">
						<?php
						printf(
							/* translators: %s: time ago */
							esc_html__( 'Last generated: %s ago', 'agentgarrison' ),
							esc_html( human_time_diff( $rayetun_ag_llms_settings['last_generated'], time() ) )
						);
						?>
					</p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="agentgarrison-col agentgarrison-col--preview">
			<div class="agentgarrison-card">
				<div class="agentgarrison-card__header">
					<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Live Preview', 'agentgarrison' ); ?></h2>
					<div class="agentgarrison-preview-actions">
						<button class="agentgarrison-btn agentgarrison-btn--sm agentgarrison-btn--secondary js-copy-llms" title="<?php esc_attr_e( 'Copy to clipboard', 'agentgarrison' ); ?>">
							<?php esc_html_e( 'Copy', 'agentgarrison' ); ?>
						</button>
						<button class="agentgarrison-btn agentgarrison-btn--sm agentgarrison-btn--secondary js-download-llms" title="<?php esc_attr_e( 'Download as .txt', 'agentgarrison' ); ?>">
							<?php esc_html_e( 'Download', 'agentgarrison' ); ?>
						</button>
						<button class="agentgarrison-btn agentgarrison-btn--sm js-preview-llms">
							<?php esc_html_e( 'Refresh', 'agentgarrison' ); ?>
						</button>
					</div>
				</div>
				<pre class="agentgarrison-preview-pane js-llms-preview"><?php esc_html_e( 'Click Regenerate to see your llms.txt preview.', 'agentgarrison' ); ?></pre>
			</div>
		</div>
	</div>

	<!-- Validator & AI Insights -->
	<div class="agentgarrison-card agentgarrison-validator-card">
		<div class="agentgarrison-card__header">
			<div>
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Validator & AI Insights', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc"><?php esc_html_e( 'Check your llms.txt against the spec and see how AI models will read it.', 'agentgarrison' ); ?></p>
			</div>
			<button class="agentgarrison-btn agentgarrison-btn--primary js-validate-llms">
				<?php esc_html_e( 'Run Validator', 'agentgarrison' ); ?>
			</button>
		</div>

		<div class="agentgarrison-validator-body js-validator-body" style="display:none;">

			<!-- Insight stat cards -->
			<div class="agentgarrison-validator-stats">
				<div class="agentgarrison-vstat">
					<div class="agentgarrison-vstat__num js-vstat-size">—</div>
					<div class="agentgarrison-vstat__label"><?php esc_html_e( 'File Size', 'agentgarrison' ); ?></div>
				</div>
				<div class="agentgarrison-vstat">
					<div class="agentgarrison-vstat__num js-vstat-tokens">—</div>
					<div class="agentgarrison-vstat__label"><?php esc_html_e( 'Est. Tokens', 'agentgarrison' ); ?></div>
				</div>
				<div class="agentgarrison-vstat">
					<div class="agentgarrison-vstat__num js-vstat-included">—</div>
					<div class="agentgarrison-vstat__label"><?php esc_html_e( 'Pages Included', 'agentgarrison' ); ?></div>
				</div>
				<div class="agentgarrison-vstat">
					<div class="agentgarrison-vstat__num js-vstat-excluded">—</div>
					<div class="agentgarrison-vstat__label"><?php esc_html_e( 'Pages Excluded', 'agentgarrison' ); ?></div>
				</div>
				<div class="agentgarrison-vstat">
					<div class="agentgarrison-vstat__num js-vstat-sections">—</div>
					<div class="agentgarrison-vstat__label"><?php esc_html_e( 'Sections', 'agentgarrison' ); ?></div>
				</div>
			</div>

			<!-- Spec checks -->
			<div class="agentgarrison-validator-checks js-validator-checks"></div>

			<!-- Warnings -->
			<div class="agentgarrison-validator-warnings js-validator-warnings"></div>

		</div>
	</div>
</div>
