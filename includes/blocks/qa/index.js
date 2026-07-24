/**
 * AgentGarrison "AI Q&A" block — no-build implementation.
 *
 * Plain wp.element.createElement (no JSX, no bundler) so the source ships
 * readable and needs no build step. Saves native <details>/<summary> markup,
 * which the AgentGarrison Schema module auto-detects as FAQPage structured data.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';

	var el            = element.createElement;
	var RichText      = blockEditor.RichText;
	var useBlockProps = blockEditor.useBlockProps;
	var __            = i18n.__;

	blocks.registerBlockType( 'agentgarrison/qa', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps( { className: 'agentgarrison-qa agentgarrison-qa--editor' } );

			return el(
				'div',
				blockProps,
				el( RichText, {
					tagName: 'summary',
					className: 'agentgarrison-qa__question',
					value: attributes.question,
					allowedFormats: [],
					placeholder: __( 'Question…', 'agentgarrison' ),
					onChange: function ( value ) {
						props.setAttributes( { question: value } );
					}
				} ),
				el( RichText, {
					tagName: 'div',
					className: 'agentgarrison-qa__answer',
					value: attributes.answer,
					placeholder: __( 'Answer…', 'agentgarrison' ),
					onChange: function ( value ) {
						props.setAttributes( { answer: value } );
					}
				} )
			);
		},

		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save( { className: 'agentgarrison-qa' } );

			// <details><summary>Question</summary><div class="...__answer">Answer</div></details>
			// No whitespace between the tags so the Schema module's FAQ regex matches.
			return el(
				'details',
				blockProps,
				el( RichText.Content, { tagName: 'summary', value: attributes.question } ),
				el( RichText.Content, { tagName: 'div', className: 'agentgarrison-qa__answer', value: attributes.answer } )
			);
		}
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
