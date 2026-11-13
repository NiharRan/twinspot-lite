import { createInterpolateElement, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { motion } from 'motion/react';
import { AlertTriangle, CheckCircle2, ChevronRight, FlaskConical, RefreshCw, Search, X } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Card } from '../components/ui/card';
import { Spinner } from '../components/ui/spinner';
import { Badge } from '../components/ui/badge';
import { Input, Select } from '../components/ui/input';
import { getActivity } from '../lib/api';
import { cn } from '../lib/utils';
import { ago, describe } from './activity';
import type { Activity, EventType, Filters, LinkType, Overview as OverviewData } from '../types';

const LINK_LABEL: Record< LinkType, string > = {
	email: __( 'Email', 'twinspot-lite' ),
	phone: __( 'Phone', 'twinspot-lite' ),
	address: __( 'Address', 'twinspot-lite' ),
};
const LINK_FILTER: [ Filters[ 'link' ], string ][] = [
	[ '', __( 'All rings', 'twinspot-lite' ) ],
	[ 'email', __( 'Same email', 'twinspot-lite' ) ],
	[ 'phone', __( 'Same phone', 'twinspot-lite' ) ],
	[ 'address', __( 'Same address', 'twinspot-lite' ) ],
];
const EVENT_DOT: Partial< Record< EventType, string > > = { blocked: 'bg-danger', held: 'bg-accent', ring: 'bg-primary' };
const SHOWN = 3; // Order links per row before "and N more".

/** Ring size to a level: colour for the glyph and a word, so it never relies on colour alone. */
function level( size: number ): { label: string; text: string; stroke: string; line: string } {
	if ( size >= 5 ) {
		return { label: __( 'High', 'twinspot-lite' ), text: 'text-danger', stroke: '#9B2C2C', line: '#E4BDBD' };
	}
	if ( size === 4 ) {
		return { label: __( 'Elevated', 'twinspot-lite' ), text: 'text-accent-ink', stroke: '#94600F', line: '#E6D2AE' };
	}
	return size === 3
		? { label: __( 'Watch', 'twinspot-lite' ), text: 'text-primary', stroke: '#0F6E5E', line: '#B9D7CF' }
		: { label: __( 'Low', 'twinspot-lite' ), text: 'text-muted', stroke: '#55605E', line: '#CCD3D0' };
}

/** The ring drawn as itself: one node per order (up to 12), joined to the shared detail in the middle. */
function RingGlyph( { size }: { size: number } ) {
	const n = Math.min( size, 12 );
	const { stroke, line } = level( size );
	const pts = Array.from( { length: n }, ( _, i ) => {
		const a = -Math.PI / 2 + ( i * 2 * Math.PI ) / n;
		return [ 26 + 19 * Math.cos( a ), 26 + 19 * Math.sin( a ) ];
	} );
	return (
		<svg width="52" height="52" viewBox="0 0 52 52" aria-hidden="true">
			{ pts.map( ( [ x, y ], i ) => <line key={ i } x1="26" y1="26" x2={ x } y2={ y } stroke={ line } strokeWidth="1.4" /> ) }
			{ pts.map( ( [ x, y ], i ) => <circle key={ i } cx={ x } cy={ y } r="4" fill="#fff" stroke={ stroke } strokeWidth="1.8" /> ) }
			<circle cx="26" cy="26" r="5.5" fill={ stroke } />
		</svg>
	);
}

function lastOrder( iso: string ): string {
	const days = Math.round( ( new Date( iso ).getTime() - Date.now() ) / 864e5 );
	return days > -7 ? new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } ).format( days, 'day' ) : new Date( iso ).toLocaleDateString();
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
	return (
		<section className="flex min-w-0 flex-col gap-4" aria-labelledby="twinspot-rings">
			<div className="flex flex-wrap items-end justify-between gap-3">
				<div>
					<h2 id="twinspot-rings" className="text-[20px] font-bold leading-[26px] tracking-[-0.02em]">{ __( 'Linked rings', 'twinspot-lite' ) }</h2>
					<p className="mt-1 text-muted">{ __( 'Orders that share an email, phone or delivery address.', 'twinspot-lite' ) }</p>
				</div>
				<div className="relative w-full max-w-[280px]">
					<Search size={ 16 } className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
					<Input
						className="w-full pl-9"
						placeholder={ __( 'Find an order or email', 'twinspot-lite' ) }
						value={ filters.search }
						onChange={ ( e ) => setFilters( { search: e.target.value } ) }
						aria-label={ __( 'Search rings', 'twinspot-lite' ) }
					/>
				</div>
			</div>

			<div className="flex flex-wrap items-center gap-1.5">
				{ LINK_FILTER.map( ( [ id, label ] ) => (
					<button
						key={ id }
						type="button"
						aria-pressed={ filters.link === id }
						onClick={ () => setFilters( { link: id } ) }
						className={ cn( 'h-8 rounded-md border px-3 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2', filters.link === id ? 'border-ink bg-ink font-semibold text-white' : 'border-[#C9D1CD] bg-white font-medium hover:bg-info' ) }
					>
						{ label }
					</button>
				) ) }
				<Select className="ml-auto h-8 rounded-md text-[13px]" value={ filters.min_size } onChange={ ( e ) => setFilters( { min_size: Number( e.target.value ) } ) } aria-label={ __( 'Ring size', 'twinspot-lite' ) }>
					<option value={ 2 }>{ __( 'Any size', 'twinspot-lite' ) }</option>
					<option value={ 3 }>{ __( '3 or more orders', 'twinspot-lite' ) }</option>
					<option value={ 4 }>{ __( '4 or more orders', 'twinspot-lite' ) }</option>
					<option value={ 5 }>{ __( '5 or more orders', 'twinspot-lite' ) }</option>
				</Select>
			</div>

			<Card className="overflow-hidden">
				{ rings.length === 0 && (
					<div className="px-5 py-10 text-center text-muted">
						<p>{ __( 'No rings match these filters.', 'twinspot-lite' ) }</p>
						<Button variant="secondary" className="mt-3" onClick={ () => setFilters( { search: '', min_size: 2, link: '' } ) }>{ __( 'Clear filters', 'twinspot-lite' ) }</Button>
					</div>
				) }
				<ul className="m-0 list-none p-0">
					{ rings.map( ( ring ) => {
						const lv = level( ring.size );
						const more = ring.orders.length - SHOWN;
						return (
							<li key={ ring.orders.map( ( o ) => o.id ).join( '-' ) } className="m-0 flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-[#EBEFED] px-5 py-4 hover:bg-[#F6F8F7]">
								<RingGlyph size={ ring.size } />
								<div className="flex min-w-0 flex-[1_1_220px] flex-col gap-1">
									<div className="flex flex-wrap items-baseline gap-x-2.5">
										<button type="button" onClick={ () => onOpen( ring.key ) } className="text-left text-[16px] font-bold tracking-[-0.01em] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
											{ sprintf( _n( '%d order, one shopper', '%d orders, one shopper', ring.size, 'twinspot-lite' ), ring.size ) }
										</button>
										<span className={ cn( 'text-[13px] font-semibold', lv.text ) }>{ lv.label }</span>
										{ ring.sample && <Badge tone="info">{ __( 'Sample', 'twinspot-lite' ) }</Badge> }
									</div>
									<div className="text-muted">
										{ ring.orders.slice( 0, SHOWN ).map( ( o, n ) => (
											<span key={ o.id }>
												{ n > 0 && ', ' }
												{ o.url ? <a className="text-primary hover:underline" href={ o.url }>#{ o.id }</a> : <span>#{ o.id }</span> }
											</span>
										) ) }
										{ more > 0 && ' ' + sprintf( __( 'and %d more', 'twinspot-lite' ), more ) }
									</div>
								</div>
								<div className="flex flex-[0_1_170px] flex-wrap gap-1.5">
									{ ring.links.map( ( l ) => <Badge key={ l } tone="quiet">{ LINK_LABEL[ l ] }</Badge> ) }
								</div>
								<div className="flex flex-[0_0_110px] flex-col text-right">
									<span className="font-bold">{ ring.total }</span>
									<span className="text-[13px] text-muted">{ ring.last_order ? lastOrder( ring.last_order ) : '—' }</span>
								</div>
								<Button variant="ghost" className="w-10 px-0" onClick={ () => onOpen( ring.key ) } aria-label={ __( 'Open ring map', 'twinspot-lite' ) }><ChevronRight size={ 18 } /></Button>
							</li>
						);
					} ) }
				</ul>
				<div className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 text-muted">
					<span>{ sprintf( __( '%1$d of %2$d rings', 'twinspot-lite' ), rings.length, total ) }</span>
					{ pages > 1 && (
						<div className="flex items-center gap-2">
							<Button variant="secondary" disabled={ filters.page <= 1 } onClick={ () => setPage( filters.page - 1 ) }>{ __( 'Previous', 'twinspot-lite' ) }</Button>
							<span className="px-1">{ filters.page } / { pages }</span>
							<Button variant="secondary" disabled={ filters.page >= pages } onClick={ () => setPage( filters.page + 1 ) }>{ __( 'Next', 'twinspot-lite' ) }</Button>
						</div>
					) }
				</div>
			</Card>
		</section>
	);
}

function ProgressBar( { value }: { value: number } ) {
	const pct = Math.round( value * 100 );
	return (
		<div className="w-full max-w-md" role="progressbar" aria-valuemin={ 0 } aria-valuemax={ 100 } aria-valuenow={ pct } aria-label={ __( 'Scan progress', 'twinspot-lite' ) }>
			<div className="h-1.5 overflow-hidden rounded-full bg-primary-tint">
				<motion.div className="h-full rounded-full bg-primary" initial={ false } animate={ { width: `${ pct }%` } } transition={ { type: 'tween', ease: 'easeOut', duration: 0.5 } } />
			</div>
			<div className="mt-2 text-[13px] text-muted">{ sprintf( __( 'Scanning orders… %d%%', 'twinspot-lite' ), pct ) }</div>
		</div>
	);
}

const HERO = 'max-w-[820px] text-[30px] font-semibold leading-[36px] tracking-[-0.035em] md:text-[42px] md:leading-[48px]';

function Step( { n, active, title, children }: { n: number; active?: boolean; title: string; children: React.ReactNode } ) {
	return (
		<li className="m-0 flex gap-5 border-b border-line py-[22px]">
			<span className={ cn( 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-bold', active ? 'bg-ink text-white' : 'border-[1.5px] border-[#9AA5A2] text-muted' ) }>{ n }</span>
			<div className="min-w-0 flex-1">
				<h2 className="text-[17px] font-bold leading-6">{ title }</h2>
				{ children }
			</div>
		</li>
	);
}

function FirstRun( { onImport, onSample, onSettings, importing, progress }: { onImport: () => void; onSample: ( on: boolean ) => void; onSettings: () => void; importing: boolean; progress: number } ) {
	return (
		<div className="flex flex-wrap items-start gap-x-14 gap-y-8">
			<section className="flex min-w-0 flex-[999_1_520px] flex-col gap-9">
				<div className="flex flex-col gap-3.5">
					<h1 className={ HERO }>{ __( 'Find the shopper who keeps coming back as someone new.', 'twinspot-lite' ) }</h1>
					<p className="max-w-[560px] text-[16px] leading-[25px] text-muted">{ __( 'Twinspot links orders that share an email, phone or delivery address, then refuses your welcome coupon to anyone who already had it.', 'twinspot-lite' ) }</p>
				</div>
				<ol className="m-0 list-none border-t border-line p-0">
					<Step n={ 1 } active title={ __( 'Scan your past orders', 'twinspot-lite' ) }>
						<p className="mt-0.5 text-muted">{ __( 'Reads the last 12 months, so returning shoppers are known from day one.', 'twinspot-lite' ) }</p>
						<div className="mt-3.5">
							{ importing ? <ProgressBar value={ progress } /> : (
								<div className="flex flex-wrap gap-2.5">
									<Button className="h-11 px-5" onClick={ onImport }><RefreshCw size={ 16 } />{ __( 'Scan past orders', 'twinspot-lite' ) }</Button>
									<Button variant="secondary" className="h-11 bg-transparent" onClick={ () => onSample( true ) }><FlaskConical size={ 16 } />{ __( 'Try with sample orders', 'twinspot-lite' ) }</Button>
								</div>
							) }
						</div>
					</Step>
					<Step n={ 2 } title={ __( 'Guard a welcome coupon', 'twinspot-lite' ) }>
						<p className="mt-0.5 text-muted">{ __( 'Open a coupon and tick “New customers only (Twinspot)”.', 'twinspot-lite' ) }</p>
						<a className="mt-2 inline-block font-semibold text-primary hover:underline" href="edit.php?post_type=shop_coupon">{ __( 'Open coupons', 'twinspot-lite' ) }</a>
					</Step>
					<Step n={ 3 } title={ __( 'Choose what happens at checkout', 'twinspot-lite' ) }>
						<p className="mt-0.5 text-muted">{ __( 'Refuse the coupon, or let the order through and hold it for your review.', 'twinspot-lite' ) }</p>
						<button type="button" className="mt-2 font-semibold text-primary hover:underline" onClick={ onSettings }>{ __( 'Open settings', 'twinspot-lite' ) }</button>
					</Step>
				</ol>
			</section>
			<Card className="flex min-w-0 flex-[1_1_320px] flex-col gap-5 p-7">
				<h2 className="text-[17px] font-bold leading-6">{ __( 'What a ring looks like', 'twinspot-lite' ) }</h2>
				<svg viewBox="0 0 300 200" width="100%" role="img" aria-label={ __( 'Three orders under different names, joined because two share an email and two share a delivery address', 'twinspot-lite' ) } style={ { fontFamily: 'inherit' } }>
					{ [ [ 60, 46, 150, 66 ], [ 240, 46, 150, 66 ], [ 240, 46, 196, 150 ], [ 104, 166, 196, 150 ] ].map( ( [ x1, y1, x2, y2 ], i ) => <line key={ i } x1={ x1 } y1={ y1 } x2={ x2 } y2={ y2 } stroke="#B9D7CF" strokeWidth="1.6" /> ) }
					{ [ [ 150, 66 ], [ 196, 150 ] ].map( ( [ x, y ], i ) => <circle key={ i } cx={ x } cy={ y } r="7" fill="#0F6E5E" /> ) }
					{ [ [ 60, 46 ], [ 240, 46 ], [ 104, 166 ] ].map( ( [ x, y ], i ) => <circle key={ i } cx={ x } cy={ y } r="7" fill="#fff" stroke="#132120" strokeWidth="2" /> ) }
					<text x="60" y="26" textAnchor="middle" fontSize="12" fontWeight="600" fill="#132120">Jo Park</text>
					<text x="240" y="26" textAnchor="middle" fontSize="12" fontWeight="600" fill="#132120">J. Parker</text>
					<text x="104" y="190" textAnchor="middle" fontSize="12" fontWeight="600" fill="#132120">Joanne P</text>
					<text x="150" y="90" textAnchor="middle" fontSize="11" fill="#55605E">{ __( 'same email', 'twinspot-lite' ) }</text>
					<text x="226" y="176" textAnchor="middle" fontSize="11" fill="#55605E">{ __( 'same address', 'twinspot-lite' ) }</text>
				</svg>
				<p className="text-muted">{ __( 'Three names, one shopper. j.o.park+deal@gmail.com and jopark@gmail.com count as the same inbox.', 'twinspot-lite' ) }</p>
				<p className="border-t border-line pt-4 text-[13px] leading-[19px] text-muted">{ __( 'Details are kept as one-way hashes in your own database. Nothing is sent anywhere.', 'twinspot-lite' ) }</p>
			</Card>
		</div>
	);
}

/** The month as one sentence; falls back to a calm line when nothing was refused. */
function Hero( { act }: { act: Activity | null } ) {
	const s = act?.stats;
	let text: React.ReactNode = __( 'No repeat welcome coupons in the last 30 days. Twinspot is watching every checkout.', 'twinspot-lite' );
	if ( s && s.blocked > 0 ) {
		const raw = s.saved
			? sprintf( _n( 'In the last 30 days Twinspot refused <b>%1$d</b> repeat welcome coupon, worth <b>%2$s</b>.', 'In the last 30 days Twinspot refused <b>%1$d</b> repeat welcome coupons, worth <b>%2$s</b>.', s.blocked, 'twinspot-lite' ), s.blocked, s.saved )
			: sprintf( _n( 'In the last 30 days Twinspot refused <b>%d</b> repeat welcome coupon.', 'In the last 30 days Twinspot refused <b>%d</b> repeat welcome coupons.', s.blocked, 'twinspot-lite' ), s.blocked );
		text = createInterpolateElement( raw, { b: <strong className="font-extrabold" /> } );
	}
	return <h1 className={ cn( HERO, ! act && 'invisible' ) }>{ text }</h1>; // Hidden until loaded so the sentence does not flip.
}

function Figure( { label, value, of, last }: { label: string; value: number; of?: number; last?: boolean } ) {
	return (
		<div className={ cn( 'py-[18px] pr-6', ! last && 'md:border-r md:border-line', 'md:pl-6 md:first:pl-0' ) }>
			<dt className="text-muted">{ label }</dt>
			<dd className="m-0 mt-1 text-[26px] font-bold leading-8 tracking-[-0.02em]">
				{ value.toLocaleString() }
				{ of !== undefined && <span className="text-[14px] font-medium tracking-normal text-muted"> { sprintf( __( 'of %s', 'twinspot-lite' ), of.toLocaleString() ) }</span> }
			</dd>
		</div>
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
	onSettings: () => void;
}

export function Overview( { data, error, filters, setFilters, setPage, onOpen, importing, progress, imported, dismissImported, onImport, onSample, onSettings }: Props ) {
	const [ act, setAct ] = useState< Activity | null >( null );
	const filtering = filters.search || filters.link || filters.min_size > 2;
	const empty = !! data && data.stats.rings === 0 && ! filtering;
	const indexed = data?.stats.indexed;

	useEffect( () => {
		// Hero numbers and the timeline reuse the Activity endpoint; a failure only hides them.
		getActivity( { range: '30d', search: '', type: '', severity: '', page: 1, per_page: 5 } ).then( setAct ).catch( () => {} );
	}, [ indexed ] );

	const notices = (
		<>
			{ error && (
				<div role="alert" className="flex items-center gap-3 rounded-[10px] bg-danger-soft p-4 text-danger">
					<AlertTriangle size={ 18 } />{ error }
				</div>
			) }
			{ imported !== null && (
				<div role="status" className="flex items-center gap-3 rounded-[10px] bg-success-soft p-4">
					<CheckCircle2 size={ 20 } className="text-success" />
					<div className="flex-1">
						<strong>{ sprintf( _n( 'Scanned %1$d past order in %2$s seconds.', 'Scanned %1$d past orders in %2$s seconds.', imported.n, 'twinspot-lite' ), imported.n, String( imported.seconds ) ) }</strong>
						<p className="text-muted">{ data && data.stats.rings > 0 ? __( 'Linked orders are listed below.', 'twinspot-lite' ) : __( 'No linked orders found. New orders are checked automatically.', 'twinspot-lite' ) }</p>
					</div>
					<Button variant="ghost" className="w-10 px-0" onClick={ dismissImported } aria-label={ __( 'Dismiss', 'twinspot-lite' ) }><X size={ 16 } /></Button>
				</div>
			) }
		</>
	);

	if ( ! data ) {
		return <div className="flex flex-col gap-5">{ notices }{ ! error && <Card><Spinner /></Card> }</div>;
	}
	if ( empty ) {
		return <div className="flex flex-col gap-8">{ notices }<FirstRun onImport={ onImport } onSample={ onSample } onSettings={ onSettings } importing={ importing } progress={ progress } /></div>;
	}

	const { stats } = data;
	return (
		<div className="flex flex-col gap-10">
			{ ( error || imported !== null ) && <div className="flex flex-col gap-3">{ notices }</div> }
			<section className="flex flex-wrap items-end justify-between gap-6">
				<Hero act={ act } />
				{ importing ? <ProgressBar value={ progress } /> : (
					<Button variant="secondary" className="h-11 bg-transparent" onClick={ onImport }><RefreshCw size={ 16 } />{ __( 'Scan past orders', 'twinspot-lite' ) }</Button>
				) }
			</section>

			<dl className="m-0 grid grid-cols-2 border-y border-line md:grid-cols-4">
				<Figure label={ __( 'Rings', 'twinspot-lite' ) } value={ stats.rings } />
				<Figure label={ __( 'Accounts in rings', 'twinspot-lite' ) } value={ stats.ring_customers } />
				<Figure label={ __( 'Orders in rings', 'twinspot-lite' ) } value={ stats.orders_in_rings } of={ stats.indexed } />
				<Figure label={ __( 'Coupons guarded', 'twinspot-lite' ) } value={ stats.coupons } last />
			</dl>

			<div className="flex flex-wrap items-start gap-x-12 gap-y-10">
				<div className="min-w-0 flex-[999_1_560px]"><RingsTable data={ data } filters={ filters } setFilters={ setFilters } setPage={ setPage } onOpen={ onOpen } /></div>
				<aside className="flex min-w-0 flex-[1_1_280px] flex-col gap-5" aria-labelledby="twinspot-latest">
					<h2 id="twinspot-latest" className="text-[20px] font-bold leading-[26px] tracking-[-0.02em]">{ __( 'Latest checkouts', 'twinspot-lite' ) }</h2>
					{ act && act.events.length === 0 && <p className="text-muted">{ __( 'Nothing yet. Checkouts that use a guarded coupon show up here.', 'twinspot-lite' ) }</p> }
					{ act && act.events.length > 0 && (
						<ol className="m-0 flex list-none flex-col gap-[22px] border-l-[1.5px] border-[#C9D1CD] p-0 pl-5">
							{ act.events.map( ( e ) => {
								const d = describe( e );
								return (
									<li key={ e.id } className="relative m-0 flex flex-col gap-0.5">
										<span className={ cn( 'absolute -left-[25.5px] top-1.5 h-[9px] w-[9px] rounded-full ring-[3px] ring-[#F0F0F1]', EVENT_DOT[ e.type ] ?? 'bg-[#8A9592]' ) } />
										<span className="text-[13px] text-muted">{ ago( e.created ) }</span>
										<span className="font-semibold">{ d.title }</span>
										<span className="text-muted">{ e.coupon ? `${ e.coupon }: ${ d.outcome }` : d.outcome }</span>
									</li>
								);
							} ) }
						</ol>
					) }
					<p className="border-t border-line pt-4 text-[13px] leading-[19px] text-muted">{ __( 'Emails, phones and addresses are kept as one-way hashes in your own database. Nothing is sent anywhere.', 'twinspot-lite' ) }</p>
				</aside>
			</div>
		</div>
	);
}
