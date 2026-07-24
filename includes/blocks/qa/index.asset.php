<?php
/**
 * Dependency + version manifest for the no-build editor script (index.js).
 *
 * WordPress reads this automatically when registering the block's editorScript
 * from block.json, so wp-blocks / wp-block-editor / wp-element / wp-i18n load
 * before our script runs. Hand-authored because this block has no build step.
 *
 * @package AgentGarrison
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n' ),
	'version'      => '1.0.0',
);
