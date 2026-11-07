import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { PropsWithChildren } from 'react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
    title,
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '4xl' | '5xl';
    closeable?: boolean;
    onClose: CallableFunction;
    title?: string;
}>) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '4xl': 'sm:max-w-4xl',
        '5xl': 'sm:max-w-5xl',
    }[maxWidth];

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                id="modal"
                className="fixed inset-0 z-50 flex transform items-center overflow-y-auto px-4 py-6 transition-all sm:px-0"
                onClose={close}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-500/75" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel
                        className={`erp-modal mb-6 max-h-[calc(100dvh-5rem)] w-full min-w-0 transform overflow-y-auto rounded-xl bg-white text-slate-900 shadow-xl transition-all dark:bg-slate-900 dark:text-slate-100 sm:mx-auto ${maxWidthClass}`}
                    >
                        {title && (
                            <DialogTitle className="sr-only">
                                {title}
                            </DialogTitle>
                        )}
                        {children}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
