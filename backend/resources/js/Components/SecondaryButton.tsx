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
            className={'erp-button erp-secondary ' + className}
            disabled={disabled}
        >
            {icon && (
                <span className="h-4 w-4 shrink-0 text-slate-400">{icon}</span>
            )}
            {children}
        </button>
    );
}
