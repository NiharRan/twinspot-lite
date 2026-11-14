import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badge = cva( 'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[12px] font-semibold', {
	variants: {
		tone: {
			neutral: 'bg-primary-tint text-primary',
			warn: 'bg-accent-soft text-accent-ink',
			danger: 'bg-danger-soft text-danger',
			info: 'bg-info text-ink',
			quiet: 'bg-info text-ink',
		},
	},
	defaultVariants: { tone: 'neutral' },
} );

type Props = React.HTMLAttributes< HTMLSpanElement > & VariantProps< typeof badge >;

export const Badge = ( { className, tone, ...props }: Props ) => (
	<span className={ cn( badge( { tone } ), className ) } { ...props } />
);
