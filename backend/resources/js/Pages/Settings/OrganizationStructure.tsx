import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Row = Record<string, string | boolean | null>;

function StructureTable({ title, rows }: { title: string; rows: Row[] }) {
    const keys = rows[0] ? Object.keys(rows[0]) : [];

    return (
        <Card
            title={title}
            description={`Total ${rows.length} records configured`}
        >
            <DataTable<Row>
                data={rows}
                keyExtractor={(_, index) => index}
                columns={keys.map((key) => ({
                    header: key.replace('_', ' ').toUpperCase(),
                    accessor: (row: Row) => {
                        const val = row[key];
                        if (typeof val === 'boolean') {
                            return (
                                <span
                                    className={`px-2 py-0.5 rounded text-xs font-semibold ${val ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}
                                >
                                    {val ? 'TRUE' : 'FALSE'}
                                </span>
                            );
                        }
                        return (
                            <span className="font-mono text-xs text-slate-800">
                                {String(val ?? '-')}
                            </span>
                        );
                    },
                }))}
            />
        </Card>
    );
}

export default function OrganizationStructure({
    branches,
    divisions,
    departments,
}: {
    branches: Row[];
    divisions: Row[];
    departments: Row[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Organization Structure" />

            <div className="space-y-6">
                <PageHeader
                    title="Organizational Structure & Hierarchy"
                    description="View branch, division, and department hierarchy chains."
                />

                <div className="space-y-6">
                    <StructureTable title="Branch Hierarchy" rows={branches} />
                    <StructureTable
                        title="Division Hierarchy"
                        rows={divisions}
                    />
                    <StructureTable
                        title="Department Hierarchy"
                        rows={departments}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
