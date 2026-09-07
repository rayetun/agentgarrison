<?php
/**
 * Module registry — enable/disable state management.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_Modules {

	private static $defaults = array(
		'bot_control'      => true,
		'agent_control'    => true,
		'llms_txt'         => true,
		'analytics'        => true,
		'referral_tracker' => true,
		'content_scorer'   => true,
		'schema'           => true,
		'email_digest'     => false,
		'citation_monitor' => true,
		'reports'          => true,
		'honeypot'         => true,
		'markdown_agents'  => false,
		'qa_block'         => true,
		'visibility_score' => true,
	);

	public static function init_defaults() {
		$current = get_option( 'rayetun_ag_modules', array() );
		$merged  = array_merge( self::$defaults, $current );
		update_option( 'rayetun_ag_modules', $merged );
	}

	public static function is_enabled( $module_id ) {
		// Use get_all() so modules added in a later release inherit their default
		// state on sites whose stored option predates them (otherwise a missing
		// key reads as disabled).
		$modules = self::get_all();
		return ! empty( $modules[ $module_id ] );
	}

	public static function get_all() {
		return wp_parse_args( get_option( 'rayetun_ag_modules', array() ), self::$defaults );
	}

	public static function set( $module_id, $enabled ) {
		$modules                = self::get_all();
		$modules[ $module_id ]  = (bool) $enabled;
		update_option( 'rayetun_ag_modules', $modules );
	}

	public static function get_definitions() {
		return array(
			'bot_control' => array(
				'label'       => __( 'AI Bot Control', 'agentgarrison' ),
				'description' => __( 'Control which AI bots can access your site. Auto-generates robots.txt rules with optional X-Robots-Tag header enforcement.', 'agentgarrison' ),
				'icon'        => '🤖',
			),
			'agent_control' => array(
				'label'       => __( 'Agent Control', 'agentgarrison' ),
				'description' => __( 'Govern which site Abilities AI agents can access over MCP (WordPress 7.1+), and keep an audit log of every agent action. Extends Bot Control into the write/act era.', 'agentgarrison' ),
				'icon'        => '🛡️',
			),
			'llms_txt' => array(
				'label'       => __( 'llms.txt Generator', 'agentgarrison' ),
				'description' => __( 'Auto-generate /llms.txt and /llms-full.txt so AI models understand your site structure and prioritize your content.', 'agentgarrison' ),
				'icon'        => '📄',
			),
			'analytics' => array(
				'label'       => __( 'AI Bot Analytics', 'agentgarrison' ),
				'description' => __( 'Log every AI bot visit locally. See which bots crawl your site, how often, and which pages they target.', 'agentgarrison' ),
				'icon'        => '📊',
			),
			'referral_tracker' => array(
				'label'       => __( 'LLM Referral Tracker', 'agentgarrison' ),
				'description' => __( 'Track human visitors who arrive from ChatGPT, Perplexity, Gemini, Claude, and Copilot citations.', 'agentgarrison' ),
				'icon'        => '🔗',
			),
			'content_scorer' => array(
				'label'       => __( 'Content Readability Scorer', 'agentgarrison' ),
				'description' => __( 'Score every post for AI readability across 5 dimensions. Get actionable suggestions in the post editor.', 'agentgarrison' ),
				'icon'        => '✍️',
			),
			'schema' => array(
				'label'       => __( 'Schema & Structured Data', 'agentgarrison' ),
				'description' => __( 'Auto-inject JSON-LD schema (Article, FAQ, HowTo, Organization) with conflict detection for Yoast and Rank Math.', 'agentgarrison' ),
				'icon'        => '🗂️',
			),
			'email_digest' => array(
				'label'       => __( 'Email Digest', 'agentgarrison' ),
				'description' => __( 'Weekly or monthly email digest covering bot visits, LLM referrals, and your AI Visibility Score. White-label ready for agencies.', 'agentgarrison' ),
				'icon'        => '📧',
			),
			'citation_monitor' => array(
				'label'       => __( 'Citation Monitor', 'agentgarrison' ),
				'description' => __( 'Track which of your pages AI platforms cite for your target keywords. Includes a Demo Mode — no API key required to preview.', 'agentgarrison' ),
				'icon'        => '🔎',
			),
			'reports' => array(
				'label'       => __( 'White-label Reports', 'agentgarrison' ),
				'description' => __( 'Generate branded AI visibility reports covering score, analytics, referrals, and content. Print to PDF from your browser.', 'agentgarrison' ),
				'icon'        => '📑',
			),
			'honeypot' => array(
				'label'       => __( 'Honeypot Bot Trap', 'agentgarrison' ),
				'description' => __( 'Places a hidden, robots.txt-disallowed trap URL. Any bot that hits it is caught — great for spotting bots that spoof human User-Agents or ignore robots.txt.', 'agentgarrison' ),
				'icon'        => '🍯',
			),
			'markdown_agents' => array(
				'label'       => __( 'Markdown for Agents', 'agentgarrison' ),
				'description' => __( 'Serve clean, token-efficient Markdown to AI agents on your normal URLs via content negotiation (Accept: text/markdown). Runs entirely on your own site, free, on any host.', 'agentgarrison' ),
				'icon'        => '📝',
			),
			'qa_block' => array(
				'label'       => __( 'AI Q&A Block', 'agentgarrison' ),
				'description' => __( 'A Gutenberg block for question-and-answer content that renders as a native collapsible block and is auto-detected as FAQ structured data for AI engines.', 'agentgarrison' ),
				'icon'        => '❓',
			),
			'visibility_score' => array(
				'label'       => __( 'AI Visibility Score', 'agentgarrison' ),
				'description' => __( 'Composite 0–100 score showing your overall AI readiness across Bot Access, llms.txt, Analytics, Content, and Schema pillars.', 'agentgarrison' ),
				'icon'        => '🔍',
			),
		);
	}
}
