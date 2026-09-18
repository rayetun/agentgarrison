<?php
/**
 * MCP for Agents view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_mcp_abilities = Rayetun_AG_MCP::abilities_available();
$rayetun_ag_mcp_adapter   = Rayetun_AG_MCP::mcp_adapter_active();
$rayetun_ag_mcp_tools     = Rayetun_AG_MCP::tool_names();
?>
<div class="agentgarrison-mcp">
	<div class="agentgarrison-page-header">
		<div>
			<h1 class="agentgarrison-page-title"><?php esc_html_e( 'MCP for Agents', 'agentgarrison' ); ?></h1>
			<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Let AI agents query your published content over the Model Context Protocol, using the standard WordPress Abilities API.', 'agentgarrison' ); ?></p>
		</div>
	</div>

	<div class="agentgarrison-callout agentgarrison-callout--info">
		<?php esc_html_e( 'AgentGarrison registers your content as WordPress "Abilities" (search content, read a page as Markdown, get a site overview) and marks them public for MCP. When the WordPress MCP Adapter is active, AI agents can discover and call these tools to read your site directly, no scraping required.', 'agentgarrison' ); ?>
	</div>

	<!-- Status (full width) -->
	<div class="agentgarrison-card">
		<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Status', 'agentgarrison' ); ?></h2>

		<div class="agentgarrison-callout agentgarrison-callout--<?php echo $rayetun_ag_mcp_abilities ? 'success' : 'info'; ?>" style="margin-bottom:12px;">
			<?php if ( $rayetun_ag_mcp_abilities ) : ?>
				✅ <?php esc_html_e( 'Abilities API detected (WordPress 6.9+). Your content tools are registered.', 'agentgarrison' ); ?>
			<?php else : ?>
				ℹ️ <?php
				printf(
					/* translators: %s: current WordPress version */
					esc_html__( 'The Abilities API is part of WordPress 6.9 and later. Your site is on WordPress %s, so these tools are not registered yet. Update WordPress to enable this feature.', 'agentgarrison' ),
					esc_html( get_bloginfo( 'version' ) )
				);
				?>
			<?php endif; ?>
		</div>

		<div class="agentgarrison-callout agentgarrison-callout--<?php echo $rayetun_ag_mcp_adapter ? 'success' : 'info'; ?>">
			<?php if ( $rayetun_ag_mcp_adapter ) : ?>
				✅ <?php esc_html_e( 'The WordPress MCP Adapter is active. Your tools are exposed on its MCP server for agents to discover.', 'agentgarrison' ); ?>
			<?php else : ?>
				ℹ️ <?php esc_html_e( 'No MCP Adapter was detected on this request. Your abilities are still registered and usable, but no MCP server is exposing them yet. The MCP Adapter is a library that other plugins bundle, so you usually get it by activating one of these:', 'agentgarrison' ); ?>
				<ul style="margin:8px 0 0 18px;list-style:disc;">
					<li><?php esc_html_e( 'The WordPress AI Experiments plugin (the same "AI plugin" prompted under Settings → Connectors) — it bundles the MCP Adapter.', 'agentgarrison' ); ?></li>
					<li><?php esc_html_e( 'The official MCP Adapter plugin release, uploaded via Plugins → Add New → Upload.', 'agentgarrison' ); ?> <a href="https://github.com/WordPress/mcp-adapter/releases" target="_blank" rel="noopener" class="agentgarrison-link"><?php esc_html_e( 'Releases ↗', 'agentgarrison' ); ?></a></li>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="agentgarrison-two-col">
		<div class="agentgarrison-col agentgarrison-col--settings">
			<!-- Registered tools -->
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Registered tools', 'agentgarrison' ); ?></h2>
				<ul class="agentgarrison-mcp-tools">
					<li class="agentgarrison-mcp-tool">
						<code class="agentgarrison-mcp-tool__name">agentgarrison/search-content</code>
						<span class="agentgarrison-mcp-tool__desc"><?php esc_html_e( 'Search published posts and pages by keyword.', 'agentgarrison' ); ?></span>
					</li>
					<li class="agentgarrison-mcp-tool">
						<code class="agentgarrison-mcp-tool__name">agentgarrison/get-page-markdown</code>
						<span class="agentgarrison-mcp-tool__desc"><?php esc_html_e( 'Return a page as clean Markdown, by URL or ID.', 'agentgarrison' ); ?></span>
					</li>
					<li class="agentgarrison-mcp-tool">
						<code class="agentgarrison-mcp-tool__name">agentgarrison/get-site-overview</code>
						<span class="agentgarrison-mcp-tool__desc"><?php esc_html_e( 'Site name, description, and most recent content.', 'agentgarrison' ); ?></span>
					</li>
				</ul>
				<p class="agentgarrison-hint" style="margin-top:12px;">
					<?php
					printf(
						/* translators: %d: number of tools */
						esc_html__( '%d tools registered under the "agentgarrison" ability namespace.', 'agentgarrison' ),
						count( $rayetun_ag_mcp_tools )
					);
					?>
				</p>
			</div>
		</div>

		<div class="agentgarrison-col agentgarrison-col--preview">
			<!-- Privacy note -->
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'What agents can and cannot see', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc"><?php esc_html_e( 'These tools are read-only and only ever return content that is already public: published posts and pages. Drafts, private, password-protected, and any post you exclude from llms.txt are never exposed. No admin actions, settings, or user data are accessible.', 'agentgarrison' ); ?></p>
			</div>
		</div>
	</div>
</div>
