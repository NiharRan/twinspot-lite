<?php
/**
 * A feature of the plugin. To add one: implement this, list it in Plugin::modules().
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite;

defined( 'ABSPATH' ) || exit;

interface Module {
	/**
	 * Add this module's WordPress hooks.
	 */
	public function register(): void;
}
