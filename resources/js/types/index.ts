import type { LucideIcon } from 'lucide-vue-next';

export type UserRole = 'organizer' | 'manager' | 'volunteer';

export interface Auth {
    user: User;
    role: UserRole | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface MagicLinkFlash {
    personId: number;
    personName: string;
    personPhone: string | null;
    url: string;
}

export interface AccountInviteFlash {
    personId: number;
    personName: string;
    personPhone: string | null;
    email: string | null;
    url: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: {
        magicLink: MagicLinkFlash | null;
        accountInvite: AccountInviteFlash | null;
        recoveryRequested: boolean | null;
    };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
