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
	class WC_Order extends WC_Abstract_Order {
	}
}
