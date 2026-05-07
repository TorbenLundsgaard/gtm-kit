<?php
/**
 * Integration tests for the `gtmkit-datalayer` dependency-selection logic.
 *
 * Pattern: configure Options + filters, run the enqueue methods in the
 * same order they fire under `wp_enqueue_scripts` (header script at
 * priority 6, datalayer at priority 10), then read the registered
 * dependency array from `wp_scripts()`.
 *
 * Regression target: WP 6.9.1+ logs a `WP_Scripts::add` notice when a
 * script is enqueued with a dependency that was never registered. The
 * old code re-evaluated the `gtmkit_container_active` filter inside
 * `Frontend::will_register_container()` at priority 10, which could
 * disagree with the earlier evaluation made at `register()` time and
 * declare a dependency on `gtmkit-container` even when the header-script
 * callback was never attached. The fix asks the script registry directly
 * whether `gtmkit-container` was registered.
 *
 * Targets:
 *
 *  - {@see \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content}
 *  - {@see \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_delay_js_script}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers the script-dependency selection for the dataLayer and delay-js handles.
 */
final class DataLayerDependencyTest extends WP_UnitTestCase {

	/**
	 * Reset the script registry and any filter callbacks so each test sees a clean slate.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );
		wp_scripts()->remove( 'gtmkit-container' );
		wp_scripts()->remove( 'gtmkit-datalayer' );
		wp_scripts()->remove( 'gtmkit-delay' );
		remove_all_filters( 'gtmkit_container_active' );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-TEST123' );
		$options->set_option( 'general', 'container_active', 1 );
	}

	/**
	 * When the container script is registered, the datalayer depends on it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content
	 */
	public function test_datalayer_depends_on_container_when_container_registered(): void {
		$options  = OptionsFactory::get_instance();
		$frontend = new Frontend( $options );

		$frontend->enqueue_header_script();
		$frontend->enqueue_datalayer_content();

		$this->assertTrue( wp_script_is( 'gtmkit-container', 'registered' ) );
		$this->assertSame(
			[ 'gtmkit-container' ],
			wp_scripts()->registered['gtmkit-datalayer']->deps,
			'When gtmkit-container is registered, gtmkit-datalayer must depend on it.'
		);
	}

	/**
	 * When the container script is not registered, the datalayer falls back to `gtmkit`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content
	 */
	public function test_datalayer_falls_back_when_container_not_registered(): void {
		$options  = OptionsFactory::get_instance();
		$frontend = new Frontend( $options );

		// `enqueue_settings_and_data_script` registers `gtmkit` (priority 5),
		// then we skip `enqueue_header_script` so `gtmkit-container` is not
		// registered, then `enqueue_datalayer_content` runs (priority 10).
		$frontend->enqueue_settings_and_data_script();
		$frontend->enqueue_datalayer_content();

		$this->assertFalse( wp_script_is( 'gtmkit-container', 'registered' ) );
		$this->assertSame(
			[ 'gtmkit' ],
			wp_scripts()->registered['gtmkit-datalayer']->deps,
			'When gtmkit-container is not registered, gtmkit-datalayer must fall back to depending on gtmkit.'
		);
	}

	/**
	 * Regression for the filter-race that caused the WP 6.9.1 dep warning: if
	 * `gtmkit_container_active` was `false` at `register()` time (so the
	 * priority-6 callback was never attached and `gtmkit-container` was never
	 * registered) but flips to `true` by the time the datalayer dep predicate
	 * is evaluated at priority 10, the dependency must still fall back to
	 * `gtmkit` rather than naming the unregistered `gtmkit-container` handle.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content
	 */
	public function test_datalayer_falls_back_when_filter_flips_after_register(): void {
		$options  = OptionsFactory::get_instance();
		$frontend = new Frontend( $options );

		// Simulate: at `register()` time, no `gtmkit_container_active`
		// callback was attached and the priority-6 header-script callback
		// never ran. A late callback then flips the predicate to true.
		add_filter( 'gtmkit_container_active', '__return_true' );

		$frontend->enqueue_settings_and_data_script();
		$frontend->enqueue_datalayer_content();

		$this->assertFalse( wp_script_is( 'gtmkit-container', 'registered' ) );
		$this->assertSame(
			[ 'gtmkit' ],
			wp_scripts()->registered['gtmkit-datalayer']->deps,
			'A late gtmkit_container_active=true filter must not produce a dependency on the unregistered gtmkit-container handle.'
		);
	}

	/**
	 * The same fall-back logic must also apply to the delay-js script.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_delay_js_script
	 */
	public function test_delay_script_falls_back_when_container_not_registered(): void {
		$options  = OptionsFactory::get_instance();
		$frontend = new Frontend( $options );

		add_filter( 'gtmkit_container_active', '__return_true' );

		$frontend->enqueue_settings_and_data_script();
		$frontend->enqueue_delay_js_script();

		$this->assertFalse( wp_script_is( 'gtmkit-container', 'registered' ) );
		$this->assertSame(
			[ 'gtmkit' ],
			wp_scripts()->registered['gtmkit-delay']->deps,
			'gtmkit-delay must fall back to depending on gtmkit when gtmkit-container is not registered.'
		);
	}
}
