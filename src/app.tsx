import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MotionConfig } from 'motion/react';
import { getOverview, importBatch, setSample } from './lib/api';
import { Overview } from './screens/overview';
import type { Filters, Overview as OverviewData } from './types';

export function App() {
	const [ filters, setFilters ] = useState< Filters >( { search: '', min_size: 2, link: '', page: 1 } );
	const [ data, setData ] = useState< OverviewData | null >( null );
	const [ error, setError ] = useState( '' );
	const [ importing, setImporting ] = useState( false );
	const [ progress, setProgress ] = useState( 0 );
	const [ imported, setImported ] = useState< { n: number; seconds: number } | null >( null );

	const load = useCallback( () => {
		getOverview( { ...filters, per_page: 8 } )
			.then( setData )
			.catch( ( e: Error ) => setError( e.message ) );
	}, [ filters ] );

	useEffect( () => {
		const t = setTimeout( load, filters.search ? 250 : 0 ); // Debounce typing only.
		return () => clearTimeout( t );
	}, [ load, filters.search ] );

	const runImport = async () => {
		const start = Date.now();
		setImporting( true );
		setProgress( 0 );
		setError( '' );
		try {
			let page = 1;
			let done = 0;
			let pages = 1;
			let total = 0;
			do {
				const b = await importBatch( page );
				done += b.processed;
				total = b.total;
				pages = b.pages;
				setProgress( total ? done / total : 1 );
				page++;
			} while ( page <= pages );
			setProgress( 1 );
			setImported( { n: done, seconds: Math.round( ( Date.now() - start ) / 100 ) / 10 } );
			load();
		} catch ( e ) {
			setError( ( e as Error ).message || __( 'Import failed.', 'twinspot-lite' ) );
		}
		setImporting( false );
	};

	const toggleSample = async ( on: boolean ) => {
		setImporting( true );
		setProgress( 0.1 );
		setError( '' );
		try {
			await setSample( on );
			setProgress( 1 );
			await new Promise( ( r ) => setTimeout( r, 400 ) ); // Let the bar finish before the screen changes.
			load();
		} catch ( e ) {
			setError( ( e as Error ).message );
		}
		setImporting( false );
	};

	return (
		<MotionConfig reducedMotion="user">
			<Overview
				data={ data }
				error={ error }
				filters={ filters }
				setFilters={ ( f: Partial< Filters > ) => setFilters( ( old ) => ( { ...old, page: 1, ...f } ) ) }
				setPage={ ( page: number ) => setFilters( ( old ) => ( { ...old, page } ) ) }
				importing={ importing }
				progress={ progress }
				imported={ imported }
				dismissImported={ () => setImported( null ) }
				onImport={ runImport }
				onSample={ toggleSample }
			/>
		</MotionConfig>
	);
}
