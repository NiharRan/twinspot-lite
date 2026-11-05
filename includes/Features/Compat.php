<?php
/**
 * Declares HPOS and Cart/Checkout Blocks compatibility.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Features;

use Twinspot_Lite\Module;

defined( 'ABSPATH' ) || exit;

class Compat implements Module {
	/** @var string Main plugin file. */
	private $file;

	public function __construct( string $file ) {
		$this->file = $file;
	}

	public function register(): void {
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->file, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $this->file, true );
				}
			}
		);
	}
}
