<?php
/**
 * Module 2 — llms.txt Generator.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Llms_Txt {

	private static $instance = null;
	private $settings        = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_settings();

		if ( Rayetun_AG_Modules::is_enabled( 'llms_txt' ) ) {
			// Priority 20 so this fires reliably AFTER the modules boot on init:10.
			add_action( 'init', array( $this, 'add_rewrite_rules' ), 20 );
			add_action( 'init', array( $this, 'maybe_flush' ), 99 );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
			add_action( 'template_redirect', array( $this, 'serve_llms_file' ) );
			add_action( 'save_post', array( $this, 'schedule_regenerate' ) );
			add_action( 'rayetun_ag_llms_health_check', array( $this, 'run_health_check' ) );

			// Per-post exclude metabox.
			add_action( 'add_meta_boxes', array( $this, 'register_exclude_metabox' ) );
			add_action( 'save_post', array( $this, 'save_exclude_meta' ), 10, 1 );

			if ( ! wp_next_scheduled( 'rayetun_ag_llms_health_check' ) ) {
				wp_schedule_event( time(), 'twicedaily', 'rayetun_ag_llms_health_check' );
			}
		}

		add_action( 'wp_ajax_rayetun_ag_save_llms_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_regenerate_llms', array( $this, 'handle_regenerate' ) );
		add_action( 'wp_ajax_rayetun_ag_preview_llms', array( $this, 'handle_preview' ) );
		add_action( 'wp_ajax_rayetun_ag_validate_llms', array( $this, 'handle_validate' ) );
		add_action( 'wp_ajax_rayetun_ag_ai_llms_summary', array( $this, 'handle_ai_summary' ) );
	}

	private function load_settings() {
		$defaults = array(
			'site_context'     => '',
			'post_types'       => array( 'post', 'page' ),
			'exclude_cats'     => array(),
			'last_generated'   => 0,
			'custom_additions' => '', // Appended to the auto-generated file.
			'manual_mode'      => false, // Full manual override.
			'manual_content'   => '', // The full file content when manual_mode is on.
			'static_file'      => false, // Opt-in: write a physical llms.txt to the web root.
		);
		$this->settings = wp_parse_args( get_option( 'rayetun_ag_llms_settings', array() ), $defaults );
	}

	// -------------------------------------------------------------------------
	// Rewrite rules
	// -------------------------------------------------------------------------

	public function add_rewrite_rules() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?rayetun_ag_llms=full', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?rayetun_ag_llms=extended', 'top' );
		// Markdown export: /llms-docs/[slug].md
		add_rewrite_rule( '^llms-docs/([^/]+)\.md$', 'index.php?rayetun_ag_llms_doc=$matches[1]', 'top' );
	}

	// -------------------------------------------------------------------------
	// Physical static files (host-compatibility — e.g. nginx / WP Engine)
	// -------------------------------------------------------------------------
	//
	// Some managed hosts (WP Engine and other nginx setups) intercept requests
	// for *.txt and serve them as static files, returning 404 before WordPress
	// ever runs — so a purely virtual /llms.txt can 404. Writing a real file to
	// the web root makes the host serve it directly, which works everywhere.

	private function can_write_static() {
		// On multisite, ABSPATH is shared across all sites — a single physical
		// file would collide, so we rely on the virtual route there.
		if ( is_multisite() ) {
			return false;
		}
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		// Only attempt a direct write; never prompt the user for FTP credentials.
		return 'direct' === get_filesystem_method();
	}

	public function write_static_files() {
		// Opt-in only: the static file is written when the user enables it (for
		// hosts like WP Engine that 404 the virtual route). Default is off.
		if ( empty( $this->settings['static_file'] ) ) {
			return false;
		}
		if ( ! $this->can_write_static() || ! Rayetun_AG_Modules::is_enabled( 'llms_txt' ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			return false;
		}
		global $wp_filesystem;
		$root = trailingslashit( $wp_filesystem->abspath() );
		if ( ! $wp_filesystem->is_writable( $root ) ) {
			return false;
		}
		$wp_filesystem->put_contents( $root . 'llms.txt', $this->generate( false ), FS_CHMOD_FILE );
		$wp_filesystem->put_contents( $root . 'llms-full.txt', $this->generate( true ), FS_CHMOD_FILE );
		return true;
	}

	public function delete_static_files() {
		if ( ! $this->can_write_static() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			return;
		}
		global $wp_filesystem;
		$root = trailingslashit( $wp_filesystem->abspath() );
		foreach ( array( 'llms.txt', 'llms-full.txt' ) as $file ) {
			if ( $wp_filesystem->exists( $root . $file ) ) {
				$wp_filesystem->delete( $root . $file );
			}
		}
	}

	/**
	 * One-time rewrite flush so /llms.txt resolves without the user having to
	 * manually re-save permalinks. The flag is set on activation and on DB upgrade.
	 */
	public function maybe_flush() {
		if ( get_option( 'rayetun_ag_llms_flush' ) ) {
			$this->add_rewrite_rules();
			flush_rewrite_rules( false );
			delete_option( 'rayetun_ag_llms_flush' );
		}
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'rayetun_ag_llms';
		$vars[] = 'rayetun_ag_llms_doc';
		return $vars;
	}

	/**
	 * Resolve the current request to an llms route, independent of rewrite rules.
	 *
	 * Rewrite rules only match when they're baked into the saved rewrite_rules
	 * option, which any other plugin saving permalinks can silently drop. This
	 * path-based check guarantees /llms.txt resolves regardless of flush state.
	 *
	 * @return string The site-relative request path (e.g. "llms.txt").
	 */
	private function requested_llms_route() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = rawurldecode( $path );

		// Make the path relative to the site's home path (handles subdirectory installs).
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $home_path && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}
		return trim( $path, '/' );
	}

	public function serve_llms_file() {
		$type = get_query_var( 'rayetun_ag_llms' );
		$slug = get_query_var( 'rayetun_ag_llms_doc' );

		// Fallback: match the raw request path when the rewrite rule didn't fire.
		if ( ! $type && ! $slug ) {
			$route = $this->requested_llms_route();
			if ( 'llms.txt' === $route ) {
				$type = 'full';
			} elseif ( 'llms-full.txt' === $route ) {
				$type = 'extended';
			} elseif ( preg_match( '#^llms-docs/(.+)\.md$#', $route, $m ) ) {
				$slug = $m[1];
			}
		}

		// Serve /llms.txt or /llms-full.txt
		if ( $type ) {
			$content = $this->generate( 'extended' === $type );
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text file output
			exit;
		}

		// Serve /llms-docs/[slug].md
		if ( $slug ) {
			$slug = sanitize_title( $slug );
			$post = get_page_by_path( $slug, OBJECT, get_post_types( array( 'public' => true ) ) );
			if ( ! $post || 'publish' !== $post->post_status || get_post_meta( $post->ID, '_rayetun_ag_exclude_llms', true ) ) {
				status_header( 404 );
				exit;
			}
			$markdown = $this->post_to_markdown( $post );
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: text/markdown; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markdown plain text
			exit;
		}
	}

	// -------------------------------------------------------------------------
	// Markdown export
	// -------------------------------------------------------------------------

	private function post_to_markdown( $post ) {
		$title   = wp_strip_all_tags( $post->post_title );
		$url     = get_permalink( $post );
		$date    = get_the_date( 'Y-m-d', $post );
		$author  = get_the_author_meta( 'display_name', $post->post_author );
		$content = wp_strip_all_tags( $post->post_content );
		$excerpt = get_the_excerpt( $post );

		$md  = "# {$title}\n\n";
		$md .= "> **URL:** {$url}  \n";
		$md .= "> **Published:** {$date}  \n";
		$md .= "> **Author:** {$author}\n\n";
		if ( $excerpt ) {
			$md .= "## Summary\n\n{$excerpt}\n\n";
		}
		$md .= "## Content\n\n{$content}\n";

		return $md;
	}

	// -------------------------------------------------------------------------
	// Per-post exclude metabox
	// -------------------------------------------------------------------------

	public function register_exclude_metabox() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'rayetun_ag_llms_exclude',
				__( 'AgentGarrison llms.txt', 'agentgarrison' ),
				array( $this, 'render_exclude_metabox' ),
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render_exclude_metabox( $post ) {
		wp_nonce_field( 'rayetun_ag_llms_exclude', 'rayetun_ag_llms_nonce' );
		$excluded = get_post_meta( $post->ID, '_rayetun_ag_exclude_llms', true );
		$md_url   = home_url( '/llms-docs/' . $post->post_name . '.md' );
		?>
		<div class="agentgarrison-metabox" style="text-align:left;">
			<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
				<input type="checkbox" name="rayetun_ag_exclude_llms" value="1" <?php checked( (bool) $excluded ); ?>>
				<?php esc_html_e( 'Exclude from llms.txt', 'agentgarrison' ); ?>
			</label>
			<p style="font-size:11px;color:#5C7880;margin:8px 0 0;line-height:1.5;">
				<?php esc_html_e( 'When checked, this post is listed in the Excluded section and hidden from AI crawlers.', 'agentgarrison' ); ?>
			</p>
			<?php if ( 'publish' === $post->post_status && $post->post_name ) : ?>
			<p style="font-size:11px;margin:10px 0 0;">
				<a href="<?php echo esc_url( $md_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'View Markdown export ↗', 'agentgarrison' ); ?>
				</a>
			</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save_exclude_meta( $post_id ) {
		if ( ! isset( $_POST['rayetun_ag_llms_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['rayetun_ag_llms_nonce'] ), 'rayetun_ag_llms_exclude' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$excluded = ! empty( $_POST['rayetun_ag_exclude_llms'] );
		update_post_meta( $post_id, '_rayetun_ag_exclude_llms', $excluded ? '1' : '' );
	}

	// -------------------------------------------------------------------------
	// Generator
	// -------------------------------------------------------------------------

	/**
	 * Build a spec-compliant llms.txt list item: a markdown hyperlink optionally
	 * followed by ": description" — e.g. "- [Title](https://url): notes".
	 *
	 * @param string $title       Link text.
	 * @param string $url         Absolute URL.
	 * @param string $description Optional one-line description.
	 * @return string
	 */
	private function format_link_line( $title, $url, $description = '' ) {
		// Square brackets break markdown link text; strip them from the title.
		$title = trim( str_replace( array( '[', ']' ), '', wp_strip_all_tags( (string) $title ) ) );
		if ( '' === $title ) {
			$title = $url;
		}
		$line = '- [' . $title . '](' . esc_url_raw( $url ) . ')';

		if ( '' !== trim( (string) $description ) ) {
			// Collapse whitespace/newlines so the description stays on one line.
			$description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
			if ( '' !== $description ) {
				$line .= ': ' . $description;
			}
		}
		return $line;
	}

	public function generate( $extended = false ) {
		$settings = $this->settings;

		// Manual override: serve the user's hand-written file verbatim.
		if ( ! empty( $settings['manual_mode'] ) && '' !== trim( (string) $settings['manual_content'] ) ) {
			update_option( 'rayetun_ag_llms_settings', array_merge( $this->settings, array( 'last_generated' => time() ) ) );
			delete_transient( 'rayetun_ag_llms_drift' );
			return rtrim( $settings['manual_content'] ) . "\n";
		}

		$site_name    = get_bloginfo( 'name' );
		$site_context = $settings['site_context'] ? $settings['site_context'] : get_bloginfo( 'description' );
		$post_types   = ! empty( $settings['post_types'] ) ? (array) $settings['post_types'] : array( 'post', 'page' );

		$lines = array();
		$lines[] = '# ' . $site_name;
		if ( $site_context ) {
			$lines[] = '> ' . $site_context;
		}
		$lines[] = '';

		$excluded_lines = array();

		foreach ( $post_types as $post_type ) {
			$posts = get_posts( array(
				'post_type'      => sanitize_key( $post_type ),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );

			if ( empty( $posts ) ) {
				continue;
			}

			$pt_obj  = get_post_type_object( $post_type );
			$pt_label = $pt_obj ? $pt_obj->labels->name : ucfirst( $post_type );
			$lines[] = '## ' . $pt_label;

			foreach ( $posts as $post ) {
				// Check exclude flag.
				if ( get_post_meta( $post->ID, '_rayetun_ag_exclude_llms', true ) ) {
					$excluded_lines[] = $this->format_link_line( $post->post_title, get_permalink( $post->ID ), '' );
					continue;
				}

				// Respect Yoast / Rank Math noindex.
				if ( $this->is_noindex( $post->ID ) ) {
					continue;
				}

				$excerpt = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 25 );
				$lines[] = $this->format_link_line( $post->post_title, get_permalink( $post->ID ), $excerpt );
			}
			$lines[] = '';
		}

		if ( ! empty( $excluded_lines ) ) {
			// Use H2 (a section), not a second H1 — the spec allows only one H1.
			$lines[] = '## Excluded';
			foreach ( $excluded_lines as $el ) {
				$lines[] = $el;
			}
		}

		$output = implode( "\n", $lines );

		// Append the user's custom additions (extra links/sections) after the
		// auto-generated content, preserving auto-sync.
		if ( ! empty( $settings['custom_additions'] ) && '' !== trim( (string) $settings['custom_additions'] ) ) {
			$output = rtrim( $output ) . "\n\n" . rtrim( $settings['custom_additions'] ) . "\n";
		}

		update_option( 'rayetun_ag_llms_settings', array_merge( $this->settings, array( 'last_generated' => time() ) ) );
		delete_transient( 'rayetun_ag_llms_drift' );

		return $output;
	}

	private function is_noindex( $post_id ) {
		// Yoast SEO.
		if ( function_exists( 'wpseo_get_value' ) ) {
			$meta = wpseo_get_value( 'meta-robots-noindex', $post_id );
			if ( '1' === (string) $meta ) {
				return true;
			}
		}
		// Rank Math.
		if ( class_exists( 'RankMath' ) ) {
			$robots = get_post_meta( $post_id, 'rank_math_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				return true;
			}
		}
		return false;
	}

	public function schedule_regenerate( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		delete_transient( 'rayetun_ag_llms_cache' );
		delete_transient( 'rayetun_ag_llms_drift' );
	}

	// -------------------------------------------------------------------------
	// Drift detection
	// -------------------------------------------------------------------------

	/**
	 * How many included, published pages have changed since llms.txt was last generated.
	 *
	 * Uses post_modified_gmt vs the last-generated time so it's a single indexed COUNT
	 * query (no per-post hashing). Cached in a transient that is cleared whenever a post
	 * is saved (schedule_regenerate) or llms.txt is regenerated.
	 *
	 * @return int Number of changed/added pages. 0 if never generated.
	 */
	public function get_drift_count() {
		$last = (int) ( $this->settings['last_generated'] ?? 0 );
		if ( ! $last ) {
			return 0;
		}

		$cached = get_transient( 'rayetun_ag_llms_drift' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$post_types = ! empty( $this->settings['post_types'] ) ? (array) $this->settings['post_types'] : array( 'post', 'page' );

		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'after'  => gmdate( 'Y-m-d H:i:s', $last ),
				),
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => '_rayetun_ag_exclude_llms', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_rayetun_ag_exclude_llms', 'value' => '1', 'compare' => '!=' ),
			),
		) );

		$count = (int) $query->found_posts;
		set_transient( 'rayetun_ag_llms_drift', $count, DAY_IN_SECONDS );
		return $count;
	}

	// -------------------------------------------------------------------------
	// Health check, change history & drift alerts
	// -------------------------------------------------------------------------

	public function run_health_check() {
		// Refresh the physical files so they stay in sync with new content.
		$this->write_static_files();

		$url      = home_url( '/llms.txt' );
		$response = wp_remote_get( $url, array( 'timeout' => 10, 'sslverify' => false ) );
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );

		$health = array(
			'ok'             => 200 === $status,
			'status_code'    => $status,
			'checked_at'     => time(),
			'last_generated' => $this->settings['last_generated'],
			'stale'          => ( time() - $this->settings['last_generated'] ) > ( 7 * DAY_IN_SECONDS ),
		);

		// Record a snapshot of what is actually being served, then evaluate whether
		// the change (or a breakage) is worth alerting on.
		list( $snapshot, $prev ) = $this->record_history_snapshot( $body, $status );
		$health['links'] = $snapshot['links'];
		$health['alert'] = $this->maybe_send_alert( $snapshot, $prev, $health );

		update_option( 'rayetun_ag_llms_health', $health );
	}

	/**
	 * Count the AI-relevant metrics of a served llms.txt body.
	 *
	 * @param string $content File body.
	 * @return array{bytes:int,tokens:int,links:int,sections:int}
	 */
	private function count_llms_metrics( $content ) {
		$bytes       = strlen( (string) $content );
		$links       = 0;
		$sections    = 0;
		$in_excluded = false;

		foreach ( explode( "\n", (string) $content ) as $line ) {
			$t = trim( $line );
			if ( '' === $t ) {
				continue;
			}
			if ( 0 === strpos( $t, '## Excluded' ) ) {
				$in_excluded = true;
				continue;
			}
			if ( 0 === strpos( $t, '## ' ) ) {
				$sections++;
				continue;
			}
			if ( 0 === strpos( $t, '- ' ) && ! $in_excluded ) {
				$links++;
			}
		}

		// ~4 characters per token — the standard cross-model English heuristic.
		return array(
			'bytes'    => $bytes,
			'tokens'   => (int) ceil( $bytes / 4 ),
			'links'    => $links,
			'sections' => $sections,
		);
	}

	/**
	 * Append a change-history snapshot, but only when something meaningful changed
	 * versus the newest entry (content hash differs, or reachability flipped), so
	 * the log stays a record of real changes rather than a heartbeat.
	 *
	 * @param string $content Served body.
	 * @param int    $status  HTTP status.
	 * @return array{0:array,1:?array} [ new snapshot, previous snapshot|null ].
	 */
	private function record_history_snapshot( $content, $status ) {
		$metrics = $this->count_llms_metrics( $content );

		$history = get_option( 'rayetun_ag_llms_history', array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$prev = ! empty( $history ) ? $history[0] : null;

		$snapshot = array(
			'time'     => time(),
			'status'   => (int) $status,
			'ok'       => 200 === (int) $status,
			'bytes'    => $metrics['bytes'],
			'tokens'   => $metrics['tokens'],
			'links'    => $metrics['links'],
			'sections' => $metrics['sections'],
			'hash'     => md5( (string) $content ),
		);

		if ( null === $prev || $prev['hash'] !== $snapshot['hash'] || $prev['ok'] !== $snapshot['ok'] ) {
			array_unshift( $history, $snapshot );
			$history = array_slice( $history, 0, 30 ); // Keep the last 30 changes.
			update_option( 'rayetun_ag_llms_history', $history );
		}

		return array( $snapshot, $prev );
	}

	/**
	 * Email an alert on a genuine problem — the file broke, or lost a large share
	 * of its pages (a sign content was unpublished or a post type changed) — and
	 * dedupe so the same standing issue is only sent once. Routine growth is never
	 * alerted. Returns the current issue message (for the dashboard), or ''.
	 *
	 * @param array  $snapshot Latest snapshot.
	 * @param ?array $prev     Previous snapshot.
	 * @param array  $health   Health result.
	 * @return string
	 */
	private function maybe_send_alert( $snapshot, $prev, $health ) {
		$issue = '';
		$sig   = '';

		if ( empty( $health['ok'] ) ) {
			/* translators: %d: HTTP status code */
			$issue = sprintf( __( 'Your llms.txt is not reachable (HTTP %d).', 'agentgarrison' ), (int) $health['status_code'] );
			$sig   = 'broken:' . (int) $health['status_code'];
		} elseif ( $prev && $prev['links'] >= 5 && $snapshot['links'] < (int) ceil( $prev['links'] * 0.7 ) ) {
			/* translators: 1: previous page count, 2: current page count */
			$issue = sprintf( __( 'Your llms.txt dropped from %1$d to %2$d listed pages — a large, unexpected change. Check whether content was unpublished or a post type stopped being included.', 'agentgarrison' ), (int) $prev['links'], (int) $snapshot['links'] );
			$sig   = 'shrink:' . (int) $snapshot['links'];
		} elseif ( ! empty( $health['stale'] ) ) {
			$issue = __( 'Your llms.txt has not regenerated in over 7 days.', 'agentgarrison' );
			$sig   = 'stale';
		}

		$last = get_option( 'rayetun_ag_llms_alert', array() );

		// Healthy again — clear the standing alert so the next issue re-notifies.
		if ( '' === $sig ) {
			if ( ! empty( $last ) ) {
				delete_option( 'rayetun_ag_llms_alert' );
			}
			return '';
		}

		// Same issue as last time — keep showing it, but don't re-email.
		if ( isset( $last['sig'] ) && $last['sig'] === $sig ) {
			return $issue;
		}

		update_option( 'rayetun_ag_llms_alert', array( 'sig' => $sig, 'issue' => $issue, 'time' => time() ) );
		$this->send_alert_email( $issue );
		return $issue;
	}

	private function send_alert_email( $issue ) {
		$general = get_option( 'rayetun_ag_general_settings', array() );
		$to      = ! empty( $general['alert_email'] ) ? $general['alert_email'] : get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return;
		}
		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] llms.txt alert', 'agentgarrison' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$link    = admin_url( 'admin.php?page=agentgarrison&tab=llms-txt' );
		/* translators: %s: admin URL */
		$body    = $issue . "\n\n" . sprintf( __( 'Review it here: %s', 'agentgarrison' ), $link );
		wp_mail( $to, $subject, $body );
	}

	/**
	 * Recent change-history snapshots, newest first, for the admin view.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public function get_history( $limit = 15 ) {
		$history = get_option( 'rayetun_ag_llms_history', array() );
		if ( ! is_array( $history ) ) {
			return array();
		}
		return array_slice( $history, 0, max( 1, (int) $limit ) );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function handle_save_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$context   = sanitize_textarea_field( wp_unslash( $_POST['site_context'] ?? '' ) );
		// Sanitize each array item individually — sanitize_key covers alphanumeric + underscores.
		$raw_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array( 'post', 'page' );
		$types     = array_filter( $raw_types ); // Drop any empty values produced by sanitize_key.

		$settings = wp_parse_args(
			array(
				'site_context'     => $context,
				'post_types'       => $types,
				// Custom additions / manual override (markdown plain text).
				'custom_additions' => sanitize_textarea_field( wp_unslash( $_POST['custom_additions'] ?? '' ) ),
				'manual_mode'      => ! empty( $_POST['manual_mode'] ),
				'manual_content'   => sanitize_textarea_field( wp_unslash( $_POST['manual_content'] ?? '' ) ),
				'static_file'      => ! empty( $_POST['static_file'] ),
			),
			get_option( 'rayetun_ag_llms_settings', array() )
		);
		update_option( 'rayetun_ag_llms_settings', $settings );
		$this->settings = $settings;

		// Custom content changes the served file — clear the cache.
		delete_transient( 'rayetun_ag_llms_cache' );

		// Sync the physical file with the opt-in setting: write it when enabled,
		// remove it when the user turns the option off.
		if ( ! empty( $settings['static_file'] ) ) {
			$this->write_static_files();
		} else {
			$this->delete_static_files();
		}
		Rayetun_AG_Visibility_Score::invalidate();

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'agentgarrison' ) ) );
	}

	public function handle_regenerate() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		delete_transient( 'rayetun_ag_llms_cache' );
		$content = $this->generate();
		$this->write_static_files();
		// Log the manual regeneration in the change history (only appends if changed).
		$this->record_history_snapshot( $content, 200 );
		Rayetun_AG_Visibility_Score::invalidate();
		wp_send_json_success( array(
			'message' => __( 'llms.txt regenerated.', 'agentgarrison' ),
			'preview' => esc_textarea( $content ),
		) );
	}

	public function handle_preview() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		wp_send_json_success( array( 'preview' => esc_textarea( $this->generate() ) ) );
	}

	/**
	 * Draft the llms.txt site-summary sentence with the site's own AI provider
	 * (core AI Client on WP 7.0+, or a bring-your-own key), from the site name,
	 * tagline, and recent page titles. Zero cost to us — the request runs on the
	 * owner's configured provider.
	 */
	public function handle_ai_summary() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		if ( ! class_exists( 'Rayetun_AG_AI_Provider' ) || ! Rayetun_AG_AI_Provider::is_available() ) {
			wp_send_json_error( array( 'message' => __( 'No AI provider is configured. On WordPress 7.0+ connect one under Settings → Connectors, or add an API key on the Citation Monitor tab.', 'agentgarrison' ) ) );
		}

		$titles = array();
		$posts  = get_posts( array(
			'post_type'        => ! empty( $this->settings['post_types'] ) ? (array) $this->settings['post_types'] : array( 'post', 'page' ),
			'post_status'      => 'publish',
			'numberposts'      => 10,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		) );
		foreach ( $posts as $summary_post ) {
			$titles[] = get_the_title( $summary_post );
		}

		$prompt = sprintf(
			"Write a single concise sentence (max 25 words) describing what this website is about, for an llms.txt file that helps AI assistants understand the site. No quotes, no lists, just the sentence.\nSite name: %s\nTagline: %s\nRecent page titles: %s",
			get_bloginfo( 'name' ),
			get_bloginfo( 'description' ),
			implode( '; ', array_slice( $titles, 0, 10 ) )
		);

		$result = Rayetun_AG_AI_Provider::complete( $prompt, null, array( 'max_tokens' => 120 ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Collapse to one clean line and strip any wrapping quotes the model added.
		$summary = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $result['content'] ) ) );
		$summary = trim( $summary, " \t\n\"'" );

		wp_send_json_success( array( 'summary' => $summary ) );
	}

	// -------------------------------------------------------------------------
	// Validator & AI insights
	// -------------------------------------------------------------------------

	/**
	 * Validate the generated llms.txt against the spec and gather AI-relevant
	 * insights (size, token estimate, included / excluded breakdown).
	 *
	 * @return array
	 */
	public function validate() {
		$content = $this->generate();
		$bytes   = strlen( $content );

		// Token estimate: ~4 characters per token is the widely used heuristic
		// for English text across GPT / Claude / Gemini tokenizers.
		$tokens = (int) ceil( $bytes / 4 );

		$lines = explode( "\n", $content );

		$has_h1        = false;
		$has_summary   = false;
		$section_count = 0;
		$link_count    = 0;
		$excluded_cnt  = 0;
		$malformed     = 0;
		$in_excluded   = false;

		foreach ( $lines as $line ) {
			$t = trim( $line );
			if ( '' === $t ) {
				continue;
			}
			if ( 0 === strpos( $t, '## Excluded' ) ) {
				$in_excluded = true;
				continue;
			}
			if ( 0 === strpos( $t, '## ' ) ) {
				$section_count++;
				continue;
			}
			if ( '#' === $t[0] ) {
				$has_h1 = true;
				continue;
			}
			if ( 0 === strpos( $t, '> ' ) ) {
				$has_summary = true;
				continue;
			}
			if ( 0 === strpos( $t, '- ' ) ) {
				if ( $in_excluded ) {
					$excluded_cnt++;
					continue;
				}
				$link_count++;
				// Spec requires each list item to be a markdown hyperlink: [name](url).
				if ( ! preg_match( '#\[[^\]]+\]\(\s*https?://[^)]+\)#', $t ) ) {
					$malformed++;
				}
			}
		}

		// Spec conformance checks.
		$checks = array();
		$checks[] = array(
			'label'  => __( 'Has a site title (H1)', 'agentgarrison' ),
			'pass'   => $has_h1,
			'detail' => $has_h1 ? __( 'Found a top-level # heading.', 'agentgarrison' ) : __( 'Missing the # site title line.', 'agentgarrison' ),
		);
		$checks[] = array(
			'label'  => __( 'Has a summary description', 'agentgarrison' ),
			'pass'   => $has_summary,
			'detail' => $has_summary ? __( 'Found the > summary blockquote.', 'agentgarrison' ) : __( 'Add a Site Context above so AI models get a one-line summary.', 'agentgarrison' ),
		);
		$checks[] = array(
			'label'  => __( 'Has at least one section', 'agentgarrison' ),
			'pass'   => $section_count > 0,
			/* translators: %d: number of sections */
			'detail' => sprintf( _n( '%d section found.', '%d sections found.', $section_count, 'agentgarrison' ), $section_count ),
		);
		$checks[] = array(
			'label'  => __( 'Includes at least one page', 'agentgarrison' ),
			'pass'   => $link_count > 0,
			/* translators: %d: number of links */
			'detail' => sprintf( _n( '%d page link included.', '%d page links included.', $link_count, 'agentgarrison' ), $link_count ),
		);
		$checks[] = array(
			'label'  => __( 'Links use markdown format', 'agentgarrison' ),
			'pass'   => 0 === $malformed,
			'detail' => 0 === $malformed
				? __( 'Every link is a proper markdown hyperlink — [name](url).', 'agentgarrison' )
				/* translators: %d: number of malformed lines */
				: sprintf( _n( '%d link is not a markdown hyperlink. Use the [name](url) format.', '%d links are not markdown hyperlinks. Use the [name](url) format.', $malformed, 'agentgarrison' ), $malformed ),
		);

		// Warnings (non-fatal advisories).
		$warnings = array();
		if ( $tokens > 50000 ) {
			$warnings[] = __( 'This file is large (over ~50k tokens). It may exceed the context window of smaller AI models. Consider excluding low-value pages.', 'agentgarrison' );
		}
		if ( $link_count < 3 ) {
			$warnings[] = __( 'Very few pages are included. Add more post types or publish more content so AI models have something to work with.', 'agentgarrison' );
		}
		if ( ! $has_summary ) {
			$warnings[] = __( 'No summary line. AI models rely on it to understand what your site is about.', 'agentgarrison' );
		}

		$all_pass = true;
		foreach ( $checks as $c ) {
			if ( ! $c['pass'] ) {
				$all_pass = false;
				break;
			}
		}

		return array(
			'valid'    => $all_pass,
			'checks'   => $checks,
			'warnings' => $warnings,
			'stats'    => array(
				'bytes'    => $bytes,
				'size'     => size_format( $bytes ),
				'tokens'   => $tokens,
				'sections' => $section_count,
				'included' => $link_count,
				'excluded' => $excluded_cnt,
			),
			'content'  => $content,
		);
	}

	public function handle_validate() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$result = $this->validate();
		// Escape the raw content for safe transport to the textarea/clipboard.
		$result['content'] = esc_textarea( $result['content'] );
		wp_send_json_success( $result );
	}

	public function get_settings() {
		return $this->settings;
	}
}
