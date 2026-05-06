<?php
/**
 * Integration tests for the consent management platform plugin
 * detection helper.
 *
 * Mocks the WP active-plugins option so tests do not depend on real
 * CMP plugin packages being present in the test environment. Each
 * test asserts the helper resolves the expected slug for a given
 * active-plugins list, including the multi-active edge case where the
 * iteration order yields a deterministic first match.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\CMPDetection}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Common\CMPDetection;
use WP_UnitTestCase;

/**
 * Covers plugin-list-driven CMP detection.
 */
final class CMPDetectionTest extends WP_UnitTestCase {

	/**
	 * Original active_plugins value, restored in tear_down.
	 *
	 * @var array<int, string>|null
	 */
	private $original_active_plugins;

	/**
	 * Stash the real active-plugins list and start each test from a
	 * known-empty state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_active_plugins = get_option( 'active_plugins', [] );
		update_option( 'active_plugins', [] );
	}

	/**
	 * Restore the active-plugins list so other tests are not affected.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		update_option( 'active_plugins', $this->original_active_plugins ?? [] );
		parent::tear_down();
	}

	/**
	 * Returns null when no known CMP plugin is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_null_when_no_known_cmp_is_active(): void {
		update_option( 'active_plugins', [ 'some-other-plugin/some-other-plugin.php' ] );

		$this->assertNull( CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'cookiebot' when the canonical Cookiebot plugin slug is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_cookiebot_for_canonical_slug(): void {
		update_option( 'active_plugins', [ 'cookiebot/cookiebot.php' ] );

		$this->assertSame( 'cookiebot', CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'cookiebot' for the alternate `cookiebot-by-cybot` slug.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_cookiebot_for_alternate_slug(): void {
		update_option(
			'active_plugins',
			[ 'cookiebot-by-cybot/cookiebot.php' ]
		);

		$this->assertSame( 'cookiebot', CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'iubenda' when the Iubenda plugin slug is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_iubenda_when_plugin_active(): void {
		update_option(
			'active_plugins',
			[ 'iubenda-cookie-law-solution/iubenda_cookie_solution.php' ]
		);

		$this->assertSame( 'iubenda', CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'cookieyes' when the CookieYes plugin slug is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_cookieyes_when_plugin_active(): void {
		update_option(
			'active_plugins',
			[ 'cookie-law-info/cookie-law-info.php' ]
		);

		$this->assertSame( 'cookieyes', CMPDetection::detect_active_cmp() );
	}

	/**
	 * When more than one CMP plugin is active simultaneously (rare and
	 * misconfigured), the helper returns the first match in canonical
	 * order so callers get deterministic behavior.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_first_match_when_multiple_cmps_active(): void {
		update_option(
			'active_plugins',
			[
				'cookie-law-info/cookie-law-info.php',
				'cookiebot/cookiebot.php',
				'iubenda-cookie-law-solution/iubenda_cookie_solution.php',
			]
		);

		$this->assertSame( 'cookiebot', CMPDetection::detect_active_cmp() );
	}
}
