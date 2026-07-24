<?php
/**
 * Module — AI-Ready Q&A block.
 *
 * Registers the `agentgarrison/qa` block (a no-build static block). The block
 * saves native <details>/<summary> markup, which the Schema module already
 * turns into FAQPage structured data — so there is a single JSON-LD emitter and
 * no risk of double-emitting FAQ schema.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_QA_Block {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( Rayetun_AG_Modules::is_enabled( 'qa_block' ) ) {
			// Priority 20: modules boot on init@10, so registering the block at the
			// default init@10 would be added to the currently-running bucket and
			// never fire. A later priority runs after boot_modules on the same init.
			add_action( 'init', array( $this, 'register_block' ), 20 );
		}
	}

	/**
	 * Register the block from its block.json directory. WordPress auto-loads the
	 * editor script (with deps from index.asset.php) and the front-end style.
	 */
	public function register_block() {
		$dir = RAYETUN_AG_DIR . 'includes/blocks/qa';
		if ( file_exists( $dir . '/block.json' ) ) {
			register_block_type( $dir );
		}
	}
}
