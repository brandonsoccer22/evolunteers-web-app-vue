import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    roles?: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    allowedRoles?: string[];
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    first_name: string;
    last_name: string;
    name?: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    organizations?: Organization[];
    roles?: string[];
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Organization {
    id: number;
    name: string;
    description?: string | null;
    created_at?: string;
    updated_at?: string;
    users?: User[];
}

export interface Opportunity {
    id: number;
    name: string;
    description?: string | null;
    url?: string | null;
    start_date?: string | null;
    end_date?: string | null;
    start_time?: string | null;
    end_time?: string | null;
    created_at?: string;
    updated_at?: string;
    organizations?: Organization[];
    tags?: Tag[];
}

export interface Tag {
    id: number;
    name: string;
}

export interface PaginationLinks {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    links?: { url: string | null; label: string; active: boolean }[];
    path?: string;
    per_page: number;
    to: number | null;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    links?: PaginationLinks;
    meta?: PaginationMeta;
}
