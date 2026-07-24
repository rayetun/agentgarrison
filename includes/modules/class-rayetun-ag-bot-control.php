<?php
/**
 * Module 1 — AI Bot Control.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Bot_Control {

	private static $instance = null;
	private $bots            = array();
	private $settings        = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_bots();
		$this->load_settings();
		$this->maybe_sync_bot_version();

		if ( Rayetun_AG_Modules::is_enabled( 'bot_control' ) ) {
			add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
			add_filter( 'wp_headers', array( $this, 'inject_x_robots_tag' ) );
		}

		// AJAX — always register regardless of module state.
		add_action( 'wp_ajax_rayetun_ag_save_bot_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_import_robots_txt', array( $this, 'handle_import_robots' ) );
	}

	// -------------------------------------------------------------------------
	// Bot list
	// -------------------------------------------------------------------------

	private function load_bots() {
		$json_file = RAYETUN_AG_DIR . 'includes/data/bots.json';
		if ( ! file_exists( $json_file ) ) {
			return;
		}
		$raw = file_get_contents( $json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $raw ) {
			return;
		}
		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $decoded['bots'] ) ) {
			return;
		}
		$this->bots = $decoded;
	}

	private function maybe_sync_bot_version() {
		if ( empty( $this->bots['schema_version'] ) ) {
			return;
		}
		$stored = get_option( 'rayetun_ag_bots_version', '' );
		if ( $stored === $this->bots['schema_version'] ) {
			return;
		}
		// Merge new bots into settings without overwriting user overrides.
		$settings = $this->settings;
		if ( isset( $this->bots['bots'] ) ) {
			foreach ( $this->bots['bots'] as $bot ) {
				$id = $bot['id'];
				if ( ! isset( $settings['bots'][ $id ] ) ) {
					$cat                      = $bot['category'] ?? 'general_scraper';
					$cat_default              = $this->bots['categories'][ $cat ]['default'] ?? 'block';
					$settings['bots'][ $id ]  = $cat_default;
				}
			}
		}
		update_option( 'rayetun_ag_bot_settings', $settings );
		update_option( 'rayetun_ag_bots_version', $this->bots['schema_version'] );
		$this->settings = $settings;
	}

	private function load_settings() {
		$defaults = array(
			'categories'      => array(
				'ai_trainer'     => 'block',
				'ai_assistant'   => 'allow',
				'ai_search'      => 'allow',
				'seo_crawler'    => 'allow',
				'general_scraper'=> 'block',
			),
			'bots'            => array(),
			'x_robots_tag'    => false,
			'import_done'     => false,
		);
		$this->settings = wp_parse_args( get_option( 'rayetun_ag_bot_settings', array() ), $defaults );
	}

	// -------------------------------------------------------------------------
	// Robots.txt filter
	// -------------------------------------------------------------------------

	public function filter_robots_txt( $output, $public ) {
		$additions = $this->build_robots_rules();
		if ( $additions ) {
			$output .= "\n" . $additions;
		}
		// Add llms.txt discovery line.
		$output .= "\n# llms.txt\nLlms-txt: " . home_url( '/llms.txt' ) . "\n";
		return $output;
	}

	private function build_robots_rules() {
		$lines = array();
		if ( empty( $this->bots['bots'] ) ) {
			return '';
		}
		foreach ( $this->bots['bots'] as $bot ) {
			$rule = $this->get_bot_rule( $bot['id'], $bot['category'] );
			if ( 'block' === $rule ) {
				$lines[] = 'User-agent: ' . $bot['user_agent'];
				$lines[] = 'Disallow: /';
				$lines[] = '';
			}
		}
		return implode( "\n", $lines );
	}

	private function get_bot_rule( $bot_id, $category ) {
		// Per-bot override takes precedence.
		if ( isset( $this->settings['bots'][ $bot_id ] ) ) {
			return $this->settings['bots'][ $bot_id ];
		}
		// Fall back to category rule.
		return $this->settings['categories'][ $category ] ?? 'allow';
	}

	// -------------------------------------------------------------------------
	// X-Robots-Tag header
	// -------------------------------------------------------------------------

	public function inject_x_robots_tag( $headers ) {
		if ( empty( $this->settings['x_robots_tag'] ) ) {
			return $headers;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( $this->is_blocked_bot( $ua ) ) {
			$headers['X-Robots-Tag'] = 'noai, noimageai';
		}
		return $headers;
	}

	private function is_blocked_bot( $user_agent ) {
		if ( empty( $this->bots['bots'] ) ) {
			return false;
		}
		foreach ( $this->bots['bots'] as $bot ) {
			if ( false !== stripos( $user_agent, $bot['user_agent'] ) ) {
				return 'block' === $this->get_bot_rule( $bot['id'], $bot['category'] );
			}
		}
		return false;
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function handle_save_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$raw_cats = isset( $_POST['categories'] ) ? wp_unslash( $_POST['categories'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_bots = isset( $_POST['bots'] ) ? wp_unslash( $_POST['bots'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$allowed_rules = array( 'allow', 'block' );
		$categories    = array();
		if ( is_array( $raw_cats ) ) {
			foreach ( $raw_cats as $cat_id => $rule ) {
				$cat_id              = sanitize_key( $cat_id );
				$categories[ $cat_id ] = in_array( $rule, $allowed_rules, true ) ? $rule : 'allow';
			}
		}

		$bots = array();
		if ( is_array( $raw_bots ) ) {
			foreach ( $raw_bots as $bot_id => $rule ) {
				$bot_id          = sanitize_key( $bot_id );
				$bots[ $bot_id ] = in_array( $rule, $allowed_rules, true ) ? $rule : 'allow';
			}
		}

		$settings               = get_option( 'rayetun_ag_bot_settings', array() );
		$settings['categories'] = $categories;
		$settings['bots']       = $bots;
		$settings['x_robots_tag'] = ! empty( $_POST['x_robots_tag'] );

		update_option( 'rayetun_ag_bot_settings', $settings );
		Rayetun_AG_Visibility_Score::invalidate();
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'agentgarrison' ) ) );
	}

	public function handle_import_robots() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$existing = get_option( 'rayetun_ag_bot_settings', array() );
		if ( ! empty( $existing['import_done'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Import already completed.', 'agentgarrison' ) ) );
		}

		$robots_url  = home_url( '/robots.txt' );
		$response    = wp_remote_get( $robots_url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not fetch robots.txt.', 'agentgarrison' ) ) );
		}

		$body    = wp_remote_retrieve_body( $response );
		$mapped  = $this->parse_robots_into_settings( $body );
		$merged  = wp_parse_args( $mapped, $existing );
		$merged['import_done'] = true;
		update_option( 'rayetun_ag_bot_settings', $merged );

		wp_send_json_success( array(
			'message' => __( 'Existing robots.txt rules imported successfully.', 'agentgarrison' ),
			'mapped'  => $mapped,
		) );
	}

	private function parse_robots_into_settings( $robots_txt ) {
		$bots    = array();
		$lines   = explode( "\n", $robots_txt );
		$current_ua = '';
		$current_disallow = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			if ( 0 === stripos( $line, 'user-agent:' ) ) {
				$current_ua       = trim( substr( $line, 11 ) );
				$current_disallow = false;
			} elseif ( 0 === stripos( $line, 'disallow:' ) ) {
				$path = trim( substr( $line, 9 ) );
				if ( '/' === $path ) {
					$current_disallow = true;
				}
			}
			if ( $current_ua && $current_disallow && empty( $this->bots['bots'] ) ) {
				continue;
			}
			if ( $current_ua && $current_disallow ) {
				foreach ( $this->bots['bots'] as $bot ) {
					if ( false !== stripos( $current_ua, $bot['user_agent'] ) ) {
						$bots[ $bot['id'] ] = 'block';
					}
				}
			}
		}
		return array( 'bots' => $bots );
	}

	// -------------------------------------------------------------------------
	// Public helpers used by views
	// -------------------------------------------------------------------------

	public function get_bots_data() {
		return $this->bots;
	}

	public function get_settings() {
		return $this->settings;
	}
}
