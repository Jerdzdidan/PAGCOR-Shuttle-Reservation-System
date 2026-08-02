import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    employee?: AuthenticatedEmployee | null;
}

export interface AuthenticatedEmployee {
    employee_id: number;
    employee_code: string;
    name: string;
    email: string;
    contact_number: string | null;
    department: string | null;
    position: string | null;
    priority_status: 'REGULAR' | 'PRIORITY';
    status: 'ACTIVE' | 'INACTIVE';
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    badge?: number | string | null;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash?: { success?: string; error?: string };
    pending_completion_count?: number;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    user_type: 'ADMIN' | 'EMPLOYEE';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
