import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

type Period = {
    id: string;
    name: string;
    start_date: string;
    end_date: string;
    status: string;
};

export default function Periods({ periods }: { periods: Period[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Accounting Periods" />
            <div className="space-y-6">
                <PageHeader
                    title="Accounting Periods"
                    description="Open periods accept postings; closed periods are immutable."
                />
                <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <Card title="New Period">
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.post(
                                    route('accounting.periods.store'),
                                    Object.fromEntries(
                                        new FormData(event.currentTarget),
                                    ),
                                );
                            }}
                        >
                            <input
                                name="name"
                                required
                                placeholder="Period name"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="start_date"
                                required
                                type="date"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="end_date"
                                required
                                type="date"
                                className="w-full rounded-md border-slate-300"
                            />
                            <PrimaryButton>Create Period</PrimaryButton>
                        </form>
                    </Card>
                    <Card title="Periods">
                        <DataTable
                            data={periods}
                            keyExtractor={(item) => item.id}
                            columns={[
                                {
                                    header: 'Name',
                                    accessor: (item) => item.name,
                                },
                                {
                                    header: 'Start',
                                    accessor: (item) => item.start_date,
                                },
                                {
                                    header: 'End',
                                    accessor: (item) => item.end_date,
                                },
                                {
                                    header: 'Status',
                                    accessor: (item) => item.status,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (item) =>
                                        item.status === 'open' && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'accounting.periods.close',
                                                            item.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Close
                                            </button>
                                        ),
                                },
                            ]}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
