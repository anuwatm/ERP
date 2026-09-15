import {
    Dialog,
    DialogDescription,
    DialogPanel,
    DialogTitle,
} from '@headlessui/react';

export default function DiscardChangesDialog({
    open,
    onKeep,
    onDiscard,
}: {
    open: boolean;
    onKeep: () => void;
    onDiscard: () => void;
}) {
    return (
        <Dialog open={open} onClose={onKeep} className="relative z-[60]">
            <div className="fixed inset-0 bg-black/50" aria-hidden="true" />
            <div className="fixed inset-0 flex items-center justify-center p-4">
                <DialogPanel className="w-full max-w-sm rounded-lg bg-white p-6 text-gray-900 shadow-xl dark:bg-gray-900 dark:text-gray-100">
                    <DialogTitle className="text-lg font-semibold">
                        Discard unsaved changes?
                    </DialogTitle>
                    <DialogDescription className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Your changes have not been saved. Discard them to
                        continue.
                    </DialogDescription>
                    <div className="mt-5 flex flex-wrap justify-end gap-2">
                        <button
                            type="button"
                            data-autofocus
                            onClick={onKeep}
                            className="min-h-10 rounded-md border border-gray-300 px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:border-gray-600"
                        >
                            Keep editing
                        </button>
                        <button
                            type="button"
                            onClick={onDiscard}
                            className="min-h-10 rounded-md bg-red-700 px-3 text-sm font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-500"
                        >
                            Discard changes
                        </button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
