import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MotionConfig, motion } from 'motion/react';
import { getOverview, getSettings, importBatch, saveSettings, setSample, type SettingsData } from './lib/api';
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
	const [ ringKey, setRingKey ] = useState< number | null >( Number( new URLSearchParams( window.location.search ).get( 'ring' ) ) || null ); // ?ring=<order id> deep-links from order notes.
	const [ settings, setSettings ] = useState< SettingsData >( { enabled: true, hold: false } );
	const enabled = settings.enabled || settings.hold; // Hold mode still guards coupons, just without refusing them.
	const [ progress, setProgress ] = useState( 0 );
	const locked = useRef( false ); // Blocks a second click before `importing` re-renders.
	const [ imported, setImported ] = useState< { n: number; seconds: number } | null >( null );

	const load = useCallback( () => {
		getOverview( { ...filters, per_page: 8 } )
			.then( setData )
			.catch( ( e: Error ) => setError( e.message ) );
	}, [ filters ] );

	useEffect( () => {
		getSettings().then( setSettings ).catch( () => {} );
	}, [] );

	const save = ( next: SettingsData ) => saveSettings( next ).then( setSettings );

	useEffect( () => {
		const t = setTimeout( load, filters.search ? 250 : 0 ); // Debounce typing only.
		return () => clearTimeout( t );
	}, [ load, filters.search ] );

	const runImport = async () => {
		if ( locked.current ) {
			return;
		}
		locked.current = true;
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
		locked.current = false;
		setImporting( false );
	};

	const toggleSample = async ( on: boolean ) => {
		if ( locked.current ) {
			return;
		}
		locked.current = true;
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
		locked.current = false;
		setImporting( false );
	};

	return (
		<MotionConfig reducedMotion="user">
			<Header tab={ tab } setTab={ ( t ) => { setRingKey( null ); setTab( t ); } } enabled={ enabled } />
			{ error && tab !== 'overview' && <div role="alert" className="mb-5 rounded-xl border border-danger-line bg-danger-soft p-4 text-danger">{ error }</div> }
			<motion.div key={ ringKey ?? tab } initial={ { opacity: 0, y: 8 } } animate={ { opacity: 1, y: 0 } } transition={ { duration: 0.22, ease: 'easeOut' } }>
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
			{ ringKey === null && tab === 'activity' && <Activity onOpen={ setRingKey } /> }
			{ ringKey === null && tab === 'settings' && <Settings saved={ settings } onSave={ save } /> }
			</motion.div>
		</MotionConfig>
	);
}
