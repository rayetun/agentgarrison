<?php
/**
 * Public-facing class — loaded unconditionally.
 * Handles robots.txt and bot intercept delegation.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Public {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Nothing here — modules register their own hooks directly.
		// This class exists as an architectural placeholder and for any
		// future public-facing assets (CSS/JS for front-end features).
	}
}
