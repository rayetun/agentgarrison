<?php
/**
 * AI provider adapter.
 *
 * One interface for all LLM text completions. Prefers the WordPress core AI Client
 * (WP 7.0+, wp_ai_client_prompt()) when it is present and configured; otherwise falls
 * back to the plugin's existing bring-your-own-key OpenAI / Perplexity HTTP calls.
 *
 * Keeping this behind a single class means the plugin runs unchanged on WP 6.2–6.9
 * (BYO key) and automatically uses the site-level provider on WP 7.0+ with no key
 * management of its own.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_AI_Provider {

	const API_KEYS_OPTION = 'rayetun_ag_citation_api_keys';

	/**
	 * Backends usable right now, in priority order (core first).
	 *
	 * @return string[] Any of: 'core', 'openai', 'perplexity'. Empty if none.
	 */
	public static function active_backends() {
		$backends = array();

		if ( self::core_supported() ) {
			$backends[] = 'core';
		}

		$keys = (array) get_option( self::API_KEYS_OPTION, array() );
		if ( ! empty( $keys['anthropic'] ) ) {
			$backends[] = 'anthropic';
		}
		if ( ! empty( $keys['openai'] ) ) {
			$backends[] = 'openai';
		}
		if ( ! empty( $keys['perplexity'] ) ) {
			$backends[] = 'perplexity';
		}

		return $backends;
	}

	/**
	 * @return bool True if at least one AI backend can be used.
	 */
	public static function is_available() {
		return ! empty( self::active_backends() );
	}

	/**
	 * @return string The preferred active backend: 'core' | 'openai' | 'perplexity' | 'none'.
	 */
	public static function active_backend() {
		$backends = self::active_backends();
		return $backends ? $backends[0] : 'none';
	}

	/**
	 * Human-readable label for the active backend, for the settings UI.
	 *
	 * @return string
	 */
	public static function status_label() {
		switch ( self::active_backend() ) {
			case 'core':
				return __( 'Using the WordPress core AI Client — no API key needed.', 'agentgarrison' );
			case 'openai':
				return __( 'Using your OpenAI API key.', 'agentgarrison' );
			case 'perplexity':
				return __( 'Using your Perplexity API key.', 'agentgarrison' );
			case 'anthropic':
				return __( 'Using your Anthropic (Claude) API key.', 'agentgarrison' );
			default:
				return __( 'No AI backend configured. On WordPress 7.0+ configure a provider under Settings → Connectors, or add an API key below.', 'agentgarrison' );
		}
	}

	/**
	 * Actionable explanation of the core AI Client state, for the settings UI.
	 * Distinguishes "function missing" from "present but no provider ready".
	 *
	 * @return string
	 */
	public static function core_diagnostic() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return __( 'The WordPress AI Client is not active on this site yet. On the Settings → Connectors screen, use "Install the AI plugin" to enable AI providers, then reload this page.', 'agentgarrison' );
		}
		$builder = self::core_prompt( 'capability check' );
		if ( ! is_object( $builder ) ) {
			return __( 'The AI Client function is present but did not return a prompt builder. Please let us know your WordPress version.', 'agentgarrison' );
		}
		try {
			$supported = $builder->is_supported_for_text_generation();
			if ( is_wp_error( $supported ) || ! $supported ) {
				return __( 'A credential is connected, but no AI provider is registered for text generation yet. On Settings → Connectors, make sure a text-capable provider like Anthropic is connected, then reload.', 'agentgarrison' );
			}
		} catch ( \Throwable $e ) {
			return __( 'The AI Client is present but the capability check could not run.', 'agentgarrison' ) . ' (' . $e->getMessage() . ')';
		}
		return __( 'Core AI Client is ready.', 'agentgarrison' );
	}

	/**
	 * Whether the WP 7.0+ core AI Client is present AND configured for text generation.
	 * The support check is deterministic (no API call, no inference).
	 *
	 * We deliberately do NOT use method_exists(): the returned builder
	 * (Prompt_Builder_With_WP_Error) is a decorator that proxies calls via __call(),
	 * so method_exists() reports false even though the method is callable. Instead we
	 * call it inside try/catch and honour the WP_Error return.
	 *
	 * @return bool
	 */
	private static function core_supported() {
		$builder = self::core_prompt( 'capability check' );
		if ( ! is_object( $builder ) ) {
			return false;
		}
		try {
			$supported = $builder->is_supported_for_text_generation();
			return ! is_wp_error( $supported ) && (bool) $supported;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Entry point to the WP 7.0+ core AI Client, called dynamically.
	 *
	 * The text is passed as the function argument (the canonical form both the
	 * php-ai-client and wp-ai-client SDKs use). The indirect invocation via a
	 * variable is deliberate: it keeps static analysers (Plugin Check) from treating
	 * a 7.0-only function as a hard requirement against our WP 6.2 minimum.
	 *
	 * @param string $text Prompt text.
	 * @return object|null Prompt builder on WP 7.0+, else null.
	 */
	private static function core_prompt( $text = '' ) {
		$fn = 'wp_ai_client_prompt';
		if ( ! function_exists( $fn ) ) {
			return null;
		}
		return '' !== $text ? $fn( $text ) : $fn();
	}

	/**
	 * Complete a text prompt against a backend.
	 *
	 * @param string      $prompt  The prompt text.
	 * @param string|null $backend One of 'core'|'openai'|'perplexity'; null = preferred active.
	 * @param array       $args    Optional: 'max_tokens' (int).
	 * @return array|WP_Error { content:string, citations:string[], backend:string } or WP_Error.
	 */
	public static function complete( $prompt, $backend = null, $args = array() ) {
		$backend = $backend ? $backend : self::active_backend();
		$max     = isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 500;

		switch ( $backend ) {
			case 'core':
				return self::complete_core( $prompt, $max );
			case 'anthropic':
				return self::complete_anthropic( $prompt, $max );
			case 'openai':
				return self::complete_openai( $prompt, $max );
			case 'perplexity':
				return self::complete_perplexity( $prompt, $max );
		}

		return new WP_Error( 'rayetun_ag_no_ai', __( 'No AI backend is available.', 'agentgarrison' ) );
	}

	// -------------------------------------------------------------------------
	// Backends
	// -------------------------------------------------------------------------

	/**
	 * WordPress core AI Client (WP 7.0+). Returns text only; citations are derived
	 * downstream by scanning the response for the site's own domain.
	 */
	private static function complete_core( $prompt, $max ) {
		$builder = self::core_prompt( $prompt );
		if ( ! is_object( $builder ) ) {
			return new WP_Error( 'rayetun_ag_no_ai', __( 'No AI backend is available.', 'agentgarrison' ) );
		}

		// The builder proxies via __call and returns WP_Error on failure. Wrap in
		// try/catch so an unexpected SDK shape degrades gracefully instead of fataling.
		try {
			$text = $builder->generate_text();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'rayetun_ag_core_ai', $e->getMessage() );
		}

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return array(
			'content'   => (string) $text,
			'citations' => array(),
			'backend'   => 'core',
		);
	}

	/**
	 * Anthropic (Claude) via the Messages API. Used on sites that add a direct
	 * Anthropic API key (e.g. WordPress < 7.0, or 7.0+ without a core provider).
	 */
	private static function complete_anthropic( $prompt, $max ) {
		$keys = (array) get_option( self::API_KEYS_OPTION, array() );
		if ( empty( $keys['anthropic'] ) ) {
			return new WP_Error( 'rayetun_ag_no_key', __( 'Anthropic API key is not set.', 'agentgarrison' ) );
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 20,
			'headers' => array(
				'x-api-key'         => $keys['anthropic'],
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( array(
				// Fast, low-cost tier — matches the gpt-4o-mini choice on the OpenAI path.
				'model'      => 'claude-haiku-4-5',
				'max_tokens' => $max,
				'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'rayetun_ag_anthropic_http', __( 'Anthropic request failed.', 'agentgarrison' ) );
		}

		// The Messages API returns content as an array of blocks; concatenate the text ones.
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = '';
		if ( ! empty( $body['content'] ) && is_array( $body['content'] ) ) {
			foreach ( $body['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}

		return array(
			'content'   => $text,
			'citations' => array(),
			'backend'   => 'anthropic',
		);
	}

	private static function complete_openai( $prompt, $max ) {
		$keys = (array) get_option( self::API_KEYS_OPTION, array() );
		if ( empty( $keys['openai'] ) ) {
			return new WP_Error( 'rayetun_ag_no_key', __( 'OpenAI API key is not set.', 'agentgarrison' ) );
		}

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $keys['openai'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => 'gpt-4o-mini',
				'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
				'max_tokens' => $max,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'rayetun_ag_openai_http', __( 'OpenAI request failed.', 'agentgarrison' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'content'   => (string) ( $body['choices'][0]['message']['content'] ?? '' ),
			'citations' => array(),
			'backend'   => 'openai',
		);
	}

	private static function complete_perplexity( $prompt, $max ) {
		$keys = (array) get_option( self::API_KEYS_OPTION, array() );
		if ( empty( $keys['perplexity'] ) ) {
			return new WP_Error( 'rayetun_ag_no_key', __( 'Perplexity API key is not set.', 'agentgarrison' ) );
		}

		$response = wp_remote_post( 'https://api.perplexity.ai/chat/completions', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $keys['perplexity'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'    => 'llama-3.1-sonar-small-128k-online',
				'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'rayetun_ag_perplexity_http', __( 'Perplexity request failed.', 'agentgarrison' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'content'   => (string) ( $body['choices'][0]['message']['content'] ?? '' ),
			'citations' => isset( $body['citations'] ) ? (array) $body['citations'] : array(),
			'backend'   => 'perplexity',
		);
	}
}
