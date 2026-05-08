<?php
/**
 * Minimal WC_Cart stub for unit tests.
 *
 * @package TLA_Media\GTM_Kit
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( ! class_exists( 'WC_Cart' ) ) {
	/**
	 * Stand-in for WooCommerce's WC_Cart class for type-hint compatibility.
	 */
	class WC_Cart {

		/**
		 * Get cart contents total (net of tax).
		 *
		 * @return float
		 */
		public function get_cart_contents_total() {
			return 0.0;
		}

		/**
		 * Get cart contents tax.
		 *
		 * @return float
		 */
		public function get_cart_contents_tax() {
			return 0.0;
		}
	}
}
