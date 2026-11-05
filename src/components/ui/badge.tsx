import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badge = cva( 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-semibold', {
	variants: {
		tone: {
			neutral: 'bg-primary-tint text-primary',
			warn: 'bg-accent-soft text-[#92400E] border border-accent-line',
			danger: 'bg-danger-soft text-danger border border-danger-line',
			info: 'bg-info text-primary',
			quiet: 'bg-primary-soft text-ink rounded-md font-medium',
		},
	},
	defaultVariants: { tone: 'neutral' },
} );

type Props = React.HTMLAttributes< HTMLSpanElement > & VariantProps< typeof badge >;

export const Badge = ( { className, tone, ...props }: Props ) => (
	<span className={ cn( badge( { tone } ), className ) } { ...props } />
);
