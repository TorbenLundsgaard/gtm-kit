<?php
/**
 * Integration tests for the script gating mode setting and the
 * strong-block emission path.
 *
 * Covers the three modes — `always_load`, `weak_block`, `strong_block` —
 * plus the interaction with the `gtmkit_header_script_attributes`
 * filter (CMP attributes preserved on the masked script) and the
 * `gtmkit_strong_block_required_categories` filter (localized into the
 * shim's runtime config).
 *
 * Targets:
 *
 *  - {@see \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script}
 *  - {@see \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use TLA_Media\GTM_Kit\Options\OptionSchema;
use WP_UnitTestCase;

/**
 * Covers the script gating mode emission and admin-side wiring.
 */
final class ScriptGatingTest extends WP_UnitTestCase {

	/**
	 * Reset script registry, cached settings, and consent-related filter
	 * chains so each test sees a clean slate.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );
		wp_scripts()->remove( 'gtmkit-container' );
		wp_scripts()->remove( 'gtmkit-consent-gating' );
		remove_all_filters( 'gtmkit_header_script_attributes' );
		remove_all_filters( 'gtmkit_strong_block_required_categories' );
		remove_all_filters( 'gtmkit_consent_signal_sources' );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-TEST123' );
	}

	/**
	 * `always_load` mode renders no `type="text/plain"`, no
	 * `data-gtmkit-gated`, and does not enqueue the gating shim.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_always_load_mode_emits_no_masking(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_ALWAYS_LOAD );

		( new Frontend( $options ) )->enqueue_header_script();

		$this->assertTrue( wp_script_is( 'gtmkit-container', 'enqueued' ) );
		$this->assertFalse(
			wp_script_is( 'gtmkit-consent-gating', 'enqueued' ),
			'Shim must not be enqueued in always_load mode.'
		);

		$attrs = ( new Frontend( $options ) )->set_inline_script_attributes(
			[ 'id' => 'gtmkit-container-js-before' ],
			''
		);
		$this->assertArrayNotHasKey( 'type', $attrs );
		$this->assertArrayNotHasKey( 'data-gtmkit-gated', $attrs );
	}

	/**
	 * `weak_block` mode is identical to `always_load` at the script-tag
	 * level. The "blocking" lives in Consent Mode v2 + future event
	 * deferral, not in the rendered `<script>` element.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 */
	public function test_weak_block_mode_emits_no_masking(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_WEAK_BLOCK );

		( new Frontend( $options ) )->enqueue_header_script();

		$this->assertTrue( wp_script_is( 'gtmkit-container', 'enqueued' ) );
		$this->assertFalse(
			wp_script_is( 'gtmkit-consent-gating', 'enqueued' ),
			'Shim must not be enqueued in weak_block mode.'
		);

		$attrs = ( new Frontend( $options ) )->set_inline_script_attributes(
			[ 'id' => 'gtmkit-container-js-before' ],
			''
		);
		$this->assertArrayNotHasKey( 'type', $attrs );
		$this->assertArrayNotHasKey( 'data-gtmkit-gated', $attrs );
	}

	/**
	 * `strong_block` mode masks the GTM script with `type="text/plain"`
	 * and `data-gtmkit-gated="1"`, and enqueues the gating shim.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_strong_block_mode_masks_script_and_enqueues_shim(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_STRONG_BLOCK );

		$frontend = new Frontend( $options );
		$frontend->enqueue_header_script();

		$this->assertTrue(
			wp_script_is( 'gtmkit-consent-gating', 'enqueued' ),
			'Shim must be enqueued in strong_block mode.'
		);

		$attrs = $frontend->set_inline_script_attributes(
			[ 'id' => 'gtmkit-container-js-before' ],
			''
		);
		$this->assertSame( 'text/plain', $attrs['type'] );
		$this->assertSame( '1', $attrs['data-gtmkit-gated'] );
	}

	/**
	 * The strong-block shim depends on `gtmkit-container`, so the
	 * masked script element is in the DOM by the time the shim runs.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 */
	public function test_strong_block_shim_depends_on_container(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_STRONG_BLOCK );

		( new Frontend( $options ) )->enqueue_header_script();

		$shim = wp_scripts()->query( 'gtmkit-consent-gating' );
		$this->assertNotFalse( $shim, 'Shim must be registered.' );
		$this->assertContains( 'gtmkit-container', (array) $shim->deps );
	}

	/**
	 * CMP attributes from `gtmkit_header_script_attributes` survive on
	 * the masked script. They are inert while `type="text/plain"` is in
	 * place but let CMPs that recognise them also unblock the script.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_cmp_attributes_preserved_on_masked_script(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_STRONG_BLOCK );

		add_filter(
			'gtmkit_header_script_attributes',
			static function ( array $attrs ): array {
				$attrs['data-cookieconsent'] = 'ignore';
				$attrs['data-cookieyes']     = 'cookieyes-analytics';
				return $attrs;
			}
		);

		$attrs = ( new Frontend( $options ) )->set_inline_script_attributes(
			[ 'id' => 'gtmkit-container-js-before' ],
			''
		);

		$this->assertSame( 'text/plain', $attrs['type'] );
		$this->assertSame( '1', $attrs['data-gtmkit-gated'] );
		$this->assertSame( 'ignore', $attrs['data-cookieconsent'] );
		$this->assertSame( 'cookieyes-analytics', $attrs['data-cookieyes'] );
	}

	/**
	 * `gtmkit_strong_block_required_categories` is read and rendered into
	 * the shim's runtime config via `wp_localize_script`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 */
	public function test_required_categories_filter_localized_to_shim(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_STRONG_BLOCK );

		add_filter(
			'gtmkit_strong_block_required_categories',
			static fn(): array => [ 'analytics_storage' ]
		);

		( new Frontend( $options ) )->enqueue_header_script();

		$localized = wp_scripts()->get_data( 'gtmkit-consent-gating', 'data' );
		$this->assertIsString( $localized );
		$this->assertStringContainsString( 'gtmkitConsentGating', $localized );
		$this->assertStringContainsString( 'analytics_storage', $localized );
		$this->assertStringNotContainsString( 'ad_storage', $localized );
	}

	/**
	 * Default required-category list is `['analytics_storage', 'ad_storage']`
	 * when no filter overrides it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 */
	public function test_default_required_categories_localized(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_STRONG_BLOCK );

		( new Frontend( $options ) )->enqueue_header_script();

		$localized = wp_scripts()->get_data( 'gtmkit-consent-gating', 'data' );
		$this->assertIsString( $localized );
		$this->assertStringContainsString( 'analytics_storage', $localized );
		$this->assertStringContainsString( 'ad_storage', $localized );
	}

	/**
	 * The localized config must include the GTM container id so the shim
	 * can scope its "GTM already booted" check to *our* container, not
	 * the generic global. Without this, unrelated scripts (gtag.js for an
	 * Ads pixel, debug inspectors) that touch `window.google_tag_manager`
	 * would falsely short-circuit the unmask path.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 */
	public function test_container_id_localized_to_shim(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_STRONG_BLOCK );
		$options->set_option( 'general', 'gtm_id', 'GTM-ABC1234' );

		( new Frontend( $options ) )->enqueue_header_script();

		$localized = wp_scripts()->get_data( 'gtmkit-consent-gating', 'data' );
		$this->assertIsString( $localized );
		$this->assertStringContainsString( 'containerId', $localized );
		$this->assertStringContainsString( 'GTM-ABC1234', $localized );
	}

	/**
	 * Inline scripts unrelated to gtmkit-container are not masked, even
	 * in strong-block mode.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_only_container_script_is_masked(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', OptionSchema::GATING_MODE_STRONG_BLOCK );

		$frontend        = new Frontend( $options );
		$datalayer_attrs = $frontend->set_inline_script_attributes(
			[ 'id' => 'gtmkit-datalayer-js-before' ],
			''
		);

		$this->assertArrayNotHasKey( 'type', $datalayer_attrs );
		$this->assertArrayNotHasKey( 'data-gtmkit-gated', $datalayer_attrs );
	}

	/**
	 * `window.gtmkit.consent.state` is exposed in the inline settings
	 * script when the master toggle is on, so the shim can read the
	 * current consent state on initial load.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_consent_state_surface_emitted_when_toggle_on(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script( 'gtmkit' );
		$this->assertStringContainsString( 'window.gtmkit.consent', $inline );
		$this->assertStringContainsString( 'state:', $inline );
		$this->assertStringContainsString( "'analytics_storage'", $inline );
		$this->assertStringContainsString( "'ad_storage'", $inline );
	}

	/**
	 * Pull the `before`-position inline script for a given handle.
	 *
	 * @param string $handle The script handle.
	 *
	 * @return string
	 */
	private function extract_inline_script( string $handle ): string {
		$data = wp_scripts()->get_data( $handle, 'before' );
		if ( is_array( $data ) ) {
			return implode( '', $data );
		}
		return (string) $data;
	}
}
