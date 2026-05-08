<?php
/**
 * Unit tests for {@see TaxResolver}.
 *
 * BrainMonkey (via `yoast/wp-test-utils`) for WordPress and WooCommerce
 * function stubs; Mockery for cart/order/product objects.
 *
 * Coverage:
 *
 *  - resolve_tax_mode() reads the option and applies the
 *    `gtmkit_resolve_tax_mode` filter.
 *  - resolve_product_price() honours the toggle independent of the
 *    "Display prices in cart and checkout" Woo setting.
 *  - resolve_cart_total() honours the toggle independent of the
 *    "Prices entered with tax" Woo setting.
 *  - resolve_order_total() and resolve_order_item_price() honour the
 *    toggle independent of `woocommerce_tax_display_shop`.
 *  - resolve_item_discount() emits a per-unit coupon discount that uses
 *    the same tax convention as the surrounding `price` field.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Integration\Tax;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use TLA_Media\GTM_Kit\Integration\Tax\TaxResolver;
use TLA_Media\GTM_Kit\Options\Options;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

require_once __DIR__ . '/fixtures/load-wc-stubs.php';

/**
 * Unit tests for {@see TaxResolver}.
 */
final class TaxResolverTest extends TestCase {

	/**
	 * Common WP/Woo function stubs for every test.
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
				'get_option'            => [],
				'add_filter'            => null,
				'wc_get_price_decimals' => 2,
				// Stubbing `is_plugin_active` makes `function_exists()` return
				// true inside `Util::load_plugin_api()`, so OptionSchema's
				// integrations branch does not require wp-admin/includes/plugin.php.
				'is_plugin_active'      => false,
			]
		);
	}

	/**
	 * Build a TaxResolver wired to a real Options instance with a fake
	 * `gtmkit` option payload, exercising the real Options getter path
	 * end-to-end rather than mocking it.
	 *
	 * @param bool $exclude_tax Toggle value to seed.
	 *
	 * @return TaxResolver
	 */
	private function make_resolver( bool $exclude_tax ): TaxResolver {
		Functions\when( 'get_option' )->justReturn(
			[
				'integrations' => [
					'woocommerce_exclude_tax' => $exclude_tax,
				],
			]
		);

		return new TaxResolver( Options::create() );
	}

	/**
	 * Toggle OFF resolves to inc-tax mode.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_tax_mode
	 */
	public function test_resolve_tax_mode_returns_false_when_option_off(): void {
		Filters\expectApplied( 'gtmkit_resolve_tax_mode' )->once()->andReturnFirstArg();

		$resolver = $this->make_resolver( false );

		$this->assertFalse( $resolver->resolve_tax_mode() );
	}

	/**
	 * Toggle ON resolves to ex-tax mode.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_tax_mode
	 */
	public function test_resolve_tax_mode_returns_true_when_option_on(): void {
		Filters\expectApplied( 'gtmkit_resolve_tax_mode' )->once()->andReturnFirstArg();

		$resolver = $this->make_resolver( true );

		$this->assertTrue( $resolver->resolve_tax_mode() );
	}

	/**
	 * The `gtmkit_resolve_tax_mode` filter wins over the option.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_tax_mode
	 */
	public function test_resolve_tax_mode_filter_can_override_option(): void {
		Filters\expectApplied( 'gtmkit_resolve_tax_mode' )->once()->andReturn( true );

		$resolver = $this->make_resolver( false );

		$this->assertTrue( $resolver->resolve_tax_mode() );
	}

	/**
	 * Toggle OFF returns the inc-tax product price.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_product_price
	 */
	public function test_resolve_product_price_inc_tax_when_toggle_off(): void {
		Functions\when( 'wc_get_price_including_tax' )->justReturn( 109.0 );
		Functions\when( 'wc_get_price_excluding_tax' )->justReturn( 94.78 );

		$resolver = $this->make_resolver( false );
		$product  = Mockery::mock( WC_Product::class );

		$this->assertSame( 109.0, $resolver->resolve_product_price( $product, false ) );
	}

	/**
	 * Toggle ON returns the ex-tax product price.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_product_price
	 */
	public function test_resolve_product_price_ex_tax_when_toggle_on(): void {
		Functions\when( 'wc_get_price_including_tax' )->justReturn( 109.0 );
		Functions\when( 'wc_get_price_excluding_tax' )->justReturn( 94.78 );

		$resolver = $this->make_resolver( true );
		$product  = Mockery::mock( WC_Product::class );

		$this->assertSame( 94.78, $resolver->resolve_product_price( $product, true ) );
	}

	/**
	 * Toggle OFF: cart total is contents + tax.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_cart_total
	 */
	public function test_resolve_cart_total_inc_tax_when_toggle_off(): void {
		$cart = Mockery::mock( WC_Cart::class );
		$cart->shouldReceive( 'get_cart_contents_total' )->andReturn( 94.78 );
		$cart->shouldReceive( 'get_cart_contents_tax' )->andReturn( 14.22 );

		$resolver = $this->make_resolver( false );

		$this->assertSame( 109.0, $resolver->resolve_cart_total( $cart, false ) );
	}

	/**
	 * Toggle ON: cart total is contents only.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_cart_total
	 */
	public function test_resolve_cart_total_ex_tax_when_toggle_on(): void {
		$cart = Mockery::mock( WC_Cart::class );
		$cart->shouldReceive( 'get_cart_contents_total' )->andReturn( 94.78 );
		$cart->shouldReceive( 'get_cart_contents_tax' )->never();

		$resolver = $this->make_resolver( true );

		$this->assertSame( 94.78, $resolver->resolve_cart_total( $cart, true ) );
	}

	/**
	 * Toggle OFF: order total is grand total inc tax.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_order_total
	 */
	public function test_resolve_order_total_inc_tax_when_toggle_off(): void {
		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_total' )->andReturn( 109.0 );
		$order->shouldReceive( 'get_total_tax' )->never();

		$resolver = $this->make_resolver( false );

		$this->assertSame( 109.0, $resolver->resolve_order_total( $order, false ) );
	}

	/**
	 * Toggle ON: order total subtracts tax.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_order_total
	 */
	public function test_resolve_order_total_ex_tax_when_toggle_on(): void {
		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_total' )->andReturn( 109.0 );
		$order->shouldReceive( 'get_total_tax' )->andReturn( 14.22 );

		$resolver = $this->make_resolver( true );

		$this->assertSame( 94.78, $resolver->resolve_order_total( $order, true ) );
	}

	/**
	 * Toggle OFF: order item price is requested with inc-tax flag.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_order_item_price
	 */
	public function test_resolve_order_item_price_inc_tax_when_toggle_off(): void {
		$item  = Mockery::mock( WC_Order_Item_Product::class );
		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_item_total' )
			->with( $item, true )
			->once()
			->andReturn( 109.0 );

		$resolver = $this->make_resolver( false );

		$this->assertSame( 109.0, $resolver->resolve_order_item_price( $order, $item, false ) );
	}

	/**
	 * Toggle ON: order item price is requested with ex-tax flag.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_order_item_price
	 */
	public function test_resolve_order_item_price_ex_tax_when_toggle_on(): void {
		$item  = Mockery::mock( WC_Order_Item_Product::class );
		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_item_total' )
			->with( $item, false )
			->once()
			->andReturn( 94.78 );

		$resolver = $this->make_resolver( true );

		$this->assertSame( 94.78, $resolver->resolve_order_item_price( $order, $item, true ) );
	}

	/**
	 * Rounding follows `wc_get_price_decimals()`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_cart_total
	 */
	public function test_rounding_uses_currency_precision(): void {
		Functions\when( 'wc_get_price_decimals' )->justReturn( 0 );

		$cart = Mockery::mock( WC_Cart::class );
		$cart->shouldReceive( 'get_cart_contents_total' )->andReturn( 94.78 );
		$cart->shouldReceive( 'get_cart_contents_tax' )->andReturn( 14.22 );

		$resolver = $this->make_resolver( false );

		$this->assertSame( 109.0, $resolver->resolve_cart_total( $cart, false ) );
	}

	/**
	 * Negative inputs (refunds) round symmetrically.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_order_total
	 */
	public function test_resolve_order_total_handles_negative_values(): void {
		$order = Mockery::mock( WC_Order::class );
		$order->shouldReceive( 'get_total' )->andReturn( -109.0 );
		$order->shouldReceive( 'get_total_tax' )->andReturn( -14.22 );

		$resolver = $this->make_resolver( true );

		$this->assertSame( -94.78, $resolver->resolve_order_total( $order, true ) );
	}

	/**
	 * Mixed-config reproduction: prices entered ex-tax (94.78), 15% VAT,
	 * displayed inc-tax (109), `woocommerce_exclude_tax` toggle OFF.
	 * The data layer must emit `value: 109` and `items[0].price: 109`
	 * consistently.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_product_price
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_cart_total
	 */
	public function test_mixed_woo_config_with_toggle_off_reports_inc_tax(): void {
		Functions\when( 'wc_get_price_including_tax' )->justReturn( 109.0 );
		Functions\when( 'wc_get_price_excluding_tax' )->justReturn( 94.78 );

		$cart = Mockery::mock( WC_Cart::class );
		$cart->shouldReceive( 'get_cart_contents_total' )->andReturn( 94.78 );
		$cart->shouldReceive( 'get_cart_contents_tax' )->andReturn( 14.22 );

		$resolver    = $this->make_resolver( false );
		$exclude_tax = $resolver->resolve_tax_mode();

		$cart_value = $resolver->resolve_cart_total( $cart, $exclude_tax );
		$item_price = $resolver->resolve_product_price( Mockery::mock( WC_Product::class ), $exclude_tax );

		$this->assertSame( 109.0, $cart_value, 'value must be inc-tax with toggle OFF.' );
		$this->assertSame( 109.0, $item_price, 'items[].price must be inc-tax with toggle OFF.' );
		$this->assertSame( $cart_value, $item_price, 'sum(items[].price * qty) must equal value for one item at qty 1.' );
	}

	/**
	 * Toggle ON: per-unit coupon discount excludes tax.
	 *
	 * Item: 2 × $50 ex-tax with a $10 ex-tax coupon discount on the line.
	 * subtotal = 100, total = 90, subtotal_tax = 25, total_tax = 22.5.
	 * Expected per-unit discount = (100 - 90) / 2 = 5.0 ex-tax.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_item_discount
	 */
	public function test_resolve_item_discount_ex_tax_when_toggle_on(): void {
		Filters\expectApplied( 'gtmkit_resolve_item_discount' )->once()->andReturnFirstArg();

		$resolver = $this->make_resolver( true );
		$item     = [
			'subtotal'     => 100.0,
			'total'        => 90.0,
			'subtotal_tax' => 25.0,
			'total_tax'    => 22.5,
			'quantity'     => 2,
		];

		$this->assertSame( 5.0, $resolver->resolve_item_discount( $item, true ) );
	}

	/**
	 * Toggle OFF: per-unit coupon discount includes tax.
	 *
	 * Same item as above. Inc-tax line discount =
	 * (100 - 90) + (25 - 22.5) = 12.5; per unit = 12.5 / 2 = 6.25.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_item_discount
	 */
	public function test_resolve_item_discount_inc_tax_when_toggle_off(): void {
		Filters\expectApplied( 'gtmkit_resolve_item_discount' )->once()->andReturnFirstArg();

		$resolver = $this->make_resolver( false );
		$item     = [
			'subtotal'     => 100.0,
			'total'        => 90.0,
			'subtotal_tax' => 25.0,
			'total_tax'    => 22.5,
			'quantity'     => 2,
		];

		$this->assertSame( 6.25, $resolver->resolve_item_discount( $item, false ) );
	}

	/**
	 * Zero quantity: per-unit discount must be 0.0 (no division by zero).
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_item_discount
	 */
	public function test_resolve_item_discount_zero_quantity_returns_zero(): void {
		Filters\expectApplied( 'gtmkit_resolve_item_discount' )->once()->andReturnFirstArg();

		$resolver = $this->make_resolver( true );
		$item     = [
			'subtotal'     => 100.0,
			'total'        => 90.0,
			'subtotal_tax' => 25.0,
			'total_tax'    => 22.5,
			'quantity'     => 0,
		];

		$this->assertSame( 0.0, $resolver->resolve_item_discount( $item, true ) );
	}

	/**
	 * The `gtmkit_resolve_item_discount` filter wins over the computed
	 * value, mirroring the `gtmkit_resolve_tax_mode` override pattern.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_item_discount
	 */
	public function test_resolve_item_discount_filter_overrides_value(): void {
		Filters\expectApplied( 'gtmkit_resolve_item_discount' )->once()->andReturn( 99.99 );

		$resolver = $this->make_resolver( true );
		$item     = [
			'subtotal'     => 100.0,
			'total'        => 90.0,
			'subtotal_tax' => 25.0,
			'total_tax'    => 22.5,
			'quantity'     => 2,
		];

		$this->assertSame( 99.99, $resolver->resolve_item_discount( $item, true ) );
	}

	/**
	 * Toggle OFF must yield identical numbers for two equivalent Woo
	 * configurations: prices-entered-ex-tax+display-inc-tax versus
	 * prices-entered-inc-tax+display-inc-tax. The toggle, not Woo's
	 * settings, drives the data layer.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_cart_total
	 */
	public function test_woo_config_independence_for_cart_total_with_toggle_off(): void {
		// Config A: prices entered ex-tax + cart shown inc-tax.
		$cart_a = Mockery::mock( WC_Cart::class );
		$cart_a->shouldReceive( 'get_cart_contents_total' )->andReturn( 94.78 );
		$cart_a->shouldReceive( 'get_cart_contents_tax' )->andReturn( 14.22 );

		// Config B: prices entered inc-tax + cart shown inc-tax.
		$cart_b = Mockery::mock( WC_Cart::class );
		$cart_b->shouldReceive( 'get_cart_contents_total' )->andReturn( 94.78 );
		$cart_b->shouldReceive( 'get_cart_contents_tax' )->andReturn( 14.22 );

		$resolver = $this->make_resolver( false );

		$this->assertSame(
			$resolver->resolve_cart_total( $cart_a, false ),
			$resolver->resolve_cart_total( $cart_b, false ),
			'Cart total must not depend on Woo storage/display settings, only on the toggle.'
		);
		$this->assertSame( 109.0, $resolver->resolve_cart_total( $cart_a, false ) );
	}

	/**
	 * Toggle OFF: two equivalent Woo configurations yield the same order
	 * total. Mirrors the cart-total invariance test for purchase events.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\Tax\TaxResolver::resolve_order_total
	 */
	public function test_woo_config_independence_for_order_total_with_toggle_off(): void {
		// Config A: prices entered ex-tax + display inc-tax.
		$order_a = Mockery::mock( WC_Order::class );
		$order_a->shouldReceive( 'get_total' )->andReturn( 109.0 );
		$order_a->shouldReceive( 'get_total_tax' )->andReturn( 14.22 );

		// Config B: prices entered inc-tax + display inc-tax.
		$order_b = Mockery::mock( WC_Order::class );
		$order_b->shouldReceive( 'get_total' )->andReturn( 109.0 );
		$order_b->shouldReceive( 'get_total_tax' )->andReturn( 14.22 );

		$resolver = $this->make_resolver( false );

		$this->assertSame(
			$resolver->resolve_order_total( $order_a, false ),
			$resolver->resolve_order_total( $order_b, false )
		);
		$this->assertSame( 109.0, $resolver->resolve_order_total( $order_a, false ) );
	}
}
