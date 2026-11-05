<?php
/**
 * WordPress personal-data export, erase and privacy-policy text.
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

function twinspot_lite_rows_for_email( $email ) {
	global $wpdb;
	$h = twinspot_lite_hash( twinspot_lite_norm_email( $email ), get_option( 'twinspot_lite_secret' ) );
	if ( '' === $h ) {
		return array();
	}
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}twinspot_lite_edges WHERE email_h = %s", $h ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['twinspot-lite'] = array(
			'exporter_friendly_name' => __( 'Twinspot Lite', 'twinspot-lite' ),
			'callback'               => function ( $email ) {
				$items = array();
				foreach ( twinspot_lite_rows_for_email( $email ) as $row ) {
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
			},
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['twinspot-lite'] = array(
			'eraser_friendly_name' => __( 'Twinspot Lite', 'twinspot-lite' ),
			'callback'             => function ( $email ) {
				$rows = twinspot_lite_rows_for_email( $email );
				foreach ( $rows as $row ) {
					twinspot_lite_forget( $row['order_id'] );
				}
				return array(
					'items_removed'  => (bool) $rows,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			},
		);
		return $erasers;
	}
);

add_action(
	'admin_init',
	function () {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'Twinspot Lite',
				wp_kses_post(
					'<p>' . __( 'Twinspot Lite stores one-way (HMAC-SHA256) hashes of the billing email, phone, address and IP address of each order, to detect repeat customers and block repeat welcome coupons. The hashes cannot be turned back into the original details. They are kept on this site only, are not sent to any third party, and are deleted when the order is deleted, cancelled or erased through the WordPress privacy tools.', 'twinspot-lite' ) . '</p>'
				)
			);
		}
	}
);
