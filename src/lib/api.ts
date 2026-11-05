import apiFetch from '@wordpress/api-fetch';
import type { Overview } from '../types';

const base = '/twinspot-lite/v1';

export const getOverview = ( params: Record< string, string | number > ): Promise< Overview > => {
	const qs = new URLSearchParams( Object.entries( params ).map( ( [ k, v ] ) => [ k, String( v ) ] ) ).toString();
	return apiFetch< Overview >( { path: `${ base }/overview?${ qs }` } );
};

export interface ImportBatch {
	processed: number;
	total: number;
	pages: number;
}

export const importBatch = ( page: number ) => apiFetch< ImportBatch >( { path: `${ base }/import?page=${ page }`, method: 'POST' } );

export const setSample = ( on: boolean ) => apiFetch( { path: `${ base }/sample`, method: on ? 'POST' : 'DELETE' } );
