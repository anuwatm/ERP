export interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface AuthProps {
    user: User;
    permissions: string[];
}

export interface OrganizationProps {
    id: string;
    name: string;
    currency: string;
    timezone: string;
}

export interface FlashProps {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: AuthProps;
    org?: OrganizationProps;
    flash: FlashProps;
};
