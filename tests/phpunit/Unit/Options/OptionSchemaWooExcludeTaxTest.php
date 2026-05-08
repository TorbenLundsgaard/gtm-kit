<?php
/**
 * Unit test for the `woocommerce_exclude_tax` schema entry.
 *
 * Guards that the option is registered in the integrations schema so a
 * fresh install resolves to `false` rather than `null`.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Options;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Options\OptionKeys;
use TLA_Media\GTM_Kit\Options\OptionSchema;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Schema-registration test for `integrations.woocommerce_exclude_tax`.
 */
final class OptionSchemaWooExcludeTaxTest extends TestCase {

	/**
	 * Common setup.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		if ( ! defined( 'GTMKIT_PATH' ) ) {
			define( 'GTMKIT_PATH', '/fake/plugin/path/' );
		}
		if ( ! defined( 'GTMKIT_URL' ) ) {
			define( 'GTMKIT_URL', 'https://example.test/wp-content/plugins/gtm-kit/' );
		}

		Functions\stubs(
			[
				'add_filter'       => null,
				// Same `function_exists()` short-circuit as the resolver tests.
				'is_plugin_active' => false,
			]
		);
	}

	/**
	 * The schema declares the new option as a boolean defaulting to false.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::get_option_schema
	 */
	public function test_schema_registers_woocommerce_exclude_tax_default_false_boolean(): void {
		$schema = OptionSchema::get_option_schema( 'integrations', 'woocommerce_exclude_tax' );

		$this->assertIsArray( $schema );
		$this->assertSame( false, $schema['default'] );
		$this->assertSame( 'boolean', $schema['type'] );
	}

	/**
	 * `OptionKeys::INTEGRATIONS_WOOCOMMERCE_EXCLUDE_TAX` is registered.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionKeys::exists
	 * @covers \TLA_Media\GTM_Kit\Options\OptionKeys::parse
	 */
	public function test_option_key_constant_is_registered(): void {
		$this->assertTrue( OptionKeys::exists( OptionKeys::INTEGRATIONS_WOOCOMMERCE_EXCLUDE_TAX ) );
		$this->assertSame(
			[
				'group' => 'integrations',
				'key'   => 'woocommerce_exclude_tax',
			],
			OptionKeys::parse( OptionKeys::INTEGRATIONS_WOOCOMMERCE_EXCLUDE_TAX )
		);
	}

	/**
	 * Fresh install: `Options->get('integrations', 'woocommerce_exclude_tax')`
	 * returns the schema default, not `null`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\Options::get
	 */
	public function test_fresh_install_returns_false_not_null(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$options = Options::create();

		$this->assertFalse( $options->get( 'integrations', 'woocommerce_exclude_tax' ) );
	}

	/**
	 * Existing installs that already had `woocommerce_exclude_tax` set
	 * in the DB (via the legacy import path at PluginDataImport.php:125)
	 * must keep their stored value. Adding the schema entry must not
	 * silently overwrite a true value with the schema's `false` default.
	 *
	 * Acceptance criterion: "Existing installs with the option already
	 * in DB are unaffected."
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\Options::get
	 */
	public function test_existing_install_keeps_stored_true_value(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'integrations' => [
					'woocommerce_exclude_tax' => true,
				],
			]
		);

		$options = Options::create();

		$this->assertTrue( $options->get( 'integrations', 'woocommerce_exclude_tax' ) );
	}

	/**
	 * Same as above but for an explicit stored `false`. Order matters:
	 * the stored value must take precedence over the schema default
	 * regardless of whether they happen to coincide.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\Options::get
	 */
	public function test_existing_install_keeps_stored_false_value(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'integrations' => [
					'woocommerce_exclude_tax' => false,
				],
			]
		);

		$options = Options::create();

		$this->assertFalse( $options->get( 'integrations', 'woocommerce_exclude_tax' ) );
	}
}
