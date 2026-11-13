import { cn } from '../../lib/utils';

export const Card = ( { className, ...props }: React.HTMLAttributes< HTMLDivElement > ) => (
	<div className={ cn( 'rounded-[14px] border border-line bg-white', className ) } { ...props } />
);
