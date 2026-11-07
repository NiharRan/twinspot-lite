import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Ban, Check, CheckCircle2, CreditCard, Info, Lock, Package, PauseCircle, RotateCcw, Save, ScanSearch, ShieldCheck, Store, Tag, Ticket, User, Wallet, X, XCircle } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Card } from '../components/ui/card';
import { cn } from '../lib/utils';
import type { SettingsData } from '../lib/api';

type View = 'block' | 'classic';

function Toggle( { on, onChange, label, disabled }: { on: boolean; onChange?: ( v: boolean ) => void; label: string; disabled?: boolean } ) {
	return (
		<button
			type="button"
			role="switch"
			aria-checked={ on }
			aria-label={ label }
			disabled={ disabled }
			onClick={ () => onChange?.( ! on ) }
			className={ cn( 'relative h-6 w-11 shrink-0 rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50', on ? 'bg-primary' : 'bg-slate-300' ) }
		>
			<span className={ cn( 'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all', on ? 'left-[22px]' : 'left-0.5' ) } />
		</button>
	);
}

function Row( { icon, title, badge, children, control }: { icon: React.ReactNode; title: string; badge?: React.ReactNode; children: React.ReactNode; control: React.ReactNode } ) {
	return (
		<div className="flex items-start gap-4 rounded-xl border border-line p-5">
			<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary">{ icon }</span>
			<div className="min-w-0 flex-1">
				<div className="flex items-center gap-2 text-[15px] font-semibold">{ title }{ badge }</div>
				<div className="mt-1 text-muted">{ children }</div>
			</div>
			{ control }
		</div>
	);
}

const items = [
	{ name: 'Highland Reserve Roast • 12oz', note: 'Whole Bean / Medium Dark', price: 42.5 },
	{ name: 'Artisan Ceramic Dripper V2', note: 'Matte Sage Teal', price: 36 },
];
const money = ( n: number ) => '$' + n.toFixed( 2 );

function Preview( { draft }: { draft: SettingsData } ) {
	const [ view, setView ] = useState< View >( 'block' );
	const [ dismissed, setDismissed ] = useState( false );
	const [ pay, setPay ] = useState< 'card' | 'wallet' >( 'card' );
	const blocked = draft.enabled && ! draft.hold;
	const subtotal = 78.5;
	const discount = blocked ? 0 : 15.7; // WELCOME20 = 20% off.
	const total = subtotal + 5 - discount;
	const block = view === 'block';
	const reset = () => { setView( 'block' ); setDismissed( false ); setPay( 'card' ); };
	return (
		<div className="space-y-4">
			<Card className="flex flex-wrap items-center justify-between gap-4 p-5">
				<div className="flex items-start gap-3">
					<span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-soft text-primary"><ShieldCheck size={ 20 } /></span>
					<div>
						<h2 className="flex items-center gap-2 text-[16px] font-semibold">{ __( 'Checkout Experience & Protection Engine', 'twinspot-lite' ) }<Badge>{ __( 'Live simulation', 'twinspot-lite' ) }</Badge></h2>
						<p className="text-muted">{ __( 'Inspect how shoppers experience gentle coupon gatekeeping and tune back-office enforcement.', 'twinspot-lite' ) }</p>
					</div>
				</div>
				<div className="flex items-center gap-2">
					<div className="flex rounded-lg border border-line p-0.5">
						{ ( [ [ 'block', __( 'Block Checkout', 'twinspot-lite' ) ], [ 'classic', __( 'Classic Shortcode', 'twinspot-lite' ) ] ] as [ View, string ][] ).map( ( [ v, l ] ) => (
							<button key={ v } type="button" onClick={ () => { setView( v ); setDismissed( false ); } } className={ cn( 'rounded-md px-3 py-1.5 text-[13px]', view === v ? 'bg-primary-soft font-semibold text-primary' : 'text-muted' ) }>{ l }</button>
						) ) }
					</div>
					<Button variant="secondary" onClick={ reset }><RotateCcw size={ 14 } />{ __( 'Reset Demo', 'twinspot-lite' ) }</Button>
				</div>
			</Card>

			<div className="flex flex-wrap items-center justify-between gap-2">
				<h3 className="flex items-center gap-2 text-[15px] font-semibold"><Store size={ 16 } className="text-primary" />{ __( 'Demo Store Checkout — Clean & Polite Customer Experience', 'twinspot-lite' ) }</h3>
				<span className="flex items-center gap-2 text-[12px] font-medium"><span className="h-2 w-2 rounded-full bg-accent" />{ __( 'Simulated Shopper: repeat buyer using guest checkout', 'twinspot-lite' ) }</span>
			</div>

			<Card className="overflow-hidden">
				<div className="flex items-center gap-3 border-b border-line bg-primary-soft px-4 py-2.5">
					<span className="flex gap-1.5"><i className="h-2.5 w-2.5 rounded-full bg-slate-300" /><i className="h-2.5 w-2.5 rounded-full bg-slate-300" /><i className="h-2.5 w-2.5 rounded-full bg-slate-300" /></span>
					<span className="flex items-center gap-1.5 rounded-md bg-white px-3 py-1 font-mono text-[12px] text-muted"><Lock size={ 12 } />mystore.local/checkout/</span>
				</div>
				<div className="grid gap-5 p-5 md:grid-cols-[3fr_2fr]">
					<div className="space-y-4">
						<div className="rounded-xl border border-line p-4">
							<div className="flex items-center justify-between"><p className="font-semibold">{ __( '1. Contact & Shipping', 'twinspot-lite' ) }</p><span className="text-[12px] text-primary">{ __( 'Edit', 'twinspot-lite' ) }</span></div>
							<div className="mt-2 flex items-start gap-3">
								<span className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-soft text-muted"><User size={ 16 } /></span>
								<p className="text-muted"><span className="font-medium text-ink">Alex Mercer (Guest)</span><br />mercer.design@example.org • +1 (555) 019-8234<br />742 Evergreen Terrace, Springfield, OR 97477</p>
							</div>
						</div>

						<div className="flex items-center gap-2 text-[12px]">
							<span className="rounded bg-primary-tint px-2 py-1 font-semibold uppercase tracking-wide text-primary">{ block ? __( 'WooCommerce Checkout Block', 'twinspot-lite' ) : __( 'Classic WooCommerce Shortcode', 'twinspot-lite' ) }</span>
							<span className="text-muted">{ block ? __( 'Modern Accordion Layout', 'twinspot-lite' ) : '[woocommerce_checkout] ' + __( 'Template', 'twinspot-lite' ) }</span>
						</div>

						<div className="rounded-xl border border-line p-4">
							{ block ? (
								<div className="flex items-center justify-between"><p className="font-semibold">{ __( 'Have a promo code?', 'twinspot-lite' ) }</p><span className="text-[12px] text-muted">{ __( 'First-purchase promotions', 'twinspot-lite' ) }</span></div>
							) : (
								<p className="flex items-center gap-2 rounded-lg bg-primary-soft px-3 py-2 text-muted"><Ticket size={ 16 } />{ __( 'Have a coupon? Click here to enter your code', 'twinspot-lite' ) }</p>
							) }
							<div className="mt-3 flex gap-2">
								<div className="flex h-9 flex-1 items-center gap-2 rounded-md border border-line px-3 font-mono text-[13px] font-medium"><Tag size={ 14 } className="text-muted" />WELCOME20</div>
								<Button className="pointer-events-none">{ block ? __( 'Apply', 'twinspot-lite' ) : __( 'Apply coupon', 'twinspot-lite' ) }</Button>
							</div>
							<div className="mt-3">
								{ blocked && ! ( dismissed && block ) && (
									<div role="alert" className="flex items-start gap-3 rounded-lg border border-danger-line bg-danger-soft p-3 text-danger">
										<Info size={ 16 } className="mt-0.5" />
										<div className="flex-1">
											<p className="font-medium">{ __( 'Sorry, this coupon is only for first-time customers.', 'twinspot-lite' ) }</p>
											{ block && <p className="text-[12px] text-muted">{ __( 'If you have another offer code or store gift card, feel free to enter it above.', 'twinspot-lite' ) }</p> }
										</div>
										{ block && <button type="button" aria-label={ __( 'Dismiss', 'twinspot-lite' ) } onClick={ () => setDismissed( true ) }><X size={ 14 } /></button> }
									</div>
								) }
								{ ! blocked && <div className="flex items-center gap-2 rounded-lg border border-success-line bg-success-soft p-3 text-success"><Check size={ 16 } />{ __( 'Coupon code applied successfully.', 'twinspot-lite' ) }</div> }
							</div>
						</div>

						<div className="rounded-xl border border-line p-4">
							<p className="font-semibold">{ __( '2. Payment Method', 'twinspot-lite' ) }</p>
							<div className="mt-2 grid grid-cols-2 gap-2">
								{ ( [ [ 'card', __( 'Credit Card', 'twinspot-lite' ), <CreditCard key="c" size={ 16 } /> ], [ 'wallet', __( 'Digital Wallet', 'twinspot-lite' ), <Wallet key="w" size={ 16 } /> ] ] as [ 'card' | 'wallet', string, React.ReactNode ][] ).map( ( [ k, l, i ] ) => (
									<button key={ k } type="button" onClick={ () => setPay( k ) } className={ cn( 'flex items-center gap-2 rounded-md border px-3 py-2.5', pay === k ? 'border-primary bg-primary-soft font-medium text-primary' : 'border-line text-muted' ) }>{ i }{ l }</button>
								) ) }
							</div>
						</div>
						<div className="flex h-11 items-center justify-center gap-2 rounded-lg bg-primary font-semibold text-white"><Lock size={ 16 } />{ __( 'Place Order', 'twinspot-lite' ) } • { money( total ) }</div>
					</div>

					<div className="space-y-4">
						<div className="rounded-xl border border-line p-4">
							<div className="flex items-center justify-between"><p className="font-semibold">{ __( 'Order Summary', 'twinspot-lite' ) }</p><span className="rounded bg-primary-tint px-2 py-0.5 text-[11px] font-semibold text-primary">{ __( '2 Items', 'twinspot-lite' ) }</span></div>
							{ items.map( ( i ) => (
								<div key={ i.name } className="mt-3 flex items-center gap-3">
									<span className="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary-tint text-primary"><Package size={ 18 } /><span className="absolute -right-1.5 -top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-ink text-[10px] text-white">1</span></span>
									<span className="flex-1">{ i.name }<span className="block text-[12px] text-muted">{ i.note }</span></span>
									<span className="tabular font-medium">{ money( i.price ) }</span>
								</div>
							) ) }
							<div className="mt-3 space-y-1 border-t border-line pt-3 text-muted">
								<div className="flex justify-between"><span>{ __( 'Subtotal', 'twinspot-lite' ) }</span><span className="tabular">{ money( subtotal ) }</span></div>
								<div className="flex justify-between"><span>{ __( 'Shipping (Standard Ground)', 'twinspot-lite' ) }</span><span className="tabular">{ money( 5 ) }</span></div>
								<div className={ cn( 'flex items-center justify-between rounded-md px-2 py-1.5', blocked ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ) }>
									<span className="flex items-center gap-1.5">{ blocked ? <XCircle size={ 14 } /> : <Check size={ 14 } /> }Coupon WELCOME20</span>
									<span className="tabular text-[12px] font-semibold">{ blocked ? __( 'Not applied ($0.00)', 'twinspot-lite' ) : '−' + money( discount ) }</span>
								</div>
							</div>
							<div className="mt-3 flex items-end justify-between border-t border-line pt-3">
								<span><span className="block font-semibold">{ __( 'Total Due', 'twinspot-lite' ) }</span></span>
								<span className="tabular text-[22px] font-semibold">{ money( total ) }</span>
							</div>
						</div>
						<div className="rounded-xl bg-primary-soft p-4 text-[13px]">
							<p className="flex items-center gap-2 font-semibold text-primary"><ScanSearch size={ 14 } />{ __( 'Twinspot Store Engine Audit', 'twinspot-lite' ) }</p>
							<p className="mt-1 text-muted">
								{ blocked && __( 'Buyer previously placed Order #4891 with the same email, phone or address. Coupon WELCOME20 restricted politely without customer embarrassment.', 'twinspot-lite' ) }
								{ ! blocked && draft.hold && __( 'Buyer previously placed Order #4891 with the same email, phone or address. Coupon WELCOME20 accepted and the order is placed On hold for your review.', 'twinspot-lite' ) }
								{ ! blocked && ! draft.hold && __( 'Protection is off. Coupon WELCOME20 accepted and the order goes through as normal.', 'twinspot-lite' ) }
							</p>
						</div>
					</div>
				</div>
			</Card>
		</div>
	);
}

export function Settings( { saved, onSave }: { saved: SettingsData; onSave: ( s: SettingsData ) => Promise< unknown > } ) {
	const [ draft, setDraft ] = useState( saved );
	const [ busy, setBusy ] = useState( false );
	const locked = useRef( false );
	const [ msg, setMsg ] = useState< { ok: boolean; text: string } | null >( null );
	useEffect( () => setDraft( saved ), [ saved ] );
	const dirty = draft.enabled !== saved.enabled || draft.hold !== saved.hold;

	// Blocking and holding are alternatives: switching one on switches the other off.
	const setBlock = ( v: boolean ) => setDraft( { enabled: v, hold: v ? false : draft.hold } );
	const setHold = ( v: boolean ) => setDraft( { hold: v, enabled: v ? false : draft.enabled } );

	const save = async () => {
		if ( locked.current ) {
			return;
		}
		locked.current = true;
		setBusy( true );
		setMsg( null );
		try {
			await onSave( draft );
			setMsg( { ok: true, text: __( 'Settings saved.', 'twinspot-lite' ) } );
		} catch ( e ) {
			setMsg( { ok: false, text: ( e as Error ).message || __( 'Could not save settings.', 'twinspot-lite' ) } );
		}
		locked.current = false;
		setBusy( false );
	};

	return (
		<div className="space-y-6">
			<Preview draft={ draft } />
			<div className="flex flex-wrap items-end justify-between gap-2">
				<div>
					<h2 className="text-[20px] font-semibold">{ __( 'Plugin Configuration', 'twinspot-lite' ) }</h2>
				</div>
				<span className="flex items-center gap-1.5 font-medium text-primary"><CheckCircle2 size={ 16 } />{ draft.enabled || draft.hold ? __( 'Policy Engine: Active', 'twinspot-lite' ) : __( 'Policy Engine: Paused', 'twinspot-lite' ) }</span>
			</div>
			<Card className="p-6">
				<div className="flex flex-wrap items-start justify-between gap-2">
					<h2 className="text-[16px] font-semibold">{ __( 'Coupon Protection Rules', 'twinspot-lite' ) }</h2>
				</div>
				<p className="text-muted">{ __( 'Applies to coupons marked “New customers only (Twinspot)”, on both classic and block checkout.', 'twinspot-lite' ) }</p>
				<div className="mt-5 space-y-4">
					<Row icon={ <Ban size={ 18 } /> } title={ __( 'Block repeat coupons automatically', 'twinspot-lite' ) } control={ <Toggle on={ draft.enabled } onChange={ setBlock } label={ __( 'Block repeat coupons automatically', 'twinspot-lite' ) } /> }>
						<p>{ __( 'Refuse the coupon at checkout when the shopper has a linked earlier order.', 'twinspot-lite' ) }</p>
						<p className="mt-1 text-[12px] text-primary">{ __( 'Recommended · prevents lost discount revenue upfront', 'twinspot-lite' ) }</p>
					</Row>
					<Row icon={ <PauseCircle size={ 18 } /> } title={ __( 'Hold suspicious orders for review', 'twinspot-lite' ) } control={ <Toggle on={ draft.hold } onChange={ setHold } label={ __( 'Hold suspicious orders for review', 'twinspot-lite' ) } /> }>
						<p>{ __( 'Let checkout proceed with the discount, but set the order to “On hold” for you to review. Turns off blocking.', 'twinspot-lite' ) }</p>
					</Row>
				</div>
				<div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
					<p role="status" className={ cn( 'text-[13px]', msg ? ( msg.ok ? 'text-success' : 'text-danger' ) : 'text-muted' ) }>{ msg ? msg.text : __( 'Rules apply instantly to guest and registered checkouts.', 'twinspot-lite' ) }</p>
					<div className="flex gap-2">
						<Button variant="secondary" disabled={ ! dirty || busy } onClick={ () => { setDraft( saved ); setMsg( null ); } }>{ __( 'Discard changes', 'twinspot-lite' ) }</Button>
						<Button disabled={ ! dirty || busy } onClick={ save }><Save size={ 14 } />{ __( 'Save changes', 'twinspot-lite' ) }</Button>
					</div>
				</div>
			</Card>
		</div>
	);
}
