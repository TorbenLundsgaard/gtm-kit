<?php
/**
 * Minimal WC_Product stub for unit tests.
 *
 * @package TLA_Media\GTM_Kit
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Stand-in for WooCommerce's WC_Product class for type-hint compatibility.
	 */
	class WC_Product {

		/**
		 * Get the product ID.
		 *
		 * @return int
		 */
		public function get_id() {
			return 0;
		}
	}
}
