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
            className={'erp-button erp-primary ' + className}
            disabled={disabled}
        >
            {icon && <span className="h-4 w-4 shrink-0">{icon}</span>}
            {children}
        </button>
    );
}
