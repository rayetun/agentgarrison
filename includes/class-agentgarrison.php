<?php
/**
 * Main plugin singleton — loads all modules.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rayetun_AG_Core {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	private function includes() {
		// Shared AI provider adapter (core WP AI Client with BYO-key fallback).
		// Loaded before modules so Citation Monitor and future AI features can use it.
		require_once RAYETUN_AG_DIR . 'includes/class-rayetun-ag-ai-provider.php';

		// Always load all module files so AJAX handlers register unconditionally.
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-bot-control.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-llms-txt.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-analytics.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-referral-tracker.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-content-scorer.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-schema.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-email-digest.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-citation-monitor.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-reports.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-onboarding.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-network.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-honeypot.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-markdown-agents.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-qa-block.php';
		require_once RAYETUN_AG_DIR . 'includes/modules/class-rayetun-ag-visibility-score.php';

		// Privacy / GDPR personal-data tooling (always loaded — not module-gated).
		require_once RAYETUN_AG_DIR . 'includes/class-rayetun-ag-privacy.php';

		// Public-facing class loads unconditionally (handles robots.txt + bot intercept).
		require_once RAYETUN_AG_DIR . 'public/class-rayetun-ag-public.php';

		if ( is_admin() ) {
			require_once RAYETUN_AG_DIR . 'admin/class-rayetun-ag-admin.php';
		}
	}

	private function init_hooks() {
		// Translations load automatically for WordPress.org-hosted plugins (WP 4.6+),
		// so no load_plugin_textdomain() call is needed.
		add_action( 'plugins_loaded', array( $this, 'check_db_upgrade' ) );
		add_action( 'init', array( $this, 'boot_modules' ) );
	}

	public function check_db_upgrade() {
		Rayetun_AG_DB::maybe_upgrade();
	}

	public function boot_modules() {
		Rayetun_AG_Bot_Control::get_instance();
		Rayetun_AG_Llms_Txt::get_instance();
		Rayetun_AG_Analytics::get_instance();
		Rayetun_AG_Referral_Tracker::get_instance();
		Rayetun_AG_Content_Scorer::get_instance();
		Rayetun_AG_Schema::get_instance();
		Rayetun_AG_Email_Digest::get_instance();
		Rayetun_AG_Citation_Monitor::get_instance();
		Rayetun_AG_Reports::get_instance();
		Rayetun_AG_Network::get_instance();
		Rayetun_AG_Honeypot::get_instance();
		Rayetun_AG_Markdown_Agents::get_instance();
		Rayetun_AG_QA_Block::get_instance();
		Rayetun_AG_Visibility_Score::get_instance();
		Rayetun_AG_Privacy::get_instance();
		Rayetun_AG_Public::get_instance();

		if ( is_admin() ) {
			Rayetun_AG_Admin::get_instance();
			Rayetun_AG_Onboarding::get_instance();
		}
	}
}
