<?php
/**
 * Integration tests for the per-CMP script-attribute toggles on the
 * Consent settings page.
 *
 * Drives the cmp_script_attributes option through every named CMP +
 * the custom slot + combinations, and asserts the resulting
 * `wp_inline_script_attributes` filter output emits the expected
 * key/value pairs. Also verifies that the
 * `gtmkit_header_script_attributes` filter still runs after the
 * setting-driven build, so third-party additions and overrides keep
 * working.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers cmp_script_attributes → rendered <script> attribute mapping.
 */
final class ScriptAttributesTest extends WP_UnitTestCase {

	private const CONTAINER_HANDLE_ID = 'gtmkit-container-js-after';

	/**
	 * Default option shape mirroring OptionSchema's default. Spelled out
	 * here so test cases can copy and tweak without relying on schema
	 * internals.
	 *
	 * @var array<string, mixed>
	 */
	private const EMPTY_CMP_VALUE = [
		'cookiebot' => false,
		'iubenda'   => false,
		'cookieyes' => false,
		'custom'    => [
			'name'  => '',
			'value' => '',
		],
	];

	/**
	 * Reset filter and option state so each test sees a clean slate.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		remove_all_filters( 'gtmkit_header_script_attributes' );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'consent_gating_mode', 'always_load' );
	}

	/**
	 * Helper: invoke set_inline_script_attributes for the GTM container
	 * inline-script handle.
	 *
	 * @param array<string, mixed> $cmp_value cmp_script_attributes value to write before invoking.
	 * @return array<string, mixed>
	 */
	private function rendered_attrs_for( array $cmp_value ): array {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'cmp_script_attributes', $cmp_value );

		return ( new Frontend( $options ) )->set_inline_script_attributes(
			[ 'id' => self::CONTAINER_HANDLE_ID ],
			''
		);
	}

	/**
	 * All toggles off + no custom: only the always-on cache-plugin
	 * compatibility attributes are emitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_all_off_emits_only_cache_compat_attrs(): void {
		$attrs = $this->rendered_attrs_for( self::EMPTY_CMP_VALUE );

		$this->assertSame( 'false', $attrs['data-cfasync'] );
		$this->assertSame( '', $attrs['data-nowprocket'] );
		$this->assertArrayNotHasKey( 'data-cookieconsent', $attrs );
		$this->assertArrayNotHasKey( 'data-cmp-ab', $attrs );
		$this->assertArrayNotHasKey( 'data-cookie-consent', $attrs );
	}

	/**
	 * Cookiebot toggle on: `data-cookieconsent="ignore"` is emitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_cookiebot_toggle_emits_cookieconsent_attr(): void {
		$value              = self::EMPTY_CMP_VALUE;
		$value['cookiebot'] = true;

		$attrs = $this->rendered_attrs_for( $value );

		$this->assertSame( 'ignore', $attrs['data-cookieconsent'] );
	}

	/**
	 * Iubenda toggle on: `data-cmp-ab="1"` is emitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_iubenda_toggle_emits_cmp_ab_attr(): void {
		$value            = self::EMPTY_CMP_VALUE;
		$value['iubenda'] = true;

		$attrs = $this->rendered_attrs_for( $value );

		$this->assertSame( '1', $attrs['data-cmp-ab'] );
	}

	/**
	 * CookieYes toggle on: `data-cookie-consent="ignore"` is emitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_cookieyes_toggle_emits_cookie_consent_attr(): void {
		$value              = self::EMPTY_CMP_VALUE;
		$value['cookieyes'] = true;

		$attrs = $this->rendered_attrs_for( $value );

		$this->assertSame( 'ignore', $attrs['data-cookie-consent'] );
	}

	/**
	 * Multiple CMP toggles can be on simultaneously; all matching
	 * attributes are rendered.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_multiple_toggles_emit_all_attrs(): void {
		$value              = self::EMPTY_CMP_VALUE;
		$value['cookiebot'] = true;
		$value['iubenda']   = true;
		$value['cookieyes'] = true;

		$attrs = $this->rendered_attrs_for( $value );

		$this->assertSame( 'ignore', $attrs['data-cookieconsent'] );
		$this->assertSame( '1', $attrs['data-cmp-ab'] );
		$this->assertSame( 'ignore', $attrs['data-cookie-consent'] );
	}

	/**
	 * Custom name with a valid character set is rendered with its
	 * paired value.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_valid_custom_attribute_emits_pair(): void {
		$value           = self::EMPTY_CMP_VALUE;
		$value['custom'] = [
			'name'  => 'data-foo',
			'value' => 'bar',
		];

		$attrs = $this->rendered_attrs_for( $value );

		$this->assertSame( 'bar', $attrs['data-foo'] );
	}

	/**
	 * A custom name that is entirely disallowed characters strips to an
	 * empty string and the attribute is dropped. Defence in depth at
	 * the render site catches values that bypassed the save-time
	 * sanitiser (legacy values, filter-injected values, hand-edited DB
	 * rows).
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_fully_invalid_custom_name_is_dropped_at_render(): void {
		$value           = self::EMPTY_CMP_VALUE;
		$value['custom'] = [
			'name'  => '<>!@#',
			'value' => 'bar',
		];

		$attrs = $this->rendered_attrs_for( $value );

		// No attribute keyed under any sanitised remainder; only the
		// always-on cache-compat pair survives.
		$this->assertArrayNotHasKey( '<>!@#', $attrs );
		$this->assertArrayNotHasKey( '', $attrs );
		$this->assertArrayNotHasKey( 'bar', $attrs );
		$this->assertSame( 'false', $attrs['data-cfasync'] );
		$this->assertSame( '', $attrs['data-nowprocket'] );
	}

	/**
	 * Custom name that contains some invalid characters keeps the valid
	 * run; e.g. `data foo!` becomes `datafoo`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_partial_custom_name_strips_invalid_characters(): void {
		$value           = self::EMPTY_CMP_VALUE;
		$value['custom'] = [
			'name'  => 'data foo!',
			'value' => 'bar',
		];

		$attrs = $this->rendered_attrs_for( $value );

		$this->assertSame( 'bar', $attrs['datafoo'] );
	}

	/**
	 * The `gtmkit_header_script_attributes` filter still fires after the
	 * setting-driven build. Filter additions appear in the output.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_filter_additions_appear_in_output(): void {
		add_filter(
			'gtmkit_header_script_attributes',
			static function ( array $attrs ): array {
				$attrs['data-third-party'] = 'set-by-filter';
				return $attrs;
			}
		);

		$attrs = $this->rendered_attrs_for( self::EMPTY_CMP_VALUE );

		$this->assertSame( 'set-by-filter', $attrs['data-third-party'] );
	}

	/**
	 * Filter values override setting-driven defaults. A user-land
	 * filter that sets `data-cookieconsent="block"` wins over the
	 * Cookiebot toggle's default `"ignore"`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_filter_overrides_setting_driven_attribute(): void {
		add_filter(
			'gtmkit_header_script_attributes',
			static function ( array $attrs ): array {
				$attrs['data-cookieconsent'] = 'block';
				return $attrs;
			}
		);

		$value              = self::EMPTY_CMP_VALUE;
		$value['cookiebot'] = true;

		$attrs = $this->rendered_attrs_for( $value );

		$this->assertSame( 'block', $attrs['data-cookieconsent'] );
	}

	/**
	 * The gtmkit-delay handle is still skipped, so the cache-compat
	 * attributes do not leak onto the delay-event script.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::set_inline_script_attributes
	 */
	public function test_gtmkit_delay_handle_is_skipped(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'cmp_script_attributes', self::EMPTY_CMP_VALUE );

		$attrs = ( new Frontend( $options ) )->set_inline_script_attributes(
			[ 'id' => 'gtmkit-delay-js-before' ],
			''
		);

		$this->assertArrayNotHasKey( 'data-cfasync', $attrs );
		$this->assertArrayNotHasKey( 'data-nowprocket', $attrs );
	}
}
