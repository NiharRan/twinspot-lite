import apiFetch from '@wordpress/api-fetch';
import type { Activity, ActivityFilters, Overview, RingDetail } from '../types';

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

const query = ( f: ActivityFilters ) => new URLSearchParams( Object.entries( f ).map( ( [ k, v ] ) => [ k, String( v ) ] ) ).toString();

export const getActivity = ( f: ActivityFilters ) => apiFetch< Activity >( { path: `${ base }/activity?${ query( f ) }` } );

export const exportActivity = ( f: ActivityFilters ) => apiFetch< { filename: string; csv: string } >( { path: `${ base }/activity/export?${ query( f ) }` } );

export const allowRing = ( orderId: number, on: boolean ) => apiFetch< { allowed: boolean } >( { path: `${ base }/ring/${ orderId }/allow`, method: on ? 'POST' : 'DELETE' } );

export interface SettingsData {
	enabled: boolean;
	hold: boolean;
}

export const getSettings = () => apiFetch< SettingsData >( { path: `${ base }/settings` } );

export const saveSettings = ( data: SettingsData ) => apiFetch< SettingsData >( { path: `${ base }/settings`, method: 'POST', data } );

export const setSample = ( on: boolean ) => apiFetch( { path: `${ base }/sample`, method: on ? 'POST' : 'DELETE' } );

export const getRing = ( orderId: number ) => apiFetch< RingDetail >( { path: `${ base }/ring/${ orderId }` } );

export const blockRing = ( orderId: number, on: boolean ) => apiFetch< { blocked: boolean } >( { path: `${ base }/ring/${ orderId }/block`, method: on ? 'POST' : 'DELETE' } );
