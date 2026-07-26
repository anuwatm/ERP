import { ButtonHTMLAttributes, ReactNode } from 'react';

interface DangerButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    icon?: ReactNode;
}

export default function DangerButton({
    className = '',
    disabled,
    children,
    icon,
    ...props
}: DangerButtonProps) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white shadow-md shadow-rose-600/25 border border-rose-400/30 transition-all duration-150 hover:from-rose-500 hover:to-red-500 hover:shadow-lg hover:shadow-rose-600/35 focus:outline-none focus:ring-2 focus:ring-rose-500/50 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none ${
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
