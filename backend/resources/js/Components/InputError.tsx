import { HTMLAttributes } from 'react';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            role="alert"
            {...props}
            className={'erp-field-error text-sm ' + className}
        >
            {message}
        </p>
    ) : null;
}
