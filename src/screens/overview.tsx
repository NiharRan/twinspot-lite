import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { motion } from 'motion/react';
import {
	AlertTriangle, ArrowRight, Hash, AtSign, CheckCircle2, ChevronLeft, ChevronRight, Home, Link2, Lock, MapPin,
	Network, Phone, ReceiptText, RefreshCw, RotateCcw, Search, ShieldCheck, TicketCheck, Users, User, ShoppingCart, Check, FlaskConical, X,
} from 'lucide-react';
import { Button } from '../components/ui/button';
import { Card } from '../components/ui/card';
import { Spinner } from '../components/ui/spinner';
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

function Stat( { label, value, icon: Icon, tone, index, hint }: { label: string; value: number; icon: typeof AtSign; tone: string; index: number; hint?: string } ) {
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
					{ hint && <p className="mt-1 text-[13px] text-muted">{ hint }</p> }
			</Card>
		</motion.div>
	);
}

function lastOrder( iso: string ): string {
	const days = Math.round( ( new Date( iso ).getTime() - Date.now() ) / 864e5 );
	return days > -7 ? new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } ).format( days, 'day' ) : new Date( iso ).toLocaleDateString();
}

function SizeBadge( { size }: { size: number } ) {
	const label = sprintf( _n( '%d order', '%d orders', size, 'twinspot-lite' ), size );
	if ( size >= 5 ) {
		return <Badge tone="danger"><AlertTriangle size={ 14 } />{ label }</Badge>;
	}
	if ( size === 4 ) {
		return <Badge tone="warn"><AlertTriangle size={ 14 } />{ label }</Badge>;
	}
	return size === 3 ? <Badge tone="quiet"><Link2 size={ 14 } />{ label }</Badge> : <Badge tone="neutral"><Hash size={ 14 } />{ label }</Badge>;
}

export interface TableProps {
	data: OverviewData;
	filters: Filters;
	setFilters: ( f: Partial< Filters > ) => void;
	setPage: ( p: number ) => void;
	onOpen: ( key: number ) => void;
}

export function RingsTable( { data, filters, setFilters, setPage, onOpen }: TableProps ) {
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
							<th className="px-5 py-3 text-right">{ __( 'Action', 'twinspot-lite' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ rings.length === 0 && (
						<tr><td colSpan={ 6 } className="px-5 py-10 text-center text-muted">{ __( 'No rings match these filters.', 'twinspot-lite' ) }</td></tr>
					) }
					{ rings.map( ( ring, i ) => (
						<motion.tr
							key={ ring.orders.map( ( o ) => o.id ).join( '-' ) }
							initial={ { opacity: 0 } }
							animate={ { opacity: 1 } }
							transition={ { delay: i * 0.03 } }
							className="border-t border-line hover:bg-primary-soft"
						>
							<td className="px-5 py-4"><div className="flex items-center gap-2"><SizeBadge size={ ring.size } />{ ring.sample && <Badge tone="info">{ __( 'Sample', 'twinspot-lite' ) }</Badge> }</div></td>
							<td className="px-5 py-4">
								<div className="flex flex-wrap gap-1.5">
									{ ring.links.map( ( l ) => {
										const Icon = LINK_ICON[ l ];
										return <Badge key={ l } tone="quiet" className="py-0.5 text-[12px]"><Icon size={ 13 } />{ ring.links.length === 1 ? sprintf( __( '%s only', 'twinspot-lite' ), LINK_LABEL[ l ] ) : LINK_LABEL[ l ] }</Badge>;
									} ) }
								</div>
							</td>
							<td className="tabular px-5 py-4">
								{ ring.orders.map( ( o, n ) => (
									<span key={ o.id }>
										{ n > 0 && ', ' }
										{ o.url ? <a className="text-primary hover:underline" href={ o.url }>#{ o.id }</a> : <span>#{ o.id }</span> }
									</span>
								) ) }
							</td>
							<td className="tabular px-5 py-4 text-right">{ ring.total }</td>
							<td className="px-5 py-4 text-muted">
								{ ring.last_order ? lastOrder( ring.last_order ) : '—' }
							</td>
							<td className="px-5 py-4 text-right">
								<Button variant="ghost" onClick={ () => onOpen( ring.key ) }>{ __( 'View map', 'twinspot-lite' ) }<ArrowRight size={ 14 } /></Button>
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

function ProgressBar( { value }: { value: number } ) {
	const pct = Math.round( value * 100 );
	return (
		<div className="mx-auto w-full max-w-md" role="progressbar" aria-valuemin={ 0 } aria-valuemax={ 100 } aria-valuenow={ pct } aria-label={ __( 'Import progress', 'twinspot-lite' ) }>
			<div className="h-2 overflow-hidden rounded-full bg-primary-tint">
				<motion.div className="h-full rounded-full bg-primary" initial={ false } animate={ { width: `${ pct }%` } } transition={ { type: 'tween', ease: 'easeOut', duration: 0.5 } } />
			</div>
			<div className="tabular mt-2 text-[13px] text-muted">{ sprintf( __( 'Indexing orders… %d%%', 'twinspot-lite' ), pct ) }</div>
		</div>
	);
}

function EmptyState( { onImport, onSample, importing, progress }: { onImport: () => void; onSample: ( on: boolean ) => void; importing: boolean; progress: number } ) {
	const perks = [
		{ icon: AtSign, title: __( 'Checks email aliases & typos', 'twinspot-lite' ), text: __( 'Catches Gmail dots and +tags, so john.doe+deal@gmail.com counts as john’s address.', 'twinspot-lite' ) },
		{ icon: MapPin, title: __( 'Matches delivery addresses', 'twinspot-lite' ), text: __( 'Links orders that ship to the same street address and postcode.', 'twinspot-lite' ) },
		{ icon: Lock, title: __( '100% private to your store', 'twinspot-lite' ), text: __( 'Everything stays in your own database as one-way hashes.', 'twinspot-lite' ) },
	];
	return (
		<Card className="p-10 text-center">
			<motion.div { ...fade } aria-hidden="true" className="relative mx-auto mb-6 flex h-40 w-full max-w-md items-center justify-center">
				<div className="absolute inset-0 rounded-full bg-[radial-gradient(circle,#CCFBF1_0%,rgba(240,253,250,0.6)_45%,transparent_70%)]" />
				{ [ 'left-[18%] top-[12%]', 'left-1/2 top-[4%]', 'right-[18%] top-[12%]', 'left-[18%] bottom-[8%]', 'right-[18%] bottom-[8%]' ].map( ( pos ) => (
					<span key={ pos } className={ `absolute h-1 w-1 rounded-full bg-primary/30 ${ pos }` } />
				) ) }
				<div className="relative flex items-center">
					<span className="relative flex h-16 w-16 items-center justify-center rounded-2xl border border-line bg-white text-primary shadow-sm">
						<User size={ 28 } />
						<span className="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-white">%</span>
					</span>
					<span className="-mx-2 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-dashed border-primary/40 bg-white text-primary"><ShieldCheck size={ 18 } /></span>
					<span className="relative flex h-16 w-16 items-center justify-center rounded-2xl border border-line bg-white text-primary shadow-sm">
						<ShoppingCart size={ 28 } />
						<span className="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-white"><Check size={ 12 } strokeWidth={ 3 } /></span>
					</span>
				</div>
			</motion.div>
			<h2 className="text-[20px] font-semibold leading-7">{ __( 'No rings found yet', 'twinspot-lite' ) }</h2>
			<p className="mx-auto mt-2 max-w-xl text-[15px] leading-[22px] text-muted">
				{ __( 'Twinspot analyzes your incoming orders automatically. When shoppers try to reuse welcome discount codes with slight variations of their email, phone or delivery address, they will appear here.', 'twinspot-lite' ) }
			</p>
			<div className="mt-6">
				{ importing ? <ProgressBar value={ progress } /> : (
					<div className="flex flex-wrap justify-center gap-3">
						<Button onClick={ onImport }>
							<RefreshCw size={ 16 } />{ __( 'Import last 12 months of orders', 'twinspot-lite' ) }
						</Button>
						<Button variant="secondary" className="bg-info" onClick={ () => onSample( true ) }>
							<FlaskConical size={ 16 } />{ __( 'Test with sample order data', 'twinspot-lite' ) }
						</Button>
					</div>
				) }
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
	progress: number;
	imported: { n: number; seconds: number } | null;
	dismissImported: () => void;
	onImport: () => void;
	onSample: ( on: boolean ) => void;
}

export function Overview( { data, error, filters, setFilters, setPage, onOpen, importing, progress, imported, dismissImported, onImport, onSample }: Props ) {
	const [ hideInfo, setHideInfo ] = useState( () => window.localStorage.getItem( 'twinspotLiteHideInfo' ) === '1' );
	const stats = data?.stats;
	const filtering = filters.search || filters.link || filters.min_size > 2;
	const empty = !! data && data.stats.rings === 0 && ! filtering;

	return (
		<div className="flex flex-col gap-5">
			<div className="flex flex-wrap items-start justify-between gap-4">
				<div>
					<h1 className="flex items-center gap-3 text-[24px] font-semibold leading-8">Twinspot{ stats && <Badge tone="quiet" className="text-[12px]">{ `v${ stats.version }` }</Badge> }</h1>
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

			{ importing && ! empty && <Card className="p-5"><ProgressBar value={ progress } /></Card> }
			{ error && (
				<div role="alert" className="flex items-center gap-3 rounded-xl border border-danger-line bg-danger-soft p-4 text-danger">
					<AlertTriangle size={ 18 } />{ error }
				</div>
			) }
			{ imported !== null && (
				<motion.div { ...fade } role="status" className="flex items-center gap-3 rounded-xl border border-success-line bg-success-soft p-4">
					<CheckCircle2 size={ 20 } className="text-success" />
					<div className="flex-1">
							<strong>{ sprintf( _n( 'Success: indexed %1$d past order in %2$s seconds.', 'Success: indexed %1$d past orders in %2$s seconds.', imported.n, 'twinspot-lite' ), imported.n, String( imported.seconds ) ) }</strong>
							<p className="text-muted">{ data && data.stats.rings > 0 ? __( 'Linked orders are listed below.', 'twinspot-lite' ) : __( 'No linked orders found in recent orders. New orders are checked automatically.', 'twinspot-lite' ) }</p>
						</div>
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

			{ ! data && ! error && <Card><Spinner /></Card> }

			{ data && ! empty && (
				<div className="grid gap-5 md:grid-cols-3">
					<Stat index={ 0 } label={ __( 'Rings found', 'twinspot-lite' ) } value={ data.stats.rings } hint={ sprintf( _n( 'Across %d customer', 'Across %d customers', data.stats.ring_customers, 'twinspot-lite' ), data.stats.ring_customers ) } icon={ Network } tone="bg-info text-primary" />
					<Stat index={ 1 } label={ __( 'Orders in rings', 'twinspot-lite' ) } value={ data.stats.orders_in_rings } hint={ sprintf( __( '%s%% of indexed orders', 'twinspot-lite' ), ( data.stats.indexed ? ( data.stats.orders_in_rings / data.stats.indexed ) * 100 : 0 ).toFixed( 1 ) ) } icon={ ReceiptText } tone="bg-info text-primary" />
					<Stat index={ 2 } label={ __( 'Coupons blocked (30 days)', 'twinspot-lite' ) } value={ data.stats.blocked_30d } hint={ __( 'In the last 30 days', 'twinspot-lite' ) } icon={ TicketCheck } tone="bg-primary-tint text-primary" />
				</div>
			) }
			{ data && ( empty ? <EmptyState onImport={ onImport } onSample={ onSample } importing={ importing } progress={ progress } /> : <RingsTable data={ data } filters={ filters } setFilters={ setFilters } setPage={ setPage } onOpen={ onOpen } /> ) }
			{ data && empty && (
				<div className="grid gap-5 md:grid-cols-3">
					<Stat index={ 0 } label={ __( 'Indexed customers', 'twinspot-lite' ) } value={ data.stats.customers } icon={ Users } tone="bg-info text-primary" />
					<Stat index={ 1 } label={ __( 'Coupons monitored', 'twinspot-lite' ) } value={ data.stats.coupons } icon={ TicketCheck } tone="bg-info text-primary" />
					<Stat index={ 2 } label={ __( 'Coupons blocked (30 days)', 'twinspot-lite' ) } value={ data.stats.blocked_30d } icon={ ShieldCheck } tone="bg-primary-tint text-primary" />
				</div>
			) }
		</div>
	);
}
