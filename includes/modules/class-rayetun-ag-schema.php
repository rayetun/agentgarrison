<?php
/**
 * Module 6 — Schema & Structured Data.
 *
 * Outputs a single connected JSON-LD @graph following the convention used by
 * Yoast / Rank Math and preferred by LLMs and Google: nodes are linked by @id
 * (Organization ⇄ WebPage ⇄ Article/Product ⇄ Person ⇄ BreadcrumbList), dates are
 * ISO-8601, URLs are absolute, images are ImageObject, and inLanguage is set.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Schema {

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

		if ( Rayetun_AG_Modules::is_enabled( 'schema' ) ) {
			add_action( 'wp_head', array( $this, 'output_schema' ), 20 );
			add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
			add_action( 'save_post', array( $this, 'save_overrides' ), 10, 1 );

			// Author credential fields on the user profile screen.
			add_action( 'show_user_profile', array( $this, 'render_user_fields' ) );
			add_action( 'edit_user_profile', array( $this, 'render_user_fields' ) );
			add_action( 'personal_options_update', array( $this, 'save_user_fields' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_fields' ) );
		}

		add_action( 'wp_ajax_rayetun_ag_save_schema_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_rayetun_ag_preview_post_schema', array( $this, 'handle_preview_post_schema' ) );
		add_action( 'wp_ajax_rayetun_ag_validate_schema', array( $this, 'handle_validate_schema' ) );
	}

	private function load_settings() {
		$defaults = array(
			'article'         => true,
			'product'         => true,
			'faq'             => true,
			'howto'           => true,
			'breadcrumb'      => true,
			'author'          => true,
			'organization'    => '',
			'org_description' => '',
			'org_logo'        => '',
			// Trust & Editorial signals (S2).
			'publishing_principles' => '',
			'ethics_policy'         => '',
			'corrections_policy'    => '',
			'ownership_funding'     => '',
			'founding_date'         => '',
			'same_as'               => '', // Newline-separated profile URLs.
			'default_reviewer'      => '', // Site-wide fallback reviewer name.
			// Detection & more types (S3).
			'website'               => true,  // WebSite node + SearchAction.
			'speakable'             => false, // SpeakableSpecification on WebPage.
			'local_business'        => false, // LocalBusiness node.
			'lb_type'               => 'LocalBusiness',
			'lb_phone'              => '',
			'lb_price'              => '',
			'lb_street'             => '',
			'lb_city'               => '',
			'lb_region'             => '',
			'lb_postal'             => '',
			'lb_country'            => '',
			'lb_lat'                => '',
			'lb_lng'                => '',
			'lb_hours'              => '', // Newline-separated, e.g. "Mo-Fr 09:00-17:00".
		);
		$this->settings = wp_parse_args( get_option( 'rayetun_ag_schema_settings', array() ), $defaults );
	}

	/**
	 * Parse the newline-separated sameAs URLs into a clean array of valid URLs.
	 */
	private function parse_same_as() {
		if ( empty( $this->settings['same_as'] ) ) {
			return array();
		}
		$urls = preg_split( '/[\r\n]+/', $this->settings['same_as'] );
		$out  = array();
		foreach ( $urls as $u ) {
			$u = esc_url_raw( trim( $u ) );
			if ( $u ) {
				$out[] = $u;
			}
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// @id helpers (stable, Yoast-style references)
	// -------------------------------------------------------------------------

	private function org_id() {
		return home_url( '/#organization' );
	}
	private function website_id() {
		return home_url( '/#website' );
	}
	private function localbusiness_id() {
		return home_url( '/#localbusiness' );
	}
	private function person_id( $author_id ) {
		return home_url( '/#/schema/person/' . (int) $author_id );
	}
	private function webpage_id( $url ) {
		return $url . '#webpage';
	}
	private function article_id( $url ) {
		return $url . '#article';
	}
	private function product_id( $url ) {
		return $url . '#product';
	}
	private function breadcrumb_id( $url ) {
		return $url . '#breadcrumb';
	}

	// -------------------------------------------------------------------------
	// Conflict detection
	// -------------------------------------------------------------------------

	private function has_seo_plugin_schema() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	public function has_conflict() {
		return $this->has_seo_plugin_schema();
	}

	// -------------------------------------------------------------------------
	// Output
	// -------------------------------------------------------------------------

	/**
	 * Assemble the complete @graph: site-wide nodes plus the post-level nodes for
	 * a given post. Shared by the front-end output, the admin preview, and the
	 * validator so all three stay in sync.
	 */
	private function build_full_graph( $post = null ) {
		$graph = array();

		// Organization is site-wide.
		$org = $this->build_organization();
		if ( $org ) {
			$graph[] = $org;
		}

		// WebSite + SearchAction (site-wide).
		if ( ! empty( $this->settings['website'] ) ) {
			$graph[] = $this->build_website();
		}

		// LocalBusiness (site-wide, optional).
		if ( ! empty( $this->settings['local_business'] ) ) {
			$lb = $this->build_local_business();
			if ( $lb ) {
				$graph[] = $lb;
			}
		}

		// Post-level nodes for a supported post.
		if ( $post instanceof WP_Post && $this->is_supported_post( $post ) ) {
			$graph = array_merge( $graph, $this->build_post_nodes( $post ) );
		}

		return $graph;
	}

	public function output_schema() {
		$post  = ( is_singular() && get_queried_object() instanceof WP_Post ) ? get_queried_object() : null;
		$graph = $this->build_full_graph( $post );

		if ( empty( $graph ) ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		// JSON_HEX_TAG hex-encodes angle brackets (u003C / u003E) so the JSON can
		// never contain a script-breaking sequence such as a closing script tag.
		// This matches how core hardens JSON printed inside script elements.
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is HTML-context hardened via JSON_HEX_TAG.
	}

	private function is_supported_post( $post ) {
		$type = get_post_type_object( $post->post_type );
		return $type && $type->public && 'attachment' !== $post->post_type;
	}

	/**
	 * Build the array of post-level @graph nodes for a single post, respecting
	 * per-post overrides. Shared by the front-end output and the admin preview.
	 */
	private function build_post_nodes( $post ) {
		$nodes     = array();
		$overrides = $this->get_overrides( $post->ID );

		// Full per-post exclusion skips post-level schema (Organization stays site-wide).
		if ( ! empty( $overrides['skip'] ) ) {
			return $nodes;
		}

		$url      = get_permalink( $post );
		$is_prod  = $this->is_product( $post );
		$lang     = get_bloginfo( 'language' );

		// --- WebPage (container node) ---
		// isPartOf points at the WebSite when that node is enabled, else the Organization.
		$is_part_of = ! empty( $this->settings['website'] ) ? $this->website_id() : $this->org_id();
		$webpage = array(
			'@type'      => 'WebPage',
			'@id'        => $this->webpage_id( $url ),
			'url'        => $url,
			'name'       => wp_strip_all_tags( $post->post_title ),
			'isPartOf'   => array( '@id' => $is_part_of ),
			'inLanguage' => $lang,
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
		);

		// --- Speakable (voice / AI answer extraction) ---
		if ( ! empty( $this->settings['speakable'] ) ) {
			$webpage['speakable'] = array(
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => array( 'h1', '.entry-content > p:first-of-type' ),
			);
		}

		// --- Content review signals (per-post, or site-wide default reviewer) ---
		$reviewer = ! empty( $overrides['reviewer'] ) ? $overrides['reviewer'] : $this->settings['default_reviewer'];
		if ( $reviewer ) {
			$webpage['reviewedBy'] = array(
				'@type' => 'Person',
				'name'  => $reviewer,
			);
		}
		if ( ! empty( $overrides['reviewed'] ) ) {
			$webpage['lastReviewed'] = $overrides['reviewed'];
		}

		// --- Breadcrumb ---
		if ( $this->type_on( 'breadcrumb', $overrides ) ) {
			$crumb = $this->build_breadcrumb( $post, $url );
			if ( $crumb ) {
				$nodes[]                = $crumb;
				$webpage['breadcrumb']  = array( '@id' => $this->breadcrumb_id( $url ) );
			}
		}

		$nodes[] = $webpage;

		// --- Product (WooCommerce) takes precedence over Article for products ---
		if ( $is_prod && $this->type_on( 'product', $overrides ) ) {
			$product = $this->build_product( $post, $url );
			if ( $product ) {
				$nodes[] = $product;
			}
		} elseif ( ! $is_prod && $this->type_on( 'article', $overrides ) && ! $this->has_seo_plugin_schema() ) {
			$nodes[] = $this->build_article( $post, $url, $lang );

			// Author Person node, linked from the Article.
			if ( $this->type_on( 'author', $overrides ) ) {
				$person = $this->build_person( $post );
				if ( $person ) {
					$nodes[] = $person;
				}
			}
		}

		// --- FAQ ---
		if ( $this->type_on( 'faq', $overrides ) ) {
			$faq = $this->build_faq( $post->post_content );
			if ( $faq ) {
				$nodes[] = $faq;
			}
		}

		// --- HowTo ---
		if ( $this->type_on( 'howto', $overrides ) ) {
			$howto = $this->build_howto( $post );
			if ( $howto ) {
				$nodes[] = $howto;
			}
		}

		return $nodes;
	}

	private function type_on( $type, $overrides ) {
		if ( empty( $this->settings[ $type ] ) ) {
			return false;
		}
		if ( ! empty( $overrides['disabled'] ) && in_array( $type, (array) $overrides['disabled'], true ) ) {
			return false;
		}
		return true;
	}

	private function is_product( $post ) {
		return function_exists( 'wc_get_product' ) && 'product' === $post->post_type;
	}

	// -------------------------------------------------------------------------
	// Node builders
	// -------------------------------------------------------------------------

	private function build_website() {
		$site = array(
			'@type'      => 'WebSite',
			'@id'        => $this->website_id(),
			'url'        => home_url( '/' ),
			'name'       => get_bloginfo( 'name' ),
			'publisher'  => array( '@id' => $this->org_id() ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		$desc = get_bloginfo( 'description' );
		if ( $desc ) {
			$site['description'] = $desc;
		}
		// SearchAction enables Google's sitelinks search box.
		$site['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		);
		return $site;
	}

	private function build_local_business() {
		$name = $this->settings['organization'] ? $this->settings['organization'] : get_bloginfo( 'name' );
		if ( empty( $name ) ) {
			return null;
		}

		$allowed_types = array( 'LocalBusiness', 'Store', 'Restaurant', 'ProfessionalService', 'MedicalBusiness', 'LegalService', 'FinancialService' );
		$type          = in_array( $this->settings['lb_type'], $allowed_types, true ) ? $this->settings['lb_type'] : 'LocalBusiness';

		$lb = array(
			'@type' => $type,
			'@id'   => $this->localbusiness_id(),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);
		if ( ! empty( $this->settings['org_logo'] ) ) {
			$lb['image'] = $this->settings['org_logo'];
		}
		if ( ! empty( $this->settings['lb_phone'] ) ) {
			$lb['telephone'] = $this->settings['lb_phone'];
		}
		if ( ! empty( $this->settings['lb_price'] ) ) {
			$lb['priceRange'] = $this->settings['lb_price'];
		}

		// PostalAddress.
		$address = array_filter( array(
			'streetAddress'   => $this->settings['lb_street'],
			'addressLocality' => $this->settings['lb_city'],
			'addressRegion'   => $this->settings['lb_region'],
			'postalCode'      => $this->settings['lb_postal'],
			'addressCountry'  => $this->settings['lb_country'],
		) );
		if ( ! empty( $address ) ) {
			$lb['address'] = array_merge( array( '@type' => 'PostalAddress' ), $address );
		}

		// Geo.
		if ( '' !== $this->settings['lb_lat'] && '' !== $this->settings['lb_lng'] ) {
			$lb['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $this->settings['lb_lat'],
				'longitude' => $this->settings['lb_lng'],
			);
		}

		// Opening hours (newline-separated strings, e.g. "Mo-Fr 09:00-17:00").
		if ( ! empty( $this->settings['lb_hours'] ) ) {
			$hours = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $this->settings['lb_hours'] ) ) );
			if ( $hours ) {
				$lb['openingHours'] = array_values( $hours );
			}
		}

		return $lb;
	}

	private function build_organization() {
		// TrustBio integration takes precedence if present.
		if ( function_exists( 'trustbio_get_organization_schema' ) ) {
			$tb = trustbio_get_organization_schema();
			if ( ! empty( $tb ) && is_array( $tb ) ) {
				return $tb;
			}
		}

		$name = $this->settings['organization'];
		if ( empty( $name ) ) {
			$name = get_bloginfo( 'name' );
		}
		if ( empty( $name ) ) {
			return null;
		}

		$org = array(
			'@type' => 'Organization',
			'@id'   => $this->org_id(),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);
		if ( ! empty( $this->settings['org_description'] ) ) {
			$org['description'] = $this->settings['org_description'];
		}
		if ( ! empty( $this->settings['org_logo'] ) ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $this->settings['org_logo'],
			);
			$org['image'] = array( '@id' => $this->settings['org_logo'] );
		}

		// --- Trust & Editorial signals (E-E-A-T) ---
		if ( ! empty( $this->settings['publishing_principles'] ) ) {
			$org['publishingPrinciples'] = $this->settings['publishing_principles'];
		}
		if ( ! empty( $this->settings['ethics_policy'] ) ) {
			$org['ethicsPolicy'] = $this->settings['ethics_policy'];
		}
		if ( ! empty( $this->settings['corrections_policy'] ) ) {
			$org['correctionsPolicy'] = $this->settings['corrections_policy'];
		}
		if ( ! empty( $this->settings['ownership_funding'] ) ) {
			$org['ownershipFundingInfo'] = $this->settings['ownership_funding'];
		}
		if ( ! empty( $this->settings['founding_date'] ) ) {
			$org['foundingDate'] = $this->settings['founding_date'];
		}
		$same_as = $this->parse_same_as();
		if ( ! empty( $same_as ) ) {
			$org['sameAs'] = $same_as;
		}

		return $org;
	}

	private function build_article( $post, $url, $lang ) {
		$article = array(
			'@type'            => 'Article',
			'@id'              => $this->article_id( $url ),
			'headline'         => wp_strip_all_tags( $post->post_title ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'url'              => $url,
			'mainEntityOfPage' => array( '@id' => $this->webpage_id( $url ) ),
			'isPartOf'         => array( '@id' => $this->webpage_id( $url ) ),
			'publisher'        => array( '@id' => $this->org_id() ),
			'inLanguage'       => $lang,
		);

		if ( ! empty( $this->settings['author'] ) ) {
			$article['author'] = array( '@id' => $this->person_id( $post->post_author ) );
		}

		if ( has_post_thumbnail( $post ) ) {
			$article['image'] = array(
				'@type' => 'ImageObject',
				'url'   => get_the_post_thumbnail_url( $post, 'full' ),
			);
		}

		$excerpt = get_the_excerpt( $post );
		if ( $excerpt ) {
			$article['description'] = wp_strip_all_tags( $excerpt );
		}

		return $article;
	}

	private function build_person( $post ) {
		$author_id = (int) $post->post_author;
		$name      = get_the_author_meta( 'display_name', $author_id );
		if ( ! $name ) {
			return null;
		}
		$person = array(
			'@type' => 'Person',
			'@id'   => $this->person_id( $author_id ),
			'name'  => $name,
		);
		$bio = get_the_author_meta( 'description', $author_id );
		if ( $bio ) {
			$person['description'] = wp_strip_all_tags( $bio );
		}
		$author_url = get_author_posts_url( $author_id );
		if ( $author_url ) {
			$person['url'] = $author_url;
		}

		// --- Author credentials (E-E-A-T) from user profile fields ---
		$job_title = get_user_meta( $author_id, 'rayetun_ag_job_title', true );
		if ( $job_title ) {
			$person['jobTitle'] = sanitize_text_field( $job_title );
		}
		$knows_about = get_user_meta( $author_id, 'rayetun_ag_knows_about', true );
		if ( $knows_about ) {
			$topics = array_filter( array_map( 'trim', explode( ',', $knows_about ) ) );
			if ( $topics ) {
				$person['knowsAbout'] = array_values( $topics );
			}
		}
		$same_as = get_user_meta( $author_id, 'rayetun_ag_same_as', true );
		if ( $same_as ) {
			$urls = array();
			foreach ( preg_split( '/[\r\n]+/', $same_as ) as $u ) {
				$u = esc_url_raw( trim( $u ) );
				if ( $u ) {
					$urls[] = $u;
				}
			}
			if ( $urls ) {
				$person['sameAs'] = $urls;
			}
		}

		return $person;
	}

	private function build_product( $post, $url ) {
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return null;
		}

		$data = array(
			'@type' => 'Product',
			'@id'   => $this->product_id( $url ),
			'name'  => $product->get_name(),
			'url'   => $url,
		);

		$desc = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
		if ( $desc ) {
			$data['description'] = wp_strip_all_tags( $desc );
		}
		$sku = $product->get_sku();
		if ( $sku ) {
			$data['sku'] = $sku;
		}
		if ( has_post_thumbnail( $post ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post, 'full' );
		}

		$price = $product->get_price();
		if ( '' !== $price ) {
			$data['offers'] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => $url,
			);
		}

		if ( $product->get_rating_count() > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (string) $product->get_review_count(),
			);
		}

		return $data;
	}

	private function build_breadcrumb( $post, $url ) {
		$items    = array();
		$position = 1;

		// Home.
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __( 'Home', 'agentgarrison' ),
			'item'     => home_url( '/' ),
		);

		if ( 'page' === $post->post_type ) {
			// Page ancestors, top-down.
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => wp_strip_all_tags( get_the_title( $ancestor_id ) ),
					'item'     => get_permalink( $ancestor_id ),
				);
			}
		} else {
			// Primary category trail (respects Yoast / Rank Math primary category).
			$term = $this->get_primary_term( $post );
			if ( $term ) {
				$term_ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) );
				foreach ( $term_ancestors as $tid ) {
					$t = get_term( $tid, $term->taxonomy );
					if ( $t && ! is_wp_error( $t ) ) {
						$items[] = array(
							'@type'    => 'ListItem',
							'position' => $position++,
							'name'     => $t->name,
							'item'     => get_term_link( $t ),
						);
					}
				}
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $term->name,
					'item'     => get_term_link( $term ),
				);
			}
		}

		// Current page.
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => wp_strip_all_tags( $post->post_title ),
			'item'     => $url,
		);

		if ( count( $items ) < 2 ) {
			return null;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $this->breadcrumb_id( $url ),
			'itemListElement' => $items,
		);
	}

	private function get_primary_term( $post ) {
		$taxonomy = 'category';
		// Yoast primary category.
		$primary_id = (int) get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true );
		if ( ! $primary_id ) {
			// Rank Math primary category.
			$primary_id = (int) get_post_meta( $post->ID, 'rank_math_primary_category', true );
		}
		if ( $primary_id ) {
			$term = get_term( $primary_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
		$terms = get_the_terms( $post->ID, $taxonomy );
		if ( $terms && ! is_wp_error( $terms ) ) {
			return $terms[0];
		}
		return null;
	}

	private function build_faq( $content ) {
		$questions = array();
		$seen      = array();

		$add = function ( $q, $a ) use ( &$questions, &$seen ) {
			$q = trim( wp_strip_all_tags( $q ) );
			$a = trim( wp_strip_all_tags( $a ) );
			if ( '' === $q || '' === $a ) {
				return;
			}
			$key = md5( strtolower( $q ) );
			if ( isset( $seen[ $key ] ) ) {
				return;
			}
			$seen[ $key ]  = true;
			$questions[]   = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $a,
				),
			);
		};

		// Pattern 1: <details><summary>Question</summary> Answer </details>.
		if ( preg_match_all( '/<details[^>]*>\s*<summary[^>]*>(.*?)<\/summary>(.*?)<\/details>/is', $content, $d, PREG_SET_ORDER ) ) {
			foreach ( $d as $m ) {
				$add( $m[1], $m[2] );
			}
		}

		// Pattern 2: heading question (H2–H4) followed by one or more block-level answers
		// (paragraphs, lists, divs) up to the next heading. Looser than the old H3+<p> rule.
		if ( preg_match_all( '/<h([2-4])[^>]*>(.*?)<\/h\1>(.*?)(?=<h[2-4][^>]*>|$)/is', $content, $h, PREG_SET_ORDER ) ) {
			foreach ( $h as $m ) {
				$question = $m[2];
				// Only treat it as an FAQ entry when the heading reads like a question.
				if ( false === strpos( $question, '?' ) ) {
					continue;
				}
				$add( $question, $m[3] );
			}
		}

		if ( count( $questions ) < 2 ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
	}

	private function build_howto( $post ) {
		if ( ! preg_match( '/\bhow to\b/i', $post->post_title ) ) {
			return null;
		}
		if ( ! preg_match( '/<ol[^>]*>(.*?)<\/ol>/is', $post->post_content, $ol ) ) {
			return null;
		}
		if ( ! preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $ol[1], $items ) ) {
			return null;
		}

		$steps = array();
		foreach ( $items[1] as $i => $item ) {
			$text = trim( wp_strip_all_tags( $item ) );
			if ( $text ) {
				$steps[] = array(
					'@type'    => 'HowToStep',
					'position' => $i + 1,
					'text'     => $text,
				);
			}
		}

		if ( count( $steps ) < 2 ) {
			return null;
		}

		return array(
			'@type' => 'HowTo',
			'name'  => wp_strip_all_tags( $post->post_title ),
			'step'  => $steps,
		);
	}

	// -------------------------------------------------------------------------
	// Per-post overrides + metabox
	// -------------------------------------------------------------------------

	private function get_overrides( $post_id ) {
		$raw = get_post_meta( $post_id, '_rayetun_ag_schema_overrides', true );
		$data = $raw ? json_decode( $raw, true ) : array();
		return wp_parse_args( is_array( $data ) ? $data : array(), array(
			'skip'     => false,
			'disabled' => array(),
			'reviewer' => '',
			'reviewed' => '',
		) );
	}

	public function register_metabox() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $pt ) {
			if ( 'attachment' === $pt ) {
				continue;
			}
			add_meta_box(
				'rayetun_ag_schema',
				__( 'AgentGarrison Schema', 'agentgarrison' ),
				array( $this, 'render_metabox' ),
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render_metabox( $post ) {
		wp_nonce_field( 'rayetun_ag_schema_overrides', 'rayetun_ag_schema_nonce' );
		$overrides = $this->get_overrides( $post->ID );

		$types = array(
			'article'    => __( 'Article', 'agentgarrison' ),
			'product'    => __( 'Product', 'agentgarrison' ),
			'faq'        => __( 'FAQ', 'agentgarrison' ),
			'howto'      => __( 'HowTo', 'agentgarrison' ),
			'breadcrumb' => __( 'Breadcrumb', 'agentgarrison' ),
			'author'     => __( 'Author', 'agentgarrison' ),
		);
		?>
		<div class="agentgarrison-schema-metabox">
			<label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:10px;">
				<input type="checkbox" name="rayetun_ag_schema_exclude" value="1" <?php checked( ! empty( $overrides['skip'] ) ); ?>>
				<?php esc_html_e( 'Exclude this post from schema', 'agentgarrison' ); ?>
			</label>

			<p style="font-size:11px;color:#5C7880;margin:0 0 6px;"><?php esc_html_e( 'Disable specific types for this post:', 'agentgarrison' ); ?></p>
			<?php foreach ( $types as $key => $label ) :
				$globally_on = ! empty( $this->settings[ $key ] );
				$is_disabled = ! empty( $overrides['disabled'] ) && in_array( $key, (array) $overrides['disabled'], true );
				?>
				<label style="display:flex;align-items:center;gap:7px;font-size:12px;margin-bottom:4px;<?php echo $globally_on ? '' : 'opacity:.5;'; ?>">
					<input type="checkbox" name="rayetun_ag_schema_disabled[]" value="<?php echo esc_attr( $key ); ?>"
						<?php checked( $is_disabled ); ?> <?php disabled( ! $globally_on ); ?>>
					<?php
					echo esc_html( $label );
					echo $globally_on ? '' : ' ' . esc_html__( '(off globally)', 'agentgarrison' );
					?>
				</label>
			<?php endforeach; ?>

			<hr style="margin:12px 0;border:none;border-top:1px solid #e0e8ea;">
			<p style="font-size:11px;color:#5C7880;margin:0 0 6px;font-weight:600;"><?php esc_html_e( 'Content Review (E-E-A-T)', 'agentgarrison' ); ?></p>
			<label style="display:block;font-size:11px;margin-bottom:6px;">
				<?php esc_html_e( 'Reviewed by', 'agentgarrison' ); ?>
				<input type="text" name="rayetun_ag_schema_reviewer" value="<?php echo esc_attr( $overrides['reviewer'] ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Dr. Jane Smith', 'agentgarrison' ); ?>" style="width:100%;margin-top:3px;">
			</label>
			<label style="display:block;font-size:11px;margin-bottom:4px;">
				<?php esc_html_e( 'Last reviewed', 'agentgarrison' ); ?>
				<input type="date" name="rayetun_ag_schema_reviewed" value="<?php echo esc_attr( $overrides['reviewed'] ); ?>" style="width:100%;margin-top:3px;">
			</label>
			<p style="font-size:10px;color:#9DB1B7;margin:4px 0 0;"><?php esc_html_e( 'Adds reviewedBy + lastReviewed to this page — the signal Google uses for "reviewed by" YMYL content.', 'agentgarrison' ); ?></p>

			<button type="button" class="button button-small js-preview-post-schema" style="margin-top:10px;width:100%;">
				<?php esc_html_e( 'Preview This Post\'s Schema', 'agentgarrison' ); ?>
			</button>
			<pre class="agentgarrison-metabox-preview js-post-schema-preview" style="display:none;background:#1A2B2E;color:#a8d8e0;padding:10px;border-radius:6px;font-size:11px;line-height:1.5;max-height:280px;overflow:auto;white-space:pre-wrap;word-break:break-word;margin-top:8px;"></pre>
		</div>
		<?php
	}

	public function save_overrides( $post_id ) {
		if ( ! isset( $_POST['rayetun_ag_schema_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['rayetun_ag_schema_nonce'] ), 'rayetun_ag_schema_overrides' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$allowed   = array( 'article', 'product', 'faq', 'howto', 'breadcrumb', 'author' );
		$raw_types = isset( $_POST['rayetun_ag_schema_disabled'] )
			? array_map( 'sanitize_key', (array) wp_unslash( $_POST['rayetun_ag_schema_disabled'] ) )
			: array();
		$disabled  = array_values( array_intersect( $raw_types, $allowed ) );

		$reviewed = sanitize_text_field( wp_unslash( $_POST['rayetun_ag_schema_reviewed'] ?? '' ) );
		// Only accept a YYYY-MM-DD date.
		if ( $reviewed && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewed ) ) {
			$reviewed = '';
		}

		$overrides = array(
			'skip'     => ! empty( $_POST['rayetun_ag_schema_exclude'] ),
			'disabled' => $disabled,
			'reviewer' => sanitize_text_field( wp_unslash( $_POST['rayetun_ag_schema_reviewer'] ?? '' ) ),
			'reviewed' => $reviewed,
		);
		update_post_meta( $post_id, '_rayetun_ag_schema_overrides', wp_json_encode( $overrides ) );
	}

	// -------------------------------------------------------------------------
	// Author credential fields (user profile)
	// -------------------------------------------------------------------------

	public function render_user_fields( $user ) {
		$job   = get_user_meta( $user->ID, 'rayetun_ag_job_title', true );
		$knows = get_user_meta( $user->ID, 'rayetun_ag_knows_about', true );
		$same  = get_user_meta( $user->ID, 'rayetun_ag_same_as', true );
		?>
		<h2><?php esc_html_e( 'AgentGarrison Author Credentials (Schema)', 'agentgarrison' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="rayetun_ag_job_title"><?php esc_html_e( 'Job Title', 'agentgarrison' ); ?></label></th>
				<td>
					<input type="text" name="rayetun_ag_job_title" id="rayetun_ag_job_title" class="regular-text"
						value="<?php echo esc_attr( $job ); ?>" placeholder="<?php esc_attr_e( 'e.g. Senior Editor', 'agentgarrison' ); ?>">
					<p class="description"><?php esc_html_e( 'Added as jobTitle in the author Person schema.', 'agentgarrison' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="rayetun_ag_knows_about"><?php esc_html_e( 'Areas of Expertise', 'agentgarrison' ); ?></label></th>
				<td>
					<input type="text" name="rayetun_ag_knows_about" id="rayetun_ag_knows_about" class="regular-text"
						value="<?php echo esc_attr( $knows ); ?>" placeholder="<?php esc_attr_e( 'camping, outdoor gear, hiking', 'agentgarrison' ); ?>">
					<p class="description"><?php esc_html_e( 'Comma-separated topics. Added as knowsAbout — a strong expertise signal for AI/Google.', 'agentgarrison' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="rayetun_ag_same_as"><?php esc_html_e( 'Profile URLs', 'agentgarrison' ); ?></label></th>
				<td>
					<textarea name="rayetun_ag_same_as" id="rayetun_ag_same_as" class="regular-text" rows="3"
						placeholder="https://twitter.com/handle&#10;https://linkedin.com/in/handle"><?php echo esc_textarea( $same ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line (author social/professional profiles). Added as sameAs for entity disambiguation.', 'agentgarrison' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_user_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		// The user-profile form carries WordPress's own update nonce, verified by core
		// before these hooks fire; we re-check the capability above.
		check_admin_referer( 'update-user_' . $user_id );

		update_user_meta( $user_id, 'rayetun_ag_job_title', sanitize_text_field( wp_unslash( $_POST['rayetun_ag_job_title'] ?? '' ) ) );
		update_user_meta( $user_id, 'rayetun_ag_knows_about', sanitize_text_field( wp_unslash( $_POST['rayetun_ag_knows_about'] ?? '' ) ) );
		update_user_meta( $user_id, 'rayetun_ag_same_as', sanitize_textarea_field( wp_unslash( $_POST['rayetun_ag_same_as'] ?? '' ) ) );
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function handle_save_settings() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$founding = sanitize_text_field( wp_unslash( $_POST['founding_date'] ?? '' ) );
		if ( $founding && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $founding ) ) {
			$founding = '';
		}

		$settings = array(
			'article'         => ! empty( $_POST['article'] ),
			'product'         => ! empty( $_POST['product'] ),
			'faq'             => ! empty( $_POST['faq'] ),
			'howto'           => ! empty( $_POST['howto'] ),
			'breadcrumb'      => ! empty( $_POST['breadcrumb'] ),
			'author'          => ! empty( $_POST['author'] ),
			'organization'    => sanitize_text_field( wp_unslash( $_POST['organization'] ?? '' ) ),
			'org_description' => sanitize_textarea_field( wp_unslash( $_POST['org_description'] ?? '' ) ),
			'org_logo'        => esc_url_raw( wp_unslash( $_POST['org_logo'] ?? '' ) ),
			// Trust & Editorial signals.
			'publishing_principles' => esc_url_raw( wp_unslash( $_POST['publishing_principles'] ?? '' ) ),
			'ethics_policy'         => esc_url_raw( wp_unslash( $_POST['ethics_policy'] ?? '' ) ),
			'corrections_policy'    => esc_url_raw( wp_unslash( $_POST['corrections_policy'] ?? '' ) ),
			'ownership_funding'     => sanitize_textarea_field( wp_unslash( $_POST['ownership_funding'] ?? '' ) ),
			'founding_date'         => $founding,
			'same_as'               => sanitize_textarea_field( wp_unslash( $_POST['same_as'] ?? '' ) ),
			'default_reviewer'      => sanitize_text_field( wp_unslash( $_POST['default_reviewer'] ?? '' ) ),
			// Detection & more types (S3).
			'website'        => ! empty( $_POST['website'] ),
			'speakable'      => ! empty( $_POST['speakable'] ),
			'local_business' => ! empty( $_POST['local_business'] ),
			'lb_type'        => sanitize_text_field( wp_unslash( $_POST['lb_type'] ?? 'LocalBusiness' ) ),
			'lb_phone'       => sanitize_text_field( wp_unslash( $_POST['lb_phone'] ?? '' ) ),
			'lb_price'       => sanitize_text_field( wp_unslash( $_POST['lb_price'] ?? '' ) ),
			'lb_street'      => sanitize_text_field( wp_unslash( $_POST['lb_street'] ?? '' ) ),
			'lb_city'        => sanitize_text_field( wp_unslash( $_POST['lb_city'] ?? '' ) ),
			'lb_region'      => sanitize_text_field( wp_unslash( $_POST['lb_region'] ?? '' ) ),
			'lb_postal'      => sanitize_text_field( wp_unslash( $_POST['lb_postal'] ?? '' ) ),
			'lb_country'     => sanitize_text_field( wp_unslash( $_POST['lb_country'] ?? '' ) ),
			'lb_lat'         => sanitize_text_field( wp_unslash( $_POST['lb_lat'] ?? '' ) ),
			'lb_lng'         => sanitize_text_field( wp_unslash( $_POST['lb_lng'] ?? '' ) ),
			'lb_hours'       => sanitize_textarea_field( wp_unslash( $_POST['lb_hours'] ?? '' ) ),
		);
		update_option( 'rayetun_ag_schema_settings', $settings );
		$this->settings = $settings;
		Rayetun_AG_Visibility_Score::invalidate();

		wp_send_json_success( array( 'message' => __( 'Schema settings saved.', 'agentgarrison' ) ) );
	}

	/**
	 * Build the full @graph for a given post (or the latest published post when
	 * post_id is 0) and return it pretty-printed. Powers both the editor metabox
	 * preview and the Schema tab's full-graph preview.
	 */
	public function handle_preview_post_schema() {
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

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'No post available to preview.', 'agentgarrison' ) ) );
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => $this->build_full_graph( $post ),
		);

		wp_send_json_success( array(
			'json'  => wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
			'title' => get_the_title( $post ),
		) );
	}

	// -------------------------------------------------------------------------
	// Validator
	// -------------------------------------------------------------------------

	/**
	 * Recommended properties per @type (Google-aligned). Missing ones are flagged
	 * as advisories — they don't break the markup but improve rich-result eligibility.
	 */
	private function recommended_props() {
		return array(
			'Organization'    => array( 'logo', 'sameAs' ),
			'WebSite'         => array( 'potentialAction' ),
			'WebPage'         => array( 'url', 'datePublished' ),
			'Article'         => array( 'image', 'author', 'datePublished', 'headline' ),
			'Product'         => array( 'offers', 'image' ),
			'FAQPage'         => array( 'mainEntity' ),
			'HowTo'           => array( 'step' ),
			'BreadcrumbList'  => array( 'itemListElement' ),
			'Person'          => array( 'name' ),
			'LocalBusiness'   => array( 'address', 'telephone' ),
		);
	}

	public function handle_validate_schema() {
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
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'No post available to validate.', 'agentgarrison' ) ) );
		}

		$graph        = $this->build_full_graph( $post );
		$recommended  = $this->recommended_props();
		$types_found  = array();
		$recommendations = array();

		foreach ( $graph as $node ) {
			$type = $node['@type'] ?? '';
			if ( is_array( $type ) ) {
				$type = reset( $type );
			}
			if ( ! $type ) {
				continue;
			}
			$types_found[] = $type;

			if ( isset( $recommended[ $type ] ) ) {
				$missing = array();
				foreach ( $recommended[ $type ] as $prop ) {
					if ( empty( $node[ $prop ] ) ) {
						$missing[] = $prop;
					}
				}
				if ( $missing ) {
					$recommendations[] = array(
						'type'    => $type,
						'missing' => $missing,
					);
				}
			}
		}
		$types_found = array_values( array_unique( $types_found ) );

		// Conflict report.
		$conflict = array( 'active' => false, 'plugin' => '', 'deferred' => array() );
		if ( $this->has_seo_plugin_schema() ) {
			$conflict['active']   = true;
			$conflict['plugin']   = defined( 'WPSEO_VERSION' ) ? 'Yoast SEO' : 'Rank Math';
			$conflict['deferred'] = array( 'Article' ); // AgentGarrison defers Article when an SEO plugin is active.
		}

		wp_send_json_success( array(
			'title'            => get_the_title( $post ),
			'node_count'       => count( $graph ),
			'types'            => $types_found,
			'recommendations'  => $recommendations,
			'conflict'         => $conflict,
			'rich_results_url' => 'https://search.google.com/test/rich-results?url=' . rawurlencode( get_permalink( $post ) ),
		) );
	}

	public function get_settings() {
		return $this->settings;
	}
}
