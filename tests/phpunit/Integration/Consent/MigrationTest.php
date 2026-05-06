<?php
/**
 * Integration tests for the cmp_script_attributes migration logic.
 *
 * Two paths converge on the same setting:
 *   1. Fresh installs run through Activation::set_first_install_options,
 *      which loads OptionSchema defaults and overrides the matching CMP
 *      toggle when CMPDetection finds an active plugin.
 *   2. Upgrading installs run through Upgrade::v210_upgrade, which
 *      seeds Cookiebot=true to preserve the previously hardcoded
 *      `data-cookieconsent="ignore"` behavior.
 *
 * Once the option is stored, neither routine should touch it again
 * (admin saves are the only authoritative source).
 *
 * Targets:
 *   - {@see \TLA_Media\GTM_Kit\Installation\Activation::set_first_install_options}
 *   - {@see \TLA_Media\GTM_Kit\Installation\Upgrade}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Installation\Activation;
use TLA_Media\GTM_Kit\Installation\Upgrade;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers the cmp_script_attributes migration on fresh installs and
 * upgrades.
 */
final class MigrationTest extends WP_UnitTestCase {

	/**
	 * Active-plugins value to restore in tear_down.
	 *
	 * @var array<int, string>|null
	 */
	private $original_active_plugins;

	/**
	 * Original gtmkit_version value to restore in tear_down.
	 *
	 * @var string|false
	 */
	private $original_version;

	/**
	 * Reset all state that the migration paths read from.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_active_plugins = get_option( 'active_plugins', [] );
		$this->original_version        = get_option( 'gtmkit_version' );

		// Wipe the entire gtmkit option and reset the OptionsFactory
		// singleton so each test starts from a true blank slate. The
		// singleton caches its in-memory copy at construction; without
		// resetting it, stale state from a prior test would mask the
		// migration paths under test.
		delete_option( 'gtmkit' );
		delete_option( 'gtmkit_version' );
		update_option( 'active_plugins', [] );
		OptionsFactory::reset();
	}

	/**
	 * Restore state captured in set_up.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		update_option( 'active_plugins', $this->original_active_plugins ?? [] );
		if ( false === $this->original_version ) {
			delete_option( 'gtmkit_version' );
		} else {
			update_option( 'gtmkit_version', $this->original_version );
		}
		OptionsFactory::reset();
		parent::tear_down();
	}

	/**
	 * Fresh install (no gtmkit_version recorded), Cookiebot active:
	 * Cookiebot toggle defaults true.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Activation::set_first_install_options
	 */
	public function test_fresh_install_with_cookiebot_active_pre_selects_cookiebot(): void {
		delete_option( 'gtmkit_version' );
		update_option( 'active_plugins', [ 'cookiebot/cookiebot.php' ] );

		$options = OptionsFactory::get_instance();
		( new Activation( $options ) )->set_first_install_options();

		$cmp = OptionsFactory::get_instance()->get( 'general', 'cmp_script_attributes' );
		$this->assertTrue( $cmp['cookiebot'] );
		$this->assertFalse( $cmp['iubenda'] );
		$this->assertFalse( $cmp['cookieyes'] );
	}

	/**
	 * Fresh install with Iubenda active: Iubenda toggle defaults true.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Activation::set_first_install_options
	 */
	public function test_fresh_install_with_iubenda_active_pre_selects_iubenda(): void {
		delete_option( 'gtmkit_version' );
		update_option(
			'active_plugins',
			[ 'iubenda-cookie-law-solution/iubenda_cookie_solution.php' ]
		);

		$options = OptionsFactory::get_instance();
		( new Activation( $options ) )->set_first_install_options();

		$cmp = OptionsFactory::get_instance()->get( 'general', 'cmp_script_attributes' );
		$this->assertFalse( $cmp['cookiebot'] );
		$this->assertTrue( $cmp['iubenda'] );
		$this->assertFalse( $cmp['cookieyes'] );
	}

	/**
	 * Fresh install with no detected CMP: all toggles default false.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Activation::set_first_install_options
	 */
	public function test_fresh_install_with_no_cmp_defaults_all_off(): void {
		delete_option( 'gtmkit_version' );
		update_option( 'active_plugins', [] );

		$options = OptionsFactory::get_instance();
		( new Activation( $options ) )->set_first_install_options();

		$cmp = OptionsFactory::get_instance()->get( 'general', 'cmp_script_attributes' );
		$this->assertFalse( $cmp['cookiebot'] );
		$this->assertFalse( $cmp['iubenda'] );
		$this->assertFalse( $cmp['cookieyes'] );
		$this->assertSame( '', $cmp['custom']['name'] );
		$this->assertSame( '', $cmp['custom']['value'] );
	}

	/**
	 * Upgrading install (prior version recorded, no
	 * cmp_script_attributes stored): Cookiebot toggle defaults true to
	 * preserve the previously hardcoded behavior, regardless of which
	 * CMP plugin is active. Even if the upgrader runs Iubenda, removing
	 * the Cookiebot attribute silently could break tooling that depends
	 * on it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade
	 */
	public function test_upgrading_install_preserves_cookiebot_attribute(): void {
		// Simulate an install on an older version that has never seen
		// the new option.
		update_option( 'gtmkit_version', '2.9.0' );
		update_option(
			'active_plugins',
			[ 'iubenda-cookie-law-solution/iubenda_cookie_solution.php' ]
		);

		$options = OptionsFactory::get_instance();
		new Upgrade( $options );

		$cmp = OptionsFactory::get_instance()->get( 'general', 'cmp_script_attributes' );
		$this->assertTrue( $cmp['cookiebot'], 'Cookiebot must stay on for upgraders to preserve current behavior.' );
		$this->assertFalse( $cmp['iubenda'] );
		$this->assertFalse( $cmp['cookieyes'] );
	}

	/**
	 * Pinned snapshot of the upgrader's default cmp_script_attributes
	 * shape, asserted byte-for-byte. If a future change accidentally
	 * shifts the upgrader migration (e.g. adds another toggle as on by
	 * default, or drops Cookiebot), this fails loudly instead of
	 * silently changing what existing sites emit.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade
	 */
	public function test_upgrading_install_seed_matches_pinned_shape(): void {
		update_option( 'gtmkit_version', '2.9.0' );
		update_option( 'active_plugins', [] );

		$options = OptionsFactory::get_instance();
		new Upgrade( $options );

		$cmp = OptionsFactory::get_instance()->get( 'general', 'cmp_script_attributes' );
		$this->assertSame(
			[
				'cookiebot' => true,
				'iubenda'   => false,
				'cookieyes' => false,
				'custom'    => [
					'name'  => '',
					'value' => '',
				],
			],
			$cmp
		);
	}

	/**
	 * Already-stored cmp_script_attributes value is not overwritten on
	 * subsequent upgrade runs.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade
	 */
	public function test_upgrade_is_no_op_when_setting_already_stored(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option(
			'general',
			'cmp_script_attributes',
			[
				'cookiebot' => false,
				'iubenda'   => true,
				'cookieyes' => false,
				'custom'    => [
					'name'  => 'data-foo',
					'value' => 'bar',
				],
			]
		);
		update_option( 'gtmkit_version', '2.9.0' );

		new Upgrade( $options );

		$cmp = OptionsFactory::get_instance()->get( 'general', 'cmp_script_attributes' );
		$this->assertFalse( $cmp['cookiebot'], 'Stored value must survive Upgrade.' );
		$this->assertTrue( $cmp['iubenda'] );
		$this->assertSame( 'data-foo', $cmp['custom']['name'] );
	}
}
