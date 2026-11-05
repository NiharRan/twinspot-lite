import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MotionConfig } from 'motion/react';
import { getOverview, getSettings, importBatch, saveSettings, setSample } from './lib/api';
import { Header } from './components/header';
import { Accounts } from './screens/accounts';
import { Activity } from './screens/activity';
import { Overview } from './screens/overview';
import { RingDetail } from './screens/ring-detail';
import { Rules } from './screens/rules';
import { Settings } from './screens/settings';
import type { Filters, Overview as OverviewData, Tab } from './types';

export function App() {
	const [ filters, setFilters ] = useState< Filters >( { search: '', min_size: 2, link: '', page: 1 } );
	const [ data, setData ] = useState< OverviewData | null >( null );
	const [ error, setError ] = useState( '' );
	const [ importing, setImporting ] = useState( false );
	const [ tab, setTab ] = useState< Tab >( 'overview' );
	const [ ringKey, setRingKey ] = useState< number | null >( null );
	const [ enabled, setEnabled ] = useState( true );
	const [ progress, setProgress ] = useState( 0 );
	const [ imported, setImported ] = useState< { n: number; seconds: number } | null >( null );

	const load = useCallback( () => {
		getOverview( { ...filters, per_page: 8 } )
			.then( setData )
			.catch( ( e: Error ) => setError( e.message ) );
	}, [ filters ] );

	useEffect( () => {
		getSettings().then( ( r ) => setEnabled( r.enabled ) ).catch( () => {} );
	}, [] );

	const toggleEnabled = ( on: boolean ) => {
		setEnabled( on );
		saveSettings( on ).catch( ( e: Error ) => {
			setEnabled( ! on );
			setError( e.message );
		} );
	};

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
			<Header tab={ tab } setTab={ ( t ) => { setRingKey( null ); setTab( t ); } } enabled={ enabled } />
			{ error && tab !== 'overview' && <div role="alert" className="mb-5 rounded-xl border border-danger-line bg-danger-soft p-4 text-danger">{ error }</div> }
			{ ringKey !== null && <RingDetail orderId={ ringKey } onBack={ () => setRingKey( null ) } /> }
			{ ringKey === null && tab === 'overview' && (
				<Overview
					data={ data }
					error={ error }
					filters={ filters }
					setFilters={ ( f: Partial< Filters > ) => setFilters( ( old ) => ( { ...old, page: 1, ...f } ) ) }
					setPage={ ( page: number ) => setFilters( ( old ) => ( { ...old, page } ) ) }
					onOpen={ setRingKey }
					importing={ importing }
					progress={ progress }
					imported={ imported }
					dismissImported={ () => setImported( null ) }
					onImport={ runImport }
					onSample={ toggleSample }
				/>
			) }
			{ ringKey === null && tab === 'accounts' && <Accounts onOpen={ setRingKey } data={ data } filters={ filters } setFilters={ ( f: Partial< Filters > ) => setFilters( ( old ) => ( { ...old, page: 1, ...f } ) ) } setPage={ ( page: number ) => setFilters( ( old ) => ( { ...old, page } ) ) } /> }
			{ ringKey === null && tab === 'rules' && <Rules data={ data } enabled={ enabled } /> }
			{ ringKey === null && tab === 'activity' && <Activity /> }
			{ ringKey === null && tab === 'settings' && <Settings enabled={ enabled } setEnabled={ toggleEnabled } /> }
		</MotionConfig>
	);
}
