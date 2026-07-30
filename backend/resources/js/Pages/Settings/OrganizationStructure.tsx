import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState, useEffect, useRef } from 'react';

type Branch = {
    id: string;
    code: string;
    name: string;
    address?: string | null;
    phone?: string | null;
    is_head_office: boolean;
    status: string;
};

type Division = {
    id: string;
    branch_id: string;
    code: string;
    name: string;
    status: string;
};

type Department = {
    id: string;
    branch_id: string;
    division_id: string;
    code: string;
    name: string;
    status: string;
};

type OrgUser = {
    id: string;
    branch_id?: string | null;
    division_id?: string | null;
    department_id?: string | null;
    name: string;
    email: string;
    position?: string | null;
    status: string;
};

type Tab = 'branches' | 'divisions' | 'departments';

const inputClass =
    'w-full rounded-xl border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150';

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={status === 'active' ? 'success' : 'neutral'} dot>
            <span className="capitalize">{status}</span>
        </Badge>
    );
}

function EmptyNode({ label }: { label: string }) {
    return (
        <div className="rounded-xl border border-dashed border-slate-200 dark:border-slate-800 p-3 text-center text-xs font-medium text-slate-400 dark:text-slate-500 bg-slate-50/50 dark:bg-slate-950/20">
            {label}
        </div>
    );
}

const AVATAR_THEMES = [
    'bg-gradient-to-br from-indigo-500 to-indigo-600 text-white shadow-indigo-100 dark:shadow-none',
    'bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-purple-100 dark:shadow-none',
    'bg-gradient-to-br from-blue-500 to-sky-600 text-white shadow-blue-100 dark:shadow-none',
    'bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-emerald-100 dark:shadow-none',
    'bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-amber-100 dark:shadow-none',
];

function getInitials(name: string) {
    return name
        .trim()
        .split(/\s+/)
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function getAvatarTheme(name: string) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % AVATAR_THEMES.length;
    return AVATAR_THEMES[index];
}

function UserList({ users }: { users: OrgUser[] }) {
    if (users.length === 0) {
        return <EmptyNode label="No assigned staff members" />;
    }

    return (
        <div className="mt-2 space-y-2">
            {users.map((user) => {
                const initials = getInitials(user.name);
                const theme = getAvatarTheme(user.name);
                const isInactive = user.status !== 'active';

                return (
                    <div
                        key={user.id}
                        className={`group relative rounded-xl border border-slate-100 bg-white p-2.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:border-slate-200 dark:border-slate-800/40 dark:bg-slate-900/40 dark:hover:border-slate-700/60 ${
                            isInactive ? 'opacity-60 grayscale-[20%]' : ''
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            {/* Initials Avatar */}
                            <div
                                className={`h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold shadow-sm ${theme}`}
                            >
                                {initials}
                            </div>

                            {/* Name & Position */}
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                    {user.name}
                                </div>
                                <div className="truncate text-[10px] text-slate-500 dark:text-slate-400">
                                    {user.position || 'Staff Member'}
                                </div>
                            </div>

                            {/* Active Dot indicator */}
                            <span
                                className={`h-2 w-2 rounded-full ring-2 ring-white dark:ring-slate-900 ${
                                    user.status === 'active'
                                        ? 'bg-emerald-500 animate-pulse'
                                        : 'bg-slate-300 dark:bg-slate-600'
                                }`}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function OrganizationStructure({
    branches,
    divisions,
    departments,
    users,
}: {
    branches: Branch[];
    divisions: Division[];
    departments: Department[];
    users: OrgUser[];
}) {
    const [tab, setTab] = useState<Tab>('branches');
    const [searchQuery, setSearchQuery] = useState('');
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

    const toggleNode = (id: string) => {
        setCollapsed((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const expandAll = () => setCollapsed({});
    const collapseAll = () => {
        const newCollapsed: Record<string, boolean> = {};
        branches.forEach((b) => {
            newCollapsed[b.id] = true;
        });
        divisions.forEach((d) => {
            newCollapsed[d.id] = true;
        });
        departments.forEach((dept) => {
            newCollapsed[dept.id] = true;
        });
        setCollapsed(newCollapsed);
    };

    const isSearchActive = searchQuery.trim().length > 0;

    // Advanced search hierarchical filter logic
    const filteredBranches = useMemo(() => {
        if (!searchQuery.trim()) return branches;

        const query = searchQuery.toLowerCase();
        return branches.filter((branch) => {
            const branchMatches =
                branch.name.toLowerCase().includes(query) ||
                branch.code.toLowerCase().includes(query);

            const branchDivisions = divisions.filter(
                (d) => d.branch_id === branch.id,
            );
            const divisionMatches = branchDivisions.some((division) => {
                const divMatches =
                    division.name.toLowerCase().includes(query) ||
                    division.code.toLowerCase().includes(query);

                const divisionDepartments = departments.filter(
                    (dept) => dept.division_id === division.id,
                );
                const departmentMatches = divisionDepartments.some((dept) => {
                    const deptMatches =
                        dept.name.toLowerCase().includes(query) ||
                        dept.code.toLowerCase().includes(query);

                    const departmentUsers = users.filter(
                        (u) => u.department_id === dept.id,
                    );
                    const userMatches = departmentUsers.some(
                        (u) =>
                            u.name.toLowerCase().includes(query) ||
                            u.email.toLowerCase().includes(query) ||
                            u.position?.toLowerCase().includes(query),
                    );

                    return deptMatches || userMatches;
                });

                const divisionUsers = users.filter(
                    (u) =>
                        u.division_id === division.id && !u.department_id,
                );
                const divUserMatches = divisionUsers.some(
                    (u) =>
                        u.name.toLowerCase().includes(query) ||
                        u.email.toLowerCase().includes(query) ||
                        u.position?.toLowerCase().includes(query),
                );

                return divMatches || departmentMatches || divUserMatches;
            });

            const branchUsers = users.filter(
                (u) =>
                    u.branch_id === branch.id &&
                    !u.division_id &&
                    !u.department_id,
            );
            const branchUserMatches = branchUsers.some(
                (u) =>
                    u.name.toLowerCase().includes(query) ||
                    u.email.toLowerCase().includes(query) ||
                    u.position?.toLowerCase().includes(query),
            );

            return branchMatches || divisionMatches || branchUserMatches;
        });
    }, [searchQuery, branches, divisions, departments, users]);

    const [viewMode, setViewMode] = useState<'canvas' | 'columns'>('canvas');
    const [pan, setPan] = useState({ x: 50, y: 50 });
    const [scale, setScale] = useState(1);
    const [isDragging, setIsDragging] = useState(false);
    const [dragStart, setDragStart] = useState({ x: 0, y: 0 });

    const viewportRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const viewport = viewportRef.current;
        if (!viewport) return;

        const handleWheelEvent = (e: WheelEvent) => {
            e.preventDefault();
            const zoomFactor = 0.05;
            setScale((s) => {
                const newScale = e.deltaY < 0 
                    ? Math.min(s + zoomFactor, 2.0) 
                    : Math.max(s - zoomFactor, 0.3);
                return newScale;
            });
        };

        viewport.addEventListener('wheel', handleWheelEvent, { passive: false });
        return () => {
            viewport.removeEventListener('wheel', handleWheelEvent);
        };
    }, []);

    const handleMouseDown = (e: React.MouseEvent) => {
        if (e.button !== 0) return;
        const target = e.target as HTMLElement;
        if (
            target.closest('button') ||
            target.closest('input') ||
            target.closest('select') ||
            target.closest('textarea') ||
            target.closest('a')
        ) {
            return;
        }
        e.preventDefault();
        setIsDragging(true);
        setDragStart({ x: e.clientX - pan.x, y: e.clientY - pan.y });
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (!isDragging) return;
        setPan({
            x: e.clientX - dragStart.x,
            y: e.clientY - dragStart.y,
        });
    };

    const handleMouseUp = () => {
        setIsDragging(false);
    };

    const zoomIn = () => setScale((s) => Math.min(s + 0.1, 2));
    const zoomOut = () => setScale((s) => Math.max(s - 0.1, 0.3));
    const resetZoom = () => {
        setScale(1);
        setPan({ x: 50, y: 50 });
    };

    const activeBranches = branches.filter((item) => item.status === 'active');
    const activeDivisions = divisions.filter(
        (item) => item.status === 'active',
    );

    // Edit modal states
    const [editingBranch, setEditingBranch] = useState<Branch | null>(null);
    const [editingDivision, setEditingDivision] = useState<Division | null>(
        null,
    );
    const [editingDepartment, setEditingDepartment] =
        useState<Department | null>(null);

    // Creation forms
    const branchForm = useForm({
        name: '',
        address: '',
        phone: '',
        is_head_office: false,
    });

    const divisionForm = useForm({
        branch_id: activeBranches[0]?.id ?? '',
        name: '',
    });

    const departmentForm = useForm({
        branch_id: activeBranches[0]?.id ?? '',
        division_id:
            activeDivisions.filter(
                (d) => d.branch_id === (activeBranches[0]?.id ?? ''),
            )[0]?.id ?? '',
        name: '',
    });

    // Edit forms
    const editBranchForm = useForm({
        name: '',
        address: '',
        phone: '',
        is_head_office: false,
    });

    const editDivisionForm = useForm({
        branch_id: '',
        name: '',
    });

    const editDepartmentForm = useForm({
        branch_id: '',
        division_id: '',
        name: '',
    });

    const branchById = useMemo(
        () => new Map(branches.map((item) => [item.id, item])),
        [branches],
    );
    const divisionById = useMemo(
        () => new Map(divisions.map((item) => [item.id, item])),
        [divisions],
    );

    // Form Submissions - Create
    const createBranch: FormEventHandler = (event) => {
        event.preventDefault();
        branchForm.post(route('settings.branches.store'), {
            onSuccess: () => branchForm.reset(),
        });
    };

    const createDivision: FormEventHandler = (event) => {
        event.preventDefault();
        divisionForm.post(route('settings.divisions.store'), {
            onSuccess: () => divisionForm.reset('name'),
        });
    };

    const createDepartment: FormEventHandler = (event) => {
        event.preventDefault();
        departmentForm.post(route('settings.departments.store'), {
            onSuccess: () => departmentForm.reset('name'),
        });
    };

    // Open Edit Modals
    const openEditBranch = (branch: Branch) => {
        setEditingBranch(branch);
        editBranchForm.clearErrors();
        editBranchForm.setData({
            name: branch.name,
            address: branch.address ?? '',
            phone: branch.phone ?? '',
            is_head_office: branch.is_head_office,
        });
    };

    const openEditDivision = (division: Division) => {
        setEditingDivision(division);
        editDivisionForm.clearErrors();
        editDivisionForm.setData({
            branch_id: division.branch_id,
            name: division.name,
        });
    };

    const openEditDepartment = (department: Department) => {
        setEditingDepartment(department);
        editDepartmentForm.clearErrors();
        editDepartmentForm.setData({
            branch_id: department.branch_id,
            division_id: department.division_id,
            name: department.name,
        });
    };

    // Submit Edit Modals
    const updateBranchSubmit: FormEventHandler = (event) => {
        event.preventDefault();
        if (!editingBranch) return;
        editBranchForm.patch(
            route('settings.branches.update', editingBranch.id),
            {
                preserveScroll: true,
                onSuccess: () => setEditingBranch(null),
            },
        );
    };

    const updateDivisionSubmit: FormEventHandler = (event) => {
        event.preventDefault();
        if (!editingDivision) return;
        editDivisionForm.patch(
            route('settings.divisions.update', editingDivision.id),
            {
                preserveScroll: true,
                onSuccess: () => setEditingDivision(null),
            },
        );
    };

    const updateDepartmentSubmit: FormEventHandler = (event) => {
        event.preventDefault();
        if (!editingDepartment) return;
        editDepartmentForm.patch(
            route('settings.departments.update', editingDepartment.id),
            {
                preserveScroll: true,
                onSuccess: () => setEditingDepartment(null),
            },
        );
    };

    const patch = (
        name: string,
        id: string,
        data: Record<string, string | boolean | null>,
    ) => {
        router.patch(route(name, id), data, { preserveScroll: true });
    };

    const destroy = (name: string, id: string) => {
        if (window.confirm('Confirm delete this record?')) {
            router.delete(route(name, id), { preserveScroll: true });
        }
    };

    // Cascading dropdown handlers
    const handleCreateDeptBranchChange = (branchId: string) => {
        const branchDivisions = activeDivisions.filter(
            (d) => d.branch_id === branchId,
        );
        departmentForm.setData((prev) => ({
            ...prev,
            branch_id: branchId,
            division_id: branchDivisions[0]?.id ?? '',
        }));
    };

    const handleEditDeptBranchChange = (branchId: string) => {
        const branchDivisions = activeDivisions.filter(
            (d) => d.branch_id === branchId,
        );
        editDepartmentForm.setData((prev) => ({
            ...prev,
            branch_id: branchId,
            division_id: branchDivisions[0]?.id ?? '',
        }));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Organization Structure" />

            <style dangerouslySetInnerHTML={{__html: `
                @media print {
                    /* Enforce landscape layout and narrow margins to support wider diagrams */
                    @page {
                        size: landscape;
                        margin: 10mm;
                    }
                    body {
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    /* Hide sidebars, main app layout wrappers, headers, card frames, forms, tabs, etc. */
                    body * {
                        visibility: hidden;
                    }
                    /* Expose ONLY the specific print container and its contents */
                    .print-area, .print-area * {
                        visibility: visible !important;
                    }
                    /* Re-position print area to cover full landscape page cleanly and scale to 65% */
                    .print-area {
                        position: absolute !important;
                        left: 0 !important;
                        top: 0 !important;
                        width: 100% !important;
                        height: auto !important;
                        background: white !important;
                        border: none !important;
                        box-shadow: none !important;
                        overflow: visible !important;
                        padding: 0 !important;
                        margin: 0 !important;
                        zoom: 65% !important; /* Automatically scale tree diagram down to fit standard width page */
                    }
                    /* Clear screen grid patterns on paper print */
                    .print-area::before, .print-area::after, .print-area > div:first-child {
                        background-image: none !important;
                        background-color: transparent !important;
                    }
                    /* Remove zoom / drag translation transforms to lay out vertically centered */
                    .print-canvas-wrapper {
                        transform: none !important;
                        position: relative !important;
                        left: 0 !important;
                        top: 0 !important;
                        width: 100% !important;
                        height: auto !important;
                        display: flex !important;
                        flex-direction: column !important;
                        align-items: center !important;
                        justify-content: center !important;
                        margin: 0 auto !important;
                    }
                    .print-canvas-wrapper * {
                        box-shadow: none !important;
                    }
                    /* Hide components with no-print class completely */
                    .no-print, .no-print * {
                        display: none !important;
                        visibility: hidden !important;
                    }
                }
            `}} />

            <div className="space-y-6">
                <PageHeader
                    title="Organization Structure"
                    description="Branch, division, and department master data management"
                />

                <Card
                    title="Organization Chart & Hierarchy"
                    description="Interactive visual structure representing divisions, departments, and active personnel"
                >
                    {/* Search, View Mode Toggle & Canvas Control Row */}
                    <div className="mb-6 flex flex-col xl:flex-row gap-4 items-center justify-between border-b border-slate-100 dark:border-slate-800/60 pb-5 no-print">
                        {/* Search input */}
                        <div className="relative w-full xl:w-96">
                            <span className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg className="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                type="text"
                                className="w-full pl-10 pr-10 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-900 dark:border-slate-800 dark:bg-slate-900 dark:text-white text-sm font-medium focus:border-indigo-500 focus:ring-indigo-500 shadow-inner transition-all duration-150"
                                placeholder="Search employees, divisions, or codes..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />
                            {searchQuery && (
                                <button
                                    onClick={() => setSearchQuery('')}
                                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                                >
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            )}
                        </div>

                        {/* View Mode & Zoom Toolbar */}
                        <div className="flex flex-wrap items-center gap-4 w-full xl:w-auto justify-between xl:justify-end">
                            {/* View Mode Toggle Buttons */}
                            <div className="flex bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl">
                                <button
                                    onClick={() => setViewMode('canvas')}
                                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                                        viewMode === 'canvas'
                                            ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
                                    }`}
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2l5.447 2.724A1 1 0 0115 5.618v10.764a1 1 0 01-.553.894L9 20z" />
                                    </svg>
                                    Interactive Canvas
                                </button>
                                <button
                                    onClick={() => setViewMode('columns')}
                                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                                        viewMode === 'columns'
                                            ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
                                    }`}
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                    Classic Columns
                                </button>
                            </div>

                            {/* General Toggle Controls */}
                            <div className="flex gap-2">
                                <button
                                    onClick={expandAll}
                                    className="px-3 py-2 text-xs font-bold text-indigo-600 hover:text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-400 rounded-xl transition-all"
                                >
                                    Expand All
                                </button>
                                <button
                                    onClick={collapseAll}
                                    className="px-3 py-2 text-xs font-bold text-slate-600 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 rounded-xl transition-all"
                                >
                                    Collapse All
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Hierarchy view wrapper */}
                    {filteredBranches.length === 0 ? (
                        <div className="py-12">
                            <EmptyNode label="No branches or employees found matching search query" />
                        </div>
                    ) : viewMode === 'columns' ? (
                        /* CLASSIC COLUMNS MODE */
                        <div className="overflow-x-auto pb-4 -mx-6 px-6">
                            <div className="flex gap-6 pb-2 min-w-max">
                                {filteredBranches.map((branch) => {
                                    const isBranchCollapsed = collapsed[branch.id] && !isSearchActive;

                                    const branchDivisions = divisions.filter((division) => {
                                        if (division.branch_id !== branch.id) return false;
                                        if (!isSearchActive) return true;

                                        const q = searchQuery.toLowerCase();
                                        const divMatches = division.name.toLowerCase().includes(q) || division.code.toLowerCase().includes(q);

                                        const divDepts = departments.filter((dept) => dept.division_id === division.id);
                                        const deptMatches = divDepts.some((dept) => {
                                            const dMatches = dept.name.toLowerCase().includes(q) || dept.code.toLowerCase().includes(q);
                                            const deptUsers = users.filter((u) => u.department_id === dept.id);
                                            const uMatches = deptUsers.some((u) =>
                                                u.name.toLowerCase().includes(q) ||
                                                u.email.toLowerCase().includes(q) ||
                                                u.position?.toLowerCase().includes(q)
                                            );
                                            return dMatches || uMatches;
                                        });

                                        const divUsers = users.filter((u) => u.division_id === division.id && !u.department_id);
                                        const uMatches = divUsers.some((u) =>
                                            u.name.toLowerCase().includes(q) ||
                                            u.email.toLowerCase().includes(q) ||
                                            u.position?.toLowerCase().includes(q)
                                        );

                                        return divMatches || deptMatches || uMatches;
                                    });

                                    const branchDirectUsers = users.filter(
                                        (user) => user.branch_id === branch.id && !user.division_id && !user.department_id
                                    );

                                    const totalBranchStaffCount = users.filter((u) => u.branch_id === branch.id).length;

                                    return (
                                        <div
                                            key={branch.id}
                                            className={`w-88 shrink-0 rounded-2xl border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800/40 dark:bg-slate-950/20 shadow-sm transition-all duration-200 ${
                                                branch.status !== 'active' ? 'opacity-65' : ''
                                            }`}
                                        >
                                            {/* Branch Node Card */}
                                            <div className="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
                                                <div className="h-1 bg-indigo-600" />
                                                <div className="p-3.5">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex items-start gap-2.5 min-w-0">
                                                            <div className="mt-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 p-1.5 shrink-0">
                                                                <svg className="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                                </svg>
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-1.5">
                                                                    <span className="font-mono text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded">
                                                                        {branch.code}
                                                                    </span>
                                                                    {branch.is_head_office && (
                                                                        <span className="inline-flex items-center text-[9px] font-bold text-purple-700 bg-purple-50 dark:bg-purple-950/60 dark:text-purple-400 px-1.5 py-0.5 rounded">
                                                                            HQ
                                                                        </span>
                                                                    )}
                                                                    {totalBranchStaffCount > 0 && (
                                                                        <span className="text-[9px] font-semibold text-slate-400">
                                                                            ({totalBranchStaffCount} Staff)
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <h4 className="mt-1 text-sm font-extrabold text-slate-800 dark:text-white truncate">
                                                                    {branch.name}
                                                                </h4>
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center gap-1.5 shrink-0">
                                                            <StatusBadge status={branch.status} />
                                                            <button
                                                                onClick={() => toggleNode(branch.id)}
                                                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                                            >
                                                                <svg
                                                                    className={`h-4.5 w-4.5 transform transition-transform duration-200 ${
                                                                        isBranchCollapsed ? '' : 'rotate-90'
                                                                    }`}
                                                                    fill="none"
                                                                    viewBox="0 0 24 24"
                                                                    stroke="currentColor"
                                                                >
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    {branch.phone && (
                                                        <div className="mt-2.5 flex items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                                            <svg className="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                            </svg>
                                                            {branch.phone}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {!isBranchCollapsed && (
                                                <div className="mt-3 ml-5 pl-4 border-l-2 border-dashed border-slate-200 dark:border-slate-800/80 space-y-4">
                                                    {branchDirectUsers.length > 0 && (
                                                        <div className="relative">
                                                            <div className="absolute -left-[21px] top-4 w-4 border-t-2 border-dashed border-slate-200 dark:border-slate-800/80" />
                                                            <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block mb-1">
                                                                Direct Branch Staff
                                                            </span>
                                                            <UserList users={branchDirectUsers} />
                                                        </div>
                                                    )}

                                                    {branchDivisions.length === 0 && branchDirectUsers.length === 0 && (
                                                        <div className="relative">
                                                            <div className="absolute -left-[21px] top-4.5 w-4 border-t-2 border-dashed border-slate-200 dark:border-slate-800/80" />
                                                            <EmptyNode label="No active departments" />
                                                        </div>
                                                    )}

                                                    {branchDivisions.map((division) => {
                                                        const isDivisionCollapsed = collapsed[division.id] && !isSearchActive;

                                                        const divisionDepartments = departments.filter((department) => {
                                                            if (department.division_id !== division.id) return false;
                                                            if (!isSearchActive) return true;

                                                            const q = searchQuery.toLowerCase();
                                                            const deptMatches = department.name.toLowerCase().includes(q) || department.code.toLowerCase().includes(q);

                                                            const deptUsers = users.filter((u) => u.department_id === department.id);
                                                            const uMatches = deptUsers.some((u) =>
                                                                u.name.toLowerCase().includes(q) ||
                                                                u.email.toLowerCase().includes(q) ||
                                                                u.position?.toLowerCase().includes(q)
                                                            );

                                                            return deptMatches || uMatches;
                                                        });

                                                        const divisionDirectUsers = users.filter(
                                                            (user) => user.division_id === division.id && !user.department_id
                                                        );

                                                        const totalDivStaffCount = users.filter((u) => u.division_id === division.id).length;

                                                        return (
                                                            <div key={division.id} className="relative">
                                                                <div className="absolute -left-[21px] top-5 w-4 border-t-2 border-dashed border-slate-200 dark:border-slate-800/80" />

                                                                <div
                                                                    className={`rounded-xl border-l-4 border-violet-500 border-t border-r border-b border-slate-100 bg-white dark:border-slate-800/50 dark:bg-slate-900/60 shadow-sm overflow-hidden p-3 transition-all duration-200 ${
                                                                        division.status !== 'active' ? 'opacity-65' : ''
                                                                    }`}
                                                                >
                                                                    <div className="flex items-center justify-between gap-2.5">
                                                                        <div className="flex items-center gap-2 min-w-0">
                                                                            <div className="rounded-lg bg-violet-50 dark:bg-violet-950/60 p-1.5 shrink-0">
                                                                                <svg className="h-4 w-4 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div className="min-w-0">
                                                                                <div className="flex items-center gap-1.5">
                                                                                    <span className="font-mono text-[9px] font-bold text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/60 px-1.5 py-0.5 rounded">
                                                                                        {division.code}
                                                                                    </span>
                                                                                    {totalDivStaffCount > 0 && (
                                                                                        <span className="text-[9px] font-semibold text-slate-400">
                                                                                            {totalDivStaffCount} Staff
                                                                                        </span>
                                                                                    )}
                                                                                </div>
                                                                                <h5 className="mt-0.5 text-xs font-extrabold text-slate-800 dark:text-slate-100 truncate">
                                                                                    {division.name}
                                                                                </h5>
                                                                            </div>
                                                                        </div>

                                                                        <div className="flex items-center gap-1 shrink-0">
                                                                            <button
                                                                                onClick={() => toggleNode(division.id)}
                                                                                className="rounded p-0.5 text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                                                            >
                                                                                <svg
                                                                                    className={`h-3.5 w-3.5 transform transition-transform duration-200 ${
                                                                                        isDivisionCollapsed ? '' : 'rotate-90'
                                                                                    }`}
                                                                                    fill="none"
                                                                                    viewBox="0 0 24 24"
                                                                                    stroke="currentColor"
                                                                                >
                                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                {!isDivisionCollapsed && (
                                                                    <div className="mt-3 ml-4.5 pl-3.5 border-l border-dashed border-slate-200 dark:border-slate-800/80 space-y-3.5">
                                                                        {divisionDirectUsers.length > 0 && (
                                                                            <div className="relative">
                                                                                <div className="absolute -left-[18px] top-4 w-3.5 border-t border-dashed border-slate-200 dark:border-slate-800/80" />
                                                                                <span className="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block mb-1">
                                                                                    Direct Division Staff
                                                                                </span>
                                                                                <UserList users={divisionDirectUsers} />
                                                                            </div>
                                                                        )}

                                                                        {divisionDepartments.length === 0 && divisionDirectUsers.length === 0 && (
                                                                            <div className="relative">
                                                                                <div className="absolute -left-[18px] top-4 w-3.5 border-t border-dashed border-slate-200 dark:border-slate-800/80" />
                                                                                <EmptyNode label="No active departments" />
                                                                            </div>
                                                                        )}

                                                                        {divisionDepartments.map((department) => {
                                                                            const isDeptCollapsed = collapsed[department.id] && !isSearchActive;

                                                                            const departmentUsers = users.filter((u) => {
                                                                                if (u.department_id !== department.id) return false;
                                                                                if (!isSearchActive) return true;

                                                                                const q = searchQuery.toLowerCase();
                                                                                return (
                                                                                    u.name.toLowerCase().includes(q) ||
                                                                                    u.email.toLowerCase().includes(q) ||
                                                                                    u.position?.toLowerCase().includes(q)
                                                                                );
                                                                            });

                                                                            return (
                                                                                <div key={department.id} className="relative">
                                                                                    <div className="absolute -left-[18px] top-5 w-3.5 border-t border-dashed border-slate-200 dark:border-slate-800/80" />

                                                                                    <div
                                                                                        className={`rounded-xl border-l-4 border-amber-500 border-t border-r border-b border-slate-100 bg-slate-50/40 p-2.5 dark:border-slate-800/40 dark:bg-slate-900/30 shadow-xs transition-all duration-200 ${
                                                                                            department.status !== 'active' ? 'opacity-65' : ''
                                                                                        }`}
                                                                                    >
                                                                                        <div className="flex items-center justify-between gap-2.5">
                                                                                            <div className="flex items-center gap-2 min-w-0">
                                                                                                <div className="rounded bg-amber-50 dark:bg-amber-950/40 p-1 shrink-0">
                                                                                                    <svg className="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                                                                    </svg>
                                                                                                </div>
                                                                                                <div className="min-w-0">
                                                                                                    <div className="flex items-center gap-1.5">
                                                                                                        <span className="font-mono text-[8px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 px-1 rounded">
                                                                                                            {department.code}
                                                                                                        </span>
                                                                                                        {departmentUsers.length > 0 && (
                                                                                                            <span className="text-[8.5px] font-semibold text-slate-400">
                                                                                                                {departmentUsers.length} Staff
                                                                                                            </span>
                                                                                                        )}
                                                                                                    </div>
                                                                                                    <h6 className="mt-0.5 text-xs font-bold text-slate-700 dark:text-slate-200 truncate">
                                                                                                        {department.name}
                                                                                                    </h6>
                                                                                                </div>
                                                                                            </div>

                                                                                            <div className="flex items-center gap-1 shrink-0">
                                                                                                <button
                                                                                                    onClick={() => toggleNode(department.id)}
                                                                                                    className="rounded p-0.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                                                                                >
                                                                                                    <svg
                                                                                                        className={`h-3 w-3 transform transition-transform duration-200 ${
                                                                                                            isDeptCollapsed ? '' : 'rotate-90'
                                                                                                        }`}
                                                                                                        fill="none"
                                                                                                        viewBox="0 0 24 24"
                                                                                                        stroke="currentColor"
                                                                                                    >
                                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                                                                                                    </svg>
                                                                                                </button>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                    {!isDeptCollapsed && (
                                                                                        <div className="mt-2 ml-4.5 pl-3 border-l border-dashed border-slate-200 dark:border-slate-800/80">
                                                                                            <UserList users={departmentUsers} />
                                                                                        </div>
                                                                                    )}
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ) : (
                        /* INTERACTIVE CANVAS TREE DIAGRAM MODE */
                        <div
                            ref={viewportRef}
                            className="relative w-full h-[600px] border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden bg-slate-50 dark:bg-slate-950 shadow-inner select-none cursor-grab active:cursor-grabbing print-area"
                            onMouseDown={handleMouseDown}
                            onMouseMove={handleMouseMove}
                            onMouseUp={handleMouseUp}
                            onMouseLeave={handleMouseUp}
                        >
                            {/* Dot Grid Background */}
                            <div
                                className="absolute inset-0 transition-none pointer-events-none"
                                style={{
                                    backgroundImage: searchQuery.trim() 
                                        ? undefined 
                                        : 'radial-gradient(#cbd5e1 1.2px, transparent 1.2px), radial-gradient(#e2e8f0 1.2px, transparent 1.2px)',
                                    backgroundColor: 'transparent',
                                    backgroundSize: '24px 24px',
                                    backgroundPosition: `${pan.x}px ${pan.y}px`,
                                }}
                            />
                            {/* Dark mode dot grid adjustment */}
                            <div
                                className="absolute inset-0 dark:block hidden transition-none pointer-events-none"
                                style={{
                                    backgroundImage: searchQuery.trim() 
                                        ? undefined 
                                        : 'radial-gradient(#334155 1.2px, transparent 1.2px)',
                                    backgroundColor: 'transparent',
                                    backgroundSize: '24px 24px',
                                    backgroundPosition: `${pan.x}px ${pan.y}px`,
                                }}
                            />

                            {/* Canvas Content Wrapper (Supports drag translation and scale zoom) */}
                            <div
                                className="absolute origin-top-left transition-transform duration-100 ease-out print-canvas-wrapper"
                                style={{
                                    transform: `translate(${pan.x}px, ${pan.y}px) scale(${scale})`,
                                }}
                            >
                                <div className="p-12 flex flex-col items-center gap-16">
                                    {filteredBranches.map((branch) => {
                                        const isBranchCollapsed = collapsed[branch.id] && !isSearchActive;

                                        const branchDivisions = divisions.filter((division) => {
                                            if (division.branch_id !== branch.id) return false;
                                            if (!isSearchActive) return true;

                                            const q = searchQuery.toLowerCase();
                                            const divMatches = division.name.toLowerCase().includes(q) || division.code.toLowerCase().includes(q);

                                            const divDepts = departments.filter((dept) => dept.division_id === division.id);
                                            const deptMatches = divDepts.some((dept) => {
                                                const dMatches = dept.name.toLowerCase().includes(q) || dept.code.toLowerCase().includes(q);
                                                const deptUsers = users.filter((u) => u.department_id === dept.id);
                                                const uMatches = deptUsers.some((u) =>
                                                    u.name.toLowerCase().includes(q) ||
                                                    u.email.toLowerCase().includes(q) ||
                                                    u.position?.toLowerCase().includes(q)
                                                );
                                                return dMatches || uMatches;
                                            });

                                            const divUsers = users.filter((u) => u.division_id === division.id && !u.department_id);
                                            const uMatches = divUsers.some((u) =>
                                                u.name.toLowerCase().includes(q) ||
                                                u.email.toLowerCase().includes(q) ||
                                                u.position?.toLowerCase().includes(q)
                                            );

                                            return divMatches || deptMatches || uMatches;
                                        });

                                        const branchDirectUsers = users.filter(
                                            (user) => user.branch_id === branch.id && !user.division_id && !user.department_id
                                        );

                                        const totalBranchStaffCount = users.filter((u) => u.branch_id === branch.id).length;

                                        return (
                                            <div key={branch.id} className="flex flex-col items-center interactive-node">
                                                {/* Branch card */}
                                                <div className="w-80 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-lg p-4 relative">
                                                    <div className="absolute top-0 inset-x-0 h-1 bg-indigo-600 rounded-t-2xl" />
                                                    <div className="flex justify-between items-start">
                                                        <div className="flex gap-2 min-w-0">
                                                            <div className="rounded-lg bg-indigo-50 dark:bg-indigo-950/60 p-1.5 shrink-0 mt-0.5">
                                                                <svg className="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                                </svg>
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-1.5">
                                                                    <span className="font-mono text-[9px] font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded">
                                                                        {branch.code}
                                                                    </span>
                                                                    {branch.is_head_office && (
                                                                        <span className="text-[8px] font-bold text-purple-700 bg-purple-50 dark:bg-purple-950/60 dark:text-purple-400 px-1.5 rounded">
                                                                            HQ
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <h4 className="mt-1 text-sm font-extrabold text-slate-800 dark:text-white truncate">
                                                                    {branch.name}
                                                                </h4>
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center gap-1.5 shrink-0">
                                                            <button
                                                                onClick={() => toggleNode(branch.id)}
                                                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                                            >
                                                                <svg className={`h-4.5 w-4.5 transform transition-transform duration-200 ${isBranchCollapsed ? '' : 'rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    {totalBranchStaffCount > 0 && (
                                                        <div className="mt-3 text-[10px] font-semibold text-slate-400 text-center border-t border-slate-50 dark:border-slate-800/60 pt-2.5">
                                                            Branch Total: {totalBranchStaffCount} staff members
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Branch Children Connector */}
                                                {!isBranchCollapsed && (branchDivisions.length > 0 || branchDirectUsers.length > 0) && (
                                                    <>
                                                        <div className="h-8 w-0.5 bg-slate-300 dark:bg-slate-700" />
                                                        
                                                        {/* Divisions wrapper with tree lines */}
                                                        <div className="flex gap-16 items-start justify-center relative">
                                                            {/* Horizontal connecting line bridge */}
                                                            {branchDivisions.length > 1 && (
                                                                <div className="absolute top-0 left-[calc(50%/var(--cols))] right-[calc(50%/var(--cols))] h-0.5 bg-slate-300 dark:bg-slate-700"
                                                                    style={{
                                                                        left: 'calc(100% / ' + (branchDivisions.length * 2) + ')',
                                                                        right: 'calc(100% / ' + (branchDivisions.length * 2) + ')',
                                                                    }}
                                                                />
                                                            )}

                                                            {branchDivisions.map((division) => {
                                                                const isDivisionCollapsed = collapsed[division.id] && !isSearchActive;

                                                                const divisionDepartments = departments.filter((department) => {
                                                                    if (department.division_id !== division.id) return false;
                                                                    if (!isSearchActive) return true;

                                                                    const q = searchQuery.toLowerCase();
                                                                    const deptMatches = department.name.toLowerCase().includes(q) || department.code.toLowerCase().includes(q);

                                                                    const deptUsers = users.filter((u) => u.department_id === department.id);
                                                                    const uMatches = deptUsers.some((u) =>
                                                                        u.name.toLowerCase().includes(q) ||
                                                                        u.email.toLowerCase().includes(q) ||
                                                                        u.position?.toLowerCase().includes(q)
                                                                    );

                                                                    return deptMatches || uMatches;
                                                                });

                                                                const divisionDirectUsers = users.filter(
                                                                    (user) => user.division_id === division.id && !user.department_id
                                                                );

                                                                const totalDivStaffCount = users.filter((u) => u.division_id === division.id).length;

                                                                return (
                                                                    <div key={division.id} className="flex flex-col items-center relative">
                                                                        {/* Vertical line up from division node to bridge */}
                                                                        <div className="absolute top-0 h-8 w-0.5 bg-slate-300 dark:bg-slate-700 -translate-y-full" />

                                                                        {/* Division Card Node */}
                                                                        <div className="w-72 rounded-2xl border-l-4 border-violet-500 border-t border-r border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-md p-3 relative z-10">
                                                                            <div className="flex items-center justify-between gap-2.5">
                                                                                <div className="flex items-center gap-2 min-w-0">
                                                                                    <div className="rounded-lg bg-violet-50 dark:bg-violet-950/60 p-1.5 shrink-0">
                                                                                        <svg className="h-4 w-4 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                                                        </svg>
                                                                                    </div>
                                                                                    <div className="min-w-0">
                                                                                        <div className="flex items-center gap-1.5">
                                                                                            <span className="font-mono text-[9px] font-bold text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/40 px-1.5 py-0.5 rounded">
                                                                                                {division.code}
                                                                                            </span>
                                                                                            {totalDivStaffCount > 0 && (
                                                                                                <span className="text-[9px] font-semibold text-slate-400">
                                                                                                    {totalDivStaffCount} Staff
                                                                                                </span>
                                                                                            )}
                                                                                        </div>
                                                                                        <h5 className="mt-0.5 text-xs font-extrabold text-slate-800 dark:text-slate-100 truncate">
                                                                                            {division.name}
                                                                                        </h5>
                                                                                    </div>
                                                                                </div>
                                                                                <button
                                                                                    onClick={() => toggleNode(division.id)}
                                                                                    className="rounded p-0.5 text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shrink-0"
                                                                                >
                                                                                    <svg className={`h-3.5 w-3.5 transform transition-transform duration-200 ${isDivisionCollapsed ? '' : 'rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                                                                                    </svg>
                                                                                </button>
                                                                            </div>
                                                                        </div>

                                                                        {/* Division Children Connector */}
                                                                        {!isDivisionCollapsed && (divisionDepartments.length > 0 || divisionDirectUsers.length > 0) && (
                                                                            <>
                                                                                <div className="h-8 w-0.5 bg-slate-300 dark:bg-slate-700" />
                                                                                
                                                                                <div className="flex gap-10 items-start justify-center relative">
                                                                                    {/* Horizontal connecting line bridge */}
                                                                                    {divisionDepartments.length > 1 && (
                                                                                        <div className="absolute top-0 left-[calc(50%/var(--cols))] right-[calc(50%/var(--cols))] h-0.5 bg-slate-300 dark:bg-slate-700"
                                                                                            style={{
                                                                                                left: 'calc(100% / ' + (divisionDepartments.length * 2) + ')',
                                                                                                right: 'calc(100% / ' + (divisionDepartments.length * 2) + ')',
                                                                                            }}
                                                                                        />
                                                                                    )}

                                                                                    {divisionDepartments.map((department) => {
                                                                                        const isDeptCollapsed = collapsed[department.id] && !isSearchActive;

                                                                                        const departmentUsers = users.filter((u) => {
                                                                                            if (u.department_id !== department.id) return false;
                                                                                            if (!isSearchActive) return true;

                                                                                            const q = searchQuery.toLowerCase();
                                                                                            return (
                                                                                                u.name.toLowerCase().includes(q) ||
                                                                                                u.email.toLowerCase().includes(q) ||
                                                                                                u.position?.toLowerCase().includes(q)
                                                                                            );
                                                                                        });

                                                                                        return (
                                                                                            <div key={department.id} className="flex flex-col items-center relative">
                                                                                                {/* Vertical line up to division bridge */}
                                                                                                <div className="absolute top-0 h-8 w-0.5 bg-slate-300 dark:bg-slate-700 -translate-y-full" />

                                                                                                {/* Department Card Node */}
                                                                                                <div className="w-60 rounded-2xl border-l-4 border-amber-500 border-t border-r border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 p-2.5 shadow-sm relative z-10">
                                                                                                    <div className="flex items-center justify-between gap-2">
                                                                                                        <div className="flex items-center gap-2 min-w-0">
                                                                                                            <div className="rounded bg-amber-50 dark:bg-amber-950/40 p-1 shrink-0">
                                                                                                                <svg className="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                                                                                </svg>
                                                                                                            </div>
                                                                                                            <div className="min-w-0">
                                                                                                                <div className="flex items-center gap-1">
                                                                                                                    <span className="font-mono text-[8px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 px-1 rounded">
                                                                                                                        {department.code}
                                                                                                                    </span>
                                                                                                                    {departmentUsers.length > 0 && (
                                                                                                                        <span className="text-[8.5px] font-semibold text-slate-400">
                                                                                                                            {departmentUsers.length} Staff
                                                                                                                        </span>
                                                                                                                    )}
                                                                                                                </div>
                                                                                                                <h6 className="mt-0.5 text-xs font-bold text-slate-700 dark:text-slate-200 truncate">
                                                                                                                    {department.name}
                                                                                                                </h6>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                        <button
                                                                                                            onClick={() => toggleNode(department.id)}
                                                                                                            className="rounded p-0.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0"
                                                                                                        >
                                                                                                            <svg className={`h-3 w-3 transform transition-transform duration-200 ${isDeptCollapsed ? '' : 'rotate-90'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                                                                                                            </svg>
                                                                                                        </button>
                                                                                                    </div>
                                                                                                </div>

                                                                                                {/* Department Staff List */}
                                                                                                {!isDeptCollapsed && departmentUsers.length > 0 && (
                                                                                                    <>
                                                                                                        <div className="h-6 w-0.5 bg-slate-300 dark:bg-slate-700" />
                                                                                                        <div className="w-56 bg-slate-100/50 dark:bg-slate-950/40 p-2 rounded-2xl border border-slate-100 dark:border-slate-900/60 shadow-inner">
                                                                                                            <UserList users={departmentUsers} />
                                                                                                        </div>
                                                                                                    </>
                                                                                                )}
                                                                                            </div>
                                                                                        );
                                                                                    })}
                                                                                </div>
                                                                            </>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                            <style>{`
                                @media print {
                                    .no-print { display: none !important; }
                                    .print-canvas-wrapper { overflow: visible !important; width: 100% !important; }
                                    .print-area { padding: 20px !important; }
                                }
                            `}</style>

                            {/* Floating Canvas Zoom Controls */}
                            <div className="absolute bottom-4 right-4 z-20 flex items-center gap-2 bg-white/95 dark:bg-slate-900/95 p-2 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl backdrop-blur-sm no-print">
                                <button
                                    onClick={zoomOut}
                                    className="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold transition-all border border-slate-100 dark:border-slate-750"
                                    title="Zoom Out"
                                >
                                    －
                                </button>
                                <span className="text-xs font-mono font-extrabold text-slate-700 dark:text-slate-300 w-12 text-center select-none">
                                    {Math.round(scale * 100)}%
                                </span>
                                <button
                                    onClick={zoomIn}
                                    className="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold transition-all border border-slate-100 dark:border-slate-750"
                                    title="Zoom In"
                                >
                                    ＋
                                </button>
                                <div className="w-px h-6 bg-slate-200 dark:bg-slate-800 mx-1" />
                                <button
                                    onClick={resetZoom}
                                    className="px-3 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition-all border border-slate-100 dark:border-slate-750"
                                    title="Reset view (100% & Center)"
                                >
                                    Reset
                                </button>
                                <button
                                    onClick={() => window.print()}
                                    className="px-3 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-750 dark:bg-indigo-600 dark:hover:bg-indigo-700 rounded-xl transition-all border border-indigo-500 shadow-sm flex items-center gap-1.5"
                                    title="Print organization chart"
                                >
                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Print
                                </button>
                            </div>
                        </div>
                    )}
                </Card>

                <div className="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800">
                    {(['branches', 'divisions', 'departments'] as Tab[]).map(
                        (item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setTab(item)}
                                className={`px-4 py-2 text-sm font-semibold transition-colors duration-150 ${
                                    tab === item
                                        ? 'border-b-2 border-indigo-600 text-indigo-700 dark:text-indigo-400'
                                        : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
                                }`}
                            >
                                {item[0].toUpperCase() + item.slice(1)}
                            </button>
                        ),
                    )}
                </div>

                {/* Branches Tab */}
                {tab === 'branches' && (
                    <div className="space-y-6">
                        <Card
                            title="Create Branch"
                            description="Branch code is auto-generated by system"
                        >
                            <form
                                onSubmit={createBranch}
                                className="grid gap-4 md:grid-cols-4"
                            >
                                <div>
                                    <InputLabel value="Branch Name *" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="e.g. Head Office"
                                        value={branchForm.data.name}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={branchForm.errors.name}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Phone" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="Phone number"
                                        value={branchForm.data.phone}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={branchForm.errors.phone}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Address" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="Location address"
                                        value={branchForm.data.address}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'address',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={branchForm.errors.address}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex items-end pb-2">
                                    <label className="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            checked={
                                                branchForm.data.is_head_office
                                            }
                                            onChange={(e) =>
                                                branchForm.setData(
                                                    'is_head_office',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        Set as Head Office
                                    </label>
                                </div>
                                <div className="md:col-span-4 flex justify-end">
                                    <PrimaryButton
                                        disabled={branchForm.processing}
                                        icon={
                                            <svg
                                                className="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                        }
                                    >
                                        Create Branch
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Branches"
                            description={`${branches.length} total branches registered`}
                        >
                            <DataTable
                                data={branches}
                                keyExtractor={(branch) => branch.id}
                                columns={[
                                    {
                                        header: 'Code',
                                        accessor: (branch) => (
                                            <span className="font-mono text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                                {branch.code}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Branch Name',
                                        accessor: (branch) => (
                                            <span className="font-semibold text-slate-800 dark:text-white">
                                                {branch.name}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Phone',
                                        accessor: (branch) =>
                                            branch.phone || '-',
                                    },
                                    {
                                        header: 'Address',
                                        accessor: (branch) =>
                                            branch.address || '-',
                                    },
                                    {
                                        header: 'Head Office',
                                        accessor: (branch) =>
                                            branch.is_head_office ? (
                                                <Badge variant="purple">
                                                    Head Office
                                                </Badge>
                                            ) : (
                                                '-'
                                            ),
                                    },
                                    {
                                        header: 'Status',
                                        accessor: (branch) => (
                                            <StatusBadge
                                                status={branch.status}
                                            />
                                        ),
                                    },
                                    {
                                        header: 'Actions',
                                        accessor: (branch) => (
                                            <div className="flex flex-wrap gap-2">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        openEditBranch(branch)
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                                {branch.status === 'active' && (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            patch(
                                                                'settings.branches.disable',
                                                                branch.id,
                                                                {},
                                                            )
                                                        }
                                                    >
                                                        Disable
                                                    </SecondaryButton>
                                                )}
                                                <SecondaryButton
                                                    onClick={() =>
                                                        destroy(
                                                            'settings.branches.destroy',
                                                            branch.id,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </SecondaryButton>
                                            </div>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    </div>
                )}

                {/* Divisions Tab */}
                {tab === 'divisions' && (
                    <div className="space-y-6">
                        <Card
                            title="Create Division"
                            description="Division code is auto-generated by system"
                        >
                            <form
                                onSubmit={createDivision}
                                className="grid gap-4 md:grid-cols-3"
                            >
                                <div>
                                    <InputLabel value="Belongs to Branch *" />
                                    <select
                                        className={`mt-1 ${inputClass}`}
                                        value={divisionForm.data.branch_id}
                                        onChange={(e) =>
                                            divisionForm.setData(
                                                'branch_id',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {activeBranches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.code} - {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={divisionForm.errors.branch_id}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Division Name *" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="e.g. Technology & Product"
                                        value={divisionForm.data.name}
                                        onChange={(e) =>
                                            divisionForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={divisionForm.errors.name}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <PrimaryButton
                                        disabled={divisionForm.processing}
                                        icon={
                                            <svg
                                                className="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                        }
                                    >
                                        Create Division
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Divisions"
                            description={`${divisions.length} total divisions registered`}
                        >
                            <DataTable
                                data={divisions}
                                keyExtractor={(division) => division.id}
                                columns={[
                                    {
                                        header: 'Code',
                                        accessor: (division) => (
                                            <span className="font-mono text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                                {division.code}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Division Name',
                                        accessor: (division) => (
                                            <span className="font-semibold text-slate-800 dark:text-white">
                                                {division.name}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Belongs to Branch',
                                        accessor: (division) =>
                                            branchById.get(division.branch_id)
                                                ? `${branchById.get(division.branch_id)?.code} - ${branchById.get(division.branch_id)?.name}`
                                                : '-',
                                    },
                                    {
                                        header: 'Status',
                                        accessor: (division) => (
                                            <StatusBadge
                                                status={division.status}
                                            />
                                        ),
                                    },
                                    {
                                        header: 'Actions',
                                        accessor: (division) => (
                                            <div className="flex flex-wrap gap-2">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        openEditDivision(
                                                            division,
                                                        )
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                                {division.status ===
                                                    'active' && (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            patch(
                                                                'settings.divisions.disable',
                                                                division.id,
                                                                {},
                                                            )
                                                        }
                                                    >
                                                        Disable
                                                    </SecondaryButton>
                                                )}
                                                <SecondaryButton
                                                    onClick={() =>
                                                        destroy(
                                                            'settings.divisions.destroy',
                                                            division.id,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </SecondaryButton>
                                            </div>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    </div>
                )}

                {/* Departments Tab */}
                {tab === 'departments' && (
                    <div className="space-y-6">
                        <Card
                            title="Create Department"
                            description="Department code is auto-generated by system"
                        >
                            <form
                                onSubmit={createDepartment}
                                className="grid gap-4 md:grid-cols-4"
                            >
                                <div>
                                    <InputLabel value="Branch *" />
                                    <select
                                        className={`mt-1 ${inputClass}`}
                                        value={departmentForm.data.branch_id}
                                        onChange={(e) =>
                                            handleCreateDeptBranchChange(
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {activeBranches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.code} - {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            departmentForm.errors.branch_id
                                        }
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Division *" />
                                    <select
                                        className={`mt-1 ${inputClass}`}
                                        value={departmentForm.data.division_id}
                                        onChange={(e) =>
                                            departmentForm.setData(
                                                'division_id',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {activeDivisions
                                            .filter(
                                                (item) =>
                                                    item.branch_id ===
                                                    departmentForm.data
                                                        .branch_id,
                                            )
                                            .map((division) => (
                                                <option
                                                    key={division.id}
                                                    value={division.id}
                                                >
                                                    {division.code} -{' '}
                                                    {division.name}
                                                </option>
                                            ))}
                                    </select>
                                    <InputError
                                        message={
                                            departmentForm.errors.division_id
                                        }
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Department Name *" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="e.g. Core Software Dev"
                                        value={departmentForm.data.name}
                                        onChange={(e) =>
                                            departmentForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={departmentForm.errors.name}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <PrimaryButton
                                        disabled={departmentForm.processing}
                                        icon={
                                            <svg
                                                className="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                        }
                                    >
                                        Create Department
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Departments"
                            description={`${departments.length} total departments registered`}
                        >
                            <DataTable
                                data={departments}
                                keyExtractor={(department) => department.id}
                                columns={[
                                    {
                                        header: 'Code',
                                        accessor: (department) => (
                                            <span className="font-mono text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                                {department.code}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Department Name',
                                        accessor: (department) => (
                                            <span className="font-semibold text-slate-800 dark:text-white">
                                                {department.name}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Division',
                                        accessor: (department) =>
                                            divisionById.get(
                                                department.division_id,
                                            )
                                                ? `${divisionById.get(department.division_id)?.code} - ${divisionById.get(department.division_id)?.name}`
                                                : '-',
                                    },
                                    {
                                        header: 'Branch',
                                        accessor: (department) =>
                                            branchById.get(department.branch_id)
                                                ? `${branchById.get(department.branch_id)?.code} - ${branchById.get(department.branch_id)?.name}`
                                                : '-',
                                    },
                                    {
                                        header: 'Status',
                                        accessor: (department) => (
                                            <StatusBadge
                                                status={department.status}
                                            />
                                        ),
                                    },
                                    {
                                        header: 'Actions',
                                        accessor: (department) => (
                                            <div className="flex flex-wrap gap-2">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        openEditDepartment(
                                                            department,
                                                        )
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                                {department.status ===
                                                    'active' && (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            patch(
                                                                'settings.departments.disable',
                                                                department.id,
                                                                {},
                                                            )
                                                        }
                                                    >
                                                        Disable
                                                    </SecondaryButton>
                                                )}
                                                <SecondaryButton
                                                    onClick={() =>
                                                        destroy(
                                                            'settings.departments.destroy',
                                                            department.id,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </SecondaryButton>
                                            </div>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    </div>
                )}
            </div>

            {/* Edit Branch Modal */}
            <Modal
                show={editingBranch !== null}
                onClose={() => setEditingBranch(null)}
            >
                <form onSubmit={updateBranchSubmit} className="p-6 space-y-4">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white border-b pb-2">
                        Edit Branch - #{editingBranch?.code}
                    </h3>

                    <div>
                        <InputLabel value="Branch Name *" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editBranchForm.data.name}
                            onChange={(e) =>
                                editBranchForm.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={editBranchForm.errors.name}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Phone" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editBranchForm.data.phone}
                            onChange={(e) =>
                                editBranchForm.setData('phone', e.target.value)
                            }
                        />
                        <InputError
                            message={editBranchForm.errors.phone}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Address" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editBranchForm.data.address}
                            onChange={(e) =>
                                editBranchForm.setData(
                                    'address',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={editBranchForm.errors.address}
                            className="mt-1"
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer pt-2">
                        <input
                            type="checkbox"
                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            checked={editBranchForm.data.is_head_office}
                            onChange={(e) =>
                                editBranchForm.setData(
                                    'is_head_office',
                                    e.target.checked,
                                )
                            }
                        />
                        Set as Head Office
                    </label>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditingBranch(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={editBranchForm.processing}>
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit Division Modal */}
            <Modal
                show={editingDivision !== null}
                onClose={() => setEditingDivision(null)}
            >
                <form onSubmit={updateDivisionSubmit} className="p-6 space-y-4">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white border-b pb-2">
                        Edit Division - #{editingDivision?.code}
                    </h3>

                    <div>
                        <InputLabel value="Belongs to Branch *" />
                        <select
                            className={`mt-1 ${inputClass}`}
                            value={editDivisionForm.data.branch_id}
                            onChange={(e) =>
                                editDivisionForm.setData(
                                    'branch_id',
                                    e.target.value,
                                )
                            }
                            required
                        >
                            {activeBranches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.code} - {branch.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={editDivisionForm.errors.branch_id}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Division Name *" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editDivisionForm.data.name}
                            onChange={(e) =>
                                editDivisionForm.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={editDivisionForm.errors.name}
                            className="mt-1"
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditingDivision(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={editDivisionForm.processing}>
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit Department Modal */}
            <Modal
                show={editingDepartment !== null}
                onClose={() => setEditingDepartment(null)}
            >
                <form
                    onSubmit={updateDepartmentSubmit}
                    className="p-6 space-y-4"
                >
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white border-b pb-2">
                        Edit Department - #{editingDepartment?.code}
                    </h3>

                    <div>
                        <InputLabel value="Branch *" />
                        <select
                            className={`mt-1 ${inputClass}`}
                            value={editDepartmentForm.data.branch_id}
                            onChange={(e) =>
                                handleEditDeptBranchChange(e.target.value)
                            }
                            required
                        >
                            {activeBranches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.code} - {branch.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={editDepartmentForm.errors.branch_id}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Division *" />
                        <select
                            className={`mt-1 ${inputClass}`}
                            value={editDepartmentForm.data.division_id}
                            onChange={(e) =>
                                editDepartmentForm.setData(
                                    'division_id',
                                    e.target.value,
                                )
                            }
                            required
                        >
                            {activeDivisions
                                .filter(
                                    (item) =>
                                        item.branch_id ===
                                        editDepartmentForm.data.branch_id,
                                )
                                .map((division) => (
                                    <option
                                        key={division.id}
                                        value={division.id}
                                    >
                                        {division.code} - {division.name}
                                    </option>
                                ))}
                        </select>
                        <InputError
                            message={editDepartmentForm.errors.division_id}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Department Name *" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editDepartmentForm.data.name}
                            onChange={(e) =>
                                editDepartmentForm.setData(
                                    'name',
                                    e.target.value,
                                )
                            }
                            required
                        />
                        <InputError
                            message={editDepartmentForm.errors.name}
                            className="mt-1"
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditingDepartment(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={editDepartmentForm.processing}>
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
