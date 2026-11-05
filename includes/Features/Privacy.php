<?php
/**
 * WordPress personal-data export, erase and privacy-policy text.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Features;

use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Fingerprinter;
use Twinspot_Lite\Module;

defined( 'ABSPATH' ) || exit;

class Privacy implements Module {
	/** @var Edges */
	private $edges;
	/** @var Fingerprinter */
	private $fingerprinter;

	/** @var BlockLog */
	private $block_log;

	public function __construct( Edges $edges, Fingerprinter $fingerprinter, BlockLog $block_log ) {
		$this->edges         = $edges;
		$this->fingerprinter = $fingerprinter;
		$this->block_log     = $block_log;
	}

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_text' ) );
	}

	private function rows_for_email( $email ): array {
		$h = $this->fingerprinter->email_hash( $email );
		return '' === $h ? array() : $this->edges->by_email_hash( $h );
	}

	public function add_exporter( $exporters ) {
		$exporters['twinspot-lite'] = array(
			'exporter_friendly_name' => __( 'Twinspot Lite', 'twinspot-lite' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function add_eraser( $erasers ) {
		$erasers['twinspot-lite'] = array(
			'eraser_friendly_name' => __( 'Twinspot Lite', 'twinspot-lite' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function export( $email ): array {
		$items = array();
		foreach ( $this->rows_for_email( $email ) as $row ) {
			$items[] = array(
				'group_id'    => 'twinspot-lite',
				'group_label' => __( 'Twinspot Lite order fingerprints', 'twinspot-lite' ),
				'item_id'     => 'twinspot-lite-' . $row['order_id'],
				'data'        => array(
					array(
						'name'  => __( 'Order', 'twinspot-lite' ),
						'value' => '#' . $row['order_id'],
					),
					array(
						'name'  => __( 'Stored one-way hashes of', 'twinspot-lite' ),
						'value' => implode(
							', ',
							array_filter(
								array(
									$row['email_h'] ? __( 'email', 'twinspot-lite' ) : '',
									$row['phone_h'] ? __( 'phone', 'twinspot-lite' ) : '',
									$row['addr_h'] ? __( 'address', 'twinspot-lite' ) : '',
									$row['ip_h'] ? __( 'IP address', 'twinspot-lite' ) : '',
								)
							)
						),
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	public function erase( $email ): array {
		$rows = $this->rows_for_email( $email );
		$hashes = array();
		$ids    = array();
		foreach ( $rows as $row ) {
			$hashes[] = $row['email_h'];
			$hashes[] = $row['phone_h'];
			$hashes[] = $row['addr_h'];
			$ids[]    = (int) $row['order_id'];
			$this->edges->delete( (int) $row['order_id'] );
		}
		if ( $rows ) {
			$this->block_log->forget( $hashes, $ids );
		}
		return array(
			'items_removed'  => (bool) $rows,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	public function add_policy_text(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'Twinspot Lite',
				wp_kses_post(
					'<p>' . __( 'Twinspot Lite stores one-way (HMAC-SHA256) hashes of the billing email, phone, address and IP address of each order, to detect repeat customers and block repeat welcome coupons. The hashes cannot be turned back into the original details. They are kept on this site only, are not sent to any third party, and are deleted when the order is deleted, cancelled or erased through the WordPress privacy tools.', 'twinspot-lite' ) . '</p>'
				)
			);
		}
	}
}
