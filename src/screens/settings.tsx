import { __ } from '@wordpress/i18n';
import { Card } from '../components/ui/card';

export function Settings( { enabled, setEnabled }: { enabled: boolean; setEnabled: ( on: boolean ) => void } ) {
	return (
		<Card className="p-6">
			<h2 className="text-[16px] font-semibold">{ __( 'Plugin Settings', 'twinspot-lite' ) }</h2>
			<label className="mt-4 flex cursor-pointer items-start gap-3">
				<input type="checkbox" className="mt-1" checked={ enabled } onChange={ ( e ) => setEnabled( e.target.checked ) } />
				<span>
					<span className="font-semibold">{ __( 'Block repeat welcome coupons', 'twinspot-lite' ) }</span>
					<span className="block text-muted">{ __( 'When off, orders are still indexed but no coupon is refused.', 'twinspot-lite' ) }</span>
				</span>
			</label>
		</Card>
	);
}
