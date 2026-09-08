<?php
/**
 * Fired when the plugin is deleted.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Schema changes in uninstall are intentional — suppress PHPCS notices.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_ag_bot_visits' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_ag_llm_referrals' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_ag_citation_keywords' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_ag_citation_results' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_ag_citation_scans' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_ag_agent_events' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_ag_unknown_agents' ) );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

$rayetun_ag_options = array(
	'rayetun_ag_version',
	'rayetun_ag_db_version',
	'rayetun_ag_bots_version',
	'rayetun_ag_modules',
	'rayetun_ag_bot_settings',
	'rayetun_ag_llms_settings',
	'rayetun_ag_schema_settings',
	'rayetun_ag_general_settings',
	'rayetun_ag_visibility_score_cache',
	'rayetun_ag_llms_health',
	'rayetun_ag_llms_history',
	'rayetun_ag_llms_alert',
	'rayetun_ag_digest_settings',
	'rayetun_ag_citation_api_keys',
	'rayetun_ag_citation_settings',
	'rayetun_ag_citation_last_notified',
	'rayetun_ag_report_settings',
	'rayetun_ag_onboarding_complete',
	'rayetun_ag_honeypot_settings',
	'rayetun_ag_honeypot_blocked_ips',
	'rayetun_ag_honeypot_flush',
	'rayetun_ag_markdown_settings',
	'rayetun_ag_agent_settings',
);
foreach ( $rayetun_ag_options as $rayetun_ag_option ) {
	delete_option( $rayetun_ag_option );
}

// Remove scheduled cron events.
$rayetun_ag_cron_hooks = array(
	'rayetun_ag_cleanup_old_visits',
	'rayetun_ag_check_spoofing',
	'rayetun_ag_check_spikes',
	'rayetun_ag_llms_health_check',
	'rayetun_ag_refresh_visibility_score',
	'rayetun_ag_send_weekly_digest',
	'rayetun_ag_send_monthly_digest',
	'rayetun_ag_citation_scan',
	'rayetun_ag_cleanup_agent_events',
);
foreach ( $rayetun_ag_cron_hooks as $rayetun_ag_hook ) {
	$rayetun_ag_timestamp = wp_next_scheduled( $rayetun_ag_hook );
	if ( $rayetun_ag_timestamp ) {
		wp_unschedule_event( $rayetun_ag_timestamp, $rayetun_ag_hook );
	}
}

// Remove post meta.
$rayetun_ag_meta_keys = array(
	'_rayetun_ag_ai_score',
	'_rayetun_ag_score_details',
	'_rayetun_ag_score_suggestions',
	'_rayetun_ag_exclude_llms',
	'_rayetun_ag_schema_overrides',
);
foreach ( $rayetun_ag_meta_keys as $rayetun_ag_meta_key ) {
	delete_post_meta_by_key( $rayetun_ag_meta_key );
}

// Remove author-credential user meta.
$rayetun_ag_user_meta = array( 'rayetun_ag_job_title', 'rayetun_ag_knows_about', 'rayetun_ag_same_as' );
foreach ( $rayetun_ag_user_meta as $rayetun_ag_um ) {
	delete_metadata( 'user', 0, $rayetun_ag_um, '', true );
}

// Remove any physical llms.txt files written to the web root.
if ( ! is_multisite() ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( 'direct' === get_filesystem_method() && WP_Filesystem() ) {
		global $wp_filesystem;
		$rayetun_ag_root = trailingslashit( $wp_filesystem->abspath() );
		foreach ( array( 'llms.txt', 'llms-full.txt' ) as $rayetun_ag_static ) {
			if ( $wp_filesystem->exists( $rayetun_ag_root . $rayetun_ag_static ) ) {
				$wp_filesystem->delete( $rayetun_ag_root . $rayetun_ag_static );
			}
		}
	}
}
