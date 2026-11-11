import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { cn } from '../lib/utils';
import type { ExtraTab, Tab } from '../types';

const TABS: [ Tab, string ][] = [
	[ 'overview', __( 'Overview', 'twinspot-lite' ) ],
	[ 'accounts', __( 'Linked Accounts', 'twinspot-lite' ) ],
	[ 'rules', __( 'Rules & Limits', 'twinspot-lite' ) ],
	[ 'activity', __( 'Activity Log', 'twinspot-lite' ) ],
	[ 'settings', __( 'Plugin Settings', 'twinspot-lite' ) ],
];

/** Add-on tabs from the `twinspotLite.tabs` filter, read at render time; ids that clash with built-in tabs are ignored. */
export function extraTabs(): ExtraTab[] {
	const extras = applyFilters( 'twinspotLite.tabs', [] ) as ExtraTab[];
	return extras.filter( ( t, i ) => ! TABS.some( ( [ id ] ) => id === t.id ) && extras.findIndex( ( e ) => e.id === t.id ) === i );
}

export function Header( { tab, setTab, enabled }: { tab: Tab; setTab: ( t: Tab ) => void; enabled: boolean } ) {
	return (
		<div className="-mx-5 mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-line bg-white px-5">
			<nav className="flex flex-wrap gap-6" aria-label={ __( 'Twinspot sections', 'twinspot-lite' ) }>
				{ [ ...TABS, ...extraTabs().map( ( t ): [ Tab, string ] => [ t.id, t.label ] ) ].map( ( [ id, label ] ) => (
					<button
						key={ id }
						type="button"
						aria-current={ tab === id ? 'page' : undefined }
						onClick={ () => setTab( id ) }
						className={ cn( '-mb-px border-b-2 py-3.5 text-[14px] font-medium transition-colors', tab === id ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink' ) }
					>
						{ label }
					</button>
				) ) }
			</nav>
			<span className={ cn( 'inline-flex items-center gap-2 rounded-full px-3 py-1 text-[12px] font-semibold', enabled ? 'bg-primary-tint text-primary' : 'bg-accent-soft text-ink' ) }>
				<span className={ cn( 'h-2 w-2 rounded-full', enabled ? 'bg-success' : 'bg-accent' ) } />
				{ enabled ? __( 'Shield Active', 'twinspot-lite' ) : __( 'Shield Paused', 'twinspot-lite' ) }
			</span>
		</div>
	);
}
