<?php
/**
 * REST routes used by the React admin screen. Thin: delegates to Overview and Importer.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Rest;

use Twinspot_Lite\Features\Importer;
use Twinspot_Lite\Module;

defined( 'ABSPATH' ) || exit;

class Routes implements Module {
	const NAMESPACE = 'twinspot-lite/v1';

	/** @var Overview */
	private $overview;
	/** @var Importer */
	private $importer;

	public function __construct( Overview $overview, Importer $importer ) {
		$this->overview = $overview;
		$this->importer = $importer;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$permission = fn() => current_user_can( 'manage_woocommerce' );
		register_rest_route(
			self::NAMESPACE,
			'/overview',
			array(
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'callback'            => fn( \WP_REST_Request $req ) => $this->overview->build( $req->get_params() ),
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 8,
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
					'min_size' => array(
						'type'              => 'integer',
						'default'           => 2,
						'minimum'           => 2,
						'sanitize_callback' => 'absint',
					),
					'link'     => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'email', 'phone', 'address' ),
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => fn() => array( 'imported' => $this->importer->run() ),
			)
		);
	}
}
