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
            className={'erp-button erp-danger ' + className}
            disabled={disabled}
        >
            {icon && <span className="h-4 w-4 shrink-0">{icon}</span>}
            {children}
        </button>
    );
}
