<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Installation;

use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;
use TLA_Media\GTM_Kit\Options;

/**
 * Upgrade
 */
final class Upgrade {

	/**
	 * Constructor
	 */
	public function __construct() {

		$upgrades = $this->get_upgrades();

		// Run any available upgrades.
		foreach ( $upgrades as $upgrade ) {
			$this->{$upgrade}();
		}

		\wp_cache_delete( 'gtmkit', 'options' );

		\update_option( 'gtmkit_version', GTMKIT_VERSION, false );
	}

	/**
	 * Get upgrades if applicable.
	 *
	 * @return array<string>
	 */
	protected function get_upgrades(): array {

		$available_upgrades = [
			'1.11' => 'v111_upgrade',
			'1.14' => 'v114_upgrade',
			'1.15' => 'v115_upgrade',
			'1.20' => 'v120_upgrade',
			'1.22' => 'v122_upgrade',
			'2.2'  => 'v22_upgrade',
			'2.4'  => 'v24_upgrade',
		];

		$current_version = \get_option( 'gtmkit_version' );
		$upgrades        = [];

		foreach ( $available_upgrades as $version => $upgrade ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				$upgrades[] = $upgrade;
			}
		}

		return $upgrades;
	}

	/**
	 * Upgrade routine for v1.11
	 */
	protected function v111_upgrade(): void {

		$script_implementation = Options::init()->get( 'general', 'script_implementation' );

		if ( $script_implementation === 2 ) {
			$values = [
				'general' => [
					'script_implementation' => 1,
				],
			];

			Options::init()->set( $values, false, false );
		}
	}

	/**
	 * Upgrade routine for v1.14
	 */
	protected function v114_upgrade(): void {
		global $wpdb;

		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'yes' WHERE option_name = 'gtmkit'" );

		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'gtmkit_version'" );

		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'gtmkit_activation_prevent_redirect'" );

		$values = [
			'integrations' => [
				'gui-upgrade' => '',
			],
		];

		$options = Options::init()->get_all_raw();

		if ( ! isset( $options['integrations']['cf7_load_js'] ) ) {
			$values['integrations']['cf7_load_js'] = 1;
		}
		if ( ! isset( $options['integrations']['woocommerce_shipping_info'] ) ) {
			$values['integrations']['woocommerce_shipping_info'] = 1;
		}
		if ( ! isset( $options['integrations']['woocommerce_payment_info'] ) ) {
			$values['integrations']['woocommerce_payment_info'] = 1;
		}
		if ( ! isset( $options['integrations']['woocommerce_variable_product_tracking'] ) ) {
			$values['integrations']['woocommerce_variable_product_tracking'] = 0;
		}

		Options::init()->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v1.15
	 */
	protected function v115_upgrade(): void {

		$values = [
			'integrations' => [
				'woocommerce_view_item_list_limit' => 0,
			],
		];

		Options::init()->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v1.20
	 */
	protected function v120_upgrade(): void {

		$values = [
			'premium' => [
				'addon_installed' => 0,
			],
		];

		Options::init()->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v1.22
	 */
	protected function v122_upgrade(): void {

		$values = [
			'premium' => [
				'addon_installed' => false,
			],
		];

		Options::init()->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v2.2
	 */
	protected function v22_upgrade(): void {
		$auto_update_plugins = (array) get_site_option( 'auto_update_plugins', [] );

		$automatic_updates = in_array( 'gtm-kit/gtm-kit.php', $auto_update_plugins, true );

		$values = [
			'misc' => [
				'auto_update' => $automatic_updates,
			],
		];

		Options::init()->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v2.4
	 */
	protected function v24_upgrade(): void {
		$values = [
			'general' => [
				'event_inspector' => false,
			],
		];

		Options::init()->set( $values, false, false );
	}
}
