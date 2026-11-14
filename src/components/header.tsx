import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cn } from '../lib/utils';
import type { ExtraTab, Stats, Tab } from '../types';

const TABS: [ Tab, string ][] = [
	[ 'overview', __( 'Overview', 'twinspot-lite' ) ],
	[ 'accounts', __( 'Linked accounts', 'twinspot-lite' ) ],
	[ 'rules', __( 'Rules', 'twinspot-lite' ) ],
	[ 'activity', __( 'Activity', 'twinspot-lite' ) ],
	[ 'settings', __( 'Settings', 'twinspot-lite' ) ],
];

/** Add-on tabs from the `twinspotLite.tabs` filter, read at render time; ids that clash with built-in tabs are ignored. */
export function extraTabs(): ExtraTab[] {
	const extras = applyFilters( 'twinspotLite.tabs', [] ) as ExtraTab[];
	return extras.filter( ( t, i ) => ! TABS.some( ( [ id ] ) => id === t.id ) && extras.findIndex( ( e ) => e.id === t.id ) === i );
}

export function Header( { tab, setTab, enabled, stats }: { tab: Tab; setTab: ( t: Tab ) => void; enabled: boolean; stats?: Stats } ) {
	const guarding = stats && stats.coupons > 0;
	let status: string = __( 'Guard paused', 'twinspot-lite' );
	if ( enabled ) {
		status = guarding
			? sprintf( _n( 'Guarding %d coupon', 'Guarding %d coupons', stats.coupons, 'twinspot-lite' ), stats.coupons )
			: __( 'No coupons guarded yet', 'twinspot-lite' );
	}
	return (
		<div className="mb-8 flex flex-wrap items-center justify-between gap-x-8 gap-y-2 border-b border-line">
			<div className="flex flex-wrap items-center gap-x-8 gap-y-2">
				<div className="flex items-center gap-2.5">
					<svg width="26" height="26" viewBox="0 0 26 26" aria-hidden="true">
						<circle cx="9.5" cy="13" r="6.5" fill="none" stroke="#132120" strokeWidth="2.2" />
						<circle cx="16.5" cy="13" r="6.5" fill="none" stroke="#0F6E5E" strokeWidth="2.2" />
					</svg>
					<span className="text-[17px] font-bold tracking-[-0.02em]">Twinspot</span>
					{ stats && <span className="text-[12px] text-muted">{ stats.version }</span> }
				</div>
				<nav className="flex flex-wrap gap-x-6" aria-label={ __( 'Twinspot sections', 'twinspot-lite' ) }>
					{ [ ...TABS, ...extraTabs().map( ( t ): [ Tab, string ] => [ t.id, t.label ] ) ].map( ( [ id, label ] ) => (
						<button
							key={ id }
							type="button"
							aria-current={ tab === id ? 'page' : undefined }
							onClick={ () => setTab( id ) }
							className={ cn( '-mb-px border-b-2 py-[18px] text-[14px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary', tab === id ? 'border-ink font-semibold text-ink' : 'border-transparent font-medium text-muted hover:text-ink' ) }
						>
							{ label }
						</button>
					) ) }
				</nav>
			</div>
			<span className={ cn( 'inline-flex items-center gap-2 py-2 font-semibold', enabled && guarding ? 'text-ink' : 'text-muted' ) }>
				<span className={ cn( 'h-2 w-2 rounded-full', enabled && guarding ? 'bg-success' : 'bg-accent' ) } />
				{ status }
			</span>
		</div>
	);
}
