<?php
/**
 * Minimal WC_Order_Item_Product stub for unit tests.
 *
 * @package TLA_Media\GTM_Kit
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	/**
	 * Stand-in for WooCommerce's WC_Order_Item_Product class for type-hint compatibility.
	 */
	class WC_Order_Item_Product {

		/**
		 * Get the item quantity.
		 *
		 * @return int
		 */
		public function get_quantity() {
			return 1;
		}
	}
}
