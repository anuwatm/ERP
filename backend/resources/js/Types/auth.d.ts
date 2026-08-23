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
    legal_name?: string | null;
    logo_url?: string | null;
    currency: string;
    timezone: string;
}

export interface FlashProps {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

export interface NotificationProps {
    unread_count: number;
    latest: Array<{
        id: string;
        title: string;
        body?: string | null;
        url?: string | null;
        read_at?: string | null;
    }>;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: AuthProps;
    org?: OrganizationProps | null;
    flash: FlashProps;
    notifications: NotificationProps;
};
