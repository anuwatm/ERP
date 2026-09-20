import {
    DashboardKpiCard,
    DashboardLayout,
    DashboardList,
} from '@/Components/Dashboard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type TaskItem = {
    id: string;
    title: string;
    status: string;
    priority: string;
    due_date: string | null;
};

export default function MyWorkDashboard({
    summary,
    groups,
    hasMore,
}: {
    summary: {
        due_today: number;
        overdue: number;
        completed_this_week: number;
    };
    groups: {
        project_id: string | null;
        project_name: string;
        tasks: TaskItem[];
    }[];
    hasMore: boolean;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="My Work" />
            <DashboardLayout
                title="My Work"
                description="Tasks assigned to you. Counts use the current local day and week."
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <DashboardKpiCard
                        question="Due Today"
                        value={summary.due_today}
                        context="Open assigned tasks due today"
                        tone="pending"
                    />
                    <DashboardKpiCard
                        question="Overdue"
                        value={summary.overdue}
                        context="Open assigned tasks past due"
                        tone="risk"
                    />
                    <DashboardKpiCard
                        question="Completed This Week"
                        value={summary.completed_this_week}
                        context="Assigned tasks completed this week"
                        tone="positive"
                    />
                </div>
                {groups.length === 0 ? (
                    <DashboardList
                        title="Assigned Tasks"
                        description="Open tasks by project"
                        items={[]}
                        emptyMessage="No open tasks assigned to you."
                    />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {groups.map((group) => (
                            <DashboardList
                                key={group.project_id ?? 'no-project'}
                                title={group.project_name}
                                description="Open tasks assigned to you"
                                items={group.tasks.map((task) => ({
                                    id: task.id,
                                    title: task.title,
                                    detail: `${task.status} · ${task.priority} · due ${task.due_date ?? 'not set'}`,
                                    routeName: 'tasks.index',
                                    routeParameters: { task_id: task.id },
                                }))}
                                emptyMessage="No open tasks."
                            />
                        ))}
                    </div>
                )}
                {hasMore && (
                    <p className="text-sm text-slate-600 dark:text-slate-300">
                        Showing the first 30 assigned open tasks.
                    </p>
                )}
            </DashboardLayout>
        </AuthenticatedLayout>
    );
}
