<?php
/**
 * Phase 4 — Onboarding Wizard.
 *
 * 4-step first-run setup: bot policy → site context → llms.txt → complete.
 * Shown as a full-page overlay on first activation. Skippable at any step.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Onboarding {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function is_complete() {
		return (bool) get_option( 'rayetun_ag_onboarding_complete', false );
	}

	private function __construct() {
		add_action( 'wp_ajax_rayetun_ag_onboarding_step', array( $this, 'handle_step' ) );
		add_action( 'wp_ajax_rayetun_ag_onboarding_skip', array( $this, 'handle_skip' ) );

		// Enqueue wizard assets and inject the wizard overlay on AgentGarrison pages only.
		add_action( 'admin_footer', array( $this, 'maybe_render_wizard' ) );
	}

	public function maybe_render_wizard() {
		if ( self::is_complete() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'agentgarrison' ) ) {
			return;
		}
		$this->render_wizard();
	}

	// -------------------------------------------------------------------------
	// Wizard HTML
	// -------------------------------------------------------------------------

	private function render_wizard() {
		?>
		<div class="ag-wizard-overlay" id="ag-wizard">
			<div class="ag-wizard-modal">

				<!-- Progress bar -->
				<div class="ag-wizard-progress">
					<?php for ( $rayetun_ag_wi = 1; $rayetun_ag_wi <= 4; $rayetun_ag_wi++ ) : ?>
					<div class="ag-wizard-step <?php echo 1 === $rayetun_ag_wi ? 'is-active' : ''; ?>" data-step="<?php echo absint( $rayetun_ag_wi ); ?>">
						<span class="ag-wizard-step__num"><?php echo absint( $rayetun_ag_wi ); ?></span>
						<span class="ag-wizard-step__label">
							<?php
							$rayetun_ag_wlabels = array(
								1 => __( 'Bot Policy', 'agentgarrison' ),
								2 => __( 'Site Context', 'agentgarrison' ),
								3 => __( 'Generate llms.txt', 'agentgarrison' ),
								4 => __( 'Done!', 'agentgarrison' ),
							);
							echo esc_html( $rayetun_ag_wlabels[ $rayetun_ag_wi ] );
							?>
						</span>
					</div>
					<?php endfor; ?>
				</div>

				<!-- Step panels -->
				<div class="ag-wizard-body">

					<!-- Step 1: Bot Policy -->
					<div class="ag-wizard-panel is-active" data-panel="1">
						<div class="ag-wizard-icon">🤖</div>
						<h2 class="ag-wizard-title"><?php esc_html_e( 'Choose your AI bot policy', 'agentgarrison' ); ?></h2>
						<p class="ag-wizard-desc"><?php esc_html_e( 'Select the default rule for AI bots. You can fine-tune individual bots later in Bot Control.', 'agentgarrison' ); ?></p>

						<div class="ag-wizard-choices">
							<label class="ag-wizard-choice is-active">
								<input type="radio" name="bw_bot_policy" value="recommended" checked>
								<div class="ag-wizard-choice__icon">✅</div>
								<div class="ag-wizard-choice__body">
									<strong><?php esc_html_e( 'Recommended', 'agentgarrison' ); ?></strong>
									<span><?php esc_html_e( 'Allow AI Assistants & Search, block AI Trainers & Scrapers', 'agentgarrison' ); ?></span>
								</div>
							</label>
							<label class="ag-wizard-choice">
								<input type="radio" name="bw_bot_policy" value="open">
								<div class="ag-wizard-choice__icon">🌐</div>
								<div class="ag-wizard-choice__body">
									<strong><?php esc_html_e( 'Open', 'agentgarrison' ); ?></strong>
									<span><?php esc_html_e( 'Allow all AI bots — maximise discoverability', 'agentgarrison' ); ?></span>
								</div>
							</label>
							<label class="ag-wizard-choice">
								<input type="radio" name="bw_bot_policy" value="strict">
								<div class="ag-wizard-choice__icon">🔒</div>
								<div class="ag-wizard-choice__body">
									<strong><?php esc_html_e( 'Strict', 'agentgarrison' ); ?></strong>
									<span><?php esc_html_e( 'Block all AI bots — protect content from scraping', 'agentgarrison' ); ?></span>
								</div>
							</label>
						</div>
					</div>

					<!-- Step 2: Site Context -->
					<div class="ag-wizard-panel" data-panel="2">
						<div class="ag-wizard-icon">📝</div>
						<h2 class="ag-wizard-title"><?php esc_html_e( 'Describe your site to AI models', 'agentgarrison' ); ?></h2>
						<p class="ag-wizard-desc"><?php esc_html_e( 'This one-sentence description appears at the top of your llms.txt and helps AI models understand what your site is about.', 'agentgarrison' ); ?></p>

						<div class="ag-wizard-field">
							<textarea class="agentgarrison-textarea" id="ag-wizard-context" rows="3"
								placeholder="<?php esc_attr_e( 'E.g. "A camping resource covering gear reviews, safety guides, and trip planning for outdoor enthusiasts."', 'agentgarrison' ); ?>"></textarea>
							<p class="agentgarrison-hint"><?php esc_html_e( 'Keep it to 1–2 sentences. You can edit this any time in the llms.txt settings.', 'agentgarrison' ); ?></p>
						</div>
					</div>

					<!-- Step 3: Generate llms.txt -->
					<div class="ag-wizard-panel" data-panel="3">
						<div class="ag-wizard-icon">📄</div>
						<h2 class="ag-wizard-title"><?php esc_html_e( 'Generate your llms.txt', 'agentgarrison' ); ?></h2>
						<p class="ag-wizard-desc"><?php esc_html_e( 'AgentGarrison will auto-generate /llms.txt with your posts and pages. AI crawlers use this file to understand and navigate your content.', 'agentgarrison' ); ?></p>

						<div class="ag-wizard-generate-status js-wizard-generate-status">
							<div class="ag-wizard-generate-icon">⏳</div>
							<p><?php esc_html_e( 'Click Generate to create your llms.txt file.', 'agentgarrison' ); ?></p>
						</div>

						<button class="agentgarrison-btn agentgarrison-btn--secondary ag-wizard-generate-btn js-wizard-generate">
							<?php esc_html_e( '⚡ Generate Now', 'agentgarrison' ); ?>
						</button>
					</div>

					<!-- Step 4: Complete -->
					<div class="ag-wizard-panel" data-panel="4">
						<div class="ag-wizard-icon">🎉</div>
						<h2 class="ag-wizard-title"><?php esc_html_e( "You're all set!", 'agentgarrison' ); ?></h2>
						<p class="ag-wizard-desc"><?php esc_html_e( "AgentGarrison is protecting and optimising your site for AI. Here's what happens next:", 'agentgarrison' ); ?></p>

						<ul class="ag-wizard-next-steps">
							<li>🤖 <?php esc_html_e( 'AI bots will crawl your site and visit count grows in Analytics', 'agentgarrison' ); ?></li>
							<li>📄 <?php esc_html_e( 'AI models will read your llms.txt and learn about your content', 'agentgarrison' ); ?></li>
							<li>🔗 <?php esc_html_e( 'LLM Referrals appear when AI platforms send traffic to your site', 'agentgarrison' ); ?></li>
							<li>📊 <?php esc_html_e( 'Your AI Visibility Score will improve as you optimise each pillar', 'agentgarrison' ); ?></li>
						</ul>
					</div>

				</div><!-- .ag-wizard-body -->

				<!-- Footer actions -->
				<div class="ag-wizard-footer">
					<button class="agentgarrison-btn agentgarrison-btn--secondary ag-wizard-skip js-wizard-skip">
						<?php esc_html_e( 'Skip setup', 'agentgarrison' ); ?>
					</button>
					<div class="ag-wizard-footer__right">
						<button class="agentgarrison-btn agentgarrison-btn--secondary ag-wizard-back js-wizard-back" style="display:none;">
							← <?php esc_html_e( 'Back', 'agentgarrison' ); ?>
						</button>
						<button class="agentgarrison-btn agentgarrison-btn--primary ag-wizard-next js-wizard-next">
							<?php esc_html_e( 'Next', 'agentgarrison' ); ?> →
						</button>
						<button class="agentgarrison-btn agentgarrison-btn--primary ag-wizard-finish js-wizard-finish" style="display:none;">
							<?php esc_html_e( 'Go to Dashboard', 'agentgarrison' ); ?> 🚀
						</button>
					</div>
				</div>

			</div><!-- .ag-wizard-modal -->
		</div><!-- .ag-wizard-overlay -->
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function handle_step() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$step = absint( $_POST['step'] ?? 0 );

		switch ( $step ) {
			case 1:
				// Apply bot policy.
				$policy   = sanitize_key( wp_unslash( $_POST['policy'] ?? 'recommended' ) );
				$this->apply_bot_policy( $policy );
				break;

			case 2:
				// Save site context.
				$context  = sanitize_textarea_field( wp_unslash( $_POST['context'] ?? '' ) );
				$settings = get_option( 'rayetun_ag_llms_settings', array() );
				$settings['site_context'] = $context;
				update_option( 'rayetun_ag_llms_settings', $settings );
				break;

			case 3:
				// Generate llms.txt (and write the physical file for host compatibility).
				if ( Rayetun_AG_Modules::is_enabled( 'llms_txt' ) ) {
					$llms = Rayetun_AG_Llms_Txt::get_instance();
					$llms->generate();
					$llms->write_static_files();
				}
				break;

			case 4:
				// Complete.
				update_option( 'rayetun_ag_onboarding_complete', true );
				break;
		}

		// Each wizard step changes a pillar's state — refresh the dashboard score.
		Rayetun_AG_Visibility_Score::invalidate();

		wp_send_json_success();
	}

	public function handle_skip() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		update_option( 'rayetun_ag_onboarding_complete', true );
		wp_send_json_success();
	}

	private function apply_bot_policy( $policy ) {
		$presets = array(
			'recommended' => array(
				'ai_trainer'      => 'block',
				'ai_assistant'    => 'allow',
				'ai_search'       => 'allow',
				'seo_crawler'     => 'allow',
				'general_scraper' => 'block',
			),
			'open' => array(
				'ai_trainer'      => 'allow',
				'ai_assistant'    => 'allow',
				'ai_search'       => 'allow',
				'seo_crawler'     => 'allow',
				'general_scraper' => 'allow',
			),
			'strict' => array(
				'ai_trainer'      => 'block',
				'ai_assistant'    => 'block',
				'ai_search'       => 'block',
				'seo_crawler'     => 'block',
				'general_scraper' => 'block',
			),
		);

		if ( ! isset( $presets[ $policy ] ) ) {
			$policy = 'recommended';
		}

		$settings               = get_option( 'rayetun_ag_bot_settings', array() );
		$settings['categories'] = $presets[ $policy ];
		update_option( 'rayetun_ag_bot_settings', $settings );
	}
}
