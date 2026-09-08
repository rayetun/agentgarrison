<?php
/**
 * Module 5 — Content Readability Scorer.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Content_Scorer {

	private static $instance = null;

	private $vague_words = array( 'things', 'stuff', 'various', 'several', 'many', 'some', 'a lot', 'kind of', 'sort of', 'etc', 'and so on', 'and more' );

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( Rayetun_AG_Modules::is_enabled( 'content_scorer' ) ) {
			add_action( 'add_meta_boxes', array( $this, 'register_score_metabox' ) );
			add_action( 'save_post', array( $this, 'recalculate_score' ), 20 );
			add_filter( 'manage_posts_columns', array( $this, 'add_score_column' ) );
			add_action( 'manage_posts_custom_column', array( $this, 'render_score_column' ), 10, 2 );
		}

		add_action( 'wp_ajax_rayetun_ag_get_content_scores', array( $this, 'handle_get_scores' ) );
		add_action( 'wp_ajax_rayetun_ag_dismiss_suggestion', array( $this, 'handle_dismiss_suggestion' ) );
		add_action( 'wp_ajax_rayetun_ag_rescore_post', array( $this, 'handle_rescore' ) );
		add_action( 'wp_ajax_rayetun_ag_ai_content_suggestions', array( $this, 'handle_ai_suggestions' ) );
	}

	// -------------------------------------------------------------------------
	// Scoring engine
	// -------------------------------------------------------------------------

	public function score_post( $post ) {
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post ) {
			return array( 'score' => 0, 'dimensions' => array(), 'suggestions' => array() );
		}

		$content   = wp_strip_all_tags( $post->post_content );
		$raw_html  = $post->post_content;
		$title     = $post->post_title;

		$dimensions  = array();
		$suggestions = array();

		// 1. Answer Density (25%).
		$answer = $this->score_answer_density( $content, $title );
		$dimensions['answer_density'] = array( 'label' => __( 'Answer Density', 'agentgarrison' ), 'score' => $answer, 'weight' => 25 );
		if ( $answer < 60 ) {
			$suggestions[] = array( 'id' => 'answer', 'text' => __( 'Add a direct answer to the page topic within the first 150 words.', 'agentgarrison' ) );
		}

		// 2. Header Hierarchy (20%).
		$headers = $this->score_header_hierarchy( $raw_html );
		$dimensions['header_hierarchy'] = array( 'label' => __( 'Header Hierarchy', 'agentgarrison' ), 'score' => $headers['score'], 'weight' => 20 );
		if ( $headers['score'] < 70 && $headers['message'] ) {
			$suggestions[] = array( 'id' => 'headers', 'text' => $headers['message'] );
		}

		// 3. Fact Density (20%).
		$facts = $this->score_fact_density( $content );
		$dimensions['fact_density'] = array( 'label' => __( 'Fact Density', 'agentgarrison' ), 'score' => $facts, 'weight' => 20 );
		if ( $facts < 60 ) {
			$suggestions[] = array( 'id' => 'facts', 'text' => __( 'Add specific numbers, dates, statistics, or named entities to increase factual density.', 'agentgarrison' ) );
		}

		// 4. Reading Level (20%) — approximate.
		$reading = $this->score_reading_level( $content );
		$dimensions['reading_level'] = array( 'label' => __( 'Reading Level (approx.)', 'agentgarrison' ), 'score' => $reading, 'weight' => 20 );
		if ( $reading < 60 ) {
			$suggestions[] = array( 'id' => 'reading', 'text' => __( 'Shorten your sentences. Aim for around 15–20 words per sentence for best AI extractability.', 'agentgarrison' ) );
		}

		// 5. Ambiguity Index (15%).
		$ambiguity = $this->score_ambiguity( $content );
		$dimensions['ambiguity'] = array( 'label' => __( 'Clarity', 'agentgarrison' ), 'score' => $ambiguity, 'weight' => 15 );
		if ( $ambiguity < 70 ) {
			$suggestions[] = array( 'id' => 'ambiguity', 'text' => __( 'Replace vague words like "things", "stuff", and "various" with specific terms.', 'agentgarrison' ) );
		}

		// Composite.
		$composite = 0;
		foreach ( $dimensions as $dim ) {
			$composite += ( $dim['score'] / 100 ) * $dim['weight'];
		}
		$composite = (int) round( $composite );

		return array(
			'score'       => $composite,
			'dimensions'  => $dimensions,
			'suggestions' => $suggestions,
		);
	}

	private function score_answer_density( $content, $title ) {
		$first_150 = strtolower( implode( ' ', array_slice( preg_split( '/\s+/', $content ), 0, 150 ) ) );
		if ( '' === trim( $first_150 ) ) {
			return 0;
		}
		// Extract meaningful words from the title (length > 3).
		$title_words = array_filter( preg_split( '/\s+/', strtolower( wp_strip_all_tags( $title ) ) ), function ( $w ) {
			return strlen( $w ) > 3;
		} );
		if ( empty( $title_words ) ) {
			return 70;
		}
		$hits = 0;
		foreach ( $title_words as $word ) {
			if ( false !== strpos( $first_150, $word ) ) {
				$hits++;
			}
		}
		$ratio = $hits / count( $title_words );
		return (int) min( 100, round( $ratio * 120 ) );
	}

	private function score_header_hierarchy( $html ) {
		preg_match_all( '/<h([1-6])\b/i', $html, $matches );
		$levels = array_map( 'intval', $matches[1] );

		if ( empty( $levels ) ) {
			return array( 'score' => 40, 'message' => __( 'No headings found. Add H2 and H3 subheadings to structure your content for AI.', 'agentgarrison' ) );
		}

		$score    = 100;
		$message  = '';
		$prev     = 1;
		foreach ( $levels as $level ) {
			if ( $level - $prev > 1 ) {
				$score  -= 20;
				if ( ! $message ) {
					$message = sprintf(
						/* translators: 1: heading level, 2: heading level */
						__( 'Heading hierarchy skips a level (H%1$d follows H%2$d). Keep nesting sequential.', 'agentgarrison' ),
						$level,
						$prev
					);
				}
			}
			$prev = $level;
		}
		return array( 'score' => max( 0, $score ), 'message' => $message );
	}

	private function score_fact_density( $content ) {
		$word_count = max( 1, str_word_count( $content ) );
		preg_match_all( '/\b\d[\d,\.]*\b/', $content, $numbers );
		$num_count = count( $numbers[0] );
		// Capitalised mid-sentence words as a proxy for named entities.
		preg_match_all( '/(?<=[a-z]\s)[A-Z][a-z]+/', $content, $entities );
		$entity_count = count( $entities[0] );

		$density = ( ( $num_count + $entity_count ) / $word_count ) * 1000;
		return (int) min( 100, round( $density * 6 ) );
	}

	private function score_reading_level( $content ) {
		$sentences = max( 1, preg_match_all( '/[.!?]+/', $content ) );
		$words     = max( 1, str_word_count( $content ) );
		$avg       = $words / $sentences;

		// Ideal 12–20 words/sentence. Penalise either extreme.
		if ( $avg >= 12 && $avg <= 20 ) {
			return 100;
		}
		if ( $avg < 12 ) {
			return (int) max( 40, 100 - ( 12 - $avg ) * 5 );
		}
		return (int) max( 20, 100 - ( $avg - 20 ) * 4 );
	}

	private function score_ambiguity( $content ) {
		$lower      = strtolower( $content );
		$word_count = max( 1, str_word_count( $content ) );
		$hits       = 0;
		foreach ( $this->vague_words as $vague ) {
			$hits += substr_count( $lower, $vague );
		}
		$ratio = ( $hits / $word_count ) * 100;
		return (int) max( 0, min( 100, round( 100 - $ratio * 12 ) ) );
	}

	// -------------------------------------------------------------------------
	// Metabox
	// -------------------------------------------------------------------------

	public function register_score_metabox() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'rayetun_ag_score',
				__( 'AgentGarrison AI Score', 'agentgarrison' ),
				array( $this, 'render_metabox' ),
				$pt,
				'side',
				'high'
			);
		}
	}

	public function render_metabox( $post ) {
		$score = get_post_meta( $post->ID, '_rayetun_ag_ai_score', true );
		$details = get_post_meta( $post->ID, '_rayetun_ag_score_details', true );

		if ( '' === $score ) {
			$result  = $this->score_post( $post );
			$score   = $result['score'];
			$details = $result['dimensions'];
		}

		$color = $score >= 70 ? '#2E7D32' : ( $score >= 50 ? '#F0A500' : '#C62828' );
		?>
		<div class="agentgarrison-metabox">
			<div class="agentgarrison-metabox__score" style="color:<?php echo esc_attr( $color ); ?>">
				<?php echo absint( $score ); ?><span class="agentgarrison-metabox__max">/100</span>
			</div>
			<?php if ( ! empty( $details ) && is_array( $details ) ) : ?>
			<ul class="agentgarrison-metabox__dims">
				<?php foreach ( $details as $dim ) :
					$dc = $dim['score'] >= 70 ? '#2E7D32' : ( $dim['score'] >= 50 ? '#F0A500' : '#C62828' );
				?>
				<li>
					<span><?php echo esc_html( $dim['label'] ); ?></span>
					<strong style="color:<?php echo esc_attr( $dc ); ?>"><?php echo absint( $dim['score'] ); ?></strong>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<p class="agentgarrison-metabox__hint"><?php esc_html_e( 'Score updates when you save. See full suggestions in AgentGarrison → Content Scorer.', 'agentgarrison' ); ?></p>
				<?php if ( class_exists( 'Rayetun_AG_AI_Provider' ) && Rayetun_AG_AI_Provider::is_available() ) : ?>
				<button type="button" class="agentgarrison-btn agentgarrison-btn--sm agentgarrison-btn--secondary js-ai-content-fixes" data-post="<?php echo absint( $post->ID ); ?>" style="margin-top:10px;width:100%;">
					✨ <?php esc_html_e( 'Get AI fixes', 'agentgarrison' ); ?>
				</button>
				<div class="agentgarrison-ai-fixes js-ai-fixes" style="margin-top:10px;"></div>
				<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save / recalculate
	// -------------------------------------------------------------------------

	public function recalculate_score( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$result = $this->score_post( $post );

		update_post_meta( $post_id, '_rayetun_ag_ai_score', $result['score'] );
		update_post_meta( $post_id, '_rayetun_ag_score_details', $result['dimensions'] );

		// Preserve dismissed suggestion IDs.
		$existing  = get_post_meta( $post_id, '_rayetun_ag_score_suggestions', true );
		$dismissed = array();
		if ( $existing ) {
			$decoded = json_decode( $existing, true );
			if ( is_array( $decoded ) && isset( $decoded['dismissed'] ) ) {
				$dismissed = $decoded['dismissed'];
			}
		}
		update_post_meta( $post_id, '_rayetun_ag_score_suggestions', wp_json_encode( array(
			'suggestions' => $result['suggestions'],
			'dismissed'   => $dismissed,
		) ) );
	}

	// -------------------------------------------------------------------------
	// Post list column
	// -------------------------------------------------------------------------

	public function add_score_column( $columns ) {
		$columns['rayetun_ag_score'] = __( 'AI Score', 'agentgarrison' );
		return $columns;
	}

	public function render_score_column( $column, $post_id ) {
		if ( 'rayetun_ag_score' !== $column ) {
			return;
		}
		$score = get_post_meta( $post_id, '_rayetun_ag_ai_score', true );
		if ( '' === $score ) {
			echo '<span style="color:#9DB1B7;">—</span>';
			return;
		}
		$score = absint( $score );
		$class = $score >= 70 ? 'is-good' : ( $score >= 50 ? 'is-mid' : 'is-low' );
		echo '<span class="agentgarrison-score-pill ' . esc_attr( $class ) . '">' . absint( $score ) . '</span>';
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function handle_get_scores() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}

		$per_page = 30;
		$paged    = max( 1, absint( $_POST['paged'] ?? 1 ) );
		$filter   = sanitize_key( $_POST['filter'] ?? 'all' );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query_args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'meta_key'       => '_rayetun_ag_ai_score',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
		);

		if ( in_array( $filter, array( 'low', 'mid', 'good' ), true ) ) {
			$ranges = array(
				'low'  => array( 0, 49 ),
				'mid'  => array( 50, 69 ),
				'good' => array( 70, 100 ),
			);
			$query_args['meta_query'] = array(
				array(
					'key'     => '_rayetun_ag_ai_score',
					'value'   => $ranges[ $filter ],
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				),
			);
		}

		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query = new WP_Query( $query_args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$score = (int) get_post_meta( $post->ID, '_rayetun_ag_ai_score', true );
			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
				'score'     => $score,
				'type'      => $post->post_type,
			);
		}

		wp_send_json_success( array(
			'items'    => $items,
			'paged'    => $paged,
			'pages'    => (int) $query->max_num_pages,
			'total'    => (int) $query->found_posts,
		) );
	}

	public function handle_rescore() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'agentgarrison' ) ) );
		}
		$this->recalculate_score( $post_id );
		wp_send_json_success( array( 'score' => (int) get_post_meta( $post_id, '_rayetun_ag_ai_score', true ) ) );
	}

	/**
	 * Ask the site's own AI provider for concrete edits that would raise a post's
	 * AI-readability score, focused on its weakest dimensions. Runs on the owner's
	 * configured provider (core AI Client on WP 7.0+, or a BYO key) — no cost to us.
	 */
	public function handle_ai_suggestions() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		if ( ! class_exists( 'Rayetun_AG_AI_Provider' ) || ! Rayetun_AG_AI_Provider::is_available() ) {
			wp_send_json_error( array( 'message' => __( 'No AI provider is configured. On WordPress 7.0+ connect one under Settings → Connectors, or add an API key on the Citation Monitor tab.', 'agentgarrison' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'agentgarrison' ) ) );
		}

		$result = $this->score_post( $post );
		$weak   = array();
		foreach ( $result['dimensions'] as $dim ) {
			if ( $dim['score'] < 70 ) {
				$weak[] = $dim['label'] . ' (' . (int) $dim['score'] . '/100)';
			}
		}

		$content = wp_trim_words( wp_strip_all_tags( $post->post_content ), 600, '' );
		$prompt  = sprintf(
			"You are optimizing a web article so AI assistants (ChatGPT, Claude, Perplexity) can understand and cite it. Give exactly 3 specific, actionable edits — reference the article's actual content. Focus on the weakest areas: %s. Return each suggestion on its own line starting with '- ', no preamble.\n\nTitle: %s\n\nContent:\n%s",
			$weak ? implode( ', ', $weak ) : __( 'overall clarity and structure', 'agentgarrison' ),
			$post->post_title,
			$content
		);

		$response = Rayetun_AG_AI_Provider::complete( $prompt, null, array( 'max_tokens' => 400 ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$suggestions = array();
		foreach ( preg_split( '/\n+/', (string) $response['content'] ) as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			$line = ltrim( $line, "-*•0123456789. \t" );
			if ( '' !== $line ) {
				$suggestions[] = $line;
			}
		}
		$suggestions = array_slice( $suggestions, 0, 5 );

		wp_send_json_success( array(
			'score'       => (int) $result['score'],
			'suggestions' => $suggestions,
		) );
	}

	public function handle_dismiss_suggestion() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		$post_id = absint( $_POST['post_id'] ?? 0 );
		$sug_id  = sanitize_key( $_POST['suggestion_id'] ?? '' );
		if ( ! $post_id || ! $sug_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'agentgarrison' ) ) );
		}

		$existing = get_post_meta( $post_id, '_rayetun_ag_score_suggestions', true );
		$data     = $existing ? json_decode( $existing, true ) : array( 'suggestions' => array(), 'dismissed' => array() );
		if ( ! is_array( $data ) ) {
			$data = array( 'suggestions' => array(), 'dismissed' => array() );
		}
		$data['dismissed'][] = $sug_id;
		$data['dismissed']   = array_unique( $data['dismissed'] );
		update_post_meta( $post_id, '_rayetun_ag_score_suggestions', wp_json_encode( $data ) );

		wp_send_json_success();
	}
}
