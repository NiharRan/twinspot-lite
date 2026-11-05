<?php
/**
 * Admin screen: list of rings and import of past orders.
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'woocommerce', 'Twinspot', 'Twinspot', 'manage_woocommerce', 'twinspot-lite', 'twinspot_lite_admin_page' );
	}
);

add_action(
	'admin_post_twinspot_lite_import',
	function () {
		check_admin_referer( 'twinspot_lite_import' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'twinspot-lite' ) );
		}
		// shortcut: newest 1000 orders from the last 12 months in one request; upgrade to batches for bigger stores.
		$orders = wc_get_orders(
			array(
				'limit'        => 1000,
				'date_created' => '>' . strtotime( '-12 months' ),
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-refunded' ),
			)
		);
		foreach ( $orders as $order ) {
			twinspot_lite_record( $order );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=twinspot-lite' ) );
		exit;
	}
);

function twinspot_lite_admin_page() {
	global $wpdb;
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$rows  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}twinspot_lite_edges", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rings = twinspot_lite_rings( $rows );
	usort(
		$rings,
		function ( $a, $b ) {
			return count( $b ) - count( $a );
		}
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Twinspot', 'twinspot-lite' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %d: number of orders indexed */
				esc_html__( '%d orders indexed. A ring is a group of orders linked, directly or through other orders, by the same email, phone or address.', 'twinspot-lite' ),
				count( $rows )
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="twinspot_lite_import">
			<?php wp_nonce_field( 'twinspot_lite_import' ); ?>
			<?php submit_button( __( 'Import past orders (12 months)', 'twinspot-lite' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php if ( ! $rings ) : ?>
			<p><strong><?php esc_html_e( 'No rings found.', 'twinspot-lite' ); ?></strong></p>
		<?php else : ?>
			<table class="widefat striped" style="margin-top:1em">
				<thead><tr><th><?php esc_html_e( 'Orders in ring', 'twinspot-lite' ); ?></th><th><?php esc_html_e( 'Orders', 'twinspot-lite' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $rings as $ring ) : ?>
					<tr>
						<td><?php echo (int) count( $ring ); ?></td>
						<td>
						<?php
						foreach ( $ring as $i => $id ) {
							$order = wc_get_order( $id );
							echo $i ? ', ' : '';
							if ( $order ) {
								echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . (int) $id . '</a>';
							} else {
								echo '#' . (int) $id;
							}
						}
						?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
