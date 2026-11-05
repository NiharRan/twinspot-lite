<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/core.php';
function eq( $a, $b, $m ) { if ( $a !== $b ) { fwrite( STDERR, "FAIL $m: " . var_export( $a, true ) . "\n" ); exit( 1 ); } }

eq( twinspot_lite_norm_email( 'John.Doe+x@Gmail.com' ), 'johndoe@gmail.com', 'gmail alias' );
eq( twinspot_lite_norm_email( 'a.b+c@corp.com' ), 'a.b@corp.com', 'non-gmail keeps dots' );
eq( twinspot_lite_norm_email( 'nope' ), '', 'bad email' );
eq( twinspot_lite_norm_phone( '+1 (555) 123-4567' ), '5551234567', 'phone' );
eq( twinspot_lite_norm_phone( '12' ), '', 'short phone' );
eq( twinspot_lite_norm_addr( '12 Main St.', 'ab-1 2cd' ), '12mainst|ab12cd', 'addr' );
eq( twinspot_lite_norm_addr( '12 Main St', '' ), '', 'addr needs postcode' );

// multi-hop: 1-2 share phone, 2-3 share addr, 4 alone
$rings = twinspot_lite_rings( array(
	array( 'order_id' => 1, 'email_h' => 'a', 'phone_h' => 'p', 'addr_h' => '' ),
	array( 'order_id' => 2, 'email_h' => 'b', 'phone_h' => 'p', 'addr_h' => 'x' ),
	array( 'order_id' => 3, 'email_h' => 'c', 'phone_h' => '', 'addr_h' => 'x' ),
	array( 'order_id' => 4, 'email_h' => 'd', 'phone_h' => '', 'addr_h' => '' ),
) );
eq( count( $rings ), 1, 'one ring' );
sort( $rings[0] );
eq( $rings[0], array( 1, 2, 3 ), 'transitive ring' );
echo "ok\n";
