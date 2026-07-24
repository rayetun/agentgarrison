<?php
/**
 * Module 7 — AI Visibility Score.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Visibility_Score {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( Rayetun_AG_Modules::is_enabled( 'visibility_score' ) ) {
			if ( ! wp_next_scheduled( 'rayetun_ag_refresh_visibility_score' ) ) {
				wp_schedule_event( time(), 'rayetun_ag_six_hourly', 'rayetun_ag_refresh_visibility_score' );
			}
			add_action( 'rayetun_ag_refresh_visibility_score', array( $this, 'calculate_and_cache' ) );
		}

		add_action( 'wp_ajax_rayetun_ag_get_visibility_score', array( $this, 'handle_get_score' ) );
	}

	// -------------------------------------------------------------------------
	// Score calculation
	// -------------------------------------------------------------------------

	public function get_score() {
		$cache = get_option( 'rayetun_ag_visibility_score_cache', array() );
		if ( ! empty( $cache['calculated_at'] ) && ( time() - $cache['calculated_at'] ) < 6 * HOUR_IN_SECONDS ) {
			return $cache;
		}
		return $this->calculate_and_cache();
	}

	/**
	 * Drop the cached score so the next dashboard load recomputes it. Call this
	 * after any action that changes a pillar's underlying state (settings saved,
	 * llms.txt generated, module toggled, onboarding step completed).
	 */
	public static function invalidate() {
		delete_option( 'rayetun_ag_visibility_score_cache' );
	}

	public function calculate_and_cache() {
		$pillars  = array();
		$checklist = array();

		// Pillar 1 — Bot Access (20%).
		$bot_score    = 0;
		$bot_settings = get_option( 'rayetun_ag_bot_settings', array() );
		if ( Rayetun_AG_Modules::is_enabled( 'bot_control' ) ) {
			$bot_score += 50;
			$cats = $bot_settings['categories'] ?? array();
			if ( ! empty( $cats['ai_assistant'] ) && 'allow' === $cats['ai_assistant'] ) {
				$bot_score += 50;
			} else {
				$checklist[] = array(
					'priority'     => 'high',
					'icon'         => '🤖',
					'title'        => __( 'Allow AI Assistants', 'agentgarrison' ),
					'description'  => __( 'ChatGPT, Claude, and Gemini are currently blocked. Allow AI Assistants so they can crawl your site and cite your content in answers.', 'agentgarrison' ),
					'action_label' => __( 'Fix in Bot Control', 'agentgarrison' ),
					'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=bot-control' ),
				);
			}
		} else {
			$checklist[] = array(
				'priority'     => 'high',
				'icon'         => '🤖',
				'title'        => __( 'Enable Bot Control', 'agentgarrison' ),
				'description'  => __( 'Bot Control is off. Enable it to manage which AI bots can access your site and to auto-generate robots.txt rules.', 'agentgarrison' ),
				'action_label' => __( 'Go to Settings', 'agentgarrison' ),
				'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=settings' ),
			);
		}
		$pillars['bot_access'] = array(
			'label'  => __( 'Bot Access', 'agentgarrison' ),
			'score'  => $bot_score,
			'weight' => 20,
		);

		// Pillar 2 — llms.txt (20%).
		$llms_score = 0;
		if ( Rayetun_AG_Modules::is_enabled( 'llms_txt' ) ) {
			$llms_settings = get_option( 'rayetun_ag_llms_settings', array() );
			if ( ! empty( $llms_settings['last_generated'] ) ) {
				$llms_score += 60;
				$health = get_option( 'rayetun_ag_llms_health', array() );
				if ( ! empty( $health['ok'] ) ) {
					$llms_score += 20;
				} else {
					$checklist[] = array(
						'priority'     => 'high',
					'icon'         => '📄',
					'title'        => __( 'Fix llms.txt Error', 'agentgarrison' ),
					'description'  => __( 'Your llms.txt is returning an error. AI crawlers cannot read your site map. Try re-saving your permalink settings, then click Regenerate.', 'agentgarrison' ),
					'action_label' => __( 'Go to llms.txt', 'agentgarrison' ),
					'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=llms-txt' ),
				);
				}
				if ( empty( $health['stale'] ) ) {
					$llms_score += 20;
				} else {
					$checklist[] = array(
						'priority'     => 'medium',
						'icon'         => '🕐',
						'title'        => __( 'Regenerate Stale llms.txt', 'agentgarrison' ),
						'description'  => __( 'Your llms.txt hasn\'t been updated in over 7 days. AI crawlers may be reading outdated content. Click Regenerate Now to refresh it.', 'agentgarrison' ),
						'action_label' => __( 'Regenerate', 'agentgarrison' ),
						'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=llms-txt' ),
					);
				}

				// Drift: pages changed since the last generation.
				if ( class_exists( 'Rayetun_AG_Llms_Txt' ) ) {
					$drift = Rayetun_AG_Llms_Txt::get_instance()->get_drift_count();
					if ( $drift > 0 ) {
						$checklist[] = array(
							'priority'     => 'low',
							'icon'         => '📝',
							/* translators: %d: number of changed pages */
							'title'        => sprintf( _n( '%d Page Changed Since Last llms.txt', '%d Pages Changed Since Last llms.txt', $drift, 'agentgarrison' ), $drift ),
							'description'  => __( 'Content has been edited or added since your llms.txt was last generated. Regenerate it so AI crawlers see your latest pages.', 'agentgarrison' ),
							'action_label' => __( 'Regenerate', 'agentgarrison' ),
							'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=llms-txt' ),
						);
					}
				}
			} else {
				$checklist[] = array(
					'priority'     => 'high',
					'icon'         => '📄',
					'title'        => __( 'Generate Your llms.txt', 'agentgarrison' ),
					'description'  => __( 'llms.txt tells AI models what your site is about — think of it as robots.txt for the AI era. Without it, AI engines can\'t prioritise your content.', 'agentgarrison' ),
					'action_label' => __( 'Generate Now', 'agentgarrison' ),
					'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=llms-txt' ),
				);
			}
		} else {
			$checklist[] = array(
				'priority'     => 'high',
				'icon'         => '📄',
				'title'        => __( 'Enable llms.txt Module', 'agentgarrison' ),
				'description'  => __( 'The llms.txt module is off. Enable it to auto-generate the AI navigation file that ChatGPT, Perplexity, and Gemini crawlers read.', 'agentgarrison' ),
				'action_label' => __( 'Go to Settings', 'agentgarrison' ),
				'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=settings' ),
			);
		}
		$pillars['llms_txt'] = array(
			'label'  => __( 'llms.txt', 'agentgarrison' ),
			'score'  => $llms_score,
			'weight' => 20,
		);

		// Pillar 3 — Analytics (15%).
		$analytics_score = 0;
		if ( Rayetun_AG_Modules::is_enabled( 'analytics' ) ) {
			$analytics_score = 60;
			global $wpdb;
			$table = $wpdb->prefix . Rayetun_AG_DB::BOT_VISITS_TABLE;
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $count > 0 ) {
				$analytics_score = 100;
			} else {
				$checklist[] = array(
					'priority'     => 'low',
					'icon'         => '📊',
					'title'        => __( 'Waiting for First Bot Visit', 'agentgarrison' ),
					'description'  => __( 'Analytics is active and recording. AI bots typically crawl new or updated content within 3–5 days. No action needed — just wait.', 'agentgarrison' ),
					'action_label' => '',
					'action_url'   => '',
				);
			}
		} else {
			$checklist[] = array(
				'priority'     => 'medium',
				'icon'         => '📊',
				'title'        => __( 'Enable Bot Analytics', 'agentgarrison' ),
				'description'  => __( 'You\'re flying blind without analytics. Enable this to see which AI bots crawl your site, how often, and which pages they target.', 'agentgarrison' ),
				'action_label' => __( 'Go to Settings', 'agentgarrison' ),
				'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=settings' ),
			);
		}
		$pillars['analytics'] = array(
			'label'  => __( 'Analytics', 'agentgarrison' ),
			'score'  => $analytics_score,
			'weight' => 15,
		);

		// Pillar 4 — Content Quality (25%).
		$content_score = 0;
		if ( Rayetun_AG_Modules::is_enabled( 'content_scorer' ) ) {
			global $wpdb;
			$scores = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = '_rayetun_ag_ai_score'
				AND p.post_status = 'publish'
				ORDER BY p.post_date DESC LIMIT 20"
			);
			if ( ! empty( $scores ) ) {
				$content_score = (int) ( array_sum( $scores ) / count( $scores ) );
				if ( $content_score < 70 ) {
					$checklist[] = array(
						'priority'     => 'medium',
						'icon'         => '✍️',
						'title'        => __( 'Improve Content AI Score', 'agentgarrison' ),
						'description'  => sprintf(
							/* translators: %d: average score */
							__( 'Your average AI readability score is %d/100. Open Content Scorer, sort by lowest score, and improve the weakest posts first.', 'agentgarrison' ),
							$content_score
						),
						'action_label' => __( 'Open Content Scorer', 'agentgarrison' ),
						'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=content-scorer' ),
					);
				}
			} else {
				$checklist[] = array(
					'priority'     => 'medium',
					'icon'         => '✍️',
					'title'        => __( 'Score Your Content', 'agentgarrison' ),
					'description'  => __( 'No posts have been scored yet. Save any post to trigger automatic scoring, or open Content Scorer to score existing posts.', 'agentgarrison' ),
					'action_label' => __( 'Open Content Scorer', 'agentgarrison' ),
					'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=content-scorer' ),
				);
			}
		} else {
			$checklist[] = array(
				'priority'     => 'medium',
				'icon'         => '✍️',
				'title'        => __( 'Enable Content Scorer', 'agentgarrison' ),
				'description'  => __( 'Content quality is the biggest factor in AI citations. Enable the Content Scorer to get a readability score and actionable suggestions for each post.', 'agentgarrison' ),
				'action_label' => __( 'Go to Settings', 'agentgarrison' ),
				'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=settings' ),
			);
		}
		$pillars['content_quality'] = array(
			'label'  => __( 'Content Quality', 'agentgarrison' ),
			'score'  => $content_score,
			'weight' => 25,
		);

		// Pillar 5 — Schema (20%).
		$schema_score = 0;
		if ( Rayetun_AG_Modules::is_enabled( 'schema' ) ) {
			$schema_settings = get_option( 'rayetun_ag_schema_settings', array() );
			$schema_score    = 50;
			if ( ! empty( $schema_settings['organization'] ) || ! empty( get_bloginfo( 'name' ) ) ) {
				$schema_score += 30;
			} else {
				$checklist[] = array(
					'priority'     => 'medium',
					'icon'         => '🏢',
					'title'        => __( 'Add Organization Name', 'agentgarrison' ),
					'description'  => __( 'Your Organization schema is missing a name. Adding it tells AI models who runs this site, improving brand recognition in AI-generated answers.', 'agentgarrison' ),
					'action_label' => __( 'Go to Schema', 'agentgarrison' ),
					'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=schema' ),
				);
			}
			if ( ! empty( $schema_settings['faq'] ) ) {
				$schema_score += 20;
			}
		} else {
			$checklist[] = array(
				'priority'     => 'medium',
				'icon'         => '🗂️',
				'title'        => __( 'Enable Schema Module', 'agentgarrison' ),
				'description'  => __( 'Structured data (JSON-LD) helps AI models extract facts from your pages. Enable Schema to auto-inject Article, FAQPage, and Organization markup.', 'agentgarrison' ),
				'action_label' => __( 'Go to Settings', 'agentgarrison' ),
				'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=settings' ),
			);
		}
		$pillars['schema'] = array(
			'label'  => __( 'Schema', 'agentgarrison' ),
			'score'  => $schema_score,
			'weight' => 20,
		);

		// Pillar 6 — AI Citations (conditional). Only counts once a live scan has run,
		// so users who never use Citation Monitor are not penalised. When present, the
		// composite renormalises against the new total weight.
		if ( Rayetun_AG_Modules::is_enabled( 'citation_monitor' ) && class_exists( 'Rayetun_AG_Citation_Monitor' ) ) {
			$cited_rate = Rayetun_AG_Citation_Monitor::get_instance()->get_live_cited_rate();
			if ( null !== $cited_rate ) {
				$pillars['citations'] = array(
					'label'  => __( 'AI Citations', 'agentgarrison' ),
					'score'  => $cited_rate,
					'weight' => 20,
				);
				if ( $cited_rate < 60 ) {
					$checklist[] = array(
						'priority'     => 'medium',
						'icon'         => '🔎',
						'title'        => __( 'Improve Your AI Citation Rate', 'agentgarrison' ),
						/* translators: %d: cited-rate percentage */
						'description'  => sprintf( __( 'AI assistants cite your site for %d%% of your tracked questions. Strengthen the pages competitors are winning, then re-scan.', 'agentgarrison' ), $cited_rate ),
						'action_label' => __( 'Open Citation Monitor', 'agentgarrison' ),
						'action_url'   => admin_url( 'admin.php?page=agentgarrison&tab=citation-monitor' ),
					);
				}
			}
		}

		// Composite score — normalised against total pillar weight so it stays 0–100
		// whether or not the conditional citations pillar is present.
		$composite    = 0;
		$total_weight = 0;
		foreach ( $pillars as $pillar ) {
			$composite    += ( $pillar['score'] / 100 ) * $pillar['weight'];
			$total_weight += $pillar['weight'];
		}
		$composite = $total_weight ? (int) round( $composite / $total_weight * 100 ) : 0;

		$result = array(
			'score'         => $composite,
			'grade'         => $this->get_grade( $composite ),
			'pillars'       => $pillars,
			'checklist'     => $checklist,
			'calculated_at' => time(),
		);

		update_option( 'rayetun_ag_visibility_score_cache', $result );
		return $result;
	}

	private function get_grade( $score ) {
		if ( $score >= 90 ) { return 'A+'; }
		if ( $score >= 80 ) { return 'A'; }
		if ( $score >= 70 ) { return 'B'; }
		if ( $score >= 60 ) { return 'C'; }
		if ( $score >= 50 ) { return 'D'; }
		return 'F';
	}

	public function handle_get_score() {
		check_ajax_referer( 'rayetun_ag_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'agentgarrison' ) ), 403 );
		}
		wp_send_json_success( $this->get_score() );
	}
}
