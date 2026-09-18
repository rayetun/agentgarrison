<?php
/**
 * Plugin Name:       AgentGarrison – AI Visibility, AEO, GEO, AI Bot & Agent Control
 * Plugin URI:        https://wordpress.org/plugins/agentgarrison/
 * Description:       AI visibility toolkit: GEO/AEO optimization, llms.txt, AI bot & agent control, and citation tracking for ChatGPT, Perplexity, Claude & Gemini. No account needed.
 * Version:           1.2.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Md Rayhan Uddin
 * Author URI:        https://rayetun.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agentgarrison
 * Domain Path:       /languages
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAYETUN_AG_VERSION', '1.2.1' );
define( 'RAYETUN_AG_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAYETUN_AG_URL', plugin_dir_url( __FILE__ ) );
define( 'RAYETUN_AG_FILE', __FILE__ );

require_once RAYETUN_AG_DIR . 'includes/class-rayetun-ag-db.php';
require_once RAYETUN_AG_DIR . 'includes/class-rayetun-ag-modules.php';
require_once RAYETUN_AG_DIR . 'includes/class-agentgarrison.php';

register_activation_hook( __FILE__, array( 'Rayetun_AG_DB', 'create_tables' ) );
register_activation_hook( __FILE__, 'rayetun_ag_on_activation' );
register_deactivation_hook( __FILE__, 'rayetun_ag_on_deactivation' );

function rayetun_ag_on_activation() {
	Rayetun_AG_Modules::init_defaults();
	// Flag a one-time rewrite flush on the next init, once module rewrite rules
	// are registered (they aren't yet at activation time).
	update_option( 'rayetun_ag_llms_flush', 1 );
	update_option( 'rayetun_ag_honeypot_flush', 1 );
	flush_rewrite_rules();
}

function rayetun_ag_on_deactivation() {
	// Remove the physical llms.txt files we may have written to the web root.
	if ( class_exists( 'Rayetun_AG_Llms_Txt' ) ) {
		Rayetun_AG_Llms_Txt::get_instance()->delete_static_files();
	}
	flush_rewrite_rules();
}

Rayetun_AG_Core::get_instance();
