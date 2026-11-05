import { cn } from '../../lib/utils';

const field =
	'h-9 rounded-md border border-line bg-white px-3 text-[14px] text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';

export const Input = ( { className, ...props }: React.InputHTMLAttributes< HTMLInputElement > ) => <input className={ cn( field, className ) } { ...props } />;
export const Select = ( { className, ...props }: React.SelectHTMLAttributes< HTMLSelectElement > ) => <select className={ cn( field, 'pr-8', className ) } { ...props } />;
