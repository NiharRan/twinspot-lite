<?php
/** Dev only, run with: php tests/core-test.php (not shipped). */
if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/autoload.php';
use Twinspot_Lite\Core\Normalize;
use Twinspot_Lite\Core\Rings;
function twinspot_lite_eq( $actual, $expected, $label ) {
	if ( $actual !== $expected ) {
		echo 'FAIL ' . $label . ': ' . json_encode( $actual ) . "\n";
		exit( 1 );
	}
}

twinspot_lite_eq( Normalize::email( 'John.Doe+x@Gmail.com' ), 'johndoe@gmail.com', 'gmail alias' );
twinspot_lite_eq( Normalize::email( 'a.b+c@corp.com' ), 'a.b@corp.com', 'non-gmail keeps dots' );
twinspot_lite_eq( Normalize::email( 'nope' ), '', 'bad email' );
twinspot_lite_eq( Normalize::phone( '+1 (555) 123-4567' ), '5551234567', 'phone' );
twinspot_lite_eq( Normalize::phone( '12' ), '', 'short phone' );
twinspot_lite_eq( Normalize::address( '12 Main St.', 'ab-1 2cd' ), '12mainst|ab12cd', 'addr' );
twinspot_lite_eq( Normalize::address( '12 Main St', '' ), '', 'addr needs postcode' );

// multi-hop: 1-2 share phone, 2-3 share addr, 4 alone
$twinspot_lite_rings = Rings::find( array(
	array( 'order_id' => 1, 'email_h' => 'a', 'phone_h' => 'p', 'addr_h' => '' ),
	array( 'order_id' => 2, 'email_h' => 'b', 'phone_h' => 'p', 'addr_h' => 'x' ),
	array( 'order_id' => 3, 'email_h' => 'c', 'phone_h' => '', 'addr_h' => 'x' ),
	array( 'order_id' => 4, 'email_h' => 'd', 'phone_h' => '', 'addr_h' => '' ),
) );
twinspot_lite_eq( count( $twinspot_lite_rings ), 1, 'one ring' );
sort( $twinspot_lite_rings[0] );
twinspot_lite_eq( $twinspot_lite_rings[0], array( 1, 2, 3 ), 'transitive ring' );
echo "ok\n";
// Linked-by: email shared by 1-2, address shared by 2-3, phone unique.
twinspot_lite_eq(
	Rings::links( array(
		array( 'email_h' => 'a', 'phone_h' => '1', 'addr_h' => '' ),
		array( 'email_h' => 'a', 'phone_h' => '2', 'addr_h' => 'x' ),
		array( 'email_h' => 'c', 'phone_h' => '3', 'addr_h' => 'x' ),
	) ),
	array( 'email', 'address' ),
	'ring links'
);
echo "ok links\n";

twinspot_lite_eq(
	Rings::shared( array( 'email_h' => 'z', 'phone_h' => 'p', 'addr_h' => 'x' ), array(
		array( 'email_h' => 'a', 'phone_h' => 'p', 'addr_h' => '' ),
		array( 'email_h' => 'b', 'phone_h' => '', 'addr_h' => 'x' ),
	) ),
	array( 'phone', 'address' ),
	'shared links'
);
twinspot_lite_eq( Rings::shared( array( 'email_h' => '', 'phone_h' => '', 'addr_h' => '' ), array( array( 'email_h' => '', 'phone_h' => '', 'addr_h' => '' ) ) ), array(), 'empty hashes match nothing' );
twinspot_lite_eq( \Twinspot_Lite\Core\Csv::row( array( 'a"b', '=1+1', 5 ) ), "\"a\"\"b\",\"'=1+1\",\"5\"\r\n", 'csv row' );
echo "ok shared+csv\n";
