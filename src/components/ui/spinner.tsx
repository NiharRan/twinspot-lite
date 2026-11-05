import { __ } from '@wordpress/i18n';
import { Loader2 } from 'lucide-react';

export function Spinner( { className = 'p-10' }: { className?: string } ) {
	return (
		<div role="status" className={ `flex items-center justify-center gap-2 text-muted ${ className }` }>
			<Loader2 size={ 18 } className="animate-spin" />
			<span>{ __( 'Loading…', 'twinspot-lite' ) }</span>
		</div>
	);
}
