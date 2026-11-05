import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const button = cva(
	'inline-flex items-center justify-center gap-2 rounded-md text-[14px] font-medium h-9 px-4 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-60 disabled:pointer-events-none',
	{
		variants: {
			variant: {
				primary: 'bg-primary text-white hover:bg-primary-hover active:bg-primary-press',
				secondary: 'bg-white text-ink border border-line hover:bg-primary-soft',
				ghost: 'text-muted hover:bg-primary-soft hover:text-primary',
			},
		},
		defaultVariants: { variant: 'primary' },
	}
);

type Props = React.ButtonHTMLAttributes< HTMLButtonElement > & VariantProps< typeof button >;

export function Button( { className, variant, ...props }: Props ) {
	return <button type="button" className={ cn( button( { variant } ), className ) } { ...props } />;
}
