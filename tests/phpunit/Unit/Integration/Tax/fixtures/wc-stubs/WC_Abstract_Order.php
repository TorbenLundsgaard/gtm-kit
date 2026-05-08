<?php
/**
 * Minimal WC_Abstract_Order stub for unit tests.
 *
 * Mirrors the real-world inheritance (WC_Order and WC_Order_Refund both
 * extend it) so Mockery mocks of WC_Order satisfy type hints that target
 * WC_Abstract_Order.
 *
 * @package TLA_Media\GTM_Kit
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

if ( ! class_exists( 'WC_Abstract_Order' ) ) {
	/**
	 * Stand-in for WooCommerce's abstract order class.
	 */
	abstract class WC_Abstract_Order {

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
		 * Get the per-item total. Mirrors WC_Abstract_Order::get_item_total signature.
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
