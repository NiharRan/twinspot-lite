import { __, sprintf } from '@wordpress/i18n';
import { AtSign, Home, Phone, TicketCheck } from 'lucide-react';
import { Card } from '../components/ui/card';
import type { Overview } from '../types';

export function Rules( { data, enabled }: { data: Overview | null; enabled: boolean } ) {
	const rows = [
		{ icon: AtSign, title: __( 'Email', 'twinspot-lite' ), text: __( 'Gmail dots and +tags are ignored, so john.doe+deal@gmail.com matches johndoe@gmail.com.', 'twinspot-lite' ) },
		{ icon: Phone, title: __( 'Phone', 'twinspot-lite' ), text: __( 'The last 10 digits are compared, ignoring spaces and dashes.', 'twinspot-lite' ) },
		{ icon: Home, title: __( 'Address', 'twinspot-lite' ), text: __( 'Street line and postcode must both be present to match.', 'twinspot-lite' ) },
	];
	return (
		<div className="flex flex-col gap-5">
			<Card className="p-6">
				<h2 className="text-[16px] font-semibold">{ __( 'New customers only coupons', 'twinspot-lite' ) }</h2>
				<p className="mt-1 text-muted">
					{ enabled
						? __( 'A coupon with “New customers only (Twinspot)” ticked is refused when the shopper’s email, phone or address matches an earlier order.', 'twinspot-lite' )
						: __( 'Blocking is paused in Plugin Settings. Orders are still indexed.', 'twinspot-lite' ) }
				</p>
				<div className="mt-4 flex items-center gap-3">
					<span className="flex h-9 w-9 items-center justify-center rounded-lg bg-info text-primary"><TicketCheck size={ 18 } /></span>
					<span>{ data ? sprintf( __( '%d coupons use this rule.', 'twinspot-lite' ), data.stats.coupons ) : '…' }</span>
					<a className="text-primary hover:underline" href="edit.php?post_type=shop_coupon">{ __( 'Edit coupons', 'twinspot-lite' ) }</a>
				</div>
			</Card>
			<Card className="p-6">
				<h2 className="text-[16px] font-semibold">{ __( 'What counts as a match', 'twinspot-lite' ) }</h2>
				<div className="mt-4 grid gap-6 md:grid-cols-3">
					{ rows.map( ( r ) => (
						<div key={ r.title } className="flex gap-3">
							<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-tint text-primary"><r.icon size={ 18 } /></span>
							<div><div className="font-semibold">{ r.title }</div><p className="mt-1 text-[13px] leading-[18px] text-muted">{ r.text }</p></div>
						</div>
					) ) }
				</div>
				<p className="mt-5 text-[13px] text-muted">{ __( 'IP addresses are stored but never used to match, because shared networks would block innocent shoppers. If a detail is missing, nothing matches and checkout continues.', 'twinspot-lite' ) }</p>
			</Card>
		</div>
	);
}
