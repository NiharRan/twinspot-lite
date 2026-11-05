import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { __ } from '@wordpress/i18n';

/** Right-hand side panel (shadcn Sheet). Portals into #twinspot-root so the scoped Tailwind styles still apply. */
export function Sheet( { open, onClose, title, description, children, footer }: { open: boolean; onClose: () => void; title: React.ReactNode; description: string; children: React.ReactNode; footer?: React.ReactNode } ) {
	return (
		<Dialog.Root open={ open } onOpenChange={ ( o ) => ! o && onClose() }>
			<Dialog.Portal container={ document.getElementById( 'twinspot-root' ) }>
				<Dialog.Overlay className="ts-overlay fixed inset-0 z-[100000] bg-slate-900/40" />
				<Dialog.Content className="ts-sheet fixed inset-y-0 right-0 z-[100001] flex w-full max-w-[560px] flex-col bg-white shadow-2xl focus:outline-none">
					<div className="flex items-start justify-between gap-3 border-b border-line p-5">
						<div className="min-w-0"><Dialog.Title asChild><div className="text-[16px] font-semibold leading-6">{ title }</div></Dialog.Title><Dialog.Description className="mt-0.5 text-[12px] text-muted">{ description }</Dialog.Description></div>
						<Dialog.Close className="rounded-md p-1.5 text-muted hover:bg-primary-soft hover:text-ink" aria-label={ __( 'Close', 'twinspot-lite' ) }><X size={ 18 } /></Dialog.Close>
					</div>
					<div className="flex-1 overflow-y-auto p-5">{ children }</div>
					{ footer && <div className="flex flex-wrap items-center gap-2 border-t border-line p-4">{ footer }</div> }
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
}
