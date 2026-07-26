import {
    SelectHTMLAttributes,
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
} from 'react';

export default forwardRef(function SelectInput(
    {
        className = '',
        children,
        isFocused = false,
        ...props
    }: SelectHTMLAttributes<HTMLSelectElement> & { isFocused?: boolean },
    ref,
) {
    const localRef = useRef<HTMLSelectElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <select
            {...props}
            className={
                'w-full rounded-xl border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150 ' +
                className
            }
            ref={localRef}
        >
            {children}
        </select>
    );
});
