import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Ban, Check, CheckCircle2, CloudCog, Info, PauseCircle, RotateCcw, Save, ShieldCheck, Store, X } from 'lucide-react';
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
	const blocked = draft.enabled && ! draft.hold;
	const subtotal = 78.5;
	const discount = blocked ? 0 : 15.7; // WELCOME20 = 20% off.
	const total = subtotal + 5 - discount;
	const notice = (
		<div role="alert" className="flex items-start gap-3 rounded-lg border border-danger-line bg-danger-soft p-3 text-danger">
			<Info size={ 16 } className="mt-0.5" />
			<div className="flex-1">
				<p className="font-medium">{ __( 'Sorry, this coupon is only for first-time customers.', 'twinspot-lite' ) }</p>
				{ view === 'block' && <p className="text-[12px] text-muted">{ __( 'If you have another offer code or store gift card, feel free to enter it above.', 'twinspot-lite' ) }</p> }
			</div>
			{ view === 'block' && (
				<button type="button" aria-label={ __( 'Dismiss', 'twinspot-lite' ) } onClick={ () => setDismissed( true ) }><X size={ 14 } /></button>
			) }
		</div>
	);
	const reset = () => { setView( 'block' ); setDismissed( false ); };
	return (
		<Card className="p-6">
			<div className="flex flex-wrap items-start justify-between gap-4">
				<div className="flex items-start gap-3">
					<span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-soft text-primary"><ShieldCheck size={ 20 } /></span>
					<div>
						<h2 className="flex items-center gap-2 text-[16px] font-semibold">{ __( 'Checkout Experience', 'twinspot-lite' ) }<Badge>{ __( 'Live simulation', 'twinspot-lite' ) }</Badge></h2>
						<p className="text-muted">{ __( 'See what a returning shopper sees. Updates as you change the settings below.', 'twinspot-lite' ) }</p>
					</div>
				</div>
				<div className="flex items-center gap-2">
					<div className="flex rounded-lg border border-line p-0.5">
						{ ( [ [ 'block', __( 'Block Checkout', 'twinspot-lite' ) ], [ 'classic', __( 'Classic Shortcode', 'twinspot-lite' ) ] ] as [ View, string ][] ).map( ( [ v, l ] ) => (
							<button key={ v } type="button" onClick={ () => { setView( v ); setDismissed( false ); } } className={ cn( 'rounded-md px-3 py-1.5 text-[13px]', view === v ? 'bg-primary-soft font-semibold text-primary' : 'text-muted' ) }>{ l }</button>
						) ) }
					</div>
					<Button variant="secondary" onClick={ reset }><RotateCcw size={ 14 } />{ __( 'Reset', 'twinspot-lite' ) }</Button>
				</div>
			</div>

			<div className="mt-5 grid gap-5 rounded-xl border border-line bg-primary-soft/40 p-5 md:grid-cols-[3fr_2fr]">
				<div className="space-y-4">
					<div className="rounded-lg bg-white p-4">
						<p className="font-semibold">{ __( '1. Contact & Shipping', 'twinspot-lite' ) }</p>
						<p className="mt-1 text-muted">Alex Mercer (Guest) · mercer.design@example.org<br />742 Evergreen Terrace, Springfield, OR 97477</p>
					</div>
					<div className="rounded-lg bg-white p-4">
						<p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted">{ view === 'block' ? __( 'WooCommerce Checkout Block', 'twinspot-lite' ) : '[woocommerce_checkout]' }</p>
						<p className="font-semibold">{ view === 'block' ? __( 'Have a promo code?', 'twinspot-lite' ) : __( 'Have a coupon? Click here to enter your code', 'twinspot-lite' ) }</p>
						<div className="mt-2 flex gap-2">
							<div className="flex h-9 flex-1 items-center rounded-md border border-line px-3 font-mono text-[13px]">WELCOME20</div>
							<Button className="pointer-events-none">{ view === 'block' ? __( 'Apply', 'twinspot-lite' ) : __( 'Apply coupon', 'twinspot-lite' ) }</Button>
						</div>
						<div className="mt-3">
							{ blocked && ! ( dismissed && view === 'block' ) && notice }
							{ ! blocked && <div className="flex items-center gap-2 rounded-lg border border-success-line bg-success-soft p-3 text-success"><Check size={ 16 } />{ __( 'Coupon code applied successfully.', 'twinspot-lite' ) }</div> }
						</div>
						<p className="mt-2 flex items-center gap-2 text-[12px] text-muted"><CheckCircle2 size={ 14 } />{ __( 'Shoppers never see risk scores, fingerprints or internal triggers.', 'twinspot-lite' ) }</p>
					</div>
				</div>
				<div className="space-y-4">
					<div className="rounded-lg bg-white p-4">
						<p className="font-semibold">{ __( 'Order Summary', 'twinspot-lite' ) }</p>
						{ items.map( ( i ) => (
							<div key={ i.name } className="mt-3 flex justify-between gap-3"><span>{ i.name }<span className="block text-[12px] text-muted">{ i.note }</span></span><span className="tabular">{ money( i.price ) }</span></div>
						) ) }
						<div className="mt-3 space-y-1 border-t border-line pt-3 text-muted">
							<div className="flex justify-between"><span>{ __( 'Subtotal', 'twinspot-lite' ) }</span><span className="tabular">{ money( subtotal ) }</span></div>
							<div className="flex justify-between"><span>{ __( 'Shipping', 'twinspot-lite' ) }</span><span className="tabular">{ money( 5 ) }</span></div>
							<div className={ cn( 'flex justify-between', blocked ? 'text-danger' : 'text-success' ) }>
								<span>WELCOME20</span><span className="tabular">{ blocked ? __( 'Not applied', 'twinspot-lite' ) : '−' + money( discount ) }</span>
							</div>
						</div>
						<div className="mt-3 flex items-end justify-between border-t border-line pt-3"><span className="font-semibold">{ __( 'Total due', 'twinspot-lite' ) }</span><span className="tabular text-[20px] font-semibold">{ money( total ) }</span></div>
					</div>
					<div className="rounded-lg bg-white p-4 text-[13px]">
						<p className="flex items-center gap-2 font-semibold text-primary"><Store size={ 14 } />{ __( 'What happens behind the scenes', 'twinspot-lite' ) }</p>
						<p className="mt-1 text-muted">
							{ blocked && __( 'This shopper earlier placed an order with the same email, phone or address. The coupon is refused politely.', 'twinspot-lite' ) }
							{ ! blocked && draft.hold && __( 'The coupon works, but the order is placed On hold with a note so you can review it.', 'twinspot-lite' ) }
							{ ! blocked && ! draft.hold && __( 'Protection is off. The coupon works and the order goes through as normal.', 'twinspot-lite' ) }
						</p>
					</div>
				</div>
			</div>
		</Card>
	);
}

export function Settings( { saved, onSave }: { saved: SettingsData; onSave: ( s: SettingsData ) => Promise< unknown > } ) {
	const [ draft, setDraft ] = useState( saved );
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState< { ok: boolean; text: string } | null >( null );
	useEffect( () => setDraft( saved ), [ saved ] );
	const dirty = draft.enabled !== saved.enabled || draft.hold !== saved.hold;

	// Blocking and holding are alternatives: switching one on switches the other off.
	const setBlock = ( v: boolean ) => setDraft( { enabled: v, hold: v ? false : draft.hold } );
	const setHold = ( v: boolean ) => setDraft( { hold: v, enabled: v ? false : draft.enabled } );

	const save = async () => {
		setBusy( true );
		setMsg( null );
		try {
			await onSave( draft );
			setMsg( { ok: true, text: __( 'Settings saved.', 'twinspot-lite' ) } );
		} catch ( e ) {
			setMsg( { ok: false, text: ( e as Error ).message || __( 'Could not save settings.', 'twinspot-lite' ) } );
		}
		setBusy( false );
	};

	return (
		<div className="space-y-6">
			<Preview draft={ draft } />
			<Card className="p-6">
				<h2 className="text-[16px] font-semibold">{ __( 'Coupon Protection Rules', 'twinspot-lite' ) }</h2>
				<p className="text-muted">{ __( 'Applies to coupons marked “New customers only (Twinspot)”, on both classic and block checkout.', 'twinspot-lite' ) }</p>
				<div className="mt-5 space-y-4">
					<Row icon={ <Ban size={ 18 } /> } title={ __( 'Block repeat coupons automatically', 'twinspot-lite' ) } control={ <Toggle on={ draft.enabled } onChange={ setBlock } label={ __( 'Block repeat coupons automatically', 'twinspot-lite' ) } /> }>
						<p>{ __( 'Refuse the coupon at checkout when the shopper has a linked earlier order.', 'twinspot-lite' ) }</p>
						<p className="mt-1 text-[12px] text-primary">{ __( 'Recommended · prevents lost discount revenue upfront', 'twinspot-lite' ) }</p>
					</Row>
					<Row icon={ <PauseCircle size={ 18 } /> } title={ __( 'Hold suspicious orders for review', 'twinspot-lite' ) } control={ <Toggle on={ draft.hold } onChange={ setHold } label={ __( 'Hold suspicious orders for review', 'twinspot-lite' ) } /> }>
						<p>{ __( 'Let checkout proceed with the discount, but set the order to “On hold” for you to review. Turns off blocking.', 'twinspot-lite' ) }</p>
					</Row>
					<Row icon={ <CloudCog size={ 18 } /> } title={ __( 'Optional cloud cluster lookup', 'twinspot-lite' ) } badge={ <Badge>{ __( 'Coming soon', 'twinspot-lite' ) }</Badge> } control={ <Toggle on={ false } disabled label={ __( 'Optional cloud cluster lookup', 'twinspot-lite' ) } /> }>
						<p>{ __( 'Compare anonymized hashes with known cross-store abuse networks. Not available yet: this plugin makes no external calls and nothing leaves your store.', 'twinspot-lite' ) }</p>
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
