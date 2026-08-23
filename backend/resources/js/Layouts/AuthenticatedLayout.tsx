import Dropdown from '@/Components/Dropdown';
import ThemeToggle from '@/Components/UI/ThemeToggle';
import { PageProps } from '@/Types/auth';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

interface AuthenticatedLayoutProps extends PropsWithChildren {
    header?: ReactNode;
}

export default function AuthenticatedLayout({
    header,
    children,
}: AuthenticatedLayoutProps) {
    const pageProps = usePage<PageProps>().props;
    const user = pageProps.auth.user;
    const org = pageProps.org;
    const permissions = pageProps.auth.permissions ?? [];
    const notifications = pageProps.notifications ?? {
        unread_count: 0,
        latest: [],
    };

    const [sidebarOpen, setSidebarOpen] = useState(false);

    const navItems = [
        {
            group: 'Core',
            items: [
                {
                    name: 'Dashboard',
                    href: route('dashboard'),
                    active: route().current('dashboard'),
                    permission: 'dashboard.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                            />
                        </svg>
                    ),
                },
            ],
        },
        {
            group: 'Executive',
            items: [
                {
                    name: 'Executive Dashboard',
                    href: route('executive.dashboard'),
                    active: route().current('executive.dashboard'),
                    permission: 'executive.dashboard.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M4 19V5m0 14h16M8 16V9m4 7V7m4 9v-4"
                            />
                        </svg>
                    ),
                },
            ],
        },
        {
            group: 'Sales',
            items: [
                {
                    name: 'Sales Dashboard',
                    href: route('sales.dashboard'),
                    active: route().current('sales.dashboard'),
                    permission: 'sales.dashboard.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M11 3v18m4-14H9.5a3.5 3.5 0 000 7H14a3.5 3.5 0 010 7H8"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Customers',
                    href: route('customers.index'),
                    active: route().current('customers.*'),
                    permission: 'customers.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m8-4a4 4 0 10-8 0 4 4 0 008 0z"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Deals',
                    href: route('deals.index'),
                    active: route().current('deals.*'),
                    permission: 'deals.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 12h6m-6 4h6M4 6h16M6 6v14h12V6"
                            />
                        </svg>
                    ),
                },
            ],
        },
        {
            group: 'Finance',
            items: [
                {
                    name: 'Finance Dashboard',
                    href: route('finance.dashboard'),
                    active: route().current('finance.dashboard'),
                    permission: 'expenses.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m-7 4h14"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Invoices',
                    href: route('invoices.index'),
                    active: route().current('invoices.*'),
                    permission: 'invoices.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 14h6m-6 4h6M7 4h10l2 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6l2-2z"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Expenses',
                    href: route('expenses.index'),
                    active: route().current('expenses.*'),
                    permission: 'expenses.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m-6-6a6 6 0 1112 0 6 6 0 01-12 0z"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Tax Reports',
                    href: route('tax-reports.index'),
                    active: route().current('tax-reports.*'),
                    permission: 'tax_reports.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 17h6m-6-4h6m-6-4h2m-4 12h10a2 2 0 002-2V7l-4-4H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Suppliers',
                    href: route('suppliers.index'),
                    active: route().current('suppliers.*'),
                    permission: 'suppliers.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M3 7h18M5 7v12h14V7M8 11h3m2 0h3M8 15h8"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Purchase Orders',
                    href: route('purchase-orders.index'),
                    active: route().current('purchase-orders.*'),
                    permission: 'purchase_orders.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 12h6m-6 4h6M7 4h10l2 2v14H5V6l2-2z"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Inventory / GRN',
                    href: route('goods-receipts.index'),
                    active: route().current('goods-receipts.*'),
                    permission: 'inventory.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 4L4 7"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Products/Services',
                    href: route('products.index'),
                    active: route().current('products.*'),
                    permission: 'products.manage',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            />
                        </svg>
                    ),
                },
            ],
        },
        {
            group: 'Delivery',
            items: [
                {
                    name: 'Delivery Dashboard',
                    href: route('delivery.dashboard'),
                    active: route().current('delivery.dashboard'),
                    permission: ['projects.view', 'tasks.view'],
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 17H7a2 2 0 01-2-2V5a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8m-4-4v8"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Tasks',
                    href: route('tasks.index'),
                    active: route().current('tasks.*'),
                    permission: 'tasks.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 11l3 3L22 4M7 5H4a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2v-5M7 12h1m-1 4h6"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Projects',
                    href: route('projects.index'),
                    active: route().current('projects.*'),
                    permission: 'projects.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 5h6m-8 4h10M7 13h10M9 17h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"
                            />
                        </svg>
                    ),
                },
            ],
        },
        {
            group: 'Administration',
            items: [
                {
                    name: 'Users',
                    href: route('users.index'),
                    active: route().current('users.*'),
                    permission: 'users.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Roles & Permissions',
                    href: route('roles.index'),
                    active: route().current('roles.*'),
                    permission: 'roles.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Audit Logs',
                    href: route('audit-logs.index'),
                    active: route().current('audit-logs.*'),
                    permission: 'audit.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                            />
                        </svg>
                    ),
                },
            ],
        },
        {
            group: 'Organization Settings',
            items: [
                {
                    name: 'Organization Profile',
                    href: route('settings.organization.edit'),
                    active: route().current('settings.organization.*'),
                    permission: 'settings.organization.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Structure & Chain',
                    href: route('settings.structure.index'),
                    active: route().current('settings.structure.*'),
                    permission: 'settings.structure.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                            />
                        </svg>
                    ),
                },
                {
                    name: 'Notifications',
                    href: route('settings.notifications.edit'),
                    active: route().current('settings.notifications.*'),
                    permission: 'settings.organization.view',
                    icon: (
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9"
                            />
                        </svg>
                    ),
                },
            ],
        },
    ];

    const visibleNavItems = navItems
        .map((section) => ({
            ...section,
            items: section.items.filter((item) =>
                Array.isArray(item.permission)
                    ? item.permission.some((permission) =>
                          permissions.includes(permission),
                      )
                    : permissions.includes(item.permission),
            ),
        }))
        .filter((section) => section.items.length > 0);
    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col md:flex-row font-sans selection:bg-indigo-500 selection:text-white transition-colors duration-200">
            {/* Desktop Sidebar */}
            <aside className="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-slate-900 dark:bg-slate-950/90 backdrop-blur-xl border-r border-slate-800/70 text-slate-300 z-30 shadow-2xl">
                {/* Brand Logo */}
                <div className="flex h-16 items-center gap-3 px-5 bg-slate-950 border-b border-slate-800/80">
                    {org?.logo_url ? (
                        <img
                            src={org.logo_url}
                            alt={org.name}
                            className="h-9 w-9 object-contain rounded-lg bg-white/10 p-1 border border-white/10 shadow-sm"
                        />
                    ) : (
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-violet-600 font-bold text-white shadow-lg shadow-indigo-500/30 ring-1 ring-white/20">
                            <svg
                                className="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2.5"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>
                        </div>
                    )}
                    <div>
                        <div className="font-bold tracking-wider text-white text-sm font-sans flex items-center gap-1.5 truncate max-w-[140px]">
                            {org?.name || 'ERP System'}
                        </div>
                        <div className="text-[10px] text-slate-400 font-medium tracking-widest uppercase">
                            Enterprise Suite
                        </div>
                    </div>
                </div>

                {/* Sidebar Nav */}
                <div className="flex-1 overflow-y-auto px-3.5 py-6 space-y-6 scrollbar-thin scrollbar-thumb-slate-800">
                    {visibleNavItems.map((section, idx) => (
                        <div key={idx}>
                            <div className="px-3 mb-2.5 text-[10px] font-bold tracking-widest text-slate-300 uppercase">
                                {section.group}
                            </div>
                            <div className="space-y-1">
                                {section.items.map((item) => (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        className={`group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-all duration-200 ${
                                            item.active
                                                ? 'bg-gradient-to-r from-indigo-600/90 to-indigo-700/90 text-white shadow-lg shadow-indigo-600/25 ring-1 ring-indigo-400/30'
                                                : 'text-slate-300 hover:bg-slate-900/90 hover:text-white hover:translate-x-0.5'
                                        }`}
                                    >
                                        {/* Active Left Pill */}
                                        {item.active && (
                                            <span className="absolute left-0 top-2 bottom-2 w-1 bg-white rounded-r-full shadow-sm" />
                                        )}
                                        <span
                                            className={`transition-colors duration-200 ${
                                                item.active
                                                    ? 'text-white'
                                                    : 'text-slate-300 group-hover:text-indigo-400'
                                            }`}
                                        >
                                            {item.icon}
                                        </span>
                                        <span className="truncate">
                                            {item.name}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Org Footer Info */}
                <div className="p-4 bg-slate-950 border-t border-slate-800/80">
                    <div className="flex items-center gap-3 px-2 py-1.5 rounded-lg bg-slate-900/60 border border-slate-800/60">
                        <div className="relative flex h-2.5 w-2.5">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
                        </div>
                        <div className="truncate text-xs text-slate-300 font-medium">
                            {org?.name || 'ERP System'}
                        </div>
                    </div>
                </div>
            </aside>

            {/* Mobile Nav Backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-md md:hidden transition-opacity"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Mobile Drawer */}
            <div
                className={`fixed inset-y-0 left-0 z-50 w-64 bg-slate-950/95 backdrop-blur-2xl text-slate-300 border-r border-slate-800/80 transition-transform duration-300 ease-in-out md:hidden ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center justify-between px-6 bg-slate-950 border-b border-slate-800/80">
                    <div className="font-bold text-white text-sm flex items-center gap-2 truncate">
                        <span className="h-2 w-2 rounded-full bg-indigo-500 flex-shrink-0" />
                        <span className="truncate">
                            {org?.name || 'ERP System'}
                        </span>
                    </div>
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-900"
                    >
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
                <div className="px-4 py-6 space-y-6 overflow-y-auto">
                    {visibleNavItems.map((section, idx) => (
                        <div key={idx}>
                            <div className="px-3 mb-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                                {section.group}
                            </div>
                            <div className="space-y-1">
                                {section.items.map((item) => (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        onClick={() => setSidebarOpen(false)}
                                        className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all ${
                                            item.active
                                                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                                                : 'text-slate-400 hover:bg-slate-900 hover:text-slate-200'
                                        }`}
                                    >
                                        {item.icon}
                                        {item.name}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Main Layout Area */}
            <div className="flex-1 md:ms-64 flex flex-col min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
                {/* Top Header */}
                <header className="sticky top-0 z-20 h-16 bg-white/80 dark:bg-slate-950/80 backdrop-blur-xl border-b border-slate-200/80 dark:border-slate-800/80 px-4 md:px-8 flex items-center justify-between shadow-sm transition-colors duration-200">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="md:hidden text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-900/60"
                        >
                            <svg
                                className="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                        {org && (
                            <span className="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-900/80 text-xs font-bold text-slate-800 dark:text-white border border-slate-200 dark:border-slate-800 shadow-inner">
                                <span className="h-2 w-2 rounded-full bg-indigo-500 dark:bg-indigo-400 shadow-sm shadow-indigo-400/50" />
                                {org.name}
                            </span>
                        )}
                    </div>

                    <div className="flex items-center gap-3">
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    title="Notifications"
                                    className="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-100 dark:hover:bg-slate-900"
                                >
                                    <svg
                                        className="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9"
                                        />
                                    </svg>
                                    {notifications.unread_count > 0 && (
                                        <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-slate-950">
                                            {notifications.unread_count > 99
                                                ? '99+'
                                                : notifications.unread_count}
                                        </span>
                                    )}
                                </button>
                            </Dropdown.Trigger>
                            <Dropdown.Content align="right" width="48">
                                <div className="border-b border-slate-100 bg-slate-50/80 px-4 py-3 text-xs font-bold text-slate-900 dark:border-slate-800/80 dark:bg-slate-950/60 dark:text-white">
                                    Notifications
                                </div>
                                <div className="max-h-80 overflow-y-auto py-1">
                                    {notifications.latest.length === 0 ? (
                                        <div className="px-4 py-4 text-xs font-medium text-slate-500 dark:text-slate-300">
                                            No notifications
                                        </div>
                                    ) : (
                                        notifications.latest.map(
                                            (notification) =>
                                                notification.url ? (
                                                    <Link
                                                        key={notification.id}
                                                        href={notification.url}
                                                        className="block px-4 py-3 text-xs transition hover:bg-slate-100 dark:hover:bg-slate-800/80"
                                                    >
                                                        <div className="font-bold text-slate-900 dark:text-white">
                                                            {notification.title}
                                                        </div>
                                                        {notification.body && (
                                                            <div className="mt-1 line-clamp-2 text-slate-600 dark:text-slate-300">
                                                                {
                                                                    notification.body
                                                                }
                                                            </div>
                                                        )}
                                                    </Link>
                                                ) : (
                                                    <div
                                                        key={notification.id}
                                                        className="px-4 py-3 text-xs"
                                                    >
                                                        <div className="font-bold text-slate-900 dark:text-white">
                                                            {notification.title}
                                                        </div>
                                                        {notification.body && (
                                                            <div className="mt-1 line-clamp-2 text-slate-600 dark:text-slate-300">
                                                                {
                                                                    notification.body
                                                                }
                                                            </div>
                                                        )}
                                                    </div>
                                                ),
                                        )
                                    )}
                                </div>
                            </Dropdown.Content>
                        </Dropdown>

                        {/* Theme Switcher Toggle */}
                        <ThemeToggle />

                        {/* User Dropdown */}
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-2.5 rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/90 px-3.5 py-1.5 text-sm font-bold text-slate-800 dark:text-white hover:border-slate-300 dark:hover:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/40 shadow-sm"
                                >
                                    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 font-bold text-xs text-white shadow-sm shadow-indigo-500/30">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <span className="max-w-[120px] truncate text-xs font-bold">
                                        {user.name}
                                    </span>
                                    <svg
                                        className="h-4 w-4 text-slate-400 dark:text-slate-200"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fillRule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </Dropdown.Trigger>

                            <Dropdown.Content align="right" width="48">
                                <div className="px-4 py-3 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/80 dark:bg-slate-950/60 text-xs">
                                    <div className="font-bold text-slate-900 dark:text-white">
                                        {user.name}
                                    </div>
                                    <div className="text-slate-600 dark:text-slate-200 truncate mt-0.5 font-medium">
                                        {user.email}
                                    </div>
                                </div>
                                <div className="py-1">
                                    <Dropdown.Link href={route('profile.edit')}>
                                        <svg
                                            className="h-4 w-4 text-slate-400 group-hover:text-indigo-500"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                            />
                                        </svg>
                                        <span>Profile Settings</span>
                                    </Dropdown.Link>
                                    <Dropdown.Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                    >
                                        <svg
                                            className="h-4 w-4 text-rose-500"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth="2"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                            />
                                        </svg>
                                        <span className="text-rose-600 dark:text-rose-400">
                                            Log Out
                                        </span>
                                    </Dropdown.Link>
                                </div>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-4 sm:p-6 md:p-8 space-y-6">
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}
