<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/autoload.php';
use Twinspot_Lite\Core\Normalize;
use Twinspot_Lite\Core\Rings;
function eq( $a, $b, $m ) { if ( $a !== $b ) { fwrite( STDERR, "FAIL $m: " . var_export( $a, true ) . "\n" ); exit( 1 ); } }

eq( Normalize::email( 'John.Doe+x@Gmail.com' ), 'johndoe@gmail.com', 'gmail alias' );
eq( Normalize::email( 'a.b+c@corp.com' ), 'a.b@corp.com', 'non-gmail keeps dots' );
eq( Normalize::email( 'nope' ), '', 'bad email' );
eq( Normalize::phone( '+1 (555) 123-4567' ), '5551234567', 'phone' );
eq( Normalize::phone( '12' ), '', 'short phone' );
eq( Normalize::address( '12 Main St.', 'ab-1 2cd' ), '12mainst|ab12cd', 'addr' );
eq( Normalize::address( '12 Main St', '' ), '', 'addr needs postcode' );

// multi-hop: 1-2 share phone, 2-3 share addr, 4 alone
$rings = Rings::find( array(
	array( 'order_id' => 1, 'email_h' => 'a', 'phone_h' => 'p', 'addr_h' => '' ),
	array( 'order_id' => 2, 'email_h' => 'b', 'phone_h' => 'p', 'addr_h' => 'x' ),
	array( 'order_id' => 3, 'email_h' => 'c', 'phone_h' => '', 'addr_h' => 'x' ),
	array( 'order_id' => 4, 'email_h' => 'd', 'phone_h' => '', 'addr_h' => '' ),
) );
eq( count( $rings ), 1, 'one ring' );
sort( $rings[0] );
eq( $rings[0], array( 1, 2, 3 ), 'transitive ring' );
echo "ok\n";
// Linked-by: email shared by 1-2, address shared by 2-3, phone unique.
eq(
	Rings::links( array(
		array( 'email_h' => 'a', 'phone_h' => '1', 'addr_h' => '' ),
		array( 'email_h' => 'a', 'phone_h' => '2', 'addr_h' => 'x' ),
		array( 'email_h' => 'c', 'phone_h' => '3', 'addr_h' => 'x' ),
	) ),
	array( 'email', 'address' ),
	'ring links'
);
echo "ok links\n";

eq(
	Rings::shared( array( 'email_h' => 'z', 'phone_h' => 'p', 'addr_h' => 'x' ), array(
		array( 'email_h' => 'a', 'phone_h' => 'p', 'addr_h' => '' ),
		array( 'email_h' => 'b', 'phone_h' => '', 'addr_h' => 'x' ),
	) ),
	array( 'phone', 'address' ),
	'shared links'
);
eq( Rings::shared( array( 'email_h' => '', 'phone_h' => '', 'addr_h' => '' ), array( array( 'email_h' => '', 'phone_h' => '', 'addr_h' => '' ) ) ), array(), 'empty hashes match nothing' );
eq( \Twinspot_Lite\Core\Csv::row( array( 'a"b', '=1+1', 5 ) ), "\"a\"\"b\",\"'=1+1\",\"5\"\r\n", 'csv row' );
echo "ok shared+csv\n";
