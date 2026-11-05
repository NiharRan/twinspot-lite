import apiFetch from '@wordpress/api-fetch';
import type { Overview } from '../types';

const base = '/twinspot-lite/v1';

export const getOverview = ( params: Record< string, string | number > ): Promise< Overview > => {
	const qs = new URLSearchParams( Object.entries( params ).map( ( [ k, v ] ) => [ k, String( v ) ] ) ).toString();
	return apiFetch< Overview >( { path: `${ base }/overview?${ qs }` } );
};

export const importOrders = () => apiFetch< { imported: number } >( { path: `${ base }/import`, method: 'POST' } );
