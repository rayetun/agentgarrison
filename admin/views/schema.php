<?php
/**
 * Schema settings view.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$rayetun_ag_schema          = Rayetun_AG_Schema::get_instance();
$rayetun_ag_schema_settings = $rayetun_ag_schema->get_settings();
$rayetun_ag_schema_conflict = $rayetun_ag_schema->has_conflict();

$rayetun_ag_schema_types = array(
	'article'    => array( 'label' => __( 'Article', 'agentgarrison' ), 'desc' => __( 'Applied to posts, pages, and custom post types automatically.', 'agentgarrison' ) ),
	'product'    => array( 'label' => __( 'Product (WooCommerce)', 'agentgarrison' ), 'desc' => __( 'Price, availability, SKU, and ratings for WooCommerce products.', 'agentgarrison' ) ),
	'faq'        => array( 'label' => __( 'FAQPage', 'agentgarrison' ), 'desc' => __( 'Auto-detected from question headings, paragraphs, and details/summary blocks.', 'agentgarrison' ) ),
	'howto'      => array( 'label' => __( 'HowTo', 'agentgarrison' ), 'desc' => __( 'Auto-detected from "How to" titles with an ordered step list.', 'agentgarrison' ) ),
	'author'     => array( 'label' => __( 'Author (Person)', 'agentgarrison' ), 'desc' => __( 'Adds structured author data linked to articles.', 'agentgarrison' ) ),
	'breadcrumb' => array( 'label' => __( 'BreadcrumbList', 'agentgarrison' ), 'desc' => __( 'Generated from page hierarchy or primary category.', 'agentgarrison' ) ),
	'website'    => array( 'label' => __( 'WebSite + Search', 'agentgarrison' ), 'desc' => __( 'Site-wide WebSite node with a SearchAction (Google sitelinks search box).', 'agentgarrison' ) ),
	'speakable'  => array( 'label' => __( 'Speakable', 'agentgarrison' ), 'desc' => __( 'Marks the title and intro for voice assistants and AI answer extraction.', 'agentgarrison' ) ),
);
?>
<div class="agentgarrison-schema">
	<div class="agentgarrison-page-header">
		<div>
			<h1 class="agentgarrison-page-title"><?php esc_html_e( 'Schema & Structured Data', 'agentgarrison' ); ?></h1>
			<p class="agentgarrison-page-subtitle"><?php esc_html_e( 'Auto-inject JSON-LD schema so AI engines understand your content.', 'agentgarrison' ); ?></p>
		</div>
		<div class="agentgarrison-page-actions">
			<button class="agentgarrison-btn agentgarrison-btn--secondary js-preview-org-schema">
				<?php esc_html_e( 'Preview Organization', 'agentgarrison' ); ?>
			</button>
			<button class="agentgarrison-btn agentgarrison-btn--secondary js-preview-full-schema">
				<?php esc_html_e( 'Preview Full Page Schema', 'agentgarrison' ); ?>
			</button>
			<button class="agentgarrison-btn agentgarrison-btn--secondary js-validate-schema">
				<?php esc_html_e( 'Validate Schema', 'agentgarrison' ); ?>
			</button>
			<button class="agentgarrison-btn agentgarrison-btn--primary js-save-schema-settings">
				<?php esc_html_e( 'Save Schema Settings', 'agentgarrison' ); ?>
			</button>
			<span class="agentgarrison-save-status js-schema-save-status"></span>
		</div>
	</div>

	<?php if ( $rayetun_ag_schema_conflict ) : ?>
	<div class="agentgarrison-callout agentgarrison-callout--warning">
		<?php esc_html_e( 'Yoast SEO or Rank Math is active. AgentGarrison automatically skips Article schema to avoid duplicate markup — FAQ, HowTo, and Organization schema still apply.', 'agentgarrison' ); ?>
	</div>
	<?php endif; ?>

	<div class="agentgarrison-schema-layout">

		<!-- Left column: settings -->
		<div class="agentgarrison-schema-settings-col">

			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Schema Types', 'agentgarrison' ); ?></h2>

				<?php foreach ( $rayetun_ag_schema_types as $rayetun_ag_schema_type_key => $rayetun_ag_schema_type ) : ?>
				<div class="agentgarrison-options-row">
					<label class="agentgarrison-custom-checkbox">
						<input type="checkbox" class="agentgarrison-schema-type"
							name="<?php echo esc_attr( $rayetun_ag_schema_type_key ); ?>" value="1"
							<?php checked( ! empty( $rayetun_ag_schema_settings[ $rayetun_ag_schema_type_key ] ) ); ?>>
						<span class="agentgarrison-custom-checkbox__box"></span>
						<span class="agentgarrison-custom-checkbox__label">
							<strong><?php echo esc_html( $rayetun_ag_schema_type['label'] ); ?></strong>
						</span>
					</label>
					<span class="agentgarrison-hint"><?php echo esc_html( $rayetun_ag_schema_type['desc'] ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Organization', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc" style="margin-bottom:18px;"><?php esc_html_e( 'Appears on every page as site-wide Organization schema, helping AI models identify your brand.', 'agentgarrison' ); ?></p>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-org-name"><?php esc_html_e( 'Organization Name', 'agentgarrison' ); ?></label>
					<input type="text" id="ag-org-name" class="agentgarrison-input" name="organization"
						value="<?php echo esc_attr( $rayetun_ag_schema_settings['organization'] ); ?>"
						placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<p class="agentgarrison-hint"><?php esc_html_e( 'Leave blank to use your site title.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-org-description"><?php esc_html_e( 'Organization Description', 'agentgarrison' ); ?></label>
					<textarea id="ag-org-description" class="agentgarrison-textarea" rows="3" name="org_description"
						placeholder="<?php esc_attr_e( 'A short description of your organization for AI models. E.g. "We build WordPress plugins for SEO and AI optimization."', 'agentgarrison' ); ?>"><?php echo esc_textarea( $rayetun_ag_schema_settings['org_description'] ?? '' ); ?></textarea>
					<p class="agentgarrison-hint"><?php esc_html_e( 'Used in the Organization schema description property. Helps AI models understand what your organization does.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-org-logo"><?php esc_html_e( 'Logo URL', 'agentgarrison' ); ?></label>
					<input type="url" id="ag-org-logo" class="agentgarrison-input" name="org_logo"
						value="<?php echo esc_attr( $rayetun_ag_schema_settings['org_logo'] ); ?>"
						placeholder="https://example.com/logo.png">
					<p class="agentgarrison-hint"><?php esc_html_e( 'Full URL to your organization logo. Recommended for Google rich results.', 'agentgarrison' ); ?></p>
				</div>
			</div>

			<!-- Trust & Editorial Signals -->
			<div class="agentgarrison-card">
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Trust & Editorial Signals', 'agentgarrison' ); ?></h2>
				<p class="agentgarrison-card__desc" style="margin-bottom:18px;"><?php esc_html_e( 'E-E-A-T signals AI engines and Google use to judge whether your content is trustworthy enough to cite. All optional — empty fields are simply not output.', 'agentgarrison' ); ?></p>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-pub-principles"><?php esc_html_e( 'Editorial Guidelines URL', 'agentgarrison' ); ?></label>
					<input type="url" id="ag-pub-principles" class="agentgarrison-input" name="publishing_principles"
						value="<?php echo esc_attr( $rayetun_ag_schema_settings['publishing_principles'] ); ?>"
						placeholder="https://example.com/editorial-guidelines">
					<p class="agentgarrison-hint"><?php esc_html_e( 'Your editorial standards page. Output as schema.org publishingPrinciples.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-ethics"><?php esc_html_e( 'Ethics Policy URL', 'agentgarrison' ); ?></label>
					<input type="url" id="ag-ethics" class="agentgarrison-input" name="ethics_policy"
						value="<?php echo esc_attr( $rayetun_ag_schema_settings['ethics_policy'] ); ?>"
						placeholder="https://example.com/ethics-policy">
					<p class="agentgarrison-hint"><?php esc_html_e( 'Output as ethicsPolicy.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-corrections"><?php esc_html_e( 'Corrections Policy URL', 'agentgarrison' ); ?></label>
					<input type="url" id="ag-corrections" class="agentgarrison-input" name="corrections_policy"
						value="<?php echo esc_attr( $rayetun_ag_schema_settings['corrections_policy'] ); ?>"
						placeholder="https://example.com/corrections-policy">
					<p class="agentgarrison-hint"><?php esc_html_e( 'Output as correctionsPolicy.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-ownership"><?php esc_html_e( 'Ownership & Funding', 'agentgarrison' ); ?></label>
					<textarea id="ag-ownership" class="agentgarrison-textarea" rows="2" name="ownership_funding"
						placeholder="<?php esc_attr_e( 'Who owns and funds this site.', 'agentgarrison' ); ?>"><?php echo esc_textarea( $rayetun_ag_schema_settings['ownership_funding'] ); ?></textarea>
					<p class="agentgarrison-hint"><?php esc_html_e( 'Output as ownershipFundingInfo.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-founding"><?php esc_html_e( 'Founding Date', 'agentgarrison' ); ?></label>
					<input type="date" id="ag-founding" class="agentgarrison-input agentgarrison-input--sm" name="founding_date"
						value="<?php echo esc_attr( $rayetun_ag_schema_settings['founding_date'] ); ?>">
					<p class="agentgarrison-hint"><?php esc_html_e( 'Output as foundingDate.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-sameas"><?php esc_html_e( 'Official Profile URLs (sameAs)', 'agentgarrison' ); ?></label>
					<textarea id="ag-sameas" class="agentgarrison-textarea" rows="3" name="same_as"
						placeholder="https://twitter.com/yourbrand&#10;https://www.linkedin.com/company/yourbrand"><?php echo esc_textarea( $rayetun_ag_schema_settings['same_as'] ); ?></textarea>
					<p class="agentgarrison-hint"><?php esc_html_e( 'One URL per line. Output as sameAs — links your brand to its official profiles for entity disambiguation.', 'agentgarrison' ); ?></p>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-default-reviewer"><?php esc_html_e( 'Default Content Reviewer', 'agentgarrison' ); ?></label>
					<input type="text" id="ag-default-reviewer" class="agentgarrison-input" name="default_reviewer"
						value="<?php echo esc_attr( $rayetun_ag_schema_settings['default_reviewer'] ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Editorial Board', 'agentgarrison' ); ?>">
					<p class="agentgarrison-hint"><?php esc_html_e( 'Site-wide fallback reviewer added as reviewedBy on every page. Override per post in the editor sidebar.', 'agentgarrison' ); ?></p>
				</div>
			</div>

			<!-- Local Business -->
			<div class="agentgarrison-card">
				<div class="agentgarrison-card__header" style="margin-bottom:8px;">
					<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Local Business', 'agentgarrison' ); ?></h2>
					<label class="agentgarrison-switch">
						<input type="checkbox" id="ag-lb-enable" name="local_business" value="1"
							<?php checked( ! empty( $rayetun_ag_schema_settings['local_business'] ) ); ?>>
						<span class="agentgarrison-switch__slider"></span>
					</label>
				</div>
				<p class="agentgarrison-card__desc" style="margin-bottom:18px;"><?php esc_html_e( 'For sites with a physical location. Outputs a LocalBusiness node with address, hours, and geo.', 'agentgarrison' ); ?></p>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-lb-type"><?php esc_html_e( 'Business Type', 'agentgarrison' ); ?></label>
					<div class="agentgarrison-select-wrap agentgarrison-select-wrap--sm">
						<select id="ag-lb-type" class="agentgarrison-select" name="lb_type">
							<?php
							$rayetun_ag_lb_types = array( 'LocalBusiness', 'Store', 'Restaurant', 'ProfessionalService', 'MedicalBusiness', 'LegalService', 'FinancialService' );
							foreach ( $rayetun_ag_lb_types as $rayetun_ag_lbt ) :
								?>
								<option value="<?php echo esc_attr( $rayetun_ag_lbt ); ?>" <?php selected( $rayetun_ag_schema_settings['lb_type'], $rayetun_ag_lbt ); ?>><?php echo esc_html( $rayetun_ag_lbt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="agentgarrison-field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-phone"><?php esc_html_e( 'Phone', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-phone" class="agentgarrison-input" name="lb_phone" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_phone'] ); ?>">
					</div>
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-price"><?php esc_html_e( 'Price Range', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-price" class="agentgarrison-input" name="lb_price" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_price'] ); ?>" placeholder="$$">
					</div>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-lb-street"><?php esc_html_e( 'Street Address', 'agentgarrison' ); ?></label>
					<input type="text" id="ag-lb-street" class="agentgarrison-input" name="lb_street" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_street'] ); ?>">
				</div>

				<div class="agentgarrison-field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-city"><?php esc_html_e( 'City', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-city" class="agentgarrison-input" name="lb_city" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_city'] ); ?>">
					</div>
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-region"><?php esc_html_e( 'Region / State', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-region" class="agentgarrison-input" name="lb_region" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_region'] ); ?>">
					</div>
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-postal"><?php esc_html_e( 'Postal Code', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-postal" class="agentgarrison-input" name="lb_postal" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_postal'] ); ?>">
					</div>
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-country"><?php esc_html_e( 'Country', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-country" class="agentgarrison-input" name="lb_country" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_country'] ); ?>" placeholder="US">
					</div>
				</div>

				<div class="agentgarrison-field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-lat"><?php esc_html_e( 'Latitude', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-lat" class="agentgarrison-input" name="lb_lat" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_lat'] ); ?>">
					</div>
					<div class="agentgarrison-field">
						<label class="agentgarrison-label" for="ag-lb-lng"><?php esc_html_e( 'Longitude', 'agentgarrison' ); ?></label>
						<input type="text" id="ag-lb-lng" class="agentgarrison-input" name="lb_lng" value="<?php echo esc_attr( $rayetun_ag_schema_settings['lb_lng'] ); ?>">
					</div>
				</div>

				<div class="agentgarrison-field">
					<label class="agentgarrison-label" for="ag-lb-hours"><?php esc_html_e( 'Opening Hours', 'agentgarrison' ); ?></label>
					<textarea id="ag-lb-hours" class="agentgarrison-textarea" rows="2" name="lb_hours"
						placeholder="Mo-Fr 09:00-17:00&#10;Sa 10:00-14:00"><?php echo esc_textarea( $rayetun_ag_schema_settings['lb_hours'] ); ?></textarea>
					<p class="agentgarrison-hint"><?php esc_html_e( 'One rule per line in schema.org openingHours format (e.g. "Mo-Fr 09:00-17:00").', 'agentgarrison' ); ?></p>
				</div>
			</div>

		</div>

		<!-- Right column: live preview -->
		<div class="agentgarrison-schema-preview-col">
			<div class="agentgarrison-card agentgarrison-schema-preview-card">
				<div class="agentgarrison-card__header">
					<h2 class="agentgarrison-card__title js-schema-preview-title"><?php esc_html_e( 'Schema Preview', 'agentgarrison' ); ?></h2>
					<span class="agentgarrison-badge agentgarrison-badge--info">JSON-LD</span>
				</div>
				<p class="agentgarrison-hint" style="margin-bottom:12px;">
					<?php esc_html_e( '"Preview Organization" shows the site-wide brand schema. "Preview Full Page Schema" renders the complete @graph for your latest post — exactly what is injected into the page head.', 'agentgarrison' ); ?>
				</p>
				<pre class="agentgarrison-preview-pane js-schema-preview"><?php esc_html_e( 'Preview will appear here...', 'agentgarrison' ); ?></pre>
				<div class="agentgarrison-schema-preview-meta js-schema-preview-meta" style="display:none;">
					<span class="agentgarrison-preview-status agentgarrison-preview-status--ok">✓ <?php esc_html_e( 'Valid JSON-LD', 'agentgarrison' ); ?></span>
				</div>
			</div>
		</div>

	</div><!-- .agentgarrison-schema-layout -->

	<!-- Validator results -->
	<div class="agentgarrison-card agentgarrison-schema-validator js-schema-validator" style="display:none;">
		<div class="agentgarrison-card__header">
			<div>
				<h2 class="agentgarrison-card__title"><?php esc_html_e( 'Schema Validation', 'agentgarrison' ); ?> — <span class="js-validator-post-title"></span></h2>
				<p class="agentgarrison-card__desc js-validator-summary"></p>
			</div>
			<a href="#" target="_blank" rel="noopener" class="agentgarrison-btn agentgarrison-btn--secondary agentgarrison-btn--sm js-rich-results-link">
				<?php esc_html_e( 'Test in Google →', 'agentgarrison' ); ?>
			</a>
		</div>

		<div class="agentgarrison-validator-section">
			<h3 class="agentgarrison-validator-subtitle"><?php esc_html_e( 'Schema Types Emitted', 'agentgarrison' ); ?></h3>
			<div class="agentgarrison-type-chips js-validator-types"></div>
		</div>

		<div class="agentgarrison-validator-section js-validator-recommendations-wrap">
			<h3 class="agentgarrison-validator-subtitle"><?php esc_html_e( 'Recommended Improvements', 'agentgarrison' ); ?></h3>
			<div class="agentgarrison-validator-checks js-validator-recommendations"></div>
		</div>

		<div class="agentgarrison-validator-section js-validator-conflict-wrap" style="display:none;">
			<h3 class="agentgarrison-validator-subtitle"><?php esc_html_e( 'SEO Plugin Conflict Report', 'agentgarrison' ); ?></h3>
			<div class="agentgarrison-vwarning js-validator-conflict"></div>
		</div>
	</div>
</div>
