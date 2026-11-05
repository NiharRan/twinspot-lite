<?php
/**
 * Wires services and modules together. The only place that knows every class.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite;

use Twinspot_Lite\Admin\Page;
use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Fingerprinter;
use Twinspot_Lite\Features\Compat;
use Twinspot_Lite\Features\CouponGuard;
use Twinspot_Lite\Features\Importer;
use Twinspot_Lite\Features\OrderTracker;
use Twinspot_Lite\Features\Privacy;
use Twinspot_Lite\Rest\Overview;
use Twinspot_Lite\Rest\Routes;

defined( 'ABSPATH' ) || exit;

class Plugin {
	/** @var string Main plugin file. */
	private $file;

	public function __construct( string $file ) {
		$this->file = $file;
	}

	public static function activate(): void {
		( new Edges() )->install();
		( new Fingerprinter() )->install();
	}

	public function boot(): void {
		foreach ( apply_filters( 'twinspot_lite_modules', $this->modules() ) as $module ) {
			if ( $module instanceof Module ) {
				$module->register();
			}
		}
	}

	/**
	 * Every feature of the plugin. Add a new Module here (or via the twinspot_lite_modules filter).
	 *
	 * @return Module[]
	 */
	private function modules(): array {
		$edges   = new Edges();
		$prints  = new Fingerprinter();
		$log     = new BlockLog();
		$tracker = new OrderTracker( $edges, $prints );

		return array(
			new Compat( $this->file ),
			$tracker,
			new CouponGuard( $edges, $prints, $log ),
			new Privacy( $edges, $prints ),
			new Routes( new Overview( $edges, $prints, $log ), new Importer( $tracker, $edges, $prints ), $log ),
			new Page( plugin_dir_path( $this->file ) ),
		);
	}
}
