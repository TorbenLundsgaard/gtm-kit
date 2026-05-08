<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common\Conditionals;

/**
 * Conditional that is only met when the Bricks theme is active.
 */
class BricksConditional implements Conditional {

	/**
	 * Returns `true` when the Bricks theme is installed and activated.
	 *
	 * @return bool `true` when the Bricks theme is installed and activated.
	 */
	public function is_met(): bool {
		// Match by directory name only. `get( 'Name' )` and `get( 'Template' )`
		// pass through `translate_with_gettext_context()` against the active
		// theme's text domain, which JIT-loads it; on requests where this runs
		// before `init`, WP 6.7+ logs a `_doing_it_wrong` notice. `get_stylesheet()`
		// (the active theme's directory) and `get_template()` (parent template
		// directory, falling back to stylesheet) read the cached directory names
		// directly and never trigger translation loading.
		$theme = \wp_get_theme();
		return ( $theme->get_stylesheet() === 'bricks' || $theme->get_template() === 'bricks' );
	}
}
