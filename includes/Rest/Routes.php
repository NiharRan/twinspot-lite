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

	/** @var Activity */
	private $activity;

	public function __construct( Overview $overview, Importer $importer, Activity $activity ) {
		$this->overview = $overview;
		$this->importer = $importer;
		$this->activity = $activity;
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
			'/ring/(?P<order_id>\\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'callback'            => function ( \WP_REST_Request $req ) {
					$ring = $this->overview->detail( (int) $req['order_id'] );
					unset( $ring['ids'] );
					return $ring ?? new \WP_Error( 'twinspot_lite_no_ring', __( 'This order is not in a ring.', 'twinspot-lite' ), array( 'status' => 404 ) );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/ring/(?P<order_id>\\d+)/block',
			array(
				'methods'             => array( 'POST', 'DELETE' ),
				'permission_callback' => $permission,
				'callback'            => function ( \WP_REST_Request $req ) {
					$on = $this->overview->set_blocked( (int) $req['order_id'], 'POST' === $req->get_method() );
					return null === $on ? new \WP_Error( 'twinspot_lite_no_ring', __( 'This order is not in a ring.', 'twinspot-lite' ), array( 'status' => 404 ) ) : array( 'blocked' => $on );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/ring/(?P<order_id>\\d+)/allow',
			array(
				'methods'             => array( 'POST', 'DELETE' ),
				'permission_callback' => $permission,
				'callback'            => function ( \WP_REST_Request $req ) {
					$on = $this->overview->set_allowed( (int) $req['order_id'], 'POST' === $req->get_method() );
					return null === $on ? new \WP_Error( 'twinspot_lite_no_ring', __( 'This order is not in a ring.', 'twinspot-lite' ), array( 'status' => 404 ) ) : array( 'allowed' => $on );
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => fn( \WP_REST_Request $req ) => $this->importer->run( max( 1, (int) $req->get_param( 'page' ) ) ),
				'args'                => array(
					'page' => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/activity',
			array(
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'callback'            => fn( \WP_REST_Request $req ) => $this->activity->build( $req->get_params() ),
				'args'                => $this->activity_args(),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/activity/export',
			array(
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'callback'            => fn( \WP_REST_Request $req ) => $this->activity->export( $req->get_params() ),
				'args'                => $this->activity_args(),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => $permission,
				'callback'            => function ( \WP_REST_Request $req ) {
					if ( 'POST' === $req->get_method() ) {
						$hold = rest_sanitize_boolean( $req->get_param( 'hold' ) );
						update_option( 'twinspot_lite_enabled', ! $hold && rest_sanitize_boolean( $req->get_param( 'enabled' ) ) ? 'yes' : 'no' ); // Block and hold are exclusive.
						update_option( 'twinspot_lite_hold_orders', $hold ? 'yes' : 'no' );
					}
					return array(
						'enabled' => 'no' !== get_option( 'twinspot_lite_enabled' ),
						'hold'    => 'yes' === get_option( 'twinspot_lite_hold_orders' ),
					);
				},
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/sample',
			array(
				'methods'             => array( 'POST', 'DELETE' ),
				'permission_callback' => $permission,
				'callback'            => function ( \WP_REST_Request $req ) {
					'POST' === $req->get_method() ? $this->importer->add_sample() : $this->importer->clear_sample();
					return array( 'ok' => true );
				},
			)
		);
	}

	private function activity_args(): array {
		$text = array(
			'type'     => 'string',
			'default'  => '',
			'enum'     => array( '', 'blocked', 'clean', 'ring', 'held', 'system' ),
		);
		return array(
			'range'    => array(
				'type'    => 'string',
				'default' => '24h',
				'enum'    => array( '24h', '7d', '30d' ),
			),
			'type'     => $text,
			'severity' => array(
				'type'    => 'string',
				'default' => '',
				'enum'    => array( '', 'blocked', 'flagged', 'allowed', 'system' ),
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 25,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
		);
	}
}
