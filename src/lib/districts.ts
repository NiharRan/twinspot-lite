import { forceCenter, forceCollide, forceLink, forceManyBody, forceSimulation, type SimulationNodeDatum } from 'd3-force';
import type { RingDetail } from '../types';

const RING_STEP = 90; // Radius of the first order ring, and the step to the next.
const NODE_GAP = 70; // Minimum arc length between neighbouring orders on a ring.
const DISTRICT_GAP = 140; // Free space between two districts.
const BRIDGE_SPREAD = 60; // Spacing between bridge orders that share the same hubs.

type Point = { x: number; y: number };
type Hub = SimulationNodeDatum & { id: string; radius: number };

/** Orders that fit on ring `k` (0-based) of a district. */
const ringCapacity = ( k: number ) => Math.max( 1, Math.floor( ( 2 * Math.PI * RING_STEP * ( k + 1 ) ) / NODE_GAP ) );

/** Outer radius of a district holding `n` orders. */
function districtRadius( n: number ): number {
	let k = 0;
	for ( let left = n - ringCapacity( 0 ); left > 0; left -= ringCapacity( ++k ) );
	return RING_STEP * ( k + 1 );
}

/**
 * City-style layout: every hub is a district, its own orders sit on rings around it, and an order tied
 * to several hubs is a bridge placed between them. Keys are hub ids and `o<edge_id>` for orders.
 */
export function districtPositions( ring: RingDetail ): Map< string, Point > {
	const known = new Set( ring.hubs.map( ( h ) => h.id ) );
	const hubsOf = new Map< number, string[] >();
	for ( const [ o, h ] of ring.edges ) {
		if ( known.has( h ) ) hubsOf.set( o, [ ...( hubsOf.get( o ) ?? [] ), h ] );
	}

	const members = new Map< string, number[] >( ring.hubs.map( ( h ) => [ h.id, [] ] ) );
	const bridges: [ number, string[] ][] = [];
	for ( const o of ring.orders ) {
		const hs = hubsOf.get( o.edge_id ) ?? [];
		if ( hs.length === 1 ) members.get( hs[ 0 ] )!.push( o.edge_id );
		else if ( hs.length > 1 ) bridges.push( [ o.edge_id, hs ] );
	}

	// District centres: simulate hubs only, pulling districts that share a bridge together.
	const sim: Hub[] = ring.hubs.map( ( h ) => ( { id: h.id, radius: districtRadius( members.get( h.id )!.length ) } ) );
	const byId = new Map( sim.map( ( h ) => [ h.id, h ] ) );
	const links: { source: string; target: string }[] = [];
	for ( const [ , hs ] of bridges ) {
		for ( let i = 1; i < hs.length; i++ ) links.push( { source: hs[ 0 ], target: hs[ i ] } );
	}
	forceSimulation( sim )
		.force( 'link', forceLink< Hub, { source: string; target: string } >( links ).id( ( h ) => h.id ).distance( ( l ) => {
			const a = byId.get( l.source as unknown as string ) ?? ( l.source as unknown as Hub );
			const b = byId.get( l.target as unknown as string ) ?? ( l.target as unknown as Hub );
			return a.radius + b.radius + DISTRICT_GAP + BRIDGE_SPREAD;
		} ) )
		.force( 'charge', forceManyBody().strength( -800 ) )
		.force( 'collide', forceCollide< Hub >( ( h ) => h.radius + DISTRICT_GAP / 2 ).iterations( 4 ) )
		.force( 'center', forceCenter( 0, 0 ) )
		.stop()
		.tick( 300 );

	const pos = new Map< string, Point >();
	for ( const h of sim ) pos.set( h.id, { x: h.x ?? 0, y: h.y ?? 0 } );

	// Members: evenly spaced on rings around their hub.
	for ( const [ hub, ids ] of members ) {
		const c = pos.get( hub )!;
		let placed = 0;
		for ( let k = 0; placed < ids.length; k++ ) {
			const n = Math.min( ringCapacity( k ), ids.length - placed );
			const r = RING_STEP * ( k + 1 );
			for ( let i = 0; i < n; i++ ) {
				const a = ( 2 * Math.PI * i ) / n + k * 0.5; // Offset each ring so rows do not line up.
				pos.set( `o${ ids[ placed + i ] }`, { x: c.x + r * Math.cos( a ), y: c.y + r * Math.sin( a ) } );
			}
			placed += n;
		}
	}

	// Bridges: mean of their hubs, fanned out sideways when several share the same hubs.
	const groups = new Map< string, number[] >();
	for ( const [ o, hs ] of bridges ) groups.set( hs.join( '|' ), [ ...( groups.get( hs.join( '|' ) ) ?? [] ), o ] );
	for ( const [ key, ids ] of groups ) {
		const cs = key.split( '|' ).map( ( h ) => pos.get( h )! );
		const mx = cs.reduce( ( s, c ) => s + c.x, 0 ) / cs.length;
		const my = cs.reduce( ( s, c ) => s + c.y, 0 ) / cs.length;
		const dx = cs[ 1 ].x - cs[ 0 ].x;
		const dy = cs[ 1 ].y - cs[ 0 ].y;
		const len = Math.hypot( dx, dy ) || 1;
		ids.forEach( ( o, i ) => {
			const off = ( i - ( ids.length - 1 ) / 2 ) * BRIDGE_SPREAD;
			pos.set( `o${ o }`, { x: mx - ( dy / len ) * off, y: my + ( dx / len ) * off } );
		} );
	}

	// Orders with no known hub still need a spot.
	for ( const o of ring.orders ) if ( ! pos.has( `o${ o.edge_id }` ) ) pos.set( `o${ o.edge_id }`, { x: 0, y: 0 } );
	return pos;
}
