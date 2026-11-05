import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Card } from '../components/ui/card';
import { getActivity } from '../lib/api';

export function Activity() {
	const [ days, setDays ] = useState< Record< string, number > | null >( null );
	useEffect( () => {
		getActivity().then( ( r ) => setDays( r.days ) ).catch( () => setDays( {} ) );
	}, [] );
	const entries = Object.entries( days ?? {} );
	return (
		<Card className="overflow-hidden">
			<div className="p-5">
				<h2 className="text-[16px] font-semibold">{ __( 'Blocked coupon attempts, last 30 days', 'twinspot-lite' ) }</h2>
			</div>
			{ days === null && <p className="px-5 pb-8 text-muted">{ __( 'Loading…', 'twinspot-lite' ) }</p> }
			{ days !== null && entries.length === 0 && <p className="px-5 pb-8 text-muted">{ __( 'Nothing blocked yet.', 'twinspot-lite' ) }</p> }
			{ entries.length > 0 && (
				<table>
					<tbody>
						{ entries.map( ( [ day, n ] ) => (
							<tr key={ day } className="border-t border-line">
								<td className="px-5 py-3">{ new Date( day + 'T00:00:00Z' ).toLocaleDateString( undefined, { timeZone: 'UTC' } ) }</td>
								<td className="tabular px-5 py-3 text-right">{ sprintf( _n( '%d coupon blocked', '%d coupons blocked', n, 'twinspot-lite' ), n ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</Card>
	);
}
