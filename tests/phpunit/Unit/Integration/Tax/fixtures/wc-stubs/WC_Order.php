<?php
/**
 * Minimal WC_Order stub for unit tests.
 *
 * @package TLA_Media\GTM_Kit
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( ! class_exists( 'WC_Order' ) ) {
	/**
	 * Stand-in for WooCommerce's WC_Order class for type-hint compatibility.
	 */
	class WC_Order {

		/**
		 * Get the order grand total (always inc-tax).
		 *
		 * @return float
		 */
		public function get_total() {
			return 0.0;
		}

		/**
		 * Get the order total tax.
		 *
		 * @return float
		 */
		public function get_total_tax() {
			return 0.0;
		}

		/**
		 * Get the per-item total. Mirrors WC_Order::get_item_total signature.
		 *
		 * @param mixed $item    The item.
		 * @param bool  $inc_tax Include tax in the returned value.
		 * @param bool  $round   Round the returned value.
		 *
		 * @return float
		 */
		public function get_item_total( $item, $inc_tax = false, $round = true ) {
			unset( $item, $inc_tax, $round );
			return 0.0;
		}
	}
}
