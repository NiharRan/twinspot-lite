import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { motion } from 'motion/react';
import {
	AlertTriangle, ArrowRight, AtSign, CheckCircle2, ChevronLeft, ChevronRight, Home, Link2, Lock, MapPin,
	Network, Phone, ReceiptText, RefreshCw, RotateCcw, Search, ShieldCheck, TicketCheck, X,
} from 'lucide-react';
import { Button } from '../components/ui/button';
import { Card } from '../components/ui/card';
import { Badge } from '../components/ui/badge';
import { Input, Select } from '../components/ui/input';
import type { Filters, LinkType, Overview as OverviewData } from '../types';

const LINK_ICON: Record< LinkType, typeof AtSign > = { email: AtSign, phone: Phone, address: Home };
const LINK_LABEL: Record< LinkType, string > = {
	email: __( 'Email', 'twinspot-lite' ),
	phone: __( 'Phone', 'twinspot-lite' ),
	address: __( 'Address', 'twinspot-lite' ),
};

const fade = { initial: { opacity: 0, y: 8 }, animate: { opacity: 1, y: 0 }, transition: { duration: 0.25 } };

function Stat( { label, value, icon: Icon, tone, index }: { label: string; value: number; icon: typeof AtSign; tone: string; index: number } ) {
	return (
		<motion.div { ...fade } transition={ { ...fade.transition, delay: index * 0.06 } }>
			<Card className="p-5">
				<div className="flex items-center justify-between text-muted">
					<span>{ label }</span>
					<span className={ `flex h-9 w-9 items-center justify-center rounded-lg ${ tone }` }>
						<Icon size={ 18 } />
					</span>
				</div>
				<div className="tabular mt-3 text-[32px] font-semibold leading-10">{ value }</div>
			</Card>
		</motion.div>
	);
}

function SizeBadge( { size }: { size: number } ) {
	const label = sprintf( _n( '%d order', '%d orders', size, 'twinspot-lite' ), size );
	if ( size >= 5 ) {
		return <Badge tone="danger"><AlertTriangle size={ 14 } />{ label }</Badge>;
	}
	if ( size === 4 ) {
		return <Badge tone="warn"><AlertTriangle size={ 14 } />{ label }</Badge>;
	}
	return <Badge tone="neutral"><Link2 size={ 14 } />{ label }</Badge>;
}

interface TableProps {
	data: OverviewData;
	filters: Filters;
	setFilters: ( f: Partial< Filters > ) => void;
	setPage: ( p: number ) => void;
}

function RingsTable( { data, filters, setFilters, setPage }: TableProps ) {
	const { rings, total, total_pages: pages } = data;
	const reset = () => setFilters( { search: '', min_size: 2, link: '' } );
	return (
		<Card className="overflow-hidden">
			<div className="flex flex-wrap items-center gap-3 p-5">
				<div className="relative min-w-[240px] flex-1">
					<Search size={ 16 } className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
					<Input
						className="w-full pl-9"
						placeholder={ __( 'Search by order number or email…', 'twinspot-lite' ) }
						value={ filters.search }
						onChange={ ( e ) => setFilters( { search: e.target.value } ) }
						aria-label={ __( 'Search rings', 'twinspot-lite' ) }
					/>
				</div>
				<Select value={ filters.min_size } onChange={ ( e ) => setFilters( { min_size: Number( e.target.value ) } ) } aria-label={ __( 'Ring size', 'twinspot-lite' ) }>
					<option value={ 2 }>{ __( 'All ring sizes (2+ orders)', 'twinspot-lite' ) }</option>
					<option value={ 3 }>{ __( '3+ orders', 'twinspot-lite' ) }</option>
					<option value={ 4 }>{ __( '4+ orders', 'twinspot-lite' ) }</option>
					<option value={ 5 }>{ __( '5+ orders', 'twinspot-lite' ) }</option>
				</Select>
				<Select value={ filters.link } onChange={ ( e ) => setFilters( { link: e.target.value as Filters[ 'link' ] } ) } aria-label={ __( 'Linked by', 'twinspot-lite' ) }>
					<option value="">{ __( 'All links', 'twinspot-lite' ) }</option>
					{ ( Object.entries( LINK_LABEL ) as [ LinkType, string ][] ).map( ( [ k, v ] ) => <option key={ k } value={ k }>{ v }</option> ) }
				</Select>
				<Button variant="ghost" onClick={ reset }><RotateCcw size={ 16 } />{ __( 'Reset', 'twinspot-lite' ) }</Button>
			</div>

			<table>
				<thead>
					<tr className="bg-info text-left text-[11px] font-semibold uppercase tracking-wider text-muted">
						<th className="px-5 py-3">{ __( 'Ring size', 'twinspot-lite' ) }</th>
						<th className="px-5 py-3">{ __( 'Linked by', 'twinspot-lite' ) }</th>
						<th className="px-5 py-3">{ __( 'Orders', 'twinspot-lite' ) }</th>
						<th className="px-5 py-3 text-right">{ __( 'Total spent', 'twinspot-lite' ) }</th>
						<th className="px-5 py-3">{ __( 'Last order', 'twinspot-lite' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ rings.length === 0 && (
						<tr><td colSpan={ 5 } className="px-5 py-10 text-center text-muted">{ __( 'No rings match these filters.', 'twinspot-lite' ) }</td></tr>
					) }
					{ rings.map( ( ring, i ) => (
						<motion.tr
							key={ ring.orders.map( ( o ) => o.id ).join( '-' ) }
							initial={ { opacity: 0 } }
							animate={ { opacity: 1 } }
							transition={ { delay: i * 0.03 } }
							className="border-t border-line hover:bg-primary-soft"
						>
							<td className="px-5 py-4"><SizeBadge size={ ring.size } /></td>
							<td className="px-5 py-4">
								<div className="flex flex-wrap gap-1.5">
									{ ring.links.map( ( l ) => {
										const Icon = LINK_ICON[ l ];
										return <Badge key={ l } tone="quiet" className="py-0.5 text-[12px]"><Icon size={ 13 } />{ LINK_LABEL[ l ] }</Badge>;
									} ) }
								</div>
							</td>
							<td className="tabular px-5 py-4">
								{ ring.orders.map( ( o, n ) => (
									<span key={ o.id }>
										{ n > 0 && ', ' }
										<a className="text-primary hover:underline" href={ o.url }>#{ o.id }</a>
									</span>
								) ) }
							</td>
							<td className="tabular px-5 py-4 text-right">{ ring.total }</td>
							<td className="px-5 py-4 text-muted">
								{ ring.last_order ? new Date( ring.last_order ).toLocaleDateString() : '—' }
							</td>
						</motion.tr>
					) ) }
				</tbody>
			</table>

			<div className="flex items-center justify-between border-t border-line p-5 text-muted">
				<span>{ sprintf( __( 'Showing %1$d of %2$d rings', 'twinspot-lite' ), rings.length, total ) }</span>
				{ pages > 1 && (
					<div className="flex items-center gap-1">
						<Button variant="ghost" disabled={ filters.page <= 1 } onClick={ () => setPage( filters.page - 1 ) } aria-label={ __( 'Previous page', 'twinspot-lite' ) }><ChevronLeft size={ 16 } /></Button>
						<span className="tabular px-2">{ filters.page } / { pages }</span>
						<Button variant="ghost" disabled={ filters.page >= pages } onClick={ () => setPage( filters.page + 1 ) } aria-label={ __( 'Next page', 'twinspot-lite' ) }><ChevronRight size={ 16 } /></Button>
					</div>
				) }
			</div>
		</Card>
	);
}

function EmptyState( { onImport, importing }: { onImport: () => void; importing: boolean } ) {
	const perks = [
		{ icon: AtSign, title: __( 'Checks email aliases', 'twinspot-lite' ), text: __( 'Catches Gmail dots and +tags, so john.doe+deal@gmail.com counts as john’s address.', 'twinspot-lite' ) },
		{ icon: MapPin, title: __( 'Matches delivery addresses', 'twinspot-lite' ), text: __( 'Links orders that ship to the same street address and postcode.', 'twinspot-lite' ) },
		{ icon: Lock, title: __( '100% private to your store', 'twinspot-lite' ), text: __( 'Everything stays in your own database as one-way hashes.', 'twinspot-lite' ) },
	];
	return (
		<Card className="p-10 text-center">
			<motion.div { ...fade } className="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-primary-tint text-primary">
				<ShieldCheck size={ 40 } />
			</motion.div>
			<h2 className="text-[20px] font-semibold leading-7">{ __( 'No rings found yet', 'twinspot-lite' ) }</h2>
			<p className="mx-auto mt-2 max-w-xl text-[15px] leading-[22px] text-muted">
				{ __( 'Twinspot checks new orders automatically. When shoppers reuse the same email, phone or address, linked orders appear here. Import your past orders to see rings from day one.', 'twinspot-lite' ) }
			</p>
			<div className="mt-6 flex justify-center">
				<Button onClick={ onImport } disabled={ importing }>
					<RefreshCw size={ 16 } className={ importing ? 'animate-spin' : '' } />
					{ importing ? __( 'Importing…', 'twinspot-lite' ) : __( 'Import last 12 months of orders', 'twinspot-lite' ) }
				</Button>
			</div>
			<div className="mt-8 grid gap-6 rounded-xl bg-info p-6 text-left md:grid-cols-3">
				{ perks.map( ( p ) => (
					<div key={ p.title } className="flex gap-3">
						<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-tint text-primary"><p.icon size={ 18 } /></span>
						<div>
							<div className="font-semibold">{ p.title }</div>
							<p className="mt-1 text-[13px] leading-[18px] text-muted">{ p.text }</p>
						</div>
					</div>
				) ) }
			</div>
		</Card>
	);
}

interface Props extends Omit< TableProps, 'data' > {
	data: OverviewData | null;
	error: string;
	importing: boolean;
	imported: number | null;
	dismissImported: () => void;
	onImport: () => void;
}

export function Overview( { data, error, filters, setFilters, setPage, importing, imported, dismissImported, onImport }: Props ) {
	const [ hideInfo, setHideInfo ] = useState( () => window.localStorage.getItem( 'twinspotLiteHideInfo' ) === '1' );
	const stats = data?.stats;
	const filtering = filters.search || filters.link || filters.min_size > 2;
	const empty = !! data && data.stats.rings === 0 && ! filtering;

	return (
		<div className="flex flex-col gap-5">
			<div className="flex flex-wrap items-start justify-between gap-4">
				<div>
					<h1 className="text-[24px] font-semibold leading-8">Twinspot</h1>
					<p className="mt-1 text-[15px] text-muted">{ __( 'Groups of orders linked by the same email, phone or address', 'twinspot-lite' ) }</p>
				</div>
				<div className="flex items-center gap-3">
					{ stats && (
						<Badge tone="info" className="h-9 px-4 text-[14px] font-medium">
							{ __( 'Orders indexed:', 'twinspot-lite' ) } <span className="tabular font-semibold">{ stats.indexed.toLocaleString() }</span>
						</Badge>
					) }
					{ ! empty && (
						<Button variant="secondary" onClick={ onImport } disabled={ importing }>
							<RefreshCw size={ 16 } className={ importing ? 'animate-spin' : '' } />
							{ importing ? __( 'Importing…', 'twinspot-lite' ) : __( 'Import past orders', 'twinspot-lite' ) }
						</Button>
					) }
				</div>
			</div>

			{ error && (
				<div role="alert" className="flex items-center gap-3 rounded-xl border border-danger-line bg-danger-soft p-4 text-danger">
					<AlertTriangle size={ 18 } />{ error }
				</div>
			) }
			{ imported !== null && (
				<motion.div { ...fade } role="status" className="flex items-center gap-3 rounded-xl border border-success-line bg-success-soft p-4">
					<CheckCircle2 size={ 20 } className="text-success" />
					<div className="flex-1"><strong>{ sprintf( _n( 'Indexed %d past order.', 'Indexed %d past orders.', imported, 'twinspot-lite' ), imported ) }</strong></div>
					<button onClick={ dismissImported } aria-label={ __( 'Dismiss', 'twinspot-lite' ) }><X size={ 16 } /></button>
				</motion.div>
			) }
			{ ! hideInfo && (
				<div className="flex items-center gap-3 rounded-xl bg-info p-4">
					<ShieldCheck size={ 18 } className="text-primary" />
					<p className="flex-1"><strong>Twinspot Lite</strong> { __( 'runs on your store database. No customer data leaves your server.', 'twinspot-lite' ) }</p>
					<button onClick={ () => { window.localStorage.setItem( 'twinspotLiteHideInfo', '1' ); setHideInfo( true ); } } aria-label={ __( 'Dismiss', 'twinspot-lite' ) }><X size={ 16 } /></button>
				</div>
			) }

			{ ! data && ! error && <Card className="p-10 text-center text-muted">{ __( 'Loading…', 'twinspot-lite' ) }</Card> }

			{ data && ! empty && (
				<div className="grid gap-5 md:grid-cols-3">
					<Stat index={ 0 } label={ __( 'Rings found', 'twinspot-lite' ) } value={ data.stats.rings } icon={ Network } tone="bg-info text-primary" />
					<Stat index={ 1 } label={ __( 'Orders in rings', 'twinspot-lite' ) } value={ data.stats.orders_in_rings } icon={ ReceiptText } tone="bg-info text-primary" />
					<Stat index={ 2 } label={ __( 'Coupons blocked (30 days)', 'twinspot-lite' ) } value={ data.stats.blocked_30d } icon={ TicketCheck } tone="bg-primary-tint text-primary" />
				</div>
			) }
			{ data && ( empty ? <EmptyState onImport={ onImport } importing={ importing } /> : <RingsTable data={ data } filters={ filters } setFilters={ setFilters } setPage={ setPage } /> ) }
		</div>
	);
}
