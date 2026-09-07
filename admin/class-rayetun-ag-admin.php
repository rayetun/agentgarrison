<?php
/**
 * Admin controller — menu registration, asset enqueueing, page routing.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_rayetun_ag_toggle_module', array( $this, 'handle_toggle_module' ) );
		add_action( 'wp_ajax_rayetun_ag_save_general_settings', array( $this, 'handle_save_general_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_toggle_dark_mode', array( $this, 'handle_toggle_dark_mode' ) );

		// Suppress third-party admin notices on AgentGarrison screens only.
		add_action( 'in_admin_header', array( $this, 'suppress_admin_notices' ), 1000 );
	}

	/**
	 * Remove other plugins' admin notices on AgentGarrison pages so the custom
	 * dashboard stays clean. Runs only on our own screens — never site-wide.
	 */
	public function suppress_admin_notices() {
		if ( ! $this->is_rayetun_ag_screen() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * True when the current admin screen is a AgentGarrison page.
	 */
	private function is_rayetun_ag_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && false !== strpos( $screen->id, 'agentgarrison' );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function register_menu() {
		// Shield-with-check icon — represents the plugin's role as an AI bot guardian.
		// Solid fills render reliably at the ~20px admin-menu size.
		$svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 4.9 3.4 9.4 8 11 4.6-1.6 8-6.1 8-11V5l-8-3z" fill="%23a7aaad"/><path d="m10.7 14.3-2.1-2.1-1.5 1.5 3.6 3.6 6.3-6.3-1.5-1.5z" fill="%23ffffff"/></svg>' );

		add_menu_page(
			__( 'AgentGarrison', 'agentgarrison' ),
			__( 'AgentGarrison', 'agentgarrison' ),
			'manage_options',
			'agentgarrison',
			array( $this, 'render_page' ),
			$svg,
			76
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( $hook ) {
		$css_file = RAYETUN_AG_DIR . 'admin/css/rayetun-ag-admin.css';
		$js_file  = RAYETUN_AG_DIR . 'admin/js/rayetun-ag-admin.js';

		$css_ver = RAYETUN_AG_VERSION . '.' . ( file_exists( $css_file ) ? filemtime( $css_file ) : '0' );
		$js_ver  = RAYETUN_AG_VERSION . '.' . ( file_exists( $js_file ) ? filemtime( $js_file ) : '0' );

		// Menu-icon styling loads on every admin page so the top-level icon can
		// react to hover/focus/current-page state. It is tiny and self-contained.
		$icon_file = RAYETUN_AG_DIR . 'admin/css/rayetun-ag-menu-icon.css';
		$icon_ver  = RAYETUN_AG_VERSION . '.' . ( file_exists( $icon_file ) ? filemtime( $icon_file ) : '0' );
		wp_enqueue_style( 'rayetun-ag-menu-icon', RAYETUN_AG_URL . 'admin/css/rayetun-ag-menu-icon.css', array(), $icon_ver );

		// Post edit + post list screens: load CSS + core JS (for the score/schema
		// metaboxes) but NOT Chart.js — the charts only render on AgentGarrison pages.
		$post_screens = array( 'post.php', 'post-new.php', 'edit.php' );
		if ( false === strpos( $hook, 'agentgarrison' ) ) {
			if ( in_array( $hook, $post_screens, true ) ) {
				wp_enqueue_style( 'rayetun-ag-admin', RAYETUN_AG_URL . 'admin/css/rayetun-ag-admin.css', array(), $css_ver );
				wp_enqueue_script( 'rayetun-ag-admin', RAYETUN_AG_URL . 'admin/js/rayetun-ag-admin.js', array( 'jquery' ), $js_ver, true );
				$this->localize_data();
			}
			return;
		}

		wp_enqueue_style( 'rayetun-ag-admin', RAYETUN_AG_URL . 'admin/css/rayetun-ag-admin.css', array(), $css_ver );

		// On our own screens only, stop WordPress from replacing the emoji sidebar
		// icons with Twemoji <img> tags after load. That async swap caused a visible
		// icon flash and a one-pixel layout shift ("shake"). Native emoji render once.
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		// Chart.js — bundled, no CDN. Enqueue before the admin script so it's available.
		$chart_file = RAYETUN_AG_DIR . 'admin/js/vendor/chart.min.js';
		if ( file_exists( $chart_file ) ) {
			wp_enqueue_script( 'rayetun-ag-chartjs', RAYETUN_AG_URL . 'admin/js/vendor/chart.min.js', array(), '4.4.3', true );
		}

		wp_enqueue_script( 'rayetun-ag-admin', RAYETUN_AG_URL . 'admin/js/rayetun-ag-admin.js', array( 'jquery' ), $js_ver, true );
		$this->localize_data();
	}

	private function localize_data() {
		wp_localize_script( 'rayetun-ag-admin', 'rayetunAgData', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'rayetun_ag_ajax' ),
			'exportNonce' => wp_create_nonce( 'rayetun_ag_export' ),
			'strings'     => array(
				'saved'        => __( 'Settings saved.', 'agentgarrison' ),
				'error'        => __( 'Something went wrong. Please try again.', 'agentgarrison' ),
				'confirm'      => __( 'Are you sure?', 'agentgarrison' ),
				'loading'      => __( 'Loading...', 'agentgarrison' ),
				'regenerating' => __( 'Regenerating...', 'agentgarrison' ),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Page router
	// -------------------------------------------------------------------------

	public function render_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed_tabs = array( 'dashboard', 'bot-control', 'agent-control', 'llms-txt', 'markdown', 'analytics', 'referrals', 'content-scorer', 'schema', 'citation-monitor', 'reports', 'settings' );
		if ( ! in_array( $tab, $allowed_tabs, true ) ) {
			$tab = 'dashboard';
		}

		// If the requested tab belongs to a disabled module, fall back to the dashboard.
		$tab_module = self::tab_module_map();
		if ( isset( $tab_module[ $tab ] ) && ! Rayetun_AG_Modules::is_enabled( $tab_module[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		$view_map = array(
			'dashboard'        => 'dashboard.php',
			'bot-control'      => 'bot-control.php',
			'agent-control'    => 'agent-control.php',
			'llms-txt'         => 'llms-txt.php',
			'markdown'         => 'markdown-agents.php',
			'analytics'        => 'analytics.php',
			'referrals'        => 'referrals.php',
			'content-scorer'   => 'content-scorer.php',
			'schema'           => 'schema.php',
			'citation-monitor' => 'citation-monitor.php',
			'reports'          => 'reports.php',
			'settings'         => 'settings.php',
		);

		// Read dark mode preference HERE — before the wrap div is rendered.
		$rayetun_ag_dark_pref = get_user_meta( get_current_user_id(), 'rayetun_ag_dark_mode', true );

		$view_file = RAYETUN_AG_DIR . 'admin/views/' . $view_map[ $tab ];
		?>
		<div class="agentgarrison-wrap <?php echo $rayetun_ag_dark_pref ? 'agentgarrison-dark' : ''; ?>"
			 data-dark="<?php echo $rayetun_ag_dark_pref ? '1' : '0'; ?>">
			<?php $this->render_sidebar( $tab, $rayetun_ag_dark_pref ); ?>
			<div class="agentgarrison-content">
				<?php
				if ( file_exists( $view_file ) ) {
					require $view_file;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Maps each sidebar tab to the module that controls it.
	 * Tabs not listed here (dashboard, settings) are always available.
	 */
	public static function tab_module_map() {
		return array(
			'bot-control'      => 'bot_control',
			'agent-control'    => 'agent_control',
			'llms-txt'         => 'llms_txt',
			'markdown'         => 'markdown_agents',
			'analytics'        => 'analytics',
			'referrals'        => 'referral_tracker',
			'content-scorer'   => 'content_scorer',
			'schema'           => 'schema',
			'citation-monitor' => 'citation_monitor',
			'reports'          => 'reports',
		);
	}

	private function render_sidebar( $active_tab, $rayetun_ag_dark = false ) {
		$nav_items = array(
			'dashboard'      => array( 'label' => __( 'Dashboard', 'agentgarrison' ), 'icon' => '🏠' ),
			'bot-control'    => array( 'label' => __( 'Bot Control', 'agentgarrison' ), 'icon' => '🤖' ),
			'agent-control'  => array( 'label' => __( 'Agent Control', 'agentgarrison' ), 'icon' => '🛡️' ),
			'llms-txt'       => array( 'label' => __( 'llms.txt', 'agentgarrison' ), 'icon' => '📄' ),
			'markdown'       => array( 'label' => __( 'Markdown', 'agentgarrison' ), 'icon' => '📝' ),
			'analytics'      => array( 'label' => __( 'Analytics', 'agentgarrison' ), 'icon' => '📊' ),
			'referrals'      => array( 'label' => __( 'Referrals', 'agentgarrison' ), 'icon' => '🔗' ),
			'content-scorer' => array( 'label' => __( 'Content Scorer', 'agentgarrison' ), 'icon' => '✍️' ),
			'schema'         => array( 'label' => __( 'Schema', 'agentgarrison' ), 'icon' => '🗂️' ),
			'citation-monitor' => array( 'label' => __( 'Citation Monitor', 'agentgarrison' ), 'icon' => '🔎' ),
			'reports'        => array( 'label' => __( 'Reports', 'agentgarrison' ), 'icon' => '📑' ),
			'settings'       => array( 'label' => __( 'Settings', 'agentgarrison' ), 'icon' => '⚙️' ),
		);

		$tab_module = self::tab_module_map();
		?>
		<div class="agentgarrison-sidebar">
			<div class="agentgarrison-sidebar__brand">
				<img class="agentgarrison-sidebar__logo"
					src="<?php echo esc_url( RAYETUN_AG_URL . 'admin/images/agentgarrison-icon.svg' ); ?>"
					alt="" width="28" height="28">
				<span class="agentgarrison-sidebar__name">AgentGarrison</span>
			</div>
			<nav class="agentgarrison-sidebar__nav">
				<?php
				foreach ( $nav_items as $tab_id => $item ) :
					// Skip tabs whose module is disabled.
					if ( isset( $tab_module[ $tab_id ] ) && ! Rayetun_AG_Modules::is_enabled( $tab_module[ $tab_id ] ) ) {
						continue;
					}
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=agentgarrison&tab=' . $tab_id ) ); ?>"
					   class="agentgarrison-nav-item <?php echo $active_tab === $tab_id ? 'is-active' : ''; ?>">
						<span class="agentgarrison-nav-item__icon"><?php echo esc_html( $item['icon'] ); ?></span>
						<span class="agentgarrison-nav-item__label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="agentgarrison-sidebar__footer">
				<button class="agentgarrison-dark-toggle js-dark-toggle" title="<?php esc_attr_e( 'Toggle dark mode', 'agentgarrison' ); ?>">
					<span class="agentgarrison-dark-toggle__icon"><?php echo $rayetun_ag_dark ? '☀️' : '🌙'; ?></span>
				</button>
				<span class="agentgarrison-version">v<?php echo esc_html( RAYETUN_AG_VERSION ); ?></span>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function handle_toggle_module() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$module_id = sanitize_key( wp_unslash( $_POST['module_id'] ?? '' ) );
		$enabled   = ! empty( $_POST['enabled'] );
		Rayetun_AG_Modules::set( $module_id, $enabled );
		Rayetun_AG_Visibility_Score::invalidate();

		// Keep the physical llms.txt in sync with the module's enabled state.
		if ( 'llms_txt' === $module_id ) {
			$llms = Rayetun_AG_Llms_Txt::get_instance();
			if ( $enabled ) {
				$llms->write_static_files();
			} else {
				$llms->delete_static_files();
			}
		}

		wp_send_json_success( array( 'module_id' => $module_id, 'enabled' => $enabled ) );
	}

	public function handle_toggle_dark_mode() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$enabled = ! empty( $_POST['enabled'] );
		update_user_meta( get_current_user_id(), 'rayetun_ag_dark_mode', $enabled ? '1' : '' );
		wp_send_json_success( array( 'dark' => $enabled ) );
	}

	public function handle_save_general_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$allowed_retention = array( 30, 60, 90, 180, 365 );
		$retention_days    = absint( $_POST['retention_days'] ?? 90 );
		if ( ! in_array( $retention_days, $allowed_retention, true ) ) {
			$retention_days = 90;
		}
		$settings = array(
			'alert_email'    => sanitize_email( wp_unslash( $_POST['alert_email'] ?? '' ) ),
			'retention_days' => $retention_days,
		);
		update_option( 'rayetun_ag_general_settings', $settings );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'agentgarrison' ) ) );
	}
}
