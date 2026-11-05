import { cn } from '../../lib/utils';

export const Card = ( { className, ...props }: React.HTMLAttributes< HTMLDivElement > ) => (
	<div className={ cn( 'rounded-xl border border-line bg-white shadow-card', className ) } { ...props } />
);
