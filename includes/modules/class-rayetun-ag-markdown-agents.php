<?php
/**
 * Phase 9 — Markdown for Agents.
 *
 * Serves clean, token-efficient Markdown to AI agents on the canonical URL via
 * HTTP content negotiation (Accept: text/markdown) — a free, origin-level
 * equivalent of Cloudflare's paid "Markdown for Agents". Done the correct way:
 * content negotiation on the canonical URL (not just a .md suffix), a Vary
 * header so caches stay correct, and a discoverable alternate URL/link.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Markdown_Agents {

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

		if ( Rayetun_AG_Modules::is_enabled( 'markdown_agents' ) ) {
			// Serve early, before the template loads.
			add_action( 'template_redirect', array( $this, 'maybe_serve_markdown' ), 1 );
			// Vary + Link discovery headers on the HTML response.
			add_action( 'send_headers', array( $this, 'add_response_headers' ) );
			// Discovery <link> in <head>.
			add_action( 'wp_head', array( $this, 'print_discovery_link' ), 1 );
			// Bust the cached conversion when content changes.
			add_action( 'save_post', array( $this, 'clear_cache' ) );
		}

		add_action( 'wp_ajax_rayetun_ag_save_markdown_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_preview_markdown', array( $this, 'handle_preview' ) );
	}

	private function load_settings() {
		$this->settings = wp_parse_args(
			get_option( 'rayetun_ag_markdown_settings', array() ),
			array(
				'post_types'   => array( 'post', 'page' ),
				'frontmatter'  => true,
				'token_header' => true,
				'discovery'    => true,
			)
		);
	}

	public function get_settings() {
		return $this->settings;
	}

	// -------------------------------------------------------------------------
	// Request handling
	// -------------------------------------------------------------------------

	private function is_enabled_post_type( $post ) {
		$types = ! empty( $this->settings['post_types'] ) ? (array) $this->settings['post_types'] : array( 'post', 'page' );
		return in_array( $post->post_type, $types, true );
	}

	private function is_excluded( $post ) {
		// Reuse the llms.txt per-post exclude flag — one switch, no duplicate UI.
		return (bool) get_post_meta( $post->ID, '_rayetun_ag_exclude_llms', true );
	}

	private function wants_markdown() {
		// Explicit, bookmarkable, cache-friendly discovery URL (?rayetun_ag_md=1).
		if ( isset( $_GET['rayetun_ag_md'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only content negotiation, no state change
			return true;
		}
		// Standard content negotiation — agents (incl. Claude Code) send this.
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		return false !== stripos( $accept, 'text/markdown' );
	}

	private function discovery_url( $post ) {
		return add_query_arg( 'rayetun_ag_md', '1', get_permalink( $post ) );
	}

	public function maybe_serve_markdown() {
		if ( is_admin() || ! is_singular() || ! $this->wants_markdown() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! $this->is_enabled_post_type( $post ) || $this->is_excluded( $post ) ) {
			return;
		}

		$markdown = $this->build_markdown( $post );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Vary: Accept', false );
		header( 'X-Robots-Tag: noindex' );
		if ( ! empty( $this->settings['token_header'] ) ) {
			header( 'X-Markdown-Tokens: ' . (int) ceil( strlen( $markdown ) / 4 ) );
		}
		echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text markdown output
		exit;
	}

	public function add_response_headers() {
		if ( is_admin() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || ! $this->is_enabled_post_type( $post ) || $this->is_excluded( $post ) ) {
			return;
		}
		// Tell caches the response varies by Accept so agents/humans don't get crossed.
		header( 'Vary: Accept', false );
		if ( ! empty( $this->settings['discovery'] ) ) {
			header( 'Link: <' . esc_url_raw( $this->discovery_url( $post ) ) . '>; rel="alternate"; type="text/markdown"', false );
		}
	}

	public function print_discovery_link() {
		if ( empty( $this->settings['discovery'] ) || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || ! $this->is_enabled_post_type( $post ) || $this->is_excluded( $post ) ) {
			return;
		}
		printf(
			'<link rel="alternate" type="text/markdown" href="%s" />' . "\n",
			esc_url( $this->discovery_url( $post ) )
		);
	}

	// -------------------------------------------------------------------------
	// Markdown building
	// -------------------------------------------------------------------------

	public function build_markdown( $post ) {
		$body = $this->get_markdown_body( $post );

		if ( empty( $this->settings['frontmatter'] ) ) {
			return rtrim( $body ) . "\n";
		}
		return $this->build_frontmatter( $post ) . "\n\n" . rtrim( $body ) . "\n";
	}

	private function get_markdown_body( $post ) {
		$key    = 'rayetun_ag_md_' . $post->ID;
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		// Apply WordPress core's the_content filter so blocks and shortcodes render
		// exactly as they do on the front end. This is core's filter, not our own.
		$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$body = $this->html_to_markdown( $html );
		set_transient( $key, $body, DAY_IN_SECONDS );
		return $body;
	}

	public function clear_cache( $post_id ) {
		delete_transient( 'rayetun_ag_md_' . absint( $post_id ) );
	}

	private function build_frontmatter( $post ) {
		$lines   = array( '---' );
		$lines[] = 'title: ' . $this->yaml_value( wp_strip_all_tags( $post->post_title ) );
		$lines[] = 'url: ' . get_permalink( $post );
		$lines[] = 'date: ' . get_the_date( 'c', $post );
		$lines[] = 'modified: ' . get_the_modified_date( 'c', $post );

		$author = get_the_author_meta( 'display_name', $post->post_author );
		if ( $author ) {
			$lines[] = 'author: ' . $this->yaml_value( $author );
		}
		if ( has_post_thumbnail( $post ) ) {
			$lines[] = 'image: ' . get_the_post_thumbnail_url( $post, 'full' );
		}
		$cats = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		if ( $cats && ! is_wp_error( $cats ) ) {
			$lines[] = 'categories: [' . implode( ', ', array_map( array( $this, 'yaml_value' ), $cats ) ) . ']';
		}
		$tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$lines[] = 'tags: [' . implode( ', ', array_map( array( $this, 'yaml_value' ), $tags ) ) . ']';
		}
		$lines[] = '---';
		return implode( "\n", $lines );
	}

	private function yaml_value( $value ) {
		$value = (string) $value;
		if ( preg_match( '/[:#\[\]\{\}",\n]/', $value ) ) {
			return '"' . str_replace( '"', '\"', $value ) . '"';
		}
		return $value;
	}

	// -------------------------------------------------------------------------
	// Dependency-free HTML → Markdown converter
	// -------------------------------------------------------------------------

	private function html_to_markdown( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return trim( wp_strip_all_tags( $html ) ); // Graceful fallback.
		}

		$dom = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		// The XML PI forces UTF-8 parsing; the wrapper div gives us a single root.
		$dom->loadHTML(
			'<?xml encoding="utf-8" ?><div data-ag-md="1">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$divs = $dom->getElementsByTagName( 'div' );
		$root = $divs->length ? $divs->item( 0 ) : $dom->documentElement;
		if ( ! $root ) {
			return trim( wp_strip_all_tags( $html ) );
		}

		$md = $this->node_to_md( $root );
		$md = preg_replace( "/[ \t]+\n/", "\n", $md );   // Trim trailing spaces.
		$md = preg_replace( "/\n{3,}/", "\n\n", $md );    // Collapse blank lines.
		return trim( $md );
	}

	private function node_to_md( $node, $depth = 0 ) {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType ) {
				$out .= preg_replace( '/\s+/', ' ', $child->nodeValue );
				continue;
			}
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			$tag = strtolower( $child->nodeName );
			switch ( $tag ) {
				case 'h1':
				case 'h2':
				case 'h3':
				case 'h4':
				case 'h5':
				case 'h6':
					$level = (int) substr( $tag, 1 );
					$out  .= "\n\n" . str_repeat( '#', $level ) . ' ' . trim( $this->node_to_md( $child ) ) . "\n\n";
					break;
				case 'p':
					$out .= "\n\n" . trim( $this->node_to_md( $child ) ) . "\n\n";
					break;
				case 'br':
					$out .= "  \n";
					break;
				case 'strong':
				case 'b':
					$out .= '**' . trim( $this->node_to_md( $child ) ) . '**';
					break;
				case 'em':
				case 'i':
					$out .= '*' . trim( $this->node_to_md( $child ) ) . '*';
					break;
				case 'a':
					$href = $child->getAttribute( 'href' );
					$text = trim( $this->node_to_md( $child ) );
					$out .= $href ? '[' . $text . '](' . $href . ')' : $text;
					break;
				case 'img':
					$src = $child->getAttribute( 'src' );
					if ( $src ) {
						$out .= '![' . $child->getAttribute( 'alt' ) . '](' . $src . ')';
					}
					break;
				case 'ul':
					$out .= "\n" . $this->list_to_md( $child, false, $depth ) . "\n";
					break;
				case 'ol':
					$out .= "\n" . $this->list_to_md( $child, true, $depth ) . "\n";
					break;
				case 'blockquote':
					$inner = trim( $this->node_to_md( $child ) );
					$out  .= "\n\n" . preg_replace( '/^/m', '> ', $inner ) . "\n\n";
					break;
				case 'pre':
					$out .= "\n\n```\n" . rtrim( $child->textContent ) . "\n```\n\n";
					break;
				case 'code':
					$out .= '`' . $child->textContent . '`';
					break;
				case 'hr':
					$out .= "\n\n---\n\n";
					break;
				case 'script':
				case 'style':
				case 'noscript':
					break;
				default:
					$out .= $this->node_to_md( $child, $depth );
			}
		}
		return $out;
	}

	private function list_to_md( $node, $ordered, $depth ) {
		$out    = '';
		$index  = 1;
		$indent = str_repeat( '  ', max( 0, $depth ) );
		foreach ( $node->childNodes as $li ) {
			if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
				continue;
			}
			$marker  = $ordered ? ( $index . '. ' ) : '- ';
			$content = trim( $this->node_to_md( $li, $depth + 1 ) );
			$content = preg_replace( "/\n{2,}/", "\n", $content );
			$out    .= $indent . $marker . $content . "\n";
			$index++;
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function handle_save_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$raw_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array( 'post', 'page' );
		$types     = array_values( array_filter( $raw_types ) );

		$settings = array(
			'post_types'   => $types,
			'frontmatter'  => ! empty( $_POST['frontmatter'] ),
			'token_header' => ! empty( $_POST['token_header'] ),
			'discovery'    => ! empty( $_POST['discovery'] ),
		);
		update_option( 'rayetun_ag_markdown_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success( array( 'message' => __( 'Markdown settings saved.', 'agentgarrison' ) ) );
	}

	public function handle_preview() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			$recent = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
			if ( $recent ) {
				$post_id = $recent[0]->ID;
			}
		}
		$preview_post = $post_id ? get_post( $post_id ) : null;
		if ( ! $preview_post ) {
			wp_send_json_error( array( 'message' => __( 'No post available to preview.', 'agentgarrison' ) ) );
		}

		// Set up post context so block/shortcode rendering matches the front end,
		// then build fresh (bypass cache) so the preview reflects current settings.
		$GLOBALS['post'] = $preview_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- preview context only; request ends after.
		setup_postdata( $preview_post );
		delete_transient( 'rayetun_ag_md_' . $post_id );
		$markdown = $this->build_markdown( $preview_post );
		wp_reset_postdata();

		wp_send_json_success( array(
			'title'   => get_the_title( $preview_post ),
			'url'     => $this->discovery_url( $preview_post ),
			'tokens'  => (int) ceil( strlen( $markdown ) / 4 ),
			'preview' => esc_textarea( $markdown ),
		) );
	}
}
