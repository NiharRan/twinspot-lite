import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MotionConfig } from 'motion/react';
import { getOverview, importOrders } from './lib/api';
import { Overview } from './screens/overview';
import type { Filters, Overview as OverviewData } from './types';

export function App() {
	const [ filters, setFilters ] = useState< Filters >( { search: '', min_size: 2, link: '', page: 1 } );
	const [ data, setData ] = useState< OverviewData | null >( null );
	const [ error, setError ] = useState( '' );
	const [ importing, setImporting ] = useState( false );
	const [ imported, setImported ] = useState< number | null >( null );

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
		setImporting( true );
		setError( '' );
		try {
			const { imported: n } = await importOrders();
			setImported( n );
			load();
		} catch ( e ) {
			setError( ( e as Error ).message || __( 'Import failed.', 'twinspot-lite' ) );
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
				imported={ imported }
				dismissImported={ () => setImported( null ) }
				onImport={ runImport }
			/>
		</MotionConfig>
	);
}
