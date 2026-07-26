import { ButtonHTMLAttributes, ReactNode } from 'react';

interface PrimaryButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    icon?: ReactNode;
}

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    icon,
    ...props
}: PrimaryButtonProps) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-600 to-violet-600 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white shadow-md shadow-indigo-600/25 border border-indigo-400/30 transition-all duration-150 hover:from-indigo-500 hover:to-violet-500 hover:shadow-lg hover:shadow-indigo-600/35 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none ${
                    disabled && 'opacity-50'
                } ` + className
            }
            disabled={disabled}
        >
            {icon && <span className="h-4 w-4 shrink-0">{icon}</span>}
            {children}
        </button>
    );
}
