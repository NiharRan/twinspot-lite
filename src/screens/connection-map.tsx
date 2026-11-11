import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Background, Handle, Position, ReactFlow, ReactFlowProvider, useNodesState, useReactFlow, type Edge, type Node, type NodeProps } from '@xyflow/react';
import { forceCenter, forceCollide, forceLink, forceManyBody, forceSimulation, type SimulationNodeDatum } from 'd3-force';
import { AtSign, Home, Maximize, Minimize, Network, Phone, RefreshCw, ShoppingCart } from 'lucide-react';
import type { LinkType, RingDetail } from '../types';

const HUB_ICON: Record< string, typeof AtSign > = { email: AtSign, phone: Phone, address: Home };
const HUB_LABEL: Record< string, string > = {
	email: __( 'Same email', 'twinspot-lite' ),
	phone: __( 'Same phone', 'twinspot-lite' ),
	address: __( 'Same address', 'twinspot-lite' ),
};

const hidden = { opacity: 0, pointerEvents: 'none' as const };

function OrderNode( { data }: NodeProps< Node< { label: string } > > ) {
	return (
		<div className="flex flex-col items-center gap-1">
			<Handle type="target" position={ Position.Top } style={ hidden } />
			<span className="flex h-11 w-11 items-center justify-center rounded-full bg-primary-tint text-primary shadow-sm"><ShoppingCart size={ 20 } /></span>
			<span className="tabular rounded bg-white px-1.5 text-[11px] font-semibold text-ink shadow-sm">{ data.label }</span>
			<Handle type="source" position={ Position.Top } style={ hidden } />
		</div>
	);
}

function HubNode( { data }: NodeProps< Node< { type: string; label?: string; count: number; top: boolean } > > ) {
	const Icon = HUB_ICON[ data.type ] ?? Network;
	return (
		<div className="flex flex-col items-center gap-1">
			<Handle type="target" position={ Position.Top } style={ hidden } />
			<span className={ `flex items-center justify-center rounded-full shadow-sm ${ data.top ? 'h-14 w-14 bg-accent-soft text-[#B45309] ring-2 ring-accent' : 'h-11 w-11 bg-primary-tint text-primary' }` }>
				<Icon size={ data.top ? 24 : 20 } />
			</span>
			<span className={ `whitespace-nowrap rounded bg-white px-1.5 text-[11px] font-semibold shadow-sm ${ data.top ? 'text-[#B45309]' : 'text-ink' }` }>{ data.label ?? HUB_LABEL[ data.type ] ?? data.type } · { data.count }</span>
			<Handle type="source" position={ Position.Top } style={ hidden } />
		</div>
	);
}

const nodeTypes = { order: OrderNode, hub: HubNode };

/** Force layout run to rest up front, so the graph opens still instead of jiggling. */
function layout( ring: RingDetail ): Node[] {
	type N = SimulationNodeDatum & { id: string };
	const sim: N[] = [ ...ring.orders.map( ( o ) => ( { id: `o${ o.edge_id }` } ) ), ...ring.hubs.map( ( h ) => ( { id: h.id } ) ) ];
	const links = ring.edges.map( ( [ o, h ] ) => ( { source: `o${ o }`, target: h } ) );
	forceSimulation( sim )
		.force( 'link', forceLink< N, { source: string; target: string } >( links ).id( ( n ) => n.id ).distance( 130 ) )
		.force( 'charge', forceManyBody().strength( -420 ) )
		.force( 'collide', forceCollide( 50 ) )
		.force( 'center', forceCenter( 0, 0 ) )
		.stop()
		.tick( 300 );
	const pos = new Map( sim.map( ( n ) => [ n.id, { x: n.x ?? 0, y: n.y ?? 0 } ] ) );
	return [
		...ring.orders.map( ( o ): Node => ( { id: `o${ o.edge_id }`, type: 'order', position: pos.get( `o${ o.edge_id }` )!, data: { label: `#${ o.id }` } } ) ),
		...ring.hubs.map( ( h ): Node => ( { id: h.id, type: 'hub', position: pos.get( h.id )!, data: { type: h.type, label: h.label, count: h.count, top: h.top } } ) ),
	];
}

function GraphView( { ring, onRefresh }: { ring: RingDetail; onRefresh: () => Promise< void > } ) {
	const { fitView } = useReactFlow();
	const initial = useMemo( () => layout( ring ), [ ring ] );
	const [ nodes, setNodes, onNodesChange ] = useNodesState( initial );
	const edges = useMemo< Edge[] >(
		() =>
			ring.edges.map( ( [ o, h ] ) => {
				const hub = ring.hubs.find( ( x ) => x.id === h );
				const top = hub?.top;
				return {
					id: `${ o }-${ h }`,
					source: `o${ o }`,
					target: h,
					style: hub?.dashed ? { stroke: '#94A3B8', strokeWidth: 1.5, strokeDasharray: '3 4' } : top ? { stroke: '#F59E0B', strokeWidth: 2.5, strokeDasharray: '6 4' } : { stroke: '#0F766E', strokeWidth: 2 },
				};
			} ),
		[ ring ]
	);
	const box = useRef< HTMLDivElement >( null );
	const [ full, setFull ] = useState( false );
	const [ refreshing, setRefreshing ] = useState( false );

	// Runs on mount and whenever fresh ring data arrives.
	useEffect( () => {
		setNodes( layout( ring ) );
		const t = window.setTimeout( () => fitView( { padding: 0.2 } ), 0 );
		return () => window.clearTimeout( t );
	}, [ ring, setNodes, fitView ] );

	useEffect( () => {
		const sync = () => {
			setFull( document.fullscreenElement === box.current );
			window.setTimeout( () => fitView( { padding: 0.2 } ), 50 ); // Canvas size changed.
		};
		document.addEventListener( 'fullscreenchange', sync );
		return () => document.removeEventListener( 'fullscreenchange', sync );
	}, [ fitView ] );

	const toggleFull = () => ( document.fullscreenElement ? document.exitFullscreen() : box.current?.requestFullscreen() );
	const refresh = useCallback( async () => {
		setRefreshing( true );
		await onRefresh();
		setRefreshing( false );
	}, [ onRefresh ] );
	const tool = 'rounded p-1 text-muted hover:bg-white hover:text-ink';

	return (
		<div ref={ box } className={ full ? 'flex h-full flex-col bg-white p-6' : '' }>
			<div className="mb-3 flex items-start justify-between">
				<div>
					<h2 className="flex items-center gap-1 text-[20px] font-semibold leading-7 text-ink"><Network size={ 20 } className="text-primary" />{ __( 'Connection map', 'twinspot-lite' ) }</h2>
					<p className="mt-0.5 text-[13px] leading-[18px] text-muted">{ sprintf( __( 'How these %d orders and customer identifiers intersect', 'twinspot-lite' ), ring.size ) }</p>
				</div>
				<div className="flex items-center gap-1 rounded-lg bg-info p-1">
					<button type="button" className={ tool } title={ __( 'Refresh', 'twinspot-lite' ) } aria-label={ __( 'Refresh', 'twinspot-lite' ) } onClick={ refresh } disabled={ refreshing }><RefreshCw size={ 18 } className={ refreshing ? 'animate-spin' : '' } /></button>
					<button type="button" className={ tool } title={ full ? __( 'Exit full screen', 'twinspot-lite' ) : __( 'Full screen', 'twinspot-lite' ) } aria-label={ full ? __( 'Exit full screen', 'twinspot-lite' ) : __( 'Full screen', 'twinspot-lite' ) } onClick={ toggleFull }>{ full ? <Minimize size={ 18 } /> : <Maximize size={ 18 } /> }</button>
				</div>
			</div>
			<div className={ `overflow-hidden rounded-xl bg-canvas ${ full ? 'min-h-0 flex-1' : 'h-[520px]' }` } role="img" aria-label={ __( 'Graph of orders and the details they share', 'twinspot-lite' ) }>
				<ReactFlow nodes={ nodes } edges={ edges } nodeTypes={ nodeTypes } onNodesChange={ onNodesChange } nodesConnectable={ false } fitView proOptions={ { hideAttribution: true } } minZoom={ 0.3 } style={ { background: 'transparent' } }>
					<Background gap={ 20 } size={ 1.5 } color="#94A3B8" style={ { opacity: 0.4 } } />
				</ReactFlow>
			</div>
		</div>
	);
}

export function ConnectionMap( { ring, onRefresh }: { ring: RingDetail; onRefresh: () => Promise< void > } ) {
	return (
		<ReactFlowProvider>
			<GraphView ring={ ring } onRefresh={ onRefresh } />
		</ReactFlowProvider>
	);
}
