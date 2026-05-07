<?php
/**
 * Integration test pinning the text-domain loader to `init` priority 0.
 *
 * WP 6.7+ logs a `_doing_it_wrong` notice when any `__()` against a
 * text domain triggers JIT loading before `init` has fired. Hooking
 * `gtmkit_load_text_domain` at the lowest priority on `init` is the
 * regression guard — any `__()` call from a callback hooked at init
 * priority 1+ finds the text domain already loaded and does not
 * trigger JIT.
 *
 * Target: `add_action( 'init', 'TLA_Media\GTM_Kit\gtmkit_load_text_domain', 0 )`
 * in `inc/main.php`.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Bootstrap;

use WP_UnitTestCase;

/**
 * Pins the text-domain loader's hook priority.
 */
final class TextDomainHookTest extends WP_UnitTestCase {

	/**
	 * `gtmkit_load_text_domain` must be hooked at `init` priority 0.
	 */
	public function test_text_domain_loader_runs_at_init_priority_zero(): void {
		$priority = has_action( 'init', 'TLA_Media\GTM_Kit\gtmkit_load_text_domain' );

		$this->assertSame(
			0,
			$priority,
			'gtmkit_load_text_domain must be hooked at init priority 0 so the text domain is registered before any other init callback can call __() against it.'
		);
	}
}
