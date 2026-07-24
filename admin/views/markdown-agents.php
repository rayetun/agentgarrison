<?php
/**
 * Markdown for Agents view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_md          = Rayetun_AG_Markdown_Agents::get_instance();
$rayetun_ag_md_settings = $rayetun_ag_md->get_settings();
$rayetun_ag_md_pt       = get_post_types( array( 'public' => true ), 'objects' );
$rayetun_ag_md_selected = (array) ( $rayetun_ag_md_settings['post_types'] ?? array( 'post', 'page' ) );

// A real sample URL the admin can click to view a page as Markdown in the browser.
$rayetun_ag_md_sample = '';
$rayetun_ag_md_recent = get_posts( array(
	'numberposts' => 1,
	'post_status' => 'publish',
	'post_type'   => ! empty( $rayetun_ag_md_selected ) ? $rayetun_ag_md_selected : array( 'post', 'page' ),
) );
if ( ! empty( $rayetun_ag_md_recent ) ) {
	$rayetun_ag_md_sample = add_query_arg( 'rayetun_ag_md', '1', get_permalink( $rayetun_ag_md_recent[0] ) );
}
?>
<div class="agentgarrison-markdown">
	<div class="agentgarrison-page-header">
		<h1 class="agentgarrison-page-title"><?php esc_html_e( 'Markdown for Agents', 'agentgarrison' ); ?></h1>
		<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Serve clean, token-efficient Markdown to AI agents on your normal URLs.', 'agentgarrison' ); ?></p>
	</div>

	<div class="agentgarrison-callout agentgarrison-callout--info">
		<?php esc_html_e( 'When an AI agent requests one of your pages with the "Accept: text/markdown" header, AgentGarrison returns a clean Markdown version of the content instead of the full HTML page. That is typically 5 to 6 times fewer tokens, which means cheaper, faster, more accurate AI processing. It runs entirely on your own site, free, on any host.', 'agentgarrison' ); ?>
	</div>

	<div class="agentgarrison-two-col">
		<div class="agentgarrison-col agentgarrison-col--settings">
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Settings', 'agentgarrison' ); ?></h2>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label"><?php esc_html_e( 'Enable for Post Types', 'agentgarrison' ); ?></label>
					<div class="agentgarrison-checkboxes">
						<?php foreach ( $rayetun_ag_md_pt as $rayetun_ag_md_pt_obj ) :
							if ( 'attachment' === $rayetun_ag_md_pt_obj->name ) {
								continue;
							}
							?>
						<label class="agentgarrison-checkbox-pill">
							<input type="checkbox" name="md_post_types[]"
								value="<?php echo esc_attr( $rayetun_ag_md_pt_obj->name ); ?>"
								<?php checked( in_array( $rayetun_ag_md_pt_obj->name, $rayetun_ag_md_selected, true ) ); ?>>
							<span class="agentgarrison-checkbox-pill__mark"></span>
							<span class="agentgarrison-checkbox-pill__text"><?php echo esc_html( $rayetun_ag_md_pt_obj->labels->name ); ?></span>
						</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="agentgarrison-options-row">
					<label class="agentgarrison-custom-checkbox">
						<input type="checkbox" id="ag-md-frontmatter" value="1" <?php checked( ! empty( $rayetun_ag_md_settings['frontmatter'] ) ); ?>>
						<span class="agentgarrison-custom-checkbox__box"></span>
						<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Include YAML frontmatter', 'agentgarrison' ); ?></span>
					</label>
					<span class="agentgarrison-hint"><?php esc_html_e( 'Adds title, URL, date, author, image, categories, and tags at the top of the file.', 'agentgarrison' ); ?></span>
				</div>

				<div class="agentgarrison-options-row">
					<label class="agentgarrison-custom-checkbox">
						<input type="checkbox" id="ag-md-token" value="1" <?php checked( ! empty( $rayetun_ag_md_settings['token_header'] ) ); ?>>
						<span class="agentgarrison-custom-checkbox__box"></span>
						<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Send X-Markdown-Tokens header', 'agentgarrison' ); ?></span>
					</label>
					<span class="agentgarrison-hint"><?php esc_html_e( 'Lets agents estimate context usage before reading the file.', 'agentgarrison' ); ?></span>
				</div>

				<div class="agentgarrison-options-row">
					<label class="agentgarrison-custom-checkbox">
						<input type="checkbox" id="ag-md-discovery" value="1" <?php checked( ! empty( $rayetun_ag_md_settings['discovery'] ) ); ?>>
						<span class="agentgarrison-custom-checkbox__box"></span>
						<span class="agentgarrison-custom-checkbox__label"><?php esc_html_e( 'Advertise the Markdown alternate', 'agentgarrison' ); ?></span>
					</label>
					<span class="agentgarrison-hint"><?php esc_html_e( 'Adds a <link rel="alternate"> tag and Link header so agents can discover the Markdown version.', 'agentgarrison' ); ?></span>
				</div>

				<div class="agentgarrison-field-actions">
					<button class="agentgarrison-btn agentgarrison-btn--primary js-save-markdown-settings"><?php esc_html_e( 'Save Settings', 'agentgarrison' ); ?></button>
					<span class="agentgarrison-save-status js-markdown-status"></span>
				</div>
			</div>

			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'How to test', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc" style="margin-bottom:14px;"><?php esc_html_e( 'The easiest way to check it is working: click the button below to open a sample page as Markdown in a new tab. This is exactly what an AI agent receives.', 'agentgarrison' ); ?></p>

				<?php if ( $rayetun_ag_md_sample ) : ?>
					<a href="<?php echo esc_url( $rayetun_ag_md_sample ); ?>" target="_blank" rel="noopener" class="agentgarrison-btn agentgarrison-btn--primary">
						<?php esc_html_e( 'View a sample page as Markdown', 'agentgarrison' ); ?>
					</a>
					<p class="agentgarrison-hint" style="margin-top:10px;"><?php esc_html_e( 'Tip: adding ?rayetun_ag_md=1 to the end of any enabled page URL returns its Markdown version.', 'agentgarrison' ); ?></p>
				<?php else : ?>
					<p class="agentgarrison-hint"><?php esc_html_e( 'Publish a post or page first, then a sample link will appear here.', 'agentgarrison' ); ?></p>
				<?php endif; ?>

				<details style="margin-top:16px;">
					<summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--ag-primary);"><?php esc_html_e( 'Advanced: test with the Accept header (command line)', 'agentgarrison' ); ?></summary>
					<pre class="agentgarrison-preview-pane" style="max-height:none;margin-top:10px;">curl -H "Accept: text/markdown" <?php echo esc_html( $rayetun_ag_md_sample ? get_permalink( $rayetun_ag_md_recent[0] ) : home_url( '/' ) ); ?></pre>
				</details>
			</div>
		</div>

		<div class="agentgarrison-col agentgarrison-col--preview">
			<div class="agentgarrison-card">
				<div class="agentgarrison-card__header">
					<h2 class="agentgarrison-card__title js-markdown-preview-title"><?php esc_html_e( 'Live Preview', 'agentgarrison' ); ?></h2>
					<button class="agentgarrison-btn agentgarrison-btn--sm js-preview-markdown"><?php esc_html_e( 'Preview latest post', 'agentgarrison' ); ?></button>
				</div>
				<p class="agentgarrison-hint js-markdown-meta" style="margin-bottom:12px;"><?php esc_html_e( 'Click to render the Markdown a single post would return.', 'agentgarrison' ); ?></p>
				<pre class="agentgarrison-preview-pane js-markdown-preview"><?php esc_html_e( 'Markdown preview will appear here…', 'agentgarrison' ); ?></pre>
			</div>
		</div>
	</div>
</div>
