<?php
/**
 * Tax mode resolver for the WooCommerce data layer.
 *
 * Centralises tax handling so a single setting controls the e-commerce
 * data layer end-to-end. The resolver decouples GTM Kit's data layer
 * from WooCommerce's "Prices entered with tax" and "Display prices in
 * cart and checkout" settings: the toggle alone determines whether
 * `value` and `items[].price` are reported including or excluding tax.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Integration\Tax;

use TLA_Media\GTM_Kit\Options\Options;
use WC_Abstract_Order;
use WC_Cart;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Resolves tax-aware totals and prices for the WooCommerce data layer.
 *
 * Reads {@see Options} once via {@see self::resolve_tax_mode()} and exposes
 * resolver methods for product prices, cart totals, and order totals. The
 * resolved boolean (`$exclude_tax`) is the canonical input to every
 * resolver so a single payload only consults the option once.
 *
 * @since 1.x
 */
final class TaxResolver {

	/**
	 * Options instance.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Resolve the tax mode for the current payload.
	 *
	 * Reads `integrations.woocommerce_exclude_tax` once and applies the
	 * `gtmkit_resolve_tax_mode` filter so advanced integrations can
	 * override the toggle programmatically (per-event or per-context).
	 *
	 * @return bool True when totals/prices must be reported excluding tax.
	 */
	public function resolve_tax_mode(): bool {
		$exclude_tax = (bool) $this->options->get( 'integrations', 'woocommerce_exclude_tax' );

		/**
		 * Filter the resolved tax mode for the data layer.
		 *
		 * @param bool $exclude_tax True to report totals/prices excluding tax,
		 *                          false to report including tax.
		 */
		return (bool) apply_filters( 'gtmkit_resolve_tax_mode', $exclude_tax );
	}

	/**
	 * Resolve a product price for the data layer.
	 *
	 * Always uses WooCommerce's tax-conversion helpers so the returned
	 * value reflects the toggle, not the store's "Display prices in cart"
	 * setting.
	 *
	 * @param WC_Product $product     The product (or variation).
	 * @param bool       $exclude_tax Tax mode resolved by {@see self::resolve_tax_mode()}.
	 *
	 * @return float Rounded to the store's currency precision.
	 */
	public function resolve_product_price( WC_Product $product, bool $exclude_tax ): float {
		$price = $exclude_tax
			? wc_get_price_excluding_tax( $product )
			: wc_get_price_including_tax( $product );

		return $this->round( (float) $price );
	}

	/**
	 * Resolve a cart total for the data layer.
	 *
	 * Uses `WC_Cart::get_cart_contents_total()` (always net of tax) plus
	 * `get_cart_contents_tax()` for the inc-tax case. Independent of the
	 * "Prices entered with tax" Woo setting.
	 *
	 * @param WC_Cart $cart        The cart.
	 * @param bool    $exclude_tax Tax mode resolved by {@see self::resolve_tax_mode()}.
	 *
	 * @return float Rounded to the store's currency precision.
	 */
	public function resolve_cart_total( WC_Cart $cart, bool $exclude_tax ): float {
		$total = (float) $cart->get_cart_contents_total();

		if ( ! $exclude_tax ) {
			$total += (float) $cart->get_cart_contents_tax();
		}

		return $this->round( $total );
	}

	/**
	 * Resolve an order total for the data layer.
	 *
	 * Accepts {@see WC_Abstract_Order} so refunds (which extend it as a
	 * sibling of {@see \WC_Order}) reuse the same helper. Negative inputs
	 * round symmetrically.
	 *
	 * @param WC_Abstract_Order $order       The order or refund.
	 * @param bool              $exclude_tax Tax mode resolved by {@see self::resolve_tax_mode()}.
	 *
	 * @return float Rounded to the store's currency precision.
	 */
	public function resolve_order_total( WC_Abstract_Order $order, bool $exclude_tax ): float {
		$total = (float) $order->get_total();

		if ( $exclude_tax ) {
			$total -= (float) $order->get_total_tax();
		}

		return $this->round( $total );
	}

	/**
	 * Resolve the per-item price for an order line item.
	 *
	 * Uses `WC_Abstract_Order::get_item_total()` with the boolean toggle
	 * so the returned price obeys `$exclude_tax` regardless of the
	 * `woocommerce_tax_display_shop` setting. Refund items are stored
	 * negative and round sign-symmetrically.
	 *
	 * @param WC_Abstract_Order     $order       The order or refund.
	 * @param WC_Order_Item_Product $item        The order line item.
	 * @param bool                  $exclude_tax Tax mode resolved by {@see self::resolve_tax_mode()}.
	 *
	 * @return float Rounded to the store's currency precision.
	 */
	public function resolve_order_item_price( WC_Abstract_Order $order, WC_Order_Item_Product $item, bool $exclude_tax ): float {
		$inc_tax = ! $exclude_tax;

		return $this->round( (float) $order->get_item_total( $item, $inc_tax ) );
	}

	/**
	 * Resolve the per-unit coupon discount for an item.
	 *
	 * The cart-item / order-item array carries `subtotal` and `total` (both
	 * net of tax) plus `subtotal_tax` and `total_tax`. When `$exclude_tax`
	 * is false the helper folds the tax delta back in so the returned
	 * discount uses the same tax convention as the surrounding `price`
	 * field.
	 *
	 * @param array<string, mixed> $item        Cart- or order-item array.
	 *                                          Required keys: `subtotal`,
	 *                                          `total`, `subtotal_tax`,
	 *                                          `total_tax`, `quantity`.
	 * @param bool                 $exclude_tax Tax mode resolved by {@see self::resolve_tax_mode()}.
	 *
	 * @return float Per-unit discount, rounded to the store's currency precision.
	 */
	public function resolve_item_discount( array $item, bool $exclude_tax ): float {
		$subtotal     = (float) ( $item['subtotal'] ?? 0 );
		$total        = (float) ( $item['total'] ?? 0 );
		$subtotal_tax = (float) ( $item['subtotal_tax'] ?? 0 );
		$total_tax    = (float) ( $item['total_tax'] ?? 0 );
		$quantity     = (int) ( $item['quantity'] ?? 0 );

		$discount = $subtotal - $total;
		if ( ! $exclude_tax ) {
			$discount += $subtotal_tax - $total_tax;
		}

		$per_unit = ( $quantity > 0 ) ? $discount / $quantity : 0.0;

		/**
		 * Filter the resolved per-unit coupon discount.
		 *
		 * @param float                $discount    Per-unit discount,
		 *                                          rounded to currency precision.
		 * @param array<string, mixed> $item        Cart- or order-item array.
		 * @param bool                 $exclude_tax Tax mode in effect.
		 */
		return (float) apply_filters( 'gtmkit_resolve_item_discount', $this->round( $per_unit ), $item, $exclude_tax );
	}

	/**
	 * Round a value to the store's currency precision.
	 *
	 * Falls back to two decimals when WooCommerce is not loaded (mostly
	 * relevant for unit tests). Negative inputs round symmetrically, so
	 * the helper is safe for refund payloads where values are negative.
	 *
	 * @param float $value Value to round.
	 *
	 * @return float
	 */
	private function round( float $value ): float {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;

		return round( $value, max( 0, $decimals ) );
	}
}
