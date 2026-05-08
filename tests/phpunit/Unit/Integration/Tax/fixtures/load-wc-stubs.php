<?php
/**
 * Load minimal WooCommerce class stubs for unit tests.
 *
 * Each stub class lives in its own file under `wc-stubs/` so the
 * one-object-per-file rule in WordPress Coding Standards is honoured.
 *
 * @package TLA_Media\GTM_Kit
 */

// phpcs:disable Squiz.Commenting.FileComment.Missing -- bootstrap loader; doc-block above is the file comment.

require_once __DIR__ . '/wc-stubs/WC_Product.php';
require_once __DIR__ . '/wc-stubs/WC_Cart.php';
require_once __DIR__ . '/wc-stubs/WC_Order.php';
require_once __DIR__ . '/wc-stubs/WC_Order_Item_Product.php';
