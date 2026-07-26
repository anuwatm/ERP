import { ButtonHTMLAttributes, ReactNode } from 'react';

interface SecondaryButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    icon?: ReactNode;
}

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    icon,
    ...props
}: SecondaryButtonProps) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center justify-center gap-2 rounded-xl border border-slate-800 bg-slate-900/90 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-300 shadow-sm transition-all duration-150 hover:bg-slate-800 hover:text-white hover:border-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none ${
                    disabled && 'opacity-50'
                } ` + className
            }
            disabled={disabled}
        >
            {icon && (
                <span className="h-4 w-4 shrink-0 text-slate-400">{icon}</span>
            )}
            {children}
        </button>
    );
}
