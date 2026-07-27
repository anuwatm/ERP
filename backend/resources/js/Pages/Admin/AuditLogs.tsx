import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type AuditActor = {
    id: string;
    name: string;
    email: string;
};

type Audit = {
    id: string;
    actor?: AuditActor | null;
    action: string;
    entity_type: string;
    entity_id?: string | null;
    before_json?: Record<string, unknown> | null;
    after_json?: Record<string, unknown> | null;
    created_at: string;
};

function formatJson(value?: Record<string, unknown> | null) {
    if (!value || Object.keys(value).length === 0) {
        return <span className="text-slate-400 font-mono text-xs">-</span>;
    }

    return (
        <pre className="max-w-xs whitespace-pre-wrap break-words rounded-lg border border-slate-200 bg-slate-50 p-2 font-mono text-[11px] text-slate-700">
            {JSON.stringify(value, null, 2)}
        </pre>
    );
}

export default function AuditLogs({ auditLogs }: { auditLogs: Audit[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="System Audit Logs" />

            <div className="space-y-6">
                <PageHeader
                    title="Audit & Event Trail"
                    description="Immutable security log of authentication, role edits, and sensitive tenant events."
                />

                <Card
                    title="System Audit Events"
                    description={`Displaying last ${auditLogs.length} events logged`}
                >
                    <DataTable
                        data={auditLogs}
                        keyExtractor={(item) => item.id}
                        columns={[
                            {
                                header: 'Event Action',
                                accessor: (item) => (
                                    <Badge variant="info" dot>
                                        <span className="font-mono">
                                            {item.action}
                                        </span>
                                    </Badge>
                                ),
                            },
                            {
                                header: 'Target Entity',
                                accessor: (item) => (
                                    <div>
                                        <div className="font-semibold capitalize text-slate-800">
                                            {item.entity_type}
                                        </div>
                                        <div className="font-mono text-[11px] text-slate-400 truncate max-w-[150px]">
                                            {item.entity_id || '-'}
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                header: 'Actor User',
                                accessor: (item) =>
                                    item.actor ? (
                                        <div className="flex items-center gap-2">
                                            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 font-bold text-xs text-slate-700">
                                                {item.actor.name
                                                    .charAt(0)
                                                    .toUpperCase()}
                                            </div>
                                            <div>
                                                <div className="text-xs font-semibold text-slate-900 dark:text-white">
                                                    {item.actor.name}
                                                </div>
                                                <div className="text-[11px] text-slate-500 font-mono">
                                                    {item.actor.email}
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <span className="text-xs text-slate-400 font-mono">
                                            System / Guest
                                        </span>
                                    ),
                            },
                            {
                                header: 'Before State',
                                accessor: (item) =>
                                    formatJson(item.before_json),
                            },
                            {
                                header: 'After State',
                                accessor: (item) => formatJson(item.after_json),
                            },
                            {
                                header: 'Timestamp',
                                accessor: (item) => (
                                    <span className="font-mono text-xs text-slate-500">
                                        {item.created_at}
                                    </span>
                                ),
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
